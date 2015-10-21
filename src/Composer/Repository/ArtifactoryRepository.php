<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Repository;

use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\LoaderInterface;
use Composer\Semver\VersionParser;

/**
 * @author Serge Smertin <serg.smertin@gmail.com>
 */
class ArtifactoryRepository extends ArrayRepository
{
    /** @var LoaderInterface */
    protected $loader;

    protected $artifactoryHome;

    protected $searchName;

    protected $repos;

    protected $versionParser;

    public function __construct(array $repoConfig, IOInterface $io)
    {
        $this->loader = new ArrayLoader();
        $this->artifactoryHome = preg_replace("|/$|", "", $repoConfig['url']);
        $this->searchName = $repoConfig['searchName'];
        $this->repos = $repoConfig['repos'] ?: array();
        $this->io = $io;
        $this->versionParser = new VersionParser();
    }

    protected function initialize()
    {
        parent::initialize();

        $this->searchArtifactory($this->artifactoryHome);
    }

    private function searchArtifactory($root)
    {
        $io = $this->io;

        $url = sprintf("%s/api/search/artifact?name=%s&repos=%s", $root, $this->searchName, implode(',', $this->repos));
        $data = file_get_contents($url);

        if ($data === false) {
            if ($io->isVerbose()) {
                $io->writeError("Failed to search for artifacts from <comment>{$url}</comment>");
                return;
            }
        }

        $results = json_decode($data)->results;

        foreach ($results as $result) {
            $dataUri = $result->uri;

            $package = $this->getComposerInformation($dataUri);
            if (!$package) {
                if ($io->isVerbose()) {
                    $io->writeError("File <comment>{$dataUri}</comment> doesn't seem to hold a package");
                }
                continue;
            }

            if ($io->isVerbose()) {
                $template = 'Found package <info>%s</info> (<comment>%s</comment>) in file <info>%s</info>';
                $io->writeError(sprintf($template, $package->getName(), $package->getPrettyVersion(), $package['dist']['url']));
            }

            $this->addPackage($package);
        }
    }

    private function getComposerInformation($dataUri)
    {
        $data = json_decode(file_get_contents($dataUri));
        if ($data === false) {
            return null;
        }
        $artifact = $data->downloadUri;

        $composerFile = "$artifact!composer.json";
        $json = file_get_contents($composerFile);

        $package = JsonFile::parseJson($json, $composerFile);
        if (!isset($package['version'])) {
            $version = basename(dirname($artifact));
            try {
                $version = $this->versionParser->normalize($version);
            } catch (\UnexpectedValueException $e) {
                $version = $this->versionParser->normalize('dev-' . $version);
            }
            $package['version'] = $version;
        }
        var_dump($package);
        $package['dist'] = array(
            'type' => 'zip',
            'url' => $artifact,
            'shasum' => $data->checksums->sha1,
        );

        $package = $this->loader->load($package);

        return $package;
    }
}

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

use Composer\Cache;
use Composer\Config;
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

    public function __construct(array $repoConfig, IOInterface $io, Config $config)
    {
        $this->loader = new ArrayLoader();
        $this->artifactoryHome = preg_replace("|/$|", "", $repoConfig['url']);
        $this->searchName = $repoConfig['searchName'];
        $this->repos = $repoConfig['repos'] ?: array();
        $this->io = $io;
        $this->cache = new Cache(
            $io,
            $config->get('cache-repo-dir') . '/' .
            preg_replace('{[^a-z0-9.]}i', '-', $repoConfig['url']),
            'a-z0-9.$'
        );
        $this->versionParser = new VersionParser();
    }

    protected function initialize()
    {
        parent::initialize();

        $this->searchArtifactory($this->artifactoryHome);
    }

    protected function searchForArtifacts()
    {
        $io = $this->io;
        $url = sprintf("%s/api/search/artifact?name=%s&repos=%s", $this->artifactoryHome, $this->searchName, implode(',', $this->repos));
        $data = file_get_contents($url);

        if ($data === false) {
            if ($io->isVerbose()) {
                $io->writeError("Failed to search for artifacts from <comment>{$url}</comment>");
                return null;
            }
        }

        return $data;
    }

    private function searchArtifactory($root)
    {
        $io = $this->io;

        $response = json_decode($this->searchForArtifacts());

        if ($response == null) {
            return;
        }

        $results = $response->results;

        foreach ($results as $result) {
            $dataUri = $result->uri;

            try {
                $package = $this->getComposerInformation($dataUri);
            } catch(\Exception $e) {
                $io->writeError("Error to get composer.json from <comment>{$dataUri}</comment>: " . $e->getMessage());
                continue;
            }

            if (!$package) {
                if ($io->isVerbose()) {
                    $io->writeError("File <comment>{$dataUri}</comment> doesn't seem to hold a package");
                }
                continue;
            }

            if ($io->isVerbose()) {
                $template = 'Found package <info>%s</info> (<comment>%s</comment>) in file <info>%s</info>';
                $io->writeError(sprintf($template, $package->getName(), $package->getPrettyVersion(), $package->getDistUrl()));
            }

            $this->addPackage($package);
        }
    }

    protected function getArtifactData($dataUri)
    {
        return file_get_contents($dataUri);
    }

    protected function getComposer($artifactUri)
    {
        $composerFile = "$artifactUri!composer.json";
        return file_get_contents($composerFile);
    }

    private function getComposerInformation($dataUri)
    {
        $cacheKey = md5($dataUri) . '.json';
        if ($res = $this->cache->read($cacheKey)) {
            $package = JsonFile::parseJson($res);
        } else {
            $data = json_decode($this->getArtifactData($dataUri));
            if ($data === false) {
                return null;
            }
            $artifact = $data->downloadUri;

            $composerFile = "$artifact!composer.json";
            $json = $this->getComposer($artifact);
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

            $package['dist'] = array(
                'type' => 'zip',
                'url' => $artifact,
                'shasum' => $data->checksums->sha1,
            );

            $this->cache->write($cacheKey, json_encode($package));
        }

        $package = $this->loader->load($package);

        return $package;
    }
}

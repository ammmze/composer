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

use Composer\TestCase;
use Composer\IO\NullIO;
use Composer\Config;
use Composer\Package\BasePackage;

class ArtifactoryRepositoryTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('You need the zip extension to run this test.');
        }
    }

    public function testExtractsConfigsFromZipArchives()
    {
        $expectedPackages = array(
            'vendor0/package0-0.0.1',
            'composer/composer-1.0.0-alpha6',
        );

        $coordinates = array('type' => 'artifactory', 'url' => 'http://localhost', 'searchName' => 'member-service-api-php-client*.jar', 'repos' => ['libs-snapshot-local', 'libs-release-local']);
        $repo = $this->getMockBuilder("\\Composer\\Repository\\ArtifactoryRepository")
            ->setConstructorArgs([$coordinates, new NullIO(), new Config()])
            ->setMethods(array('searchForArtifacts', 'getArtifactData', 'getComposer'))
            ->getMock();

        $repo->expects($this->once())
            ->method('searchForArtifacts')
            ->willReturn('{"results":[{"uri":"http://localhost/api/storage/libs-snapshot-local/vendor0/0.0.1/package0-0.0.1.zip"},{"uri":"http://localhost/api/storage/libs-snapshot-local/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip"}]}');

        $repo->expects($this->exactly(2))
            ->method('getArtifactData')
            ->withConsecutive(
                array('http://localhost/api/storage/libs-snapshot-local/vendor0/0.0.1/package0-0.0.1.zip'),
                array('http://localhost/api/storage/libs-snapshot-local/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip')
            )
            ->willReturnOnConsecutiveCalls(
                '{"metadataUri":"http://localhost/api/storage/libs-snapshot-local/vendor0/0.0.1/package0-0.0.1.zip?mdns","repo":"libs-snapshot-local","path":"/vendor0/0.0.1/package0-0.0.1.zip","created":"2015-07-06T14:03:30.779-07:00","createdBy":"admin","lastModified":"2015-07-06T14:03:30.779-07:00","modifiedBy":"admin","lastUpdated":"2015-07-06T14:03:30.781-07:00","downloadUri":"http://localhost/libs-snapshot-local/vendor0/0.0.1/package0-0.0.1.zip","mimeType":"application/zip","size":22194,"checksums":{"sha1":"9d7e1e86f7a0bcdd96a20e0f30c2fc63c0b8f5c6","md5":"f8c749286816f1d87a8902f1adab6203"},"originalChecksums":{"sha1":"9d7e1e86f7a0bcdd96a20e0f30c2fc63c0b8f5c6","md5":"f8c749286816f1d87a8902f1adab6203"},"uri":"http://localhost/api/storage/libs-snapshot-local/vendor0/0.0.1/package0-0.0.1.zip"}',
                '{"metadataUri":"http://localhost/api/storage/libs-snapshot-local/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip?mdns","repo":"libs-snapshot-local","path":"/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip","created":"2015-07-06T14:03:30.779-07:00","createdBy":"admin","lastModified":"2015-07-06T14:03:30.779-07:00","modifiedBy":"admin","lastUpdated":"2015-07-06T14:03:30.781-07:00","downloadUri":"http://localhost/libs-snapshot-local/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip","mimeType":"application/zip","size":22194,"checksums":{"sha1":"9d7e1e86f7a0bcdd96a20e0f30c2fc63c0b8f5c6","md5":"f8c749286816f1d87a8902f1adab6203"},"originalChecksums":{"sha1":"9d7e1e86f7a0bcdd96a20e0f30c2fc63c0b8f5c6","md5":"f8c749286816f1d87a8902f1adab6203"},"uri":"http://localhost/api/storage/libs-snapshot-local/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip"}'
            );

        $repo->expects($this->exactly(2))
            ->method('getComposer')
            ->withConsecutive(
                array('http://localhost/libs-snapshot-local/vendor0/0.0.1/package0-0.0.1.zip'),
                array('http://localhost/libs-snapshot-local/composer/1.0.0-alpha6/composer-1.0.0-alpha6.zip')
            )
            ->willReturnOnConsecutiveCalls(
                '{"name": "vendor0/package0","type": "library","version": "0.0.1"}',
                '{"name": "composer/composer","type": "library","version": "1.0.0-alpha6"}'
            );

         /** @var ArtifactoryRepository $repo */

        $foundPackages = array_map(function (BasePackage $package) {
            return "{$package->getPrettyName()}-{$package->getPrettyVersion()}";
        }, $repo->getPackages());

        sort($expectedPackages);
        sort($foundPackages);

        $this->assertSame($expectedPackages, $foundPackages);
    }
}

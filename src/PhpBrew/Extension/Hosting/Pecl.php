<?php

namespace PhpBrew\Extension\Hosting;

use PhpBrew\Config;
use PhpBrew\Extension\Hosting;
use PEARX\Channel as PeclChannel;

class Pecl implements Hosting {

    public $site = 'pecl.php.net';
    public $owner = NULL;
    public $repository = NULL;
    public $packageName = NULL;

    public function getName() {
        return 'pecl';
    }

    public function getExtensionListPath()
    {
        return NULL;
    }

    public function getRemoteExtensionListUrl($branch)
    {
        return NULL;
    }

    public function buildPackageDownloadUrl($version='stable')
    {
        if ($this->getPackageName() == NULL) {
            throw new Exception("Repository invalid.");
        }
        $channel = new PeclChannel($this->site);
        $xml = $channel->fetchPackageReleaseXml($this->getPackageName(), $version);
        $g = $xml->getElementsByTagName('g');
        $url = $g->item(0)->nodeValue;
        // just use tgz format file.
        return $url . '.tgz';
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function setRepository($repository)
    {
        $this->repository = $repository;
    }

    public function getPackageName()
    {
        return $this->packageName;
    }

    public function setPackageName($packageName)
    {
        $this->packageName = $packageName;
    }

    public function exists($url, $packageName = NULL)
    {
        $this->setOwner(NULL);
        $this->setRepository(NULL);
        $this->setPackageName($url);
        return true;
    }

    public function buildKnownReleasesUrl()
    {
        return sprintf("http://pecl.php.net/rest/r/%s/allreleases.xml", $this->getPackageName());
    }

    public function parseKnownReleasesResponse($content)
    {
        // convert xml to array
        $xml = simplexml_load_string($content);
        $json = json_encode($xml);
        $info2 = json_decode($json, TRUE);

        $versionList = array_map(function($version) {
            return $version['v'];
        }, $info2['r']);

        return $versionList;
    }

    public function getDefaultVersion()
    {
        return 'stable';
    }

    public function shouldLookupRecursive()
    {
        return false;
    }

    public function resolveDownloadFileName($version)
    {
        $url = $this->buildPackageDownloadUrl($version);
        // Check if the url is for php source archive
        if (preg_match('/php-.+\.tar\.(bz2|gz|xz)/', $url, $parts)) {
            return $parts[0];
        }

        // try to get the filename through parse_url
        $path = parse_url($url, PHP_URL_PATH);
        if (false === $path || false === strpos($path, ".")) {
            return NULL;
        }
        return basename($path);
    }

    public function extractPackageCommands($currentPhpExtensionDirectory, $targetFilePath)
    {
        $cmds = array(
            "tar -C $currentPhpExtensionDirectory -xzf $targetFilePath"
        );
        return $cmds;
    }

    public function postExtractPackageCommands($currentPhpExtensionDirectory, $targetFilePath)
    {
        $targetPkgDir = $currentPhpExtensionDirectory . DIRECTORY_SEPARATOR . $this->getPackageName();
        $info = pathinfo($targetFilePath);
        $packageName = $this->getPackageName();

        $cmds = array(
            "rm -rf $targetPkgDir",
            // Move "memcached-2.2.7" to "memcached"
            "mv $currentPhpExtensionDirectory/{$info['filename']} $currentPhpExtensionDirectory/$packageName",
            // Move "ext/package.xml" to "memcached/package.xml"
            "mv $currentPhpExtensionDirectory/package.xml $currentPhpExtensionDirectory/$packageName/package.xml",
        );
        return $cmds;
    }

} 
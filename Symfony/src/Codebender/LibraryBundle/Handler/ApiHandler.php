<?php

namespace Codebender\LibraryBundle\Handler;

use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\LibraryExample;
use Codebender\LibraryBundle\Entity\Version;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiHandler
{

    protected $entityManager;
    protected $container;

    function __construct(EntityManager $entityManager, ContainerInterface $containerInterface)
    {
        $this->entityManager = $entityManager;
        $this->container = $containerInterface;
    }

    /**
     * This method returns the type of the library (e.g. external/builtin) as a string.
     *
     * @param $defaultHeader
     * @return string
     */
    public function getLibraryType($defaultHeader)
    {
        if ($this->isExternalLibrary($defaultHeader)) {
            return 'external';
        } elseif ($this->isBuiltInLibrary($defaultHeader)) {
            return 'builtin';
        } elseif ($this->isBuiltInLibraryExample($defaultHeader)) {
            return 'example';
        }

        return 'unknown';
    }

    /**
     * Constrct the path for the given library and version
     * @param $defaultHeader
     * @param $version
     * @return string
     */
    public function getExternalLibraryPath($defaultHeader, $version)
    {
        $externalLibraryRoot = $this->container->getParameter('external_libraries_new') . "/";

        $library = $this->getLibraryFromDefaultHeader($defaultHeader);
        $libraryFolderName = $library->getFolderName();

        $versions = $library->getVersions();
        $version = $versions->filter(
            function ($ver) use ($version) {
                return $ver->getVersion() === $version;
            },
            $versions
        )->first();
        $versionFolderName = $version->getFolderName();

        $path = $externalLibraryRoot . '/' . $libraryFolderName . '/' . $versionFolderName;
        return $path;
    }

    public function getBuiltInLibraryPath($defaultHeader)
    {
        $builtInLibraryRoot = $this->container->getParameter('builtin_libraries');
        $path = $builtInLibraryRoot . '/libraries/' . $defaultHeader;
        return $path;
    }

    public function getBuiltInLibraryExamplePath($exmapleName)
    {
        $builtInLibraryRoot = $this->container->getParameter('builtin_libraries');
        $path = $builtInLibraryRoot . '/examples/' . $exmapleName;
        return $path;
    }

    /**
     * This method checks if a given library (version) exists
     *
     * @param $defaultHeader
     * @param $version
     * @return bool
     */
    public function libraryVersionExists($defaultHeader, $version)
    {
        if ($this->isValidExternalLibraryVersion($defaultHeader, $version)) {
            return true;
        } elseif ($this->isBuiltInLibrary($defaultHeader)) {
            return true;
        } elseif ($this->isBuiltInLibraryExample($defaultHeader)) {
            return true;
        }

        return false;
    }

    /**
     * This method checks if the given built-in library exists (specified by
     * its $defaultHeader)
     *
     * @param $defaultHeader
     * @return bool
     */
    public function isBuiltInLibrary($defaultHeader)
    {
        if (!is_dir($this->getBuiltInLibraryPath($defaultHeader))) {
            return false;
        }

        return true;
    }

    /**
     * This method checks if the given built-in library example exists (specified by
     * its $defaultHeader)
     *
     * @param $defaultHeader
     * @return bool
     */
    public function isBuiltInLibraryExample($defaultHeader)
    {
        if (!is_dir($this->getBuiltInLibraryExamplePath($defaultHeader))) {
            return false;
        }

        return true;
    }

    /**
     * This method checks if a given external library exists in the database.
     *
     * @param $defaultHeader
     * @param bool $getDisabled
     * @return bool
     */
    public function isExternalLibrary($defaultHeader, $getDisabled = false)
    {
        $library = $this->getLibraryFromDefaultHeader($defaultHeader);

        return $getDisabled ? $library !== null : $library !== null && $library->getActive();
    }

    /**
     * Converts a given default header into its Library entity
     *
     * @param $defaultHeader
     * @return Library
     */
    public function getLibraryFromDefaultHeader($defaultHeader)
    {
        $lib = $this->entityManager
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findOneBy(array('default_header' => $defaultHeader));

        return $lib;
    }

    /**
     * @param $defaultHeader
     * @return ArrayCollection
     */
    public function getAllVersionsFromDefaultHeader($defaultHeader)
    {
        $library = $this->getLibraryFromDefaultHeader($defaultHeader);
        $versionObjects = $library->getVersions();
        return $versionObjects;
    }

    /**
     * Get the Version entity for the given library and specific version
     * @param $library
     * @param $version
     * @return Version
     */
    public function getVersionFromDefaultHeader($library, $version)
    {
        /* @var ArrayCollection $versionCollection */
        $versionCollection = $this->getAllVersionsFromDefaultHeader($library);

        // check if this library contains requested version
        $result = $versionCollection->filter(
            function ($versionObject) use ($version) {
                return $versionObject->getVersion() === $version;
            }
        );

        if ($result->isEmpty()) {
            return null;
        }

        return $result->first();
    }

    /**
     * Get LibraryExample entity for the requested library example
     * @param $library
     * @param $version
     * @param $example
     * @return array
     */
    public function getExampleForExternalLibrary($library, $version, $example)
    {
        /* @var Version $versionMeta */
        $versionMeta = $this->getVersionFromDefaultHeader($library, $version);

        if ($versionMeta === null) {
            return [];
        }

        $examplenMeta = array_values(
            array_filter(
                $versionMeta->getLibraryExamples()->toArray(),
                function ($exampleObject) use ($example) {
                    return $exampleObject->getName() === $example;
                }
            )
        );

        return $examplenMeta;
    }

    /**
     * This method checks if the given version exists in the given library
     * specified by the $defaultHeader.
     *
     * @param $defaultHeader
     * @param $version
     * @return bool
     */
    private function isValidExternalLibraryVersion($defaultHeader, $version)
    {
        if (!$this->isExternalLibrary($defaultHeader)) {
            return false;
        }

        $versionsCollection = $this->getAllVersionsFromDefaultHeader($defaultHeader)
            ->filter(
                function ($versionObject) use ($version) {
                    return $versionObject->getVersion() === $version;
                }
            );

        return !$versionsCollection->isEmpty();
    }
}

<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use Exception;
use Symfony\Component\Finder\Finder;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Constraints\Null;

class DeleteLibraryApiCommand extends AbstractApiCommand
{
    protected $apiHandler;
    protected $fileSystem;

    public function execute($content)
    {
        if (!array_key_exists('library', $content) || !array_key_exists('version', $content)) {
            return ["success" => false, "message" => "You need to specify which library version to delete."];
        }

        $libraryName = $content['library'];
        $versionName = $content['version'];

        $this->apiHandler = $this->container->get('codebender_library.apiHandler');
        $this->fileSystem = new Filesystem();

        $library = $this->apiHandler->getLibraryFromDefaultHeader($libraryName);
        if (is_null($library)) {
            return ["success" => false, "message" => "There is no library called $libraryName to delete."];
        }

        $version = $this->apiHandler->getVersionFromDefaultHeader($libraryName, $versionName);
        if (is_null($version)) {
            return ["success" => false, "message" => "There is no version $versionName for library called $libraryName to delete."];
        }

        $libraryFolderName = $library->getFolderName();
        $versionFolderName = $version->getFolderName();

        $enitiesToPersist = [];
        $entitiesToRemove = [];
        $entitiesToRemove = array_merge($entitiesToRemove, $this->getVersionExamples($version));
        $entitiesToRemove = array_merge($entitiesToRemove, $this->getRelatedPreferences($version));
        if (sizeof($library->getVersions()) === 1) {
            $entitiesToRemove[] = $library;
            $dir = $libraryFolderName;
        } else {
            if ($this->isLatestVersion($library, $version)) {
                if (!array_key_exists('latest_version', $content)) {
                    return ["success" => false, "message" => "You are deleting the latest version of this library. Please specify a new latest version."];
                }
                try {
                    $enitiesToPersist[] = $this->getEntityWithNewLatestLibrary($library, $content['latest_version']);
                } catch (InvalidArgumentException $e) {
                    return ["success" => false, "message" => $e->getMessage()];
                }
            }
            $dir = "$libraryFolderName/$versionFolderName";
        }
        $entitiesToRemove[] = $version;

        try {
            $this->setNullToVersionLibrary($version);
            foreach ($enitiesToPersist as $entity) {
                $this->entityManager->persist($entity);
            }
            foreach ($entitiesToRemove as $entity) {
                $this->entityManager->remove($entity);
            }
            $this->entityManager->flush();
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }

        try {
            $this->removeLibraryDirectory($dir);
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }

        return ["success" => true, "message" => "Version $versionName of the library $libraryName has been deleted successfully."];
    }

    private function getVersionExamples($version)
    {
        $array = [];
        $examples = $version->getLibraryExamples();
        foreach ($examples as $example) {
            $array[] = $example;
        }
        return $array;
    }

    private function setNullToVersionLibrary($version)
    {
        // This is to escape foreign key constraint.
        $version->setLibrary(null);
        $this->entityManager->persist($version);
        $this->entityManager->flush();
    }

    private function isLatestVersion($library, $version)
    {
        return $library->getLatestVersion()->getId() === $version->getId();
    }

    private function getEntityWithNewLatestLibrary($library, $newVersionName)
    {
        $hasSpecifiedVersion = false;
        $versions = $library->getVersions();
        foreach ($versions as $version) {
            if ($version->getVersion() !== $newVersionName) {
                continue;
            }
            $library->setLatestVersion($version);
            $hasSpecifiedVersion = true;
            break;
        }
        if (!$hasSpecifiedVersion) {
            throw new InvalidArgumentException("The new latest version $newVersionName is not found.");
        }
        return $library;
    }

    private function getRelatedPreferences($version)
    {
        $preferences = $this->entityManager
            ->getRepository('CodebenderLibraryBundle:Preference')
            ->createQueryBuilder('p')
            ->where('p.version = :version')
            ->setParameters([':version' => $version->getId()])
            ->getQuery()
            ->getResult();

        return $preferences;
    }

    private function removeLibraryDirectory($dir) {
        $baseDir = $this->container->getParameter('external_libraries_v2');
        $targetDir = "$baseDir/$dir";
        if (!is_dir($targetDir)) {
            throw new InvalidArgumentException("$targetDir does not exist.");
        }
        $this->fileSystem->remove($targetDir);
    }
}
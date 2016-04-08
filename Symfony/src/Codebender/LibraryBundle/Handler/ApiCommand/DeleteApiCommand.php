<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use Exception;
use Symfony\Component\Finder\Finder;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Constraints\Null;

class DeleteApiCommand extends AbstractApiCommand
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

        try {
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

            $this->removeVersionExamples($version);
            $this->removeRelatedPreference($version);
            if (sizeof($library->getVersions()) === 1) {
                $this->removeLibrary($library, $version);
                $dir = $libraryFolderName;
            } else {
                $this->setNewLatestLibrary($library);
                $dir = "$libraryFolderName/$versionFolderName";
            }

            $this->removeLibraryVersion($library, $version);
            $this->entityManager->flush();
            $this->removeLibraryDirectory($dir);
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }

        return ["success" => true];
    }

    private function removeVersionExamples($version)
    {
        $examples = $version->getLibraryExamples();
        foreach ($examples as $example) {
            $this->entityManager->remove($example);
        }
    }

    private function removeLibraryVersion($library, $version)
    {
        $this->entityManager->remove($version);
    }

    private function removeLibrary($library, $version)
    {
        $this->entityManager->remove($library);
        // This is to escape foreign key constraint.
        $version->setLibrary(null);
        $this->entityManager->persist($version);
        $this->entityManager->flush();
    }

    private function setNewLatestLibrary($library)
    {

    }

    private function removeRelatedPreference($version)
    {
        $preferences = $this->entityManager
            ->getRepository('CodebenderLibraryBundle:Preference')
            ->createQueryBuilder('p')
            ->where('p.version = :version')
            ->setParameters([':version' => $version->getId()])
            ->getQuery()
            ->getResult();

        foreach ($preferences as $preference) {
            $this->entityManager->remove($preference);
        }
    }

    private function removeLibraryDirectory($dir) {
        $baseDir = $this->container->getParameter('external_libraries_new');
        $targetDir = "$baseDir/$dir";
        if (!is_dir($targetDir)) {
            throw new InvalidArgumentException("$targetDir does not exist.");
        }
        $this->fileSystem->remove($targetDir);
    }
}
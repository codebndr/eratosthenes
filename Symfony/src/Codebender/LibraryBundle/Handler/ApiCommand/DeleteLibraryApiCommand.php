<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\Version;
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

        /* @var \Codebender\LibraryBundle\Entity\Library $library */
        $library = $this->apiHandler->getLibraryFromDefaultHeader($libraryName);
        if (is_null($library)) {
            return ["success" => false, "message" => "There is no library called $libraryName to delete."];
        }

        $version = $this->apiHandler->getVersionFromDefaultHeader($libraryName, $versionName);
        if (is_null($version)) {
            return ["success" => false, "message" => "There is no version $versionName for library called $libraryName to delete."];
        }

        // If the user is deleting the latest version of the library
        if ($version === $library->getLatestVersion()) {

            // The user did not specify the next latest version
            if (!array_key_exists('nextLatestVersion', $content)) {
                return ["success" => false, "message" => "You need to specify the next latest version of the library $libraryName."];
            }

            $nextLatestVersionName = $content['nextLatestVersion'];
            $nextLatestVersion = $this->apiHandler->getVersionFromDefaultHeader($libraryName, $nextLatestVersionName);
            
            // The user specified the next latest version but the specified version does not exist
            if (is_null($nextLatestVersion)) {
                return ["success" => false, "message" => "The next latest version $nextLatestVersionName does not exist."];
            }

            $this->setNewLatestLibrary($library, $nextLatestVersion);
        }

        $libraryFolderName = $library->getFolderName();
        $versionFolderName = $version->getFolderName();

        $this->setNullToVersionLibrary($version);

        $this->removeVersionExamples($version);
        $this->removeRelatedPreference($version);
        if (sizeof($library->getVersions()) === 1) {
            $this->removeLibrary($library);
            $dir = $libraryFolderName;
        } else {
            $dir = "$libraryFolderName/$versionFolderName";
        }
        $this->removeVersion($version);

        try {
            $this->entityManager->flush();
            $this->removeLibraryDirectory($dir);
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }

        return ["success" => true, "message" => "Version $versionName of the library $libraryName has been deleted successfully."];
    }

    private function removeVersionExamples($version)
    {
        $examples = $version->getLibraryExamples();
        foreach ($examples as $example) {
            $this->entityManager->remove($example);
        }
    }

    private function removeVersion($version)
    {
        $this->entityManager->remove($version);
    }

    private function removeLibrary($library)
    {
        $this->entityManager->remove($library);
    }

    private function setNullToVersionLibrary($version)
    {
        // This is to escape foreign key constraint.
        $version->setLibrary(null);
        $this->entityManager->persist($version);
        $this->entityManager->flush();
    }

    private function setNewLatestLibrary(Library $library, Version $nextLatestVersion)
    {
        $library->setLatestVersion($nextLatestVersion);
        $this->entityManager->persist($library);
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
        $baseDir = $this->container->getParameter('external_libraries_v2');
        $targetDir = "$baseDir/$dir";
        if (!is_dir($targetDir)) {
            throw new InvalidArgumentException("$targetDir does not exist.");
        }
        $this->fileSystem->remove($targetDir);
    }
}
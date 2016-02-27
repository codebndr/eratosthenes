<?php

namespace Codebender\LibraryBundle\Handler;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\Version;
use Codebender\LibraryBundle\Entity\Example;

class NewLibraryHandler
{
    protected $entityManager;
    protected $container;

    function __construct(EntityManager $entityManager, ContainerInterface $containerInterface)
    {
        $this->entityManager = $entityManager;
        $this->container = $containerInterface;
    }

    /**
     * Performs the actual addition of a library, as well as
     * input validation of the provided form data.
     *
     * @param array $data The data of the received form
     * @return array
     */
    public function addLibrary($data)
    {
        /*
         * Check whether the right combination of data was provided,
         * and figure out the type of library addition, that is a zip archive (zip)
         * or a Github repository (git)
         */
        $uploadType = $this->validateFormData($data);
        if ($uploadType['success'] != true) {
            return array('success' => false, 'message' => 'Invalid form. Please try again.');
        }

        /*
         * Then get the files of the library (either from extracting the zip,
         * or fetching them from Githib) and proceed
         */
        $handler = $this->container->get('codebender_library.handler');
        $path = '';
        $lastCommit = null;
        switch ($uploadType['type']) {
            case 'git':
                $path = $this->getInRepoPath($data["Repo"], $data['InRepoPath']);
                $libraryStructure = $handler->getGithubRepoCode($data["GitOwner"], $data["GitRepo"], $data['GitBranch'], $path);
                $lastCommit = $handler->getLastCommitFromGithub($data['GitOwner'], $data['GitRepo'], $data['GitBranch'], $path);
                break;
            case 'zip':
                $libraryStructure = json_decode($this->getLibFromZipFile($data["Zip"]), true);
                break;
            default:
                return array('success' => false, 'message' => 'Unknown upload type.');
        }

        if ($libraryStructure['success'] !== true) {
            return array('success' => false, 'message' => $libraryStructure['message']);
        }

        /*
         * In both ways of fething, the code of the library is found
         * under the 'library' key of the response, upon success.
         */
        $libraryStructure = $libraryStructure['library'];

        if ($uploadType['type'] == 'git') {
            $libraryStructure = $this->fixGitPaths($libraryStructure, $libraryStructure['name'], '');
        }

        $data['LastCommit'] = $lastCommit;
        $data['Path'] = $path;
        $data['LibraryStructure'] = $libraryStructure;

        /*
         * It write the files to the disk and create the new Library and/or Version Entity
         * that represents what's uploaded.
         */
        $lib = $this->getLibrary($data['DefaultHeader']);
        if ($lib === Null) {
            $data['FolderName'] = $this->getFolderName($data['Name']);

            $creationResponse = json_decode($this->saveNewLibrary($data), true);
            if ($creationResponse['success'] != true) {
                return array('success' => false, 'message' => $creationResponse['message']);
            }
        } else if ($lib->getName() !== $data['Name']) {
            return array('success' => false, 'message' => "Library called '" . $lib->getName() . "' have the same header!");
        } else {
            $data['FolderName'] = $lib->getFolderName();
        }

        $creationResponse = json_decode($this->saveNewVersionAndExamples($data, $lib), true);
        if ($creationResponse['success'] != true) {
            return array('success' => false, 'message' => $creationResponse['message']);
        }

        return array('success' => true);
    }


    /**
     * Makes sure the received form does not contain Github data and
     * a zip archive at once. In such a case, the form is considered invalid.
     *
     * @param array $data The form data array
     * @return array
     */
    private function validateFormData($data)
    {
        if (($data['GitOwner'] === null && $data['GitRepo'] === null && $data['GitBranch'] === null && $data['GitPath'] === null) && is_object($data['Zip'])) {
            return array('success' => true, 'type' => 'zip');
        }
        if (($data['GitOwner'] !== null && $data['GitRepo'] !== null && $data['GitBranch'] !== null && $data['GitPath'] !== null) && $data['Zip'] === null) {
            return array('success' => true, 'type' => 'git');
        }

        return array('success' => false);
    }

    /**
     * Determines whether the basepath is exactly the same or is the
     * root directory of a provided path. Returns an empty string if the
     * two paths are equal or strips the basepath from the path, if
     * the first is a substring of the latter.
     *
     * @param string $basePath The name of the repo
     * @param string $path The provided path
     * @return string
     */
    private function getInRepoPath($basePath, $path)
    {
        if ($path == $basePath) {
            return '';
        }

        if (preg_match("/^$basePath\//", $path)) {
            return preg_replace("/^$basePath\//", '', $path);
        }

        return $path;
    }

    /**
     * The zip upload implementation, creates an assoc array in which the filenames of each file
     * include the absolute path to the file under the library root directory. This option is not available
     * when fetching libraries from Git, since filenames contain no paths. This function is called
     * recursively, and figures out the absolute path for each of the files of the provided file structure,
     * making the git assoc array compatible to the zip assoc array.
     *
     * @param $files
     * @param $root
     * @param $parentPath
     * @return mixed
     */
    private function fixGitPaths($files, $root, $parentPath)
    {
        if ($parentPath != '' && $parentPath != $root) {
            $files['name'] = $parentPath . '/' . $files['name'];
        }
        $parentPath = $files['name'];
        foreach ($files['contents'] as &$element) {
            if ($element['type'] == 'dir') {
                $element = $this->fixGitPaths($element, $root, $parentPath);
            }
        }
        return $files;
    }

    private function saveNewLibrary($data)
    {
        $lib = new Library();
        $lib->setName($data['Name']);
        $lib->setDefaultHeader($data['DefaultHeader']);
        $lib->setDescription($data['Description']);
        $lib->setOwner($data['Owner']);
        $lib->setRepo($data['Repo']);
        $lib->setBranch($data['Branch']);
        $lib->setInRepoPath($data['InRepoPath']);
        $lib->setNotes($data['Notes']);
        $lib->setVerified(false);
        $lib->setActive(false);
        $lib->setLastCommit($data['LastCommit']);
        $lib->setUrl($data['Url']);
        $lib->setFolderName($data['FolderName']);

        $create = json_decode($this->createLibraryDirectory($data['FolderName'], $data['LibraryStructure']), true);

        if (!$create['success'])
            return json_encode($create);

        $this->saveEntities(array($lib));

        return json_encode(array("success" => true));
    }

    private function saveNewVersionAndExamples($data, \Codebender\LibraryBundle\Entity\Library $lib)
    {
        $version = new Version();
        $version->setLibrary($lib);
        $version->setFolderName($data['FolderName']);
        $version->setDescription($data['Description']);
        $version->setReleaseCommit($data['LastCommit']);
        $version->setSourceUrl($data['SourceUrl']);
        $version->setNotes($data['Notes']);
        $version->setVersion($data['Version']);
        $lib->addVersion($version);

        $create = json_decode($this->createVersionDirectory($data['FolderName'], $data['LibraryStructure'], $data['Version']), true);
        if (!$create['success'])
            return json_encode($create);

        $this->saveEntities(array($lib, $version));
        $this->saveExamples($data, $lib);

        return json_encode(array("success" => true));
    }

    // TODO: save Example entities
    private function saveExamples($data, $lib)
    {
        $handler = $this->container->get('codebender_library.handler');

        $externalLibrariesPath = $this->container->getParameter('external_libraries_new');
        $examples = $handler->fetchLibraryExamples(new Finder(), $externalLibrariesPath . '/' . $data['DefaultHeader'] . '/' . $data['Version']);

        foreach ($examples as $example) {
            $path_parts = pathinfo($example['filename']);
            $this->saveExampleMeta($path_parts['filename'], $lib, $data['DefaultHeader'] . '/' . $data['Version'] . "/" . $example['filename'], null);
        }
    }

    private function createLibraryDirectory($folderName, $libraryStructure)
    {
        $path = $this->container->getParameter('external_libraries_new') . '/' . $folderName . '/';
        if (is_dir($path))
            return json_encode(array("success" => false, "message" => "Library directory already exists"));
        if (!mkdir($path))
            return json_encode(array("success" => false, "message" => "Cannot Save Library"));
        return json_encode(array("success" => true));
    }

    private function createVersionDirectory($folderName, $libraryStructure, $version)
    {
        $base = $path = $this->container->getParameter('external_libraries_new') . '/' . $folderName . '/' . $version . '/';
        return ($this->createVersionDirectoryRecur($base, $path, $libraryStructure['contents']));
    }

    // TODO: see how it works
    // if possible, break it down. it's doing two things under the same name!!
    private function createVersionDirectoryRecur($base, $path, $files)
    {
        if (is_dir($path))
            return json_encode(array("success" => false, "message" => "Library directory already exists"));
        if (!mkdir($path))
            return json_encode(array("success" => false, "message" => "Cannot Save Library"));

        foreach ($files as $file) {
            if ($file['type'] == 'dir') {
                $create = json_decode($this->createVersionDirectoryRecur($base, $base . $file['name'] . "/", $file['contents']), true);
                if (!$create['success'])
                    return (json_encode($create));
            } else {
                file_put_contents($path . $file['name'], $file['contents']);
            }
        }

        return json_encode(array('success' => true));
    }

    private function saveExampleMeta($name, $lib, $path, $boards)
    {
        //TODO make it better. You know, return things and shit
        $example = new Example();
        $example->setName($name);
        $example->setLibrary($lib);
        $example->setPath($path);
        $example->setBoards($boards);

        $this->saveEntities(array($example));
    }


    private function getLibFromZipFile($file)
    {
        if (is_dir('/tmp/lib'))
            $this->destroy_dir('/tmp/lib');
        $zip = new \ZipArchive;
        $opened = $zip->open($file);

        if ($opened === true) {
            $handler = $this->container->get('codebender_library.handler');
            $zip->extractTo('/tmp/lib/');
            $zip->close();
            $dir = json_decode($this->processZipDir('/tmp/lib'), true);

            if (!$dir['success']) {
                return json_encode($dir);
            }

            $dir = $dir['directory'];
            $baseDir = json_decode($handler->findBaseDir($dir), true);
            if ($baseDir['success'] !== true) {
                return json_encode($baseDir);
            }

            $baseDir = $baseDir['directory'];

            return json_encode(['success' => true, 'library' => $baseDir]);
        } else {
            return json_encode(['success' => false, 'message' => 'Could not unzip Archive. Code: ' . $opened]);
        }
    }

    private function processZipDir($path)
    {
        $files = [];
        $dir = preg_grep('/^([^.])/', scandir($path));
        foreach ($dir as $file) {
            if ($file == "__MACOSX") {
                continue;
            }

            if (is_dir($path . '/' . $file)) {
                $subdir = json_decode($this->processZipDir($path . '/' . $file), true);
                if ($subdir['success'] !== true) {
                    return json_encode($subdir);
                }
                array_push($files, $subdir['directory']);
            } else {
                $file = json_decode($this->processZipFile($path . '/' . $file), true);
                if ($file['success'] === true) {
                    array_push($files, $file['file']);
                } else if ($file['message'] != "Bad Encoding") {
                    return json_encode($file);
                }
            }
        }
        return json_encode(
            ['success' => true, 'directory' => ['name' => substr($path, 9), 'type' => 'dir', 'contents' => $files]]
        );
    }

    private function processZipFile($path)
    {
        $contents = file_get_contents($path);

        if ($contents === null) {
            return json_encode(['success' => false, 'message' => 'Could not read file ' . basename($path)]);
        }

        return json_encode(['success' => true, 'file' => ['name' => basename($path), 'type' => 'file', 'contents' => $contents]]);
    }

    private function destroy_dir($dir)
    {
        if (!is_dir($dir) || is_link($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $file) {
            if ($file != '.' && $file != '..' && !$this->destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!$this->destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) return false;
            }
        }
        return rmdir($dir);
    }

    /**
     * @param $defaultHeader
     * @return Library entity or Null
     */
    private function getLibrary($defaultHeader)
    {
        return $this->entityManager
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findBy(array('default_header' => $defaultHeader))[0];
    }

    private function saveEntities($entities)
    {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }

    /**
     * Make folder name based on the number of libraries with the same name.
     * @param $name
     * @return string
     */
    private function getFolderName($name) {
        $count = sizeof($this->entityManager
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findBy(array('name' => $name)));
        if ($count > 0) {
            $name = $name . '_' . $count;
        }
        return $name;
    }
}
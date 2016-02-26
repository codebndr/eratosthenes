<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Symfony\Component\Finder\Finder;
use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\Version;
use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Form\NewLibraryForm;

class NewLibraryCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        $authorizationKey = $this->container->getParameter('authorizationKey');
        $form = $this->createForm(new NewLibraryForm());

        $form->handleRequest($this->getRequest());

        if (!$form->isValid()) {
            return $this->render('CodebenderLibraryBundle:Api:newLibForm.html.twig', array(
                'authorizationKey' => $authorizationKey,
                'form' => $form->createView()
            ));
        }
        $formData = $form->getData();

        $libraryAdded = $this->addLibrary($formData);
        if ($libraryAdded['success'] !== true){
            $flashBag = $this->get('session')->getFlashBag();
            $flashBag->add('error', 'Error: ' . $libraryAdded['message']);
            $form = $this->createForm(new NewLibraryForm());

            return $this->render('CodebenderLibraryBundle:Api:newLibForm.html.twig', [
                'authorizationKey' => $authorizationKey,
                'form' => $form->createView()
            ]);
        }

        return $this->redirect($this->generateUrl('codebender_library_view_library',
            ['authorizationKey' => $authorizationKey, 'library' => $formData['DefaultHeader'], 'disabled' => 1]));
    }

    /**
     * Performs the actual addition of a library, as well as
     * input validation of the provided form data.
     *
     * @param array $data The data of the received form
     * @return array
     */
    private function addLibrary($data)
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
        $handler = $this->get('codebender_library.handler');
        $path = '';
        $lastCommit = null;
        switch ($uploadType['type']) {
            case 'git':
                $path = $this->getInRepoPath($data["Repo"], $data['InRepoPath']);
                $libraryStructure = $handler->getGithubRepoCode($data["Owner"], $data["Repo"], $data['Branch'], $path);
                $lastCommit = $handler->getLastCommitFromGithub($data['Owner'], $data['Repo'], $data['Branch'], $path);
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

        /*
         * Save the library, that is write the files to the disk and
         * create the new ExternalLibrary Entity that represents the uploaded library.
         * Remember onnly external libraries are uploaded through this process
         */
        $data['LastCommit'] = $lastCommit;
        $data['Path'] = $path;
        $data['LibraryStructure'] = $data[$libraryStructure];

        $exists = json_decode($handler->checkIfExternalExists($data['DefaultHeader']), true);
        if (!$exists) {
            $creationResponse = json_decode($this->saveNewLibrary($data), true);
            if ($creationResponse['success'] != true) {
                return array('success' => false, 'message' => $creationResponse['message']);
            }
        }

        $creationResponse = json_decode($this->saveNewVersionAndExamples($data), true);
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
        $em = $this->getDoctrine()->getManager();

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

        $create = json_decode($this->createLibaryFiles($data['DefaultHeader'], $data['LibraryStructure']), true);
        if (!$create['success'])
            return json_encode($create);

        $em->persist($lib);
        $em->flush();

        return json_encode(array("success" => true));
    }

    private function saveNewVersionAndExamples($data)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT p FROM CodebenderLibraryBundle:Library p WHERE p.default_header = :default_header'
        )->setParameter('default_header', $data['DefaultHeader']);
        $lib = $query->getOneOrNullResult();

        $version = new Version();
        $version->setLibrary($lib);
        $version->setFolderName($data['FolderName']);
        $version->setDescription($data['Description']);
        $version->setReleaseCommit($data['LastCommit']);
        $version->setSourceUrl($data['SourceUrl']);
        $version->setNotes($data['Notes']);
        $version->setVersion($data['Version']);
        $lib->addVersion($version);

        $create = json_decode($this->createVersionFiles($data['DefaultHeader'], $data['LibraryStructure'], $data['Version']), true);
        if (!$create['success'])
            return json_encode($create);

        $em->persist($lib);
        $em->persist($version);
        $em->flush();

        $this->saveExamples($data, $lib);

        return json_encode(array("success" => true));
    }

    private function saveExamples($data, $lib)
    {
        $handler = $this->get('codebender_library.handler');

        $externalLibrariesPath = $this->container->getParameter('external_libraries_new');
        $examples = $handler->fetchLibraryExamples(new Finder(), $externalLibrariesPath . '/' . $data['DefaultHeader'] . '/' . $data['Version']);

        foreach ($examples as $example) {
            $path_parts = pathinfo($example['filename']);
            $this->saveExampleMeta($path_parts['filename'], $lib, $data['DefaultHeader'] . '/' . $data['Version'] . "/" . $example['filename'], null);
        }
    }

    private function createLibaryFiles($defaultHeader, $libraryStructure)
    {
        $libBaseDir = $this->container->getParameter('external_libraries') . '/' . $defaultHeader . '/';
        return ($this->createLibDirectory($libBaseDir, $libBaseDir, $libraryStructure['contents']));
    }

    private function createVersionFiles($defaultHeader, $libraryStructure, $version)
    {
        $libBaseDir = $this->container->getParameter('external_libraries') . '/' . $defaultHeader . '/' . $version . '/';
        return ($this->createLibDirectory($libBaseDir, $libBaseDir, $libraryStructure['contents']));
    }

    // TODO: see how it works
    // if possible, break it down. it's doing two things under the same name!!
    private function createLibDirectory($base, $path, $files)
    {
        if (is_dir($path))
            return json_encode(array("success" => false, "message" => "Library directory already exists"));
        if (!mkdir($path))
            return json_encode(array("success" => false, "message" => "Cannot Save Library"));

        foreach ($files as $file) {
            if ($file['type'] == 'dir') {
                $create = json_decode($this->createLibDirectory($base, $base . $file['name'] . "/", $file['contents']), true);
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
        $em = $this->getDoctrine()->getManager();
        $em->persist($example);
        $em->flush();
    }


    private function getLibFromZipFile($file)
    {
        if (is_dir('/tmp/lib'))
            $this->destroy_dir('/tmp/lib');
        $zip = new \ZipArchive;
        $opened = $zip->open($file);

        if ($opened === true) {
            $handler = $this->get('codebender_library.handler');
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
}
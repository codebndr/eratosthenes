<?php

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;
use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Form\NewLibraryForm;
use ZipArchive;


class ApiViewsController extends Controller
{

    /**
     * Creates and handles a form for adding external libraries to the
     * library management system. Will render the form page adding a flashbag
     * error upon failure. Will redirect to the newly created view page of the library
     * upon success.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function newLibraryAction()
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
            ['authorizationKey' => $authorizationKey, 'library' => $formData['MachineName'], 'disabled' => 1]));
    }

    /**
     * Performs the actual addition of an external library, as well as
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
                $path = $this->getInRepoPath($data["GitRepo"], $data['GitPath']);
                $libraryStructure = $handler->getGithubRepoCode($data["GitOwner"], $data["GitRepo"], $data['GitBranch'], $path);
                $lastCommit = $handler->getLastCommitFromGithub($data['GitOwner'], $data['GitRepo'], $data['GitBranch'], $path);
                break;
            case 'zip':
                $libraryStructure = json_decode($this->getLibFromZipFile($data["Zip"]), true);
                break;
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
        $creationResponse = json_decode(
            $this->saveNewLibrary($data['HumanName'], $data['MachineName'],
            $data['GitOwner'], $data['GitRepo'], $data['Description'],
                $lastCommit, $data['Url'], $data['GitBranch'], $data['SourceUrl'], $data['Notes'], $path, $libraryStructure)
            , true);
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

    public function viewLibraryAction()
    {
        $request = $this->getRequest();
        $library = $request->get('library');
        $version = $request->get('version');
        $disabled = $request->get('disabled') !== 1 ? false : true;

        $apiFetchCommand = $this->get('codebender_api.fetch');
        $requestData = [
            'type'=>'fetch',
            'library' => $library,
            'disabled' => $disabled,
            'version' => $version,
            'renderView' => 'true'
        ];
        $response = $apiFetchCommand->execute($requestData);

        if ($response['success'] !== true) {
            return new JsonResponse($response);
        }

        $inSync = false;
        if (!empty($response['meta'])) {
            $apiHandler = $this->get('codebender_library.apiHandler');
            $inSync = $apiHandler->isLibraryInSyncWithGit(
                $response['meta']['gitOwner'],
                $response['meta']['gitRepo'],
                $response['meta']['gitBranch'],
                $response['meta']['gitInRepoPath'],
                $response['meta']['gitLastCommit']
            );
        }

        return $this->render('CodebenderLibraryBundle:Api:libraryView.html.twig', array(
            'library' => $response['library'],
            'versions' => $response['versions'],
            'files' => $response['files'],
            'examples' => $response['examples'],
            'meta' => $response['meta'],
            'inSyncWithGit' => $inSync
        ));
    }

    public function gitUpdatesAction()
    {
        $checkGithubUpdatesCommand = $this->get('codebender_api.checkGithubUpdates');

        $handlerResponse = $checkGithubUpdatesCommand->execute();

        if ($handlerResponse['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid authorization key.']);
        }

        //TODO: create the twig and render it on return
        return new JsonResponse($handlerResponse);
    }

    public function searchAction()
    {
        $request = $this->getRequest();
        $query = $request->query->get('q');
        $json = $request->query->get('json');
        $names = array();

        if ($query !== null && $query != "") {
            $em = $this->getDoctrine()->getManager();
            $repository = $em->getRepository('CodebenderLibraryBundle:Library');
            $libraries = $repository->createQueryBuilder('p')->where('p.default_header LIKE :token')
                ->setParameter('token', "%" . $query . "%")->getQuery()->getResult();


            foreach ($libraries as $lib) {
                if ($lib->getActive())
                    $names[] = $lib->getDefaultHeader();
            }
        }
        if ($json !== null && $json = true) {
            return new JsonResponse(['success' => true, 'libs' => $names]);
        }
        return $this->render('CodebenderLibraryBundle:Api:search.html.twig',
            ['authorizationKey' => $this->container->getParameter('authorizationKey'), 'libs' => $names]);
    }

    public function changeLibraryStatusAction($library)
    {
        if ($this->getRequest()->getMethod() != 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'POST method required']);
        }

        $apiHandler = $this->get('codebender_library.apiHandler');
        $exists = $apiHandler->isExternalLibrary($library, true);

        if (!$exists) {
            return new JsonResponse(['success' => false, 'message' => 'Library not found.']);
        }

        $apiHandler->toggleLibraryStatus($library);

        return new JsonResponse(['success' => true]);
    }

    public function downloadAction($defaultHeaderFile, $version)
    {
        $htmlcode = 200;
        $finder = new Finder();
        $exampleFinder = new Finder();

        $apiHandler = $this->get('codebender_library.apiHandler');
        $isValidLibrary = $apiHandler->libraryVersionExists($defaultHeaderFile, $version);

        if (!$isValidLibrary) {
            $value = "";
            $htmlcode = 404;
            return new Response($value, $htmlcode);
        }

        $path = $apiHandler->getExternalLibraryPath($defaultHeaderFile, $version);

        $files = $apiHandler->fetchLibraryFiles($finder, $path, false);
        $examples = $apiHandler->fetchLibraryExamples($exampleFinder, $path);

        $zipname = "/tmp/asd.zip";

        $zip = new ZipArchive();

        if ($zip->open($zipname, ZIPARCHIVE::CREATE) === false) {
            $value = "";
            $htmlcode = 404;
        } else {
            if ($zip->addEmptyDir($defaultHeaderFile) !== true) {
                $value = "";
                $htmlcode = 404;
            } else {
                foreach ($files as $file) {
                    // No special handling needed for binary files, since addFromString method is binary safe.
                    $zip->addFromString($defaultHeaderFile . '/' . $file['filename'], file_get_contents($path . '/' . $file['filename']));
                }
                foreach ($examples as $file) {
                    $zip->addFromString($defaultHeaderFile . "/" . $file["filename"], $file["content"]);
                }
                $zip->close();
                $value = file_get_contents($zipname);
            }
            unlink($zipname);
        }

        $headers = array('Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment;filename="' . $defaultHeaderFile . '.zip"');

        return new Response($value, $htmlcode, $headers);
    }

    private function saveNewLibrary($humanName, $machineName, $gitOwner, $gitRepo, $description, $lastCommit, $url, $branch, $sourceUrl, $notes, $inRepoPath, $libfiles)
    {
        $handler = $this->get('codebender_library.handler');
        $exists = json_decode($handler->checkIfExternalExists($machineName), true);
        if ($exists['success'])
            return json_encode(array("success" => false, "message" => "Library named " . $machineName . " already exists."));

        $create = json_decode($this->createLibFiles($machineName, $libfiles), true);
        if (!$create['success'])
            return json_encode($create);

        $lib = new ExternalLibrary();
        $lib->setHumanName($humanName);
        $lib->setMachineName($machineName);
        $lib->setDescription($description);
        $lib->setOwner($gitOwner);
        $lib->setRepo($gitRepo);
        $lib->setBranch($branch);
        $lib->setInRepoPath($inRepoPath);
        $lib->setSourceUrl($sourceUrl);
        $lib->setNotes($notes);
        $lib->setVerified(false);
        $lib->setActive(false);
        $lib->setLastCommit($lastCommit);
        $lib->setUrl($url);

        $em = $this->getDoctrine()->getManager();
        $em->persist($lib);
        $em->flush();

        $externalLibrariesPath = $this->container->getParameter('external_libraries');
        $examples = $handler->fetchLibraryExamples(new Finder(), $externalLibrariesPath . '/' . $machineName);

        foreach ($examples as $example) {

            $path_parts = pathinfo($example['filename']);
            $this->saveExampleMeta($path_parts['filename'], $lib, $machineName . "/" . $example['filename'], null);
        }


        return json_encode(array("success" => true));

    }

    private function createLibFiles($machineName, $lib)
    {
        $libBaseDir = $this->container->getParameter('external_libraries') . '/' . $machineName . "/";
        return ($this->createLibDirectory($libBaseDir, $libBaseDir, $lib['contents']));
    }

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
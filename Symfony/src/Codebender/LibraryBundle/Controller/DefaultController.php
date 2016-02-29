<?php

namespace Codebender\LibraryBundle\Controller;

use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * Dummy function, returns status
     *
     * @return Response
     */
    public function statusAction()
    {
        return new JsonResponse(['success' => true, 'status' => 'OK']);
    }

    /**
     * The main library manager API handler action.
     * Checks the autorization credentials and the validity of the request.
     * Can handle several types of requests, like code fetching, examples fetching, etc.
     *
     * TODO: need to refactor how this work, JsonResponse objects are returned from all over the place inconsistently
     * @param $version
     * @return JsonResponse
     */
    public function apiHandlerAction($version)
    {
        if ($version != 'v1') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid library manager API version.']);
        }

        $request = $this->getRequest();
        $content = $request->getContent();

        $content = json_decode($content, true);
        if ($content === null) {
            return new JsonResponse(['success' => false, 'message' => 'Wrong data']);
        }

        if ($this->isValid($content) === false) {
            return new JsonResponse(['success' => false, 'message' => 'Incorrect request fields']);
        }

        return new JsonResponse($this->selectAction($content));
    }

    /**
     * Decides which operation should be excuted based on the `type` parameter of
     * the request. Returns an array with the results.
     *
     * @param $content
     * @return array
     */
    private function selectAction($content)
    {
        switch ($content["type"]) {
            case "list":
                return $this->listAll();
            case "getExampleCode":
                return $this->getExampleCode($content["library"], $content["example"]);
            case "getExamples":
                return $this->getLibraryExamples($content["library"]);
            case "fetch":
                $handler = $this->get('codebender_library.handler');
                return $handler->getLibraryCode($content["library"], 0);
            case "checkGithubUpdates":
                $handler = $this->get('codebender_library.handler');
                return $handler->checkGithubUpdates();
            case "getKeywords":
                return $this->getKeywords($content["library"]);
            default:
                return ['success' => false, 'message' => 'No valid action requested'];
        }
    }

    private function isValid($requestContent)
    {
        if (!array_key_exists("type", $requestContent)) {
            return false;
        }

        if (in_array($requestContent["type"], array("getExampleCode", "getExamples", "fetch", "getKeywords")) &&
            !array_key_exists("library", $requestContent)
        ) {
            return false;
        }

        if ($requestContent["type"] == "getExampleCode" && !array_key_exists("example", $requestContent)) {
            return false;
        }

        return true;
    }

    private function listAll()
    {

        $arduinoLibraryFiles = $this->container->getParameter('builtin_libraries') . "/";

        $builtinExamples = $this->getLibariesListFromDir($arduinoLibraryFiles . "examples");
        $includedLibraries = $this->getLibariesListFromDir($arduinoLibraryFiles . "libraries");
        /*
         * External libraries list is fetched from the database, because we need to list
         * active libraries only
         */
        $externalLibraries = $this->getExternalLibrariesList();

        ksort($builtinExamples);
        ksort($includedLibraries);
        ksort($externalLibraries);

        return [
            'success' => true,
            'text' => 'Successful Request!',
            'categories' => [
                'Examples' => $builtinExamples,
                'Builtin Libraries' => $includedLibraries,
                'External Libraries' => $externalLibraries
            ]
        ];
    }

    /**
     * @param $library
     * @param $example
     * @return mixed|string
     */
    private function getExampleCode($library, $example)
    {

        $type = $this->getLibraryType($library);
        if ($type['success'] !== true) {
            return $type;
        }

        switch ($type['type']) {
            case 'builtin':
                $dir = $this->container->getParameter('builtin_libraries') . "/libraries/";
                $example = $this->getExampleCodeFromDir($dir, $library, $example);
                break;
            case 'external':
                $example = $this->getExternalExampleCode($library, $example);
                break;
            case 'example':
                $dir = $this->container->getParameter('builtin_libraries') . "/examples/";
                $example = $this->getExampleCodeFromDir($dir, $library, $example);
                break;
        }

        return $example;
    }

    public function getLibraryGitBranchesAction()
    {
        $handler = $this->get('codebender_library.handler');

        $githubUrl = $this->getRequest()->request->get('githubUrl');
        $processedGitUrl = $handler->processGithubUrl($githubUrl);

        if ($processedGitUrl['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Could not process provided url']);
        }

        $repoBranches = $handler->fetchRepoRefsFromGit($processedGitUrl['owner'], $processedGitUrl['repo']);

        if ($repoBranches['success'] !== true) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Something went wrong while fetching the library. Please double check the Url you provided.'
            ]);
        }

        return new JsonResponse(['success' => true, 'branches' => $repoBranches['headRefs']]);
    }

    public function getRepoGitTreeAndMetaAction()
    {
        $handler = $this->get('codebender_library.handler');

        $githubUrl = $this->getRequest()->request->get('githubUrl');
        $processedGitUrl = $handler->processGithubUrl($githubUrl);
        $gitBranch = $this->getRequest()->request->get('githubBranch');

        if ($processedGitUrl['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Could not process provided url']);
        }

        $githubLibrary = json_decode(
            $handler->getRepoTreeStructure(
                $processedGitUrl['owner'],
                $processedGitUrl['repo'],
                $gitBranch,
                $processedGitUrl['folder']
            ),
            true
        );

        if (!$githubLibrary['success']) {
            return new JsonResponse($githubLibrary);
        }

        $description = $handler->getRepoDefaultDescription($processedGitUrl['owner'], $processedGitUrl['repo']);

        return new JsonResponse([
            'success' => true,
            'files' => $githubLibrary['files'],
            'owner' => $processedGitUrl['owner'],
            'repo' => $processedGitUrl['repo'],
            'branch' => $gitBranch,
            'description' => $description
        ]);
    }

    private function getLibraryExamples($library)
    {
        $exists = $this->getLibraryType($library);
        if ($exists['success'] !== true) {
            return $exists;
        }
        $examples = array();
        $path = "";
        /*
         * Assume the requested library is an example
         */
        $path = $this->container->getParameter('builtin_libraries') . "/examples/" . $library;
        if ($exists['type'] == 'external') {
            $path = $this->container->getParameter('external_libraries') . '/' . $library;
        }
        if ($exists['type'] == 'builtin') {
            $path = $this->container->getParameter('builtin_libraries') . "/libraries/" . $library;
        }
        $inoFinder = new Finder();
        $inoFinder->in($path);
        $inoFinder->name('*.ino')->name('*.pde');

        foreach ($inoFinder as $example) {
            $files = array();

            $content = (!mb_check_encoding($example->getContents(), 'UTF-8')) ? mb_convert_encoding($example->getContents(), "UTF-8") : $example->getContents();
            $pathInfo = pathinfo($example->getBaseName());
            $files[] = array(
                "filename" => $pathInfo['filename'] . '.ino',
                "content" => (!mb_check_encoding($content, 'UTF-8')) ? mb_convert_encoding($content, "UTF-8") : $content
            );

            // TODO: Not only .h and .cpp files in Arduino examples
            $notInoFilesFinder = new Finder();
            $notInoFilesFinder->files()->name('*.h')->name('*.cpp');
            $notInoFilesFinder->in($path . "/" . $example->getRelativePath());

            foreach ($notInoFilesFinder as $nonInoFile) {
                $files[] = array(
                    "filename" => $nonInoFile->getBaseName(),
                    "content" => (!mb_check_encoding($nonInoFile->getContents(), 'UTF-8')) ? mb_convert_encoding($nonInoFile->getContents(), "UTF-8") : $nonInoFile->getContents()
                );
            }

            $dir = preg_replace('/[E|e]xamples\//', '', $example->getRelativePath());
            $dir = str_replace($pathInfo['filename'], '', $dir);
            $dir = str_replace('/', ':', $dir);
            if ($dir != '' && substr($dir, -1) != ':') {
                $dir .= ':';
            }


            $examples[$dir . $pathInfo['filename']] = $files;
        }
        return ['success' => true, 'examples' => $examples];
    }

    private function getLibraryType($library)
    {
        $handler = $this->get('codebender_library.handler');

        /*
         * Each library's type can be either external () ..
         */
        $isExternal = json_decode($handler->checkIfExternalExists($library), true);
        if ($isExternal['success']) {
            return ['success' => true, 'type' => 'external'];
        }

        /*
         * .. or builtin (SD, Ethernet, etc) ...
         */
        $isBuiltIn = json_decode($handler->checkIfBuiltInExists($library), true);
        if ($isBuiltIn['success']) {
            return ['success' => true, 'type' => 'builtin'];
        }

        /*
         * .. or example (01.Basics, etc)
         */
        $isExample = json_decode($this->checkIfBuiltInExampleFolderExists($library), true);
        if ($isExample['success']) {
            return ['success' => true, 'type' => 'example'];
        }

        // Library was not found, return proper message
        return ['success' => false, 'message' => 'Library named ' . $library . ' not found.'];
    }

    private function getExternalExampleCode($library, $example)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $libMeta = $entityManager
            ->getRepository('CodebenderLibraryBundle:ExternalLibrary')
            ->findBy(array('machineName' => $library));

        $exampleMeta = $entityManager
            ->getRepository('CodebenderLibraryBundle:Example')
            ->findBy(array('library' => $libMeta[0], 'name' => $example));

        if (count($exampleMeta) == 0) {
            $example = str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $exampleMeta = $entityManager
                ->getRepository('CodebenderLibraryBundle:Example')
                ->findBy(array('library' => $libMeta[0], 'name' => $filename));

            if (count($exampleMeta) > 1) {
                $meta = null;
                foreach ($exampleMeta as $e) {
                    $path = $e->getPath();
                    if (!(strpos($path, $example) === false)) {
                        $meta = $e;
                        break;
                    }
                }
                if (!$meta) {
                    return ['success' => false];
                }
            } elseif (count($exampleMeta) == 0) {
                return ['success' => false];
            } else {
                $meta = $exampleMeta[0];
            }
        } else {
            $meta = $exampleMeta[0];
        }
        $fullPath = $this->container->getParameter('external_libraries') . '/' . $meta->getPath();

        $path = pathinfo($fullPath, PATHINFO_DIRNAME);
        $files = $this->getExampleFilesFromDir($path);
        return $files;

    }

    private function getExampleCodeFromDir($dir, $library, $example)
    {
        $finder = new Finder();
        $finder->in($dir . $library);
        $finder->name($example . ".ino", $example . ".pde");

        if (iterator_count($finder) == 0) {
            $example = str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $finder->name($filename . ".ino", $filename . ".pde");
            if (iterator_count($finder) > 1) {
                $filesPath = null;
                foreach ($finder as $e) {
                    $path = $e->getPath();
                    if (!(strpos($path, $example) === false)) {
                        $filesPath = $e;
                        break;
                    }
                }
                if (!$filesPath) {
                    return ['success' => false];
                }
            } elseif (iterator_count($finder) == 0) {
                return ['success' => false];
            } else {
                $filesPathIterator = iterator_to_array($finder, false);
                $filesPath = $filesPathIterator[0]->getPath();
            }
        } else {

            $filesPathIterator = iterator_to_array($finder, false);
            $filesPath = $filesPathIterator[0]->getPath();
        }
        $files = $this->getExampleFilesFromDir($filesPath);
        return $files;
    }

    private function getExampleFilesFromDir($dir)
    {
        $filesFinder = new Finder();
        $filesFinder->in($dir);
        $filesFinder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S')->name('*.pde')->name('*.ino');

        $files = array();
        foreach ($filesFinder as $file) {
            if ($file->getExtension() == "pde") {
                $name = $file->getBasename("pde") . "ino";
            } else {
                $name = $file->getFilename();
            }

            $files[] = array(
                "filename" => $name,
                "code" => (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents()
            );

        }

        return ['success' => true, "files" => $files];
    }

    private function checkIfBuiltInExampleFolderExists($library)
    {
        $arduinoLibraryFiles = $this->container->getParameter('builtin_libraries') . "/";
        if (is_dir($arduinoLibraryFiles . "/examples/" . $library)) {
            return json_encode(array("success" => true, "message" => "Library found"));
        }

        return json_encode(array("success" => false, "message" => "No Library named " . $library . " found."));
    }

    private function getExternalLibrariesList()
    {
        $entityManager = $this->getDoctrine()->getManager();
        $externalMeta = $entityManager
            ->getRepository('CodebenderLibraryBundle:ExternalLibrary')
            ->findBy(array('active' => true));

        $libraries = array();
        foreach ($externalMeta as $library) {
            $libraryMachineName = $library->getMachineName();
            if (!isset($libraries[$libraryMachineName])) {
                $libraries[$libraryMachineName] = array(
                    "description" => $library->getDescription(),
                    "humanName" => $library->getHumanName(),
                    "examples" => array()
                );

                if ($library->getOwner() !== null && $library->getRepo() !== null) {
                    $libraries[$libraryMachineName] = array(
                        "description" => $library->getDescription(),
                        "humanName" => $library->getHumanName(),
                        "url" => "http://github.com/" . $library->getOwner() . "/" . $library->getRepo(),
                        "examples" => array()
                    );
                }
            }

            $examples = $entityManager
                ->getRepository('CodebenderLibraryBundle:Example')
                ->findBy(array('library' => $library));

            foreach ($examples as $example) {
                $names = $this
                    ->getExampleAndLibNameFromRelativePath(
                        pathinfo($example->getPath(), PATHINFO_DIRNAME),
                        $example->getName()
                    );

                $libraries[$libraryMachineName]['examples'][] = array('name' => $names['example_name']);
            }


        }

        return $libraries;
    }

    private function getLibariesListFromDir($path)
    {

        $finder = new Finder();
        $finder->files()->name('*.ino')->name('*.pde');
        $finder->in($path);

        $libraries = array();

        foreach ($finder as $file) {
            $names = $this
                ->getExampleAndLibNameFromRelativePath(
                    $file->getRelativePath(),
                    $file->getBasename("." . $file->getExtension())
                );

            if (!isset($libraries[$names['library_name']])) {
                $libraries[$names['library_name']] = array("description" => "", "examples" => array());
            }
            $libraries[$names['library_name']]['examples'][] = array('name' => $names['example_name']);
        }
        return $libraries;
    }

    private function getExampleAndLibNameFromRelativePath($path, $filename)
    {
        $type = "";
        $libraryName = strtok($path, "/");

        $tmp = strtok("/");

        while ($tmp != "" && !($tmp === false)) {
            if ($tmp != 'examples' && $tmp != 'Examples' && $tmp != $filename) {
                if ($type == "") {
                    $type = $tmp;
                } else {
                    $type = $type . ":" . $tmp;
                }
            }
            $tmp = strtok("/");


        }
        $exampleName = ($type == "" ? $filename : $type . ":" . $filename);
        return (array('library_name' => $libraryName, 'example_name' => $exampleName));
    }

    private function getKeywords($library)
    {
        if ($library === null) {
            return ['success' => false];
        }

        $exists = $this->getLibraryType($library);

        if ($exists['success'] !== true) {
            return $exists;
        }

        if ($exists['type'] === 'external') {
            $path = $this->container->getParameter('external_libraries') . '/' . $library;
        } elseif ($exists['type'] === 'builtin') {
            $path = $this->container->getParameter('builtin_libraries') . "/libraries/" . $library;
        } else {
            return ['success' => false];
        }

        $keywords = array();

        $finder = new Finder();
        $finder->in($path);
        $finder->name('/keywords\.txt/i');

        foreach ($finder as $file) {
            $content = (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents();

            $lines = preg_split('/\r\n|\r|\n/', $content);

            foreach ($lines as $rawline) {

                $line = trim($rawline);
                $parts = preg_split('/\s+/', $line);

                $totalParts = count($parts);

                if (($totalParts == 2) || ($totalParts == 3)) {

                    if ((substr($parts[1], 0, 7) == "KEYWORD")) {
                        $keywords[$parts[1]][] = $parts[0];
                    }

                    if ((substr($parts[1], 0, 7) == "LITERAL")) {
                        $keywords["KEYWORD3"][] = $parts[0];
                    }

                }

            }

            break;
        }

        return ['success' => true, 'keywords' => $keywords];

    }
}

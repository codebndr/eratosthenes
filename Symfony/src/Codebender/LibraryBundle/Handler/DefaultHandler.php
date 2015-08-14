<?php
/**
 * Created by PhpStorm.
 * User: fpapadopou
 * Date: 1/28/15
 * Time: 11:54 AM
 */

namespace Codebender\LibraryBundle\Handler;

use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class DefaultHandler
{

    protected $entityManager;
    protected $container;

    function __construct(EntityManager $entityManager, ContainerInterface $containerInterface)
    {
        $this->entityManager = $entityManager;
        $this->container = $containerInterface;
    }

    public function getLibraryCode($library, $disabled, $renderView = false)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory') . "/";

        $finder = new Finder();
        $exampleFinder = new Finder();

        if ($disabled != 1)
            $getDisabled = false;
        else
            $getDisabled = true;


        $filename = $library;
        $directory = "";

        $last_slash = strrpos($library, "/");
        if ($last_slash !== false) {
            $filename = substr($library, $last_slash + 1);
            $vendor = substr($library, 0, $last_slash);
        }

        //TODO handle the case of different .h filenames and folder names
        if ($filename == "ArduinoRobot")
            $filename = "Robot_Control";
        else if ($filename == "ArduinoRobotMotorBoard")
            $filename = "Robot_Motor";

        $exists = json_decode($this->checkIfBuiltInExists($filename), true);

        if ($exists["success"]) {
            $response = $this->fetchLibraryFiles($finder, $arduino_library_files . "/libraries/" . $filename);

            if ($renderView) {
                $examples = $this->fetchLibraryExamples($exampleFinder, $arduino_library_files . "/libraries/" . $filename);
                $meta = array();
            }
        } else {
            $response = json_decode($this->checkIfExternalExists($filename, $getDisabled), true);
            if (!$response['success']) {
                return new Response(json_encode($response));
            } else {
                $response = $this->fetchLibraryFiles($finder, $arduino_library_files . "/external-libraries/" . $filename);
                if (empty($response))
                    return new Response(json_encode(array("success" => false, "message" => "No files for Library named " . $library . " found.")));
                if ($renderView) {
                    $examples = $this->fetchLibraryExamples($exampleFinder, $arduino_library_files . "/external-libraries/" . $filename);

                    $libmeta = $this->entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $filename));
                    $filename = $libmeta[0]->getMachineName();
                    $meta = array("humanName" => $libmeta[0]->getHumanName(), "description" => $libmeta[0]->getDescription(), "verified" => $libmeta[0]->getVerified(), "gitOwner" => $libmeta[0]->getOwner(), "gitRepo" => $libmeta[0]->getRepo(), "url" => $libmeta[0]->getUrl(), "active" => $libmeta[0]->getActive());

                }
            }
        }
        if (!$renderView)
            return new Response(json_encode(array("success" => true, "message" => "Library found", "files" => $response)));
        else {

            return new Response(json_encode(array(
                "success" => true,
                "library" => $filename,
                "files" => $response,
                "examples" => $examples,
                "meta" => $meta
            )));
        }
    }

    /*
     * TODO This function is never actually used. Need to test it
     */
    public function checkGithubUpdates()
    {
        $needToUpdate = array();
        $libraries = $this->entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findAll();

        foreach ($libraries as $lib) {
            $gitOwner = $lib->getOwner();
            $gitRepo = $lib->getRepo();

            if ($gitOwner !== null and $gitRepo !== null) {
                $lastCommitFromGithub = $this->getLastCommitFromGithub($gitOwner, $gitRepo);
                if ($lastCommitFromGithub !== $lib->getLastCommit())
                    $needToUpdate[] = array('Machine Name' => $lib->getMachineName(), "Human Name" => $lib->getHumanName(), "Git Owner" => $lib->getOwner(), "Git Repo" => $lib->getRepo());
            }
        }
        if (empty($needToUpdate))
            $response = array("success" => true, "message" => "No Libraries need to update");
        else
            $response = array("success" => true, "message" => "There are Libraries that need to update", "libraries" => $needToUpdate);

        return new Response(json_encode($response));
    }

    public function getLastCommitFromGithub($gitOwner, $gitRepo, $sha = 'master', $path = '')
    {
        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');
        $github_app_name = $this->container->getParameter('github_app_name');
        $url = "https://api.github.com/repos/" . $gitOwner . "/" . $gitRepo . "/commits" . "?sha=". $sha ."&client_id=" . $client_id . "&client_secret=" . $client_secret;
        if ($path != '') {
            $url .= "&path=$path";
        }
        $json_contents = json_decode($this->curlRequest($url, null, array('User-Agent: ' . $github_app_name)), true);

        return $json_contents[0]['sha'];
    }

    public function checkIfBuiltInExists($library)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory') . "/";
        if (is_dir($arduino_library_files . "/libraries/" . $library))
            return json_encode(array("success" => true, "message" => "Library found"));
        else
            return json_encode(array("success" => false, "message" => "No Library named " . $library . " found."));
    }

    public function checkIfExternalExists($library, $getDisabled = false)
    {
        $lib = $this->entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
        if (empty($lib) || (!$getDisabled && !$lib[0]->getActive())) {
            return json_encode(array("success" => false, "message" => "No Library named " . $library . " found."));
        } else {
            return json_encode(array("success" => true, "message" => "Library found"));
        }

    }

    public function fetchLibraryFiles($finder, $directory, $getContent = true)
    {
        if (!is_dir($directory)) {
            return array();
        }

        $finder->in($directory)->exclude('examples')->exclude('Examples');
        // Left this here, just in case we need it again.
        // $finder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S')->name('*.inc')->name('*.txt');
        $finder->name('*.*');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $response = array();
        foreach ($finder as $file) {
            if ($getContent) {
                $mimeType = finfo_file($finfo, $file);
                if (strpos($mimeType, "text/") === false)
                    $content = "/*\n *\n * We detected that this is not a text file.\n * Such files are currently not supported by our editor.\n * We're sorry for the inconvenience.\n * \n */";
                else
                    $content = (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents();
                $response[] = array("filename" => $file->getRelativePathname(), "content" => $content);
            } else
                $response[] = array("filename" => $file->getRelativePathname());
        }
        return $response;
    }

    public function fetchLibraryExamples($finder, $directory)
    {
        if (is_dir($directory)) {
            $finder->in($directory);
            $finder->name('*.pde')->name('*.ino');

            $response = array();
            foreach ($finder as $file) {
                $response[] = array("filename" => $file->getRelativePathname(), "content" => (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents());
            }

            return $response;
        }

    }

    public function getRepoTreeStructure($owner, $repo, $branch, $requestedFolder)
    {

        $clientId = $this->container->getParameter('github_app_client_id');
        $clientSecret = $this->container->getParameter('github_app_client_secret');
        $githubAppName = $this->container->getParameter('github_app_name');
        $currentUrl = "https://api.github.com/repos/$owner/$repo/git/trees/$branch";

        $currentUrl = $currentUrl . "?recursive=1&client_id=$clientId&client_secret=$clientSecret";

        /*
         * See the docs here https://developer.github.com/v3/git/trees/
         * for more info on the json returned.
         * Note: Not sure if setting the User-Agent is necessary
         */
        $gitResponse = json_decode($this->curlRequest($currentUrl, null, array('User-Agent: ' . $githubAppName)), true);

        if (array_key_exists('message', $gitResponse)) {
            return json_encode(array('success' => false, 'message' => $gitResponse['message']));
        }
        // TODO: Could try some recursive call to all tree nodes of the response, instead of just quitting
        if ($gitResponse['truncated'] !== false) {
            return json_encode(array('success' => false, 'message' => 'Truncated data. Try using a subtree of the repo'));
        }

        $fileStructure = $this->createJsTreeStructure($gitResponse['tree'], $repo, '.', array('sha' => $gitResponse['sha'], 'type' => 'tree'));

        $fileStructure = $this->findSelectedNode($repo . '/' . $requestedFolder, $fileStructure);

        return json_encode(array('success' => true, 'files' => $fileStructure));
    }

    public function getGithubRepoCode($owner, $repo, $branch, $path)
    {
        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');
        $github_app_name = $this->container->getParameter('github_app_name');
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = "https://api.github.com/repos/$owner/$repo/contents/$path?ref=$branch";
        $url .= "&client_id=" . $client_id . "&client_secret=" . $client_secret;

        /*
         * See the docs here https://developer.github.com/v3/repos/contents/
         * for more info on the json returned.
         * Note: Not sure if setting the User-Agent is necessary
         */
        $contents = json_decode($this->curlRequest($url, null, array('User-Agent: ' . $github_app_name)), true);

        if (array_key_exists('message', $contents)) {
            return array('success' => false, 'message' => $contents['message']);
        }

        if ($path == '') {
            $path = $repo;
        }
        $libraryContents = array('name' => pathinfo($path, PATHINFO_BASENAME), 'type' => 'dir', 'contents' => array());
        foreach ($contents as $element) {
            if ($element['type'] == 'file') {
                $code = $this->getGithubFileCode($owner, $repo, $branch, $element['path']);
                if ($code['success'] == false) {
                    return $code;
                }
                $libraryContents['contents'][] = $code['file'];
                continue;
            }
            $directoryContents = $this->getGithubRepoCode($owner, $repo, $branch, $element['path']);
            if ($directoryContents['success'] !== true) {
                return $directoryContents;
            }
            $libraryContents['contents'][] = $directoryContents['library'];
        }

        return array('success' => true, 'library' => $libraryContents);
    }


    private function getGithubFileCode($owner, $repo, $branch, $path)
    {
        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');
        $github_app_name = $this->container->getParameter('github_app_name');
        $url = "https://api.github.com/repos/$owner/$repo/contents/$path?ref=$branch";
        $url .= "&client_id=" . $client_id . "&client_secret=" . $client_secret;

        /*
         * See the docs here https://developer.github.com/v3/repos/contents/
         * for more info on the json returned.
         * Note: Not sure if setting the User-Agent is necessary
         */
        $contents = $this->curlRequest($url, null, array('Accept: application/vnd.github.v3.raw', 'User-Agent: ' . $github_app_name));
        $jsonDecodedContent = json_decode($contents, true);

        if (json_last_error() == JSON_ERROR_NONE && array_key_exists('message', $jsonDecodedContent)) {
            return array('success' => false, 'message' => $jsonDecodedContent['message']);
        }
        if (!mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8');
        }

        return array('success' => true, 'file' => array('name' => pathinfo($path, PATHINFO_BASENAME), 'type' => 'file', 'contents' => $contents));
    }

    public function findBaseDir($dir)
    {
        foreach ($dir['contents'] as $file) {
            if ($file['type'] == 'file' && strpos($file['name'], ".h") !== false)
                return json_encode(array('success' => true, 'directory' => $dir));

        }

        foreach ($dir['contents'] as $file) {
            if ($file['type'] == 'dir') {
                foreach ($file['contents'] as $f) {
                    if ($f['type'] == 'file' && strpos($f['name'], ".h") !== false) {
                        $file = $this->fixDirName($file);
                        return json_encode(array('success' => true, 'directory' => $file));
                    }
                }
            }
        }
    }

    private function fixDirName($dir)
    {
        foreach ($dir['contents'] as &$f) {
            if ($f['type'] == 'dir') {
                $first_slash = strpos($f['name'], "/");
                $f['name'] = substr($f['name'], $first_slash + 1);
                $f = $this->fixDirName($f);
            }
        }
        return $dir;
    }


    public function curlRequest($url, $post_request_data = null, $http_header = null)
    {
        $curl_req = curl_init();
        curl_setopt_array($curl_req, array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ));
        if ($post_request_data !== null)
            curl_setopt($curl_req, CURLOPT_POSTFIELDS, $post_request_data);

        if ($http_header !== null)
            curl_setopt($curl_req, CURLOPT_HTTPHEADER, $http_header);

        $contents = curl_exec($curl_req);

        curl_close($curl_req);
        return $contents;
    }

    public function processGithubUrl($url)
    {
        $urlParts = parse_url($url);
        /*
         * If hostname is other than github.com, the url is invalid
         */
        if ($urlParts['host'] != 'github.com') {
            return array('success' => false);
        }

        $path = $urlParts['path'];
        if ($path == '') {
            return array('success' => false);
        }

        $path = $this->cleanPrependingSlash($path);
        $pathComponents = explode('/', $path);

        $owner = $pathComponents[0]; // The first part of the path is always the author
        $repo = $pathComponents[1]; // The next part of the path is always the repo name
        $folder = str_replace("$owner/$repo", '', $path); // Return the rest of the path, if any

        $folder = $this->cleanPrependingSlash($folder);

        $branch = 'master';
        if (preg_match("/tree\/(\w+)\//", $path, $matches)) {
            $branch = $matches[1];
            $folder = str_replace("tree/$branch", '', $folder);
        }

        $folder = $this->cleanPrependingSlash($folder);

        return array('success' => true, 'owner' => $owner, 'repo' => $repo, 'branch' => $branch, 'folder' => $folder);
    }

    private function cleanPrependingSlash($path)
    {
        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        return $path;
    }

    /**
     * @param array $repoTree The tree of blobs and sub-trees returned from Github's API
     * @param string $nodeName The name of the file tree node processed in the current iteration of the function
     * @param string $path The root node of the file structure on each iteration of the function
     * @param array $gitMeta The git metadata of the tree node processed in the current iteration
     * @return array The file structure in a format that can be viewed by jsTree jQuery plugin
     * @url for more info on jsTree, check this out https://www.jstree.com/
     */
    public function createJsTreeStructure($repoTree, $nodeName, $path, $gitMeta)
    {
        $fileStructure = array_merge(array('text' => $nodeName, 'icon' => 'fa fa-folder', 'children' => array()), $gitMeta);

        /*
         * Create two separate arrays, one containing the files found in the treee,
         * and one containing the nodes (folders).
         * Remember that files are listed as `blobs` and directories are listed as `trees`
         * array_values is used to re-index the two arrays
         */
        $subtreeNodes = array_values(array_filter($repoTree, function($element) { if ($element['type'] == 'tree') { return true; } return false; }));
        $files = array_values(array_filter($repoTree, function($element) { if ($element['type'] == 'blob') { return true; } return false; }));

        foreach ($files as $file) {
            if (pathinfo($file['path'], PATHINFO_DIRNAME) != $path) {
                continue;
            }
            $fileStructure['children'][] = array_merge(
                array('text' => pathinfo($file['path'], PATHINFO_BASENAME), 'icon' => 'fa fa-file', 'state' => array('disabled' => true)),
                $file);
        }

        foreach ($subtreeNodes as $directory) {
            if (pathinfo($directory['path'], PATHINFO_DIRNAME) != $path) {
                continue;
            }
            $treeUnderCurrentDir = $this->getTreeUnderProvidedDirectory($repoTree, $directory['path']);
            $result = $this->createJsTreeStructure($treeUnderCurrentDir, pathinfo($directory['path'], PATHINFO_BASENAME), $directory['path'], $directory);
            $fileStructure['children'][] = $result;
        }

        /*
         * If any headers exist among the children of a node,
         * they should be listed as possible machine names of the library,
         * in case this node is the root directory of a library
         */
        $fileStructure['machineNames'] = $this->getMachineNamesFromChildren($fileStructure['children']);

        return  $fileStructure;
    }

    /**
     * Returns the blobs and trees of a provided tree that belong to
     * a specific directory.
     * Uses a regular expression in order to strictly check if the
     * provided directory is the beginning of each of the tree elements.
     *
     * @param array $initialTree
     * @param string $directory
     * @return array
     */
    private function getTreeUnderProvidedDirectory($initialTree, $directory)
    {
        $subtree = array();

        foreach ($initialTree as $element) {
            if (!preg_match('/^' . preg_quote($directory, '/') . '/', pathinfo($element['path'], PATHINFO_DIRNAME))) {
                continue;
            }

            $subtree[] = $element;
        }
        return $subtree;
    }

    /**
     * Detects header files within the provided array and
     * returns a list containing the names of these files.
     *
     * @param array $children
     * @return array
     */
    private function getMachineNamesFromChildren($children)
    {
        $machineNames = array();

        foreach ($children as $child) {
            if ($child['type'] != 'blob' || pathinfo($child['path'], PATHINFO_EXTENSION) != 'h') {
                continue;
            }
            $machineNames[] = pathinfo($child['path'], PATHINFO_FILENAME);
        }

        return $machineNames;
    }

    private function findSelectedNode($path, $files)
    {
        /*
         * Remove trailing slashes
         */
        if (substr($path, -1) == '/') {
            $path = substr($path, 0, strlen($path) - 1);
        }
        $path = explode('/', $path);

        $files['state'] = array('opened' => true);
        if (count($path) == 1) {
            $files['state'] = array('opened' => true, 'selected' => true);
            return $files;
        }

        unset($path[0]);
        $path = array_values($path);
        if (count($path) == 0) {
            return $files;
        }

        foreach ($files['children'] as &$child) {
            if ($child['type'] != 'tree' || !array_key_exists('children', $child) || $child['text'] != $path[0]) {
                continue;
            }
            $child = $this->findSelectedNode(implode('/', $path), $child);
            break;
        }

        return $files;
    }

    public function fetchRepoRefsFromGit($owner, $repo)
    {
        $clientId = $this->container->getParameter('github_app_client_id');
        $clientSecret = $this->container->getParameter('github_app_client_secret');

        $githubAppName = $this->container->getParameter('github_app_name');
        $url = "https://api.github.com/repos/$owner/$repo/git/refs/heads";

        $url .= "?client_id=$clientId&client_secret=$clientSecret";

        /*
         * See the docs here https://developer.github.com/v3/git/refs/
         * for more info on the json returned.
         * Note: Not sure if setting the User-Agent is necessary
         */
        $gitResponse = json_decode($this->curlRequest($url, null, array('User-Agent: ' . $githubAppName)), true);

        if (array_key_exists('message', $gitResponse)) {
            return array('success' => false, 'message' => $gitResponse['message']);
        }

        $headRefs = array();
        foreach ($gitResponse as $ref) {
            $headRefs[] = str_replace('refs/heads/', '', $ref['ref']);
        }

        return array('success' => true, 'headRefs' => $headRefs);
    }

    /**
     * Returns the description of a Github repository
     *
     * @param string $owner The owner of the repository
     * @param string $repo The repository name
     * @return string The description of the repo, if any
     */
    public function getRepoDefaultDescription($owner, $repo)
    {
        $clientId = $this->container->getParameter('github_app_client_id');
        $clientSecret = $this->container->getParameter('github_app_client_secret');
        $githubAppName = $this->container->getParameter('github_app_name');

        $url = "https://api.github.com/repos/$owner/$repo";

        $url .= "?client_id=$clientId&client_secret=$clientSecret";

        /*
         * See the docs here https://developer.github.com/v3/repos/
         * for more info on the json returned.
         * Note: Not sure if setting the User-Agent is necessary
         */
        $gitResponse = json_decode($this->curlRequest($url, null, array('User-Agent: ' . $githubAppName)), true);

        if (!array_key_exists('description', $gitResponse)) {
            return '';
        }

        return $gitResponse['description'];
    }

}
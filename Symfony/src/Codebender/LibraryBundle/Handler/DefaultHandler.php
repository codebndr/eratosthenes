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
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $builtinLibrariesPath = $this->container->getParameter('builtin_libraries') . "/";
        $externalLibrariesPath = $this->container->getParameter('external_libraries') . "/";

        $finder = new Finder();
        $exampleFinder = new Finder();

        if ($disabled != 1) {
            $getDisabled = false;
        } else {
            $getDisabled = true;
        }

        $filename = $library;

        $last_slash = strrpos($library, "/");
        if ($last_slash !== false) {
            $filename = substr($library, $last_slash + 1);
        }

        //TODO handle the case of different .h filenames and folder names
        if ($filename == "ArduinoRobot") {
            $filename = "Robot_Control";
        }
        if ($filename == "ArduinoRobotMotorBoard") {
            $filename = "Robot_Motor";
        }
        if ($filename == 'BlynkSimpleSerial' || $filename == 'BlynkSimpleCC3000') {
            $filename = 'BlynkSimpleEthernet';
        }

        $exists = json_decode($this->checkIfBuiltInExists($filename), true);

        if ($exists["success"]) {
            $response = $this->fetchLibraryFiles($finder, $builtinLibrariesPath . "/libraries/" . $filename);

            if ($renderView) {
                $examples = $this->fetchLibraryExamples($exampleFinder, $builtinLibrariesPath . "/libraries/" . $filename);
                $meta = [];
            }
        } else {
            $response = json_decode($this->checkIfExternalExists($filename, $getDisabled), true);
            if (!$response['success']) {
                return $response;
            } else {
                $response = $this->fetchLibraryFiles($finder, $externalLibrariesPath . "/" . $filename);
                if (empty($response)) {
                    return ['success' => false, 'message' => 'No files for Library named ' . $library . ' found.'];
                }

                if ($renderView) {
                    $examples = $this->fetchLibraryExamples($exampleFinder, $externalLibrariesPath . "/" . $filename);

                    $externalLibrary = $this->entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')
                        ->findOneBy(array('machineName' => $filename));
                    $filename = $externalLibrary->getMachineName();
                    $meta = $externalLibrary->getLiraryMeta();
                }
            }
        }
        if (!$renderView) {
            return ['success' => true, 'message' => 'Library found', 'files' => $response];
        }

        return [
            'success' => true,
            'library' => $filename,
            'files' => $response,
            'examples' => $examples,
            'meta' => $meta
        ];
    }

    /**
     * Checks all external libraries that are uploaded from Github, making sure the commit
     * hash stored in our database is the same as the last commit on the repo origin.
     * If no branch is stored in the database for a specific library, the default (master) is
     * used. In case no in-repo path is stored in the database, an empty path is used during the
     * last commit fetching, that is, the last commit for the root directory of the repo is fethced.
     * TODO: Enchance the method, making it able to auto-update any outdated libraries.
     *
     * @return array
     */
    public function checkGithubUpdates()
    {
        $needToUpdate = array();
        $libraries = $this->entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findAll();

        foreach ($libraries as $lib) {
            $gitOwner = $lib->getOwner();
            $gitRepo = $lib->getRepo();

            if ($lib->getActive() === false || $gitOwner === null || $gitRepo === null) {
                continue;
            }

            $branch = $lib->getBranch();
            if ($branch === null) {
                $branch = ''; // not providing any branch will make git return the commits of the default branch
            }

            $directoryInRepo = $lib->getInRepoPath();
            if ($directoryInRepo === null) {
                $directoryInRepo = '';
            }

            $lastCommitFromGithub = $this->getLastCommitFromGithub($gitOwner, $gitRepo, $branch, $directoryInRepo);
            if ($lastCommitFromGithub !== $lib->getLastCommit()) {
                $needToUpdate[] = [
                    'Machine Name' => $lib->getMachineName(),
                    'Human Name' => $lib->getHumanName(),
                    'Git Owner' => $lib->getOwner(),
                    'Git Repo' => $lib->getRepo(),
                    'Git Branch' => $lib->getBranch(),
                    'Path in Git Repo' => $lib->getInRepoPath()
                ];
            }
        }
        if (empty($needToUpdate)) {
            return ['success' => true, 'message' => 'No external libraries need to be updated'];
        }

        return [
            'success' => true,
            'message' => count($needToUpdate) . " external libraries need to be updated",
            'libraries' => $needToUpdate
        ];
    }

    /**
     * Fetches the last commit sha of a repo. `sha` parameter can either be the name of a branch, or a commit
     * sha. In the first case, the commit sha's of the branch are returned. In the second case, the commit sha's
     * of the default branch are returned, as long as the have been written after the provided commit.
     * Not providing any sha/branch will make Git API return the list of commits for the default branch.
     * The API can also use a path parameter, in which case only commits that affect a specific directory are returned.
     *
     * @param $gitOwner
     * @param $gitRepo
     * @param string $sha
     * @param string $path
     * @return mixed
     */
    public function getLastCommitFromGithub($gitOwner, $gitRepo, $sha = '', $path = '')
    {
        /*
         * See the docs here https://developer.github.com/v3/repos/commits/
         * for more info on the json returned.
         */
        $url = "https://api.github.com/repos/" . $gitOwner . "/" . $gitRepo . "/commits";
        $queryParams = '';
        if ($sha != '') {
            $queryParams = "?sha=" . $sha;
        }
        if ($path != '') {
            $queryParams .= "&path=$path";
        }

        $lastCommitResponse = $this->curlGitRequest($url, $queryParams);

        return $lastCommitResponse[0]['sha'];
    }

    public function checkIfBuiltInExists($library)
    {
        $arduino_library_files = $this->container->getParameter('builtin_libraries') . "/";
        if (!is_dir($arduino_library_files . "/libraries/" . $library)) {
            return json_encode(array("success" => false, "message" => "No Library named " . $library . " found."));
        }

        return json_encode(array("success" => true, "message" => "Library found"));
    }

    public function checkIfExternalExists($library, $getDisabled = false)
    {
        $lib = $this->entityManager
                    ->getRepository('CodebenderLibraryBundle:ExternalLibrary')
                    ->findBy(array('machineName' => $library));

        if (empty($lib) || (!$getDisabled && !$lib[0]->getActive())) {
            return json_encode(array("success" => false, "message" => "No Library named " . $library . " found."));
        }

        return json_encode(array("success" => true, "message" => "Library found"));
    }

    public function fetchLibraryFiles($finder, $directory, $getContent = true)
    {
        if (!is_dir($directory)) {
            return array();
        }

        $finder->in($directory)->exclude('examples')->exclude('Examples');
        $finder->name('*.*');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $response = array();
        foreach ($finder as $file) {
            if ($getContent) {
                $mimeType = finfo_file($finfo, $file);
                if (strpos($mimeType, "text/") === false) {
                    $content = "/*\n *\n * We detected that this is not a text file.\n * Such files are currently not supported by our editor.\n * We're sorry for the inconvenience.\n * \n */";
                } else {
                    $content = (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents();
                }
                $response[] = array("filename" => $file->getRelativePathname(), "content" => $content);
            } else {
                $response[] = array("filename" => $file->getRelativePathname());
            }
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

    /**
     * Determines whether an ExternalLibrary is in sync with its Github origin repo, if any.
     *
     * @param $owner
     * @param $repo
     * @param $branch
     * @param $inRepoPath
     * @param $lastCommit
     * @return bool
     */
    public function isLibraryInSyncWithGit($owner, $repo, $branch, $inRepoPath, $lastCommit)
    {
        /*
         * The values below are fetched fromt the database of the application. If any of them is not set
         * in the database, the default (null) value will be returned.
         */
        if ($owner === null || $repo === null || $branch === null || $lastCommit === null) {
            return false;
        }

        if ($inRepoPath === null) {
            $inRepoPath = '';
        }

        $originLastCommit = $this->getLastCommitFromGithub($owner, $repo, $branch, $inRepoPath);

        if ($originLastCommit != $lastCommit) {
            return false;
        }

        return true;
    }

    /**
     * Get a Github repo's tree structure
     * @param $owner
     * @param $repo
     * @param $ref String could be a commit sha, a branch, or a tag
     * @param $requestedFolder
     * @return string
     */
    public function getRepoTreeStructure($owner, $repo, $ref, $requestedFolder)
    {
        $currentUrl = "https://api.github.com/repos/$owner/$repo/git/trees/$ref";

        $queryParams = "?recursive=1";

        /*
         * See the docs here https://developer.github.com/v3/git/trees/
         * for more info on the json returned.
         */
        $gitResponse = $this->curlGitRequest($currentUrl, $queryParams);

        if (array_key_exists('message', $gitResponse)) {
            return json_encode(array('success' => false, 'message' => $gitResponse['message']));
        }
        // TODO: Could try some recursive call to all tree nodes of the response, instead of just quitting
        if ($gitResponse['truncated'] != false) {
            return json_encode(array('success' => false, 'message' => 'Truncated data. Try using a subtree of the repo'));
        }

        $fileStructure = $this->createJsTreeStructure($gitResponse['tree'], $repo, '.', array('sha' => $gitResponse['sha'], 'type' => 'tree'));

        $fileStructure = $this->findSelectedNode($repo . '/' . $requestedFolder, $fileStructure);

        return json_encode(array('success' => true, 'files' => $fileStructure));
    }

    /**
     * Get contents from the name of the commit/branch/tag
     * @param $owner String
     * @param $repo String
     * @param $ref String The name of the commit/branch/tag
     * @param $path String
     * @return array
     */
    public function getGithubRepoCode($owner, $repo, $ref, $path)
    {
        $urlEncodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = "https://api.github.com/repos/$owner/$repo/contents/$urlEncodedPath";
        $queryParams = "?ref=$ref";

        /*
         * See the docs here https://developer.github.com/v3/repos/contents/
         * for more info on the json returned.
         */
        $contents = $this->curlGitRequest($url, $queryParams);

        // When something goes wrong during a Git API request, a `message` key exists in the response.
        // Thus we have to return `success => false`.
        if (array_key_exists('message', $contents)) {
            return ['success' => false, 'message' => $contents['message']];
        }

        if ($path == '') {
            $path = $repo;
        }
        $libraryContents = array(
            'name' => pathinfo($path, PATHINFO_BASENAME),
            'type' => 'dir',
            'contents' => array()
        );
        foreach ($contents as $element) {
            if ($element['type'] == 'file') {
                $code = $this->getGithubFileCode($owner, $repo, $element['path'], $element['sha']);
                if ($code['success'] == false) {
                    return $code;
                }
                $libraryContents['contents'][] = $code['file'];
            } elseif ($element['type'] == 'dir') {
                $directoryContents = $this->getGithubRepoCode($owner, $repo, $ref, $element['path']);
                if ($directoryContents['success'] !== true) {
                    return $directoryContents;
                }
                $libraryContents['contents'][] = $directoryContents['library'];
            }
        }

        return array('success' => true, 'library' => $libraryContents);
    }


    private function getGithubFileCode($owner, $repo, $path, $blobSha)
    {
        $url = "https://api.github.com/repos/$owner/$repo/git/blobs/$blobSha";

        /*
         * See the docs here https://developer.github.com/v3/git/blobs/
         * for more info on the json returned.
         */
        $jsonDecodedContent = $this->curlGitRequest($url);

        if (json_last_error() != JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'Invalid Git API response (cannot decode)');
        }

        if (array_key_exists('message', $jsonDecodedContent)) {
            return array('success' => false, 'message' => $jsonDecodedContent['message']);
        }

        if ($jsonDecodedContent['encoding'] != 'base64') {
            return array('success' => false, 'message' => 'Received ' . $path . ' file from Github encoded in ' . $jsonDecodedContent['encoding'] . 'encoding, which cannot be handled.');
        }

        return array('success' => true, 'file' => array('name' => pathinfo($path, PATHINFO_BASENAME), 'type' => 'file', 'contents' => base64_decode($jsonDecodedContent['content'])));
    }

    public function findBaseDir($dir)
    {
        foreach ($dir['contents'] as $file) {
            if ($file['type'] == 'file' && strpos($file['name'], ".h") !== false) {
                return json_encode(array('success' => true, 'directory' => $dir));
            }
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
        if ($post_request_data !== null) {
            curl_setopt($curl_req, CURLOPT_POSTFIELDS, $post_request_data);
        }

        if ($http_header !== null) {
            curl_setopt($curl_req, CURLOPT_HTTPHEADER, $http_header);
        }

        $contents = curl_exec($curl_req);

        curl_close($curl_req);
        return $contents;
    }

    /**
     * A wrapper for the curlRequest function which adds Github authentication
     * to the Github API request
     * Returns the json decoded Github response.
     *
     * @param string $url The requested url
     * @param string $queryParams Additional query parameters to be added to the request url
     * @return mixed
     */
    private function curlGitRequest($url, $queryParams = '')
    {
        $clientId = $this->container->getParameter('github_app_client_id');
        $clientSecret = $this->container->getParameter('github_app_client_secret');
        $githubAppName = $this->container->getParameter('github_app_name');

        $requestUrl = $url . "?client_id=" . $clientId . "&client_secret=" . $clientSecret;
        if ($queryParams != '') {
            $requestUrl = $url . $queryParams . "&client_id=" . $clientId . "&client_secret=" . $clientSecret;
        }
        /*
         * Note: The user-agent MUST be set to a valid value, otherwise the request will be rejected. One of the
         * suggested values is the application name.
         * One more thing that must be set on the headers, is the version of the API, which will offer stability
         * to the application, in case of future Github API updates.
         */
        $jsonDecodedContent = json_decode(
            $this->curlRequest(
                $requestUrl,
                null,
                ['User-Agent: ' . $githubAppName, 'Accept: application/vnd.github.v3.json']
            ),
            true
        );

        return $jsonDecodedContent;
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

    public function cleanPrependingSlash($path)
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
        $subtreeNodes = array_values(
            array_filter(
                $repoTree,
                function ($element) {
                    if ($element['type'] == 'tree') {
                        return true;
                    }
                    return false;
                }
            )
        );

        $files = array_values(
            array_filter(
                $repoTree,
                function ($element) {
                    if ($element['type'] == 'blob') {
                        return true;
                    }
                    return false;
                }
            )
        );

        foreach ($files as $file) {
            if (pathinfo($file['path'], PATHINFO_DIRNAME) != $path) {
                continue;
            }
            $fileStructure['children'][] = array_merge(
                array('text' => pathinfo($file['path'], PATHINFO_BASENAME), 'icon' => 'fa fa-file', 'state' => array('disabled' => true)),
                $file
            );
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
            if ($child['type'] == 'blob' && pathinfo($child['path'], PATHINFO_EXTENSION) == 'h') {
                $machineNames[] = pathinfo($child['path'], PATHINFO_FILENAME);
            }
        }

        return $machineNames;
    }

    /**
     * Iterates over a generated JS-tree structure and finds the selected node (and
     * its selected sub-nodes and so on) based on the provided path. Each node can either
     * be of type `blob` (file) or type `tree` (directory).
     * The method will recursively be called until all the nodes of the path are marked as
     * `selected`.
     *
     * @param string $path
     * @param array $files
     * @return array
     */
    private function findSelectedNode($path, $files)
    {
         // Remove trailing slashes
        $path = rtrim($path, '/');
        // Then split the provided path in slashes
        $path = explode('/', $path);

        $files['state'] = ['opened' => true];
        if (count($path) == 1) {
            $files['state'] += ['selected' => true];
            return $files;
        }

        // Since the current node ($path[0]) is marked as selected, remove it from the path
        // and call the method again providing the rest of the path
        unset($path[0]);
        $path = array_values($path);
        if (count($path) == 0) {
            // Getting here means we've reached the final selection node in the tree structure
            return $files;
        }

        // Find the next directory node that mathes the provided path, repeat the process,
        // marking its sub-nodes as selected.
        foreach ($files['children'] as $key => $childNode) {
            if ($childNode['type'] == 'tree' && array_key_exists('children', $childNode) && $childNode['text'] == $path[0]) {
                $files['children'][$key] = $this->findSelectedNode(implode('/', $path), $childNode);
                break;
            }
        }

        return $files;
    }

    public function fetchRepoRefsFromGit($owner, $repo)
    {
        $url = "https://api.github.com/repos/$owner/$repo/git/refs/heads";

        /*
         * See the docs here https://developer.github.com/v3/git/refs/
         * for more info on the json returned.
         */
        $gitResponse = $this->curlGitRequest($url);

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
        $url = "https://api.github.com/repos/$owner/$repo";

        /*
         * See the docs here https://developer.github.com/v3/repos/
         * for more info on the json returned.
         */
        $gitResponse = $this->curlGitRequest($url);

        if (!array_key_exists('description', $gitResponse)) {
            return '';
        }

        return $gitResponse['description'];
    }
}

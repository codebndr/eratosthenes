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
     * @param bool $checkDisabled
     * @return bool
     */
    public function libraryVersionExists($defaultHeader, $version, $checkDisabled = false)
    {
        if ($this->isValidExternalLibraryVersion($defaultHeader, $version, $checkDisabled)) {
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
     * This method toggles the active status of a library.
     *
     * @param $defaultHeader
     */
    public function toggleLibraryStatus($defaultHeader)
    {
        $entityManager = $this->entityManager;
        $library = $entityManager
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findBy(array('default_header' => $defaultHeader));

        // Do nothing if the library does not exist
        if (count($library) < 1) {
            return;
        }

        $library = $library[0];
        $currentStatus = $library->getActive();
        $library->setActive(!$currentStatus);
        $entityManager->persist($library);
        $entityManager->flush();
    }

    /**
     * This method checks if a library is in sync with its Github repository given its Github metadata.
     *
     * @param $gitOwner
     * @param $gitRepo
     * @param $gitBranch
     * @param $gitInRepoPath
     * @param $gitLastCommit
     * @return bool
     */
    public function isLibraryInSyncWithGit($gitOwner, $gitRepo, $gitBranch, $gitInRepoPath, $gitLastCommit)
    {
        /*
         * The values below are fetched fromt the database of the application. If any of them is not set
         * in the database, the default (null) value will be returned.
         */
        if ($gitOwner === null || $gitRepo === null || $gitBranch === null || $gitLastCommit === null) {
            return false;
        }

        $gitBranch = $this->convertNullToEmptyString($gitBranch); // not providing any branch will make git return the commits of the default branch
        $gitInRepoPath = $this->convertNullToEmptyString($gitInRepoPath);

        $lastCommitFromGithub = $this->getLastCommitFromGithub($gitOwner, $gitRepo, $gitBranch, $gitInRepoPath);
        return $lastCommitFromGithub === $gitLastCommit;
    }

    public function fetchLibraryFiles($finder, $directory, $getContent = true)
    {
        if (!is_dir($directory)) {
            return array();
        }
        $finder->in($directory)->exclude('examples')->exclude('Examples');
        $finder->name('*.*');
        $finder->files(); // fetch only files

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
     * This method compares the commit time of two commits. The method returns an integer less than 0 if commit1
     * is before commit2, 0 if commit 1 was committed at the same time as commit 2, and greater than 0 if commit1
     * is later than commit2.
     *
     * @param $gitOwner
     * @param $gitRepo
     * @param $commit1
     * @param $commit2
     * @return int
     */
    public function compareCommitTime($gitOwner, $gitRepo, $commit1, $commit2)
    {
        $commit1Timestamp = $this->getCommitTimestamp($gitOwner, $gitRepo, $commit1);
        $commit2Timestamp = $this->getCommitTimestamp($gitOwner, $gitRepo, $commit2);
        return $commit1Timestamp - $commit2Timestamp;
    }

    /**
     * This method returns the UNIX timestamp of a commit.
     *
     * @param $gitOwner
     * @param $gitRepo
     * @param $commit
     * @return int
     */
    public function getCommitTimestamp($gitOwner, $gitRepo, $commit)
    {
        $url = "https://api.github.com/repos/" . $gitOwner . "/" . $gitRepo . "/git/commits/" . $commit;
        $reponse = $this->curlGitRequest($url);
        $dateString = $reponse['committer']['date'];
        return strtotime($dateString);
    }

    /**
     * This method takes in an object and returns and empty
     * string if the object is null. Otherwise, the original
     * object is returned.
     *
     * @param $object
     * @return string an empty string if $object is null, otherwise
     * $object is returned
     */
    private function convertNullToEmptyString($object)
    {
        if ($object === null) {
            return '';
        }

        return $object;
    }

    /**
     * This method checks if the given version exists in the given library
     * specified by the $defaultHeader.
     *
     * @param $defaultHeader
     * @param $version
     * @param bool $checkDisabled
     * @return bool
     */
    private function isValidExternalLibraryVersion($defaultHeader, $version, $checkDisabled = false)
    {
        if (!$this->isExternalLibrary($defaultHeader, $checkDisabled)) {
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
    private function getLastCommitFromGithub($gitOwner, $gitRepo, $sha = '', $path = '')
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

    private function curlRequest($url, $post_request_data = null, $http_header = null)
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
}

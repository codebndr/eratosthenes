<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CheckGithubUpdatesCommand extends AbstractApiCommand
{
    /**
     * Checks all external libraries that are uploaded from Github, making sure the commit
     * hash stored in our database is the same as the last commit on the repo origin.
     * If no branch is stored in the database for a specific library, the default (master) is
     * used. In case no in-repo path is stored in the database, an empty path is used during the
     * last commit fetching, that is, the last commit for the root directory of the repo is fethced.
     * TODO: Enchance the method, making it able to auto-update any outdated libraries.
     *
     * @param $content
     * @return array
     */
    public function execute($content)
    {
        $needToUpdate = array();
        $libraries = $this->entityManager->getRepository('CodebenderLibraryBundle:Library')->findAll();

        foreach ($libraries as $lib) {
            if (!$this->isActive($lib) || !$this->hasGit($lib)){
                continue;
            }

            if (!$this->isUpdated($lib)) {
                $needToUpdate[] = $this->getLibrarySummary($lib);
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
     * This method checks if a given library is updated or not.
     *
     * @param $library
     * @return bool
     */
    private function isUpdated($library)
    {
        $gitOwner = $library->getOwner();
        $gitRepo = $library->getRepo();

        $branch = '1.0.x';
        $branch = $this->convertNullToEmptyString($branch); // not providing any branch will make git return the commits of the default branch

        $directoryInRepo = $library->getInRepoPath();
        $directoryInRepo = $this->convertNullToEmptyString($directoryInRepo);

        $lastCommitFromGithub = $this->getLastCommitFromGithub($gitOwner, $gitRepo, $branch, $directoryInRepo);
        return $lastCommitFromGithub === $library->getLastCommit();
    }

    /**
     * This method returns a summary of the given library.
     *
     * @param $library
     * @return array
     */
    private function getLibrarySummary($library)
    {
        return [
            'Machine Name' => $library->getDefaultHeader(),
            'Human Name' => $library->getName(),
            'Git Owner' => $library->getOwner(),
            'Git Repo' => $library->getRepo(),
            'Git Branch' => $library->getBranch(),
            'Path in Git Repo' => $library->getInRepoPath()
        ];
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
     * This method checks if a given library is active.
     *
     * @param $library
     * @return bool
     */
    private function isActive($library)
    {
        return $library->getActive();
    }

    /**
     * This method checks if a given library has a git repo.
     *
     * @param $library
     * @return bool
     */
    private function hasGit($library)
    {
        $gitOwner = $library->getOwner();
        $gitRepo = $library->getRepo();
        return !is_null($gitOwner) && !is_null($gitRepo);
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

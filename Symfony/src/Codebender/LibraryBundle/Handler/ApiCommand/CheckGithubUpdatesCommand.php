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
            if (!$this->isActive($lib) || !$this->hasGit($lib)) {
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
     * This method checks if a given library is updated or not.
     *
     * @param $library
     * @return bool
     */
    private function isUpdated($library)
    {
        $apiHandler = $this->get('codebender_library.apiHandler');
        $metaData = $library->getLibraryMeta();
        return $apiHandler->isLibraryInSyncWithGit(
            $metaData['gitOwner'],
            $metaData['gitRepo'],
            $metaData['gitBranch'],
            $metaData['gitInRepoPath'],
            $metaData['gitLastCommit']
        );
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
}

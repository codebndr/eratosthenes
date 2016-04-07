<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GetVersionsCommand extends AbstractApiCommand
{
    private $apiHandler;
    
    function __construct(EntityManager $entityManager, ContainerInterface $containerInterface)
    {
        parent::__construct($entityManager, $containerInterface);
        $this->apiHandler = $this->container->get('codebender_library.apiHandler');
    }

    /**
     * This is the main execution of the getVersions API. It
     * returns a response that includes an array of versions
     * belonging to the given library name if the library exists.
     *
     * @param $content
     * @return array
     */
    public function execute($content)
    {
        if (!$this->isValidContent($content)) {
            return ['success' => false, 'message' => 'Incorrect request fields'];
        }

        $defaultHeader = $content['library'];
        if (!$this->apiHandler->isExternalLibrary($defaultHeader)) {
            return ['success' => false, 'message' => 'Invalid library name ' . $defaultHeader];
        }

        $versions = $this->getVersionStringsFromDefaultHeader($defaultHeader);
        return ['success' => true, 'versions' => $versions];
    }

    /**
     * This method checks if the given $content is valid.
     *
     * @param $content
     * @return bool
     */
    private function isValidContent($content)
    {
        return array_key_exists("library", $content);
    }

    /**
     * This method returns an array of versions belonging to a library
     * with the given default header.
     *
     * @param $defaultHeader
     * @return array
     */
    private function getVersionStringsFromDefaultHeader($defaultHeader)
    {
        $versionObjects = $this->apiHandler->getAllVersionsFromDefaultHeader($defaultHeader);
        $versionsCollection = $versionObjects->map(function ($version) {
            return $version->getVersion();
        });
        $versions = $versionsCollection->toArray();
        return $versions;
    }
}

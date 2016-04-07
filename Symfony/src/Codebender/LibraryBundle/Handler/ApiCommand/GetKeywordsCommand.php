<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GetKeywordsCommand extends AbstractApiCommand
{
    private $apiHandler;

    function __construct(EntityManager $entityManager, ContainerInterface $containerInterface)
    {
        parent::__construct($entityManager, $containerInterface);
        $this->apiHandler = $this->container->get('codebender_library.apiHandler');
    }

    /**
     * This is the main execution of the getKeywords API.
     * This method returns a response containing all the keywords of a library version.
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
        
        $libraryType = $this->apiHandler->getLibraryType($defaultHeader);
        if ($libraryType === 'example' || $libraryType === 'unknown') {
            return ['success' => false, 'message' => 'Could not find keywords for requested library version.'];
        }

        if ($libraryType === 'external') {
            $version = $this->getRequestedVersions($content);

            if (!$this->apiHandler->libraryVersionExists($defaultHeader, $version)) {
                return ['success' => false, 'message' => 'Could not find keywords for requested library version.'];
            }

            $keywords = $this->getExternalLibraryKeywords($defaultHeader, $version);
        }

        if ($libraryType === 'builtin') {
            $keywords = $this->getBuiltInLibraryKeywords($defaultHeader);
        }

        return ['success' => true, 'keywords' => $keywords];
    }

    /**
     * This method checks if the given $content is valid.
     *
     * @param $content
     * @return bool
     */
    private function isValidContent($content)
    {
        return array_key_exists('library', $content);
    }

    /**
     * This method get partner default version for the requested external library
     * @param $content
     * @return mixed
     */
    private function getRequestedVersions($content)
    {
        if (!array_key_exists("version", $content)) {
            return $this->apiHandler->fetchPartnerDefaultVersion(
                $this->getRequest()->get('authorizationKey'),
                $content['library']
            )->getVersion();
        }

        return $content['version'];
    }

    /**
     * This method returns an array of keywords from a given library version
     * specified by its $defaultHeader and $version.
     *
     * @param $defaultHeader
     * @param $version
     * @return array
     */
    private function getExternalLibraryKeywords($defaultHeader, $version)
    {
        $path = $this->apiHandler->getExternalLibraryPath($defaultHeader, $version);
        $keywords = $this->getKeywordsFromFile($path);
        return $keywords;
    }

    /**
     * This method returns an array of keywords from a given built-in library
     * specified by its $defaultHeader.
     *
     * @param $defaultHeader
     * @return array
     */
    private function getBuiltInLibraryKeywords($defaultHeader)
    {
        $path = $this->apiHandler->getBuiltInLibraryPath($defaultHeader);
        $keywords = $this->getKeywordsFromFile($path);
        return $keywords;
    }

    /**
     * This method returns an array of keywords found in $path.
     *
     * @param $path
     * @return array
     */
    private function getKeywordsFromFile($path)
    {
        $keywords = array();

        $finder = new Finder();
        $finder->in($path);
        $finder->name('/^keywords\.txt$/i');

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
        return $keywords;
    }
}

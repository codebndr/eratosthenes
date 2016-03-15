<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Handler\ApiHandler;
use Symfony\Component\Finder\Finder;

class GetDefaultVersionCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        if (!array_key_exists('library', $content)) {
            return ['success' => false, 'message' => 'Wrong data'];
        }
        $defaultHeader = $content['library'];

        /* @var ApiHandler $handler */
        $handler = $this->get('codebender_library.apiHandler');
        // check library exists
        if (!$handler->isExternalLibrary($defaultHeader, true)) {
            return ['success' => false, 'message' => 'No library named ' . $defaultHeader . ' was found.'];
        }
        $version = $handler->fetchPartnerDefaultVersion($this->getRequest()->get('authorizationKey'), $defaultHeader);

        return ['success' => true, 'version' => $version->getVersion()];
    }
}

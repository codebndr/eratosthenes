<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Entity\Version;
use Symfony\Component\Finder\Finder;

class FetchLatestApiCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        return ['success' => false, 'message' => 'routing test'];
    }
}

<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Handler\ApiCommand\FetchApiCommand;
use Symfony\Component\Finder\Finder;

class FetchLatestApiCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        if ($content['library'] === null) {
            return ['success' => false, 'message' => 'Wrong data'];
        }

        $content['latest'] = true;
        $fetchApiCommand = new FetchApiCommand($this->entityManager, $this->container);
        return $fetchApiCommand->execute($content);
    }
}
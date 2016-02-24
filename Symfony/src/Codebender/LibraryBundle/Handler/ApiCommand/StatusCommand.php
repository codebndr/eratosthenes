<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StatusCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        return ['success' => true, 'status' => 'OK'];
    }
}

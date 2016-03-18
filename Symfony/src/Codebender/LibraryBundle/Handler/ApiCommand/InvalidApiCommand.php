<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InvalidApiCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        return ['success' => false, 'message' => 'No valid action requested'];
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> origin/v2-api-development

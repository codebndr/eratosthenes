<?php

namespace Codebender\LibraryBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuthenticationListener
{
    private $v1AuthorizationKey;
    private $container;

    /**
     * AuthenticationListener constructor.
     *
     * @param string $authorizationKey
     * @param ContainerInterface $container
     */
    public function __construct($authorizationKey, ContainerInterface $container)
    {
        $this->v1AuthorizationKey = $authorizationKey;
        $this->container = $container;
    }

    /**
     * Checks if the authorization key exists in the request parameters and if
     * it is valid. If not, sets the response to false and adds an error message.
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        /* @var \Codebender\LibraryBundle\Handler\ApiHandler $apiHandler */
        $apiHandler = $this->container->get('codebender_library.apiHandler');

        $routeParameters = $request->attributes->get('_route_params');

        if (!empty($routeParameters)
            && array_key_exists('authorizationKey', $routeParameters)
            // Support both v1 and v2 authentication methods
            && $routeParameters['authorizationKey'] != $this->v1AuthorizationKey
            && !$apiHandler->isAuthenticatedPartner($routeParameters['authorizationKey'])
        ) {
            $event->setResponse(new Response(
                json_encode(['success' => false, 'message' => '[eratosthenes] Invalid authorization key.'])
            ));
        }

    }
}
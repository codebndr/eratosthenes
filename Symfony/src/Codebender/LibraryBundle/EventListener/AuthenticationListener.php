<?php

namespace Codebender\LibraryBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class AuthenticationListener
{
    private $authorizationKey;

    /**
     * AuthenticationListener constructor.
     *
     * @param string $authorizationKey
     */
    public function __construct($authorizationKey)
    {
        $this->authorizationKey = $authorizationKey;
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

        $routeParameters = $request->attributes->get('_route_params');

        if (!empty($routeParameters)
            && array_key_exists('authorizationKey', $routeParameters)
            && $routeParameters['authorizationKey'] != $this->authorizationKey
        ) {
            $event->setResponse(new Response(
                json_encode(['success' => false, 'message' => '[eratosthenes] Invalid authorization key.'])
            ));
        }

    }
}
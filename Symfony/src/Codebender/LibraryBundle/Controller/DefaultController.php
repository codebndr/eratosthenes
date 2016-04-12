<?php

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * Dummy function, returns status
     *
     * @return Response
     */
    public function statusAction()
    {
        return new JsonResponse(['success' => true, 'status' => 'OK']);
    }

    /**
     * The main library manager API handler action.
     * Checks the autorization credentials and the validity of the request.
     * Can handle several types of requests, like code fetching, examples fetching, etc.
     *
     * @param $version
     * @return JsonResponse
     */
    public function apiHandlerAction($version)
    {
        if ($version != 'v1') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid library manager API version.']);
        }

        $request = $this->getRequest();
        $content = $request->getContent();

        $content = json_decode($content, true);
        if ($content === null) {
            return new JsonResponse(['success' => false, 'message' => 'Wrong data']);
        }

        $content['v1'] = true;

        $commandHandler = $this->get('codebender_library.apiCommandHandler');
        $service = $commandHandler->getService($content);
        $output = $service->execute($content);

        return new JsonResponse($output);
    }
}

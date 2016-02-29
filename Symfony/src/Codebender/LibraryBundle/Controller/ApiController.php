<?php

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends Controller
{
    /**
     * The main library manager API handler action.
     * Does the basic input validation, then dispatches the content to ApiCommandHandler
     * for parsing and execution in the respective API handler
     *
     * @return JsonResponse
     */
    public function apiHandlerAction()
    {
        $request = $this->getRequest();
        $content = $request->getContent();

        $content = json_decode($content, true);
        if ($content === null || !array_key_exists("type", $content)) {
            return new JsonResponse(['success' => false, 'message' => 'Wrong data']);
        }

        $commandHandler = $this->get('codebender_library.apiCommandHandler');
        $service = $commandHandler->getService($content);
        $output = $service->execute($content);

        return new JsonResponse($output);
    }
}

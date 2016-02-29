<?php

namespace Codebender\LibraryBundle\Controller;

use Codebender\LibraryBundle\Form\NewLibraryFormV2;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;
use ZipArchive;


class ApiViewsController extends Controller
{

    /**
     * Creates and handles a form for adding libraries to the
     * library management system. Will render the form page adding a flashbag
     * error upon failure. Will redirect to the newly created view page of the library
     * upon success.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function newLibraryAction()
    {

        $authorizationKey = $this->container->getParameter('authorizationKey');
        $form = $this->createForm(new NewLibraryFormV2());

        $form->handleRequest($this->getRequest());

        if (!$form->isValid()) {
            return $this->render('CodebenderLibraryBundle:Api:newLibForm.html.twig', array(
                'authorizationKey' => $authorizationKey,
                'form' => $form->createView()
            ));
        }

        $formData = $form->getData();
        $newLibraryHandler = $this->get('codebender_library.newLibraryHandler');
        $libraryAdded = $newLibraryHandler->addLibrary($formData);
        if ($libraryAdded['success'] !== true){
            $flashBag = $this->get('session')->getFlashBag();
            $flashBag->add('error', 'Error: ' . $libraryAdded['message']);
            $form = $this->createForm(new NewLibraryFormV2());

            return $this->render('CodebenderLibraryBundle:Api:newLibForm.html.twig', [
                'authorizationKey' => $authorizationKey,
                'form' => $form->createView()
            ]);
        }

        return $this->redirect($this->generateUrl('codebender_library_view_library_v2',
            ['authorizationKey' => $authorizationKey, 'library' => $formData['DefaultHeader'], 'disabled' => 1]));
    }

    public function viewLibraryAction()
    {
        $request = $this->getRequest();
        $library = $request->get('library');
        $version = $request->get('version');
        $disabled = $request->get('disabled') === "1";

        $apiFetchCommand = $this->get('codebender_api.fetch');
        $requestData = [
            'type'=>'fetch',
            'library' => $library,
            'disabled' => $disabled,
            'version' => $version,
            'renderView' => 'true'
        ];
        $response = $apiFetchCommand->execute($requestData);

        if ($response['success'] !== true) {
            return new JsonResponse($response);
        }

        $inSync = false;
        if (!empty($response['meta'])) {
            // TODO: check if the library is synced with Github
            $inSync = false;
        }

        return $this->render('CodebenderLibraryBundle:Api:libraryView.html.twig', array(
            'library' => $response['library'],
            'versions' => $response['versions'],
            'files' => $response['files'],
            'examples' => $response['examples'],
            'meta' => $response['meta'],
            'inSyncWithGit' => $inSync
        ));
    }

    public function gitUpdatesAction()
    {
        $checkGithubUpdatesCommand = $this->get('codebender_api.checkGithubUpdates');

        $handlerResponse = $checkGithubUpdatesCommand->execute();

        if ($handlerResponse['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid authorization key.']);
        }

        //TODO: create the twig and render it on return
        return new JsonResponse($handlerResponse);
    }

    public function searchAction()
    {
        $request = $this->getRequest();
        $query = $request->query->get('q');
        $json = $request->query->get('json');
        $names = array();

        if ($query !== null && $query != "") {
            $em = $this->getDoctrine()->getManager();
            $repository = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary');
            $libraries = $repository->createQueryBuilder('p')->where('p.machineName LIKE :token')
                ->setParameter('token', "%" . $query . "%")->getQuery()->getResult();


            foreach ($libraries as $lib) {
                if ($lib->getActive())
                    $names[] = $lib->getMachineName();
            }
        }
        if ($json !== null && $json = true) {
            return new JsonResponse(['success' => true, 'libs' => $names]);
        }
        return $this->render('CodebenderLibraryBundle:Api:search.html.twig',
            ['authorizationKey' => $this->container->getParameter('authorizationKey'), 'libs' => $names]);
    }

    public function changeLibraryStatusAction($library)
    {
        if ($this->getRequest()->getMethod() != 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'POST method required']);
        }

        $apiHandler = $this->get('codebender_library.apiHandler');
        $exists = $apiHandler->isExternalLibrary($library);

        if (!$exists) {
            return new JsonResponse(['success' => false, 'message' => 'Library not found.']);
        }

        $checkGithubUpdatesCommand = $this->get('codebender_api.checkGithubUpdates');

        $checkGithubUpdatesCommand->toggleLibraryStatus($library);

        return new JsonResponse(['success' => true]);
    }

    public function downloadAction($library)
    {
        $htmlcode = 200;
        $builtinLibraryFilesPath = $this->container->getParameter('builtin_libraries') . "/";
        $externalLibraryFilesPath = $this->container->getParameter('external_libraries') . "/";
        $finder = new Finder();
        $exampleFinder = new Finder();

        $filename = $library;

        $last_slash = strrpos($library, "/");
        if ($last_slash !== false) {
            $filename = substr($library, $last_slash + 1);
            $vendor = substr($library, 0, $last_slash);
        }

        $handler = $this->get('codebender_library.handler');
        $isBuiltIn = json_decode($handler->checkIfBuiltInExists($filename), true);
        if ($isBuiltIn["success"])
            $path = $builtinLibraryFilesPath . "/libraries/" . $filename;
        else {
            $isExternal = json_decode($handler->checkIfExternalExists($filename), true);
            if ($isExternal["success"]) {
                $path = $externalLibraryFilesPath . '/' . $filename;
            } else {
                $value = "";
                $htmlcode = 404;
                return new Response($value, $htmlcode);
            }
        }

        $files = $handler->fetchLibraryFiles($finder, $path, false);
        $examples = $handler->fetchLibraryExamples($exampleFinder, $path);

        $zipname = "/tmp/asd.zip";

        $zip = new ZipArchive();

        if ($zip->open($zipname, ZIPARCHIVE::CREATE) === false) {
            $value = "";
            $htmlcode = 404;
        } else {
            if ($zip->addEmptyDir($filename) !== true) {
                $value = "";
                $htmlcode = 404;
            } else {
                foreach ($files as $file) {
                    // No special handling needed for binary files, since addFromString method is binary safe.
                    $zip->addFromString($library . '/' . $file['filename'], file_get_contents($path . '/' . $file['filename']));
                }
                foreach ($examples as $file) {
                    $zip->addFromString($library . "/" . $file["filename"], $file["content"]);
                }
                $zip->close();
                $value = file_get_contents($zipname);
            }
            unlink($zipname);
        }

        $headers = array('Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment;filename="' . $filename . '.zip"');

        return new Response($value, $htmlcode, $headers);
    }
}
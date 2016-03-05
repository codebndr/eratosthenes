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
        if ($libraryAdded['success'] !== true) {
            $flashBag = $this->get('session')->getFlashBag();
            $flashBag->add('error', 'Error: ' . $libraryAdded['message']);
            $form = $this->createForm(new NewLibraryFormV2());

            return $this->render('CodebenderLibraryBundle:Api:newLibForm.html.twig', [
                'authorizationKey' => $authorizationKey,
                'form' => $form->createView()
            ]);
        }

        return $this->redirect(
            $this->generateUrl(
                'codebender_library_view_library_v2',
                ['authorizationKey' => $authorizationKey, 'library' => $formData['DefaultHeader'], 'disabled' => 1]
            )
        );
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
            $apiHandler = $this->get('codebender_library.apiHandler');
            $inSync = $apiHandler->isLibraryInSyncWithGit(
                $response['meta']['gitOwner'],
                $response['meta']['gitRepo'],
                $response['meta']['gitBranch'],
                $response['meta']['gitInRepoPath'],
                $response['meta']['gitLastCommit']
            );
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
            $repository = $em->getRepository('CodebenderLibraryBundle:Library');
            $libraries = $repository->createQueryBuilder('p')->where('p.default_header LIKE :token')
                ->setParameter('token', "%" . $query . "%")->getQuery()->getResult();


            foreach ($libraries as $lib) {
                if ($lib->getActive()) {
                    $names[] = $lib->getDefaultHeader();
                }
            }
        }
        if ($json !== null && $json === "true") {
            return new JsonResponse(['success' => true, 'libs' => $names]);
        }
        return $this->render(
            'CodebenderLibraryBundle:Api:search.html.twig',
            ['authorizationKey' => $this->container->getParameter('authorizationKey'), 'libs' => $names]
        );
    }

    public function changeLibraryStatusAction($library)
    {
        if ($this->getRequest()->getMethod() != 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'POST method required']);
        }

        $apiHandler = $this->get('codebender_library.apiHandler');
        $exists = $apiHandler->isExternalLibrary($library, true);

        if (!$exists) {
            return new JsonResponse(['success' => false, 'message' => 'Library not found.']);
        }

        $apiHandler->toggleLibraryStatus($library);

        return new JsonResponse(['success' => true]);
    }

    public function getLibraryGitInfoAction()
    {
        if ($this->getRequest()->getMethod() != 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'POST method required']);
        }

        $handler = $this->get('codebender_library.handler');

        $githubUrl = $this->getRequest()->request->get('githubUrl');
        $processedGitUrl = $handler->processGithubUrl($githubUrl);

        if ($processedGitUrl['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Could not process provided url']);
        }

        $repoBranches = $handler->fetchRepoRefsFromGit($processedGitUrl['owner'], $processedGitUrl['repo']);
        $repoReleases = $handler->fetchRepoReleasesFromGit($processedGitUrl['owner'], $processedGitUrl['repo']);

        if ($repoBranches['success'] !== true || $repoReleases['success'] !== true) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Something went wrong while fetching the library. Please double check the Url you provided.'
            ]);
        }

        return new JsonResponse(['success' => true, 'branches' => $repoBranches['headRefs'], 'releases' => $repoReleases['releases']]);
    }

    public function getRepoGitTreeAndMetaAction()
    {
        $handler = $this->get('codebender_library.handler');

        $githubUrl = $this->getRequest()->request->get('githubUrl');
        $processedGitUrl = $handler->processGithubUrl($githubUrl);
        $gitRef = $this->getRequest()->request->get('gitRef');

        if ($processedGitUrl['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Could not process provided url']);
        }

        $githubLibrary = json_decode(
            $handler->getRepoTreeStructure(
                $processedGitUrl['owner'],
                $processedGitUrl['repo'],
                $gitRef,
                $processedGitUrl['folder']
            ),
            true
        );

        if (!$githubLibrary['success']) {
            return new JsonResponse($githubLibrary);
        }

        $description = $handler->getRepoDefaultDescription($processedGitUrl['owner'], $processedGitUrl['repo']);

        return new JsonResponse([
            'success' => true,
            'files' => $githubLibrary['files'],
            'owner' => $processedGitUrl['owner'],
            'repo' => $processedGitUrl['repo'],
            'ref' => $gitRef,
            'description' => $description
        ]);
    }

    public function downloadAction($defaultHeaderFile, $version)
    {
        $htmlcode = 200;
        $finder = new Finder();
        $exampleFinder = new Finder();

        $apiHandler = $this->get('codebender_library.apiHandler');
        $isValidLibrary = $apiHandler->libraryVersionExists($defaultHeaderFile, $version, true);

        if (!$isValidLibrary) {
            $value = "";
            $htmlcode = 404;
            return new Response($value, $htmlcode);
        }

        $path = $apiHandler->getExternalLibraryPath($defaultHeaderFile, $version);

        $files = $apiHandler->fetchLibraryFiles($finder, $path, false);
        $examples = $apiHandler->fetchLibraryExamples($exampleFinder, $path);

        $zipname = "/tmp/asd.zip";

        $zip = new ZipArchive();

        if ($zip->open($zipname, ZIPARCHIVE::CREATE) === false) {
            $value = "";
            $htmlcode = 404;
        } else {
            if ($zip->addEmptyDir($defaultHeaderFile) !== true) {
                $value = "";
                $htmlcode = 404;
            } else {
                foreach ($files as $file) {
                    // No special handling needed for binary files, since addFromString method is binary safe.
                    $zip->addFromString($defaultHeaderFile . '/' . $file['filename'], file_get_contents($path . '/' . $file['filename']));
                }
                foreach ($examples as $file) {
                    $zip->addFromString($defaultHeaderFile . "/" . $file["filename"], $file["content"]);
                }
                $zip->close();
                $value = file_get_contents($zipname);
            }
            unlink($zipname);
        }

        $headers = array('Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment;filename="' . $defaultHeaderFile . '.zip"');

        return new Response($value, $htmlcode, $headers);
    }
}

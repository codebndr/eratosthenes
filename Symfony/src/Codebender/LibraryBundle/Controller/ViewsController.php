<?php
/**
 * Created by PhpStorm.
 * User: fpapadopou
 * Date: 1/28/15
 * Time: 10:19 AM
 */

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;
use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Form\NewLibraryForm;
use ZipArchive;


class ViewsController extends Controller
{

    public function newLibraryAction($authorizationKey)
    {

        if ($authorizationKey !== $this->container->getParameter('authorizationKey')) {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        $form = $this->createForm(new NewLibraryForm());

        $form->handleRequest($this->getRequest());

        $handler = $this->get('codebender_library.handler');

        if ($form->isValid()) {

            $formData = $form->getData();

            if ($formData["GitOwner"] === NULL && $formData["GitRepo"] === NULL && $formData["Zip"] !== NULL)
                $lib = json_decode($this->getLibFromZipFile($formData["Zip"]), true);
            else
                $lib = json_decode($handler->getLibFromGithub($formData["GitOwner"], $formData["GitRepo"]), true);
            if (!$lib['success'])
                return new Response(json_encode($lib));
            else
                $lib = $lib['library'];
            if ($formData["GitOwner"] === NULL && $formData["GitRepo"] === NULL && $formData["Zip"] !== NULL)
                $lastCommit = NULL;
            else
                $lastCommit = $handler->getLastCommitFromGithub($formData['GitOwner'], $formData['GitRepo']);

            $saved = json_decode($this->saveNewLibrary($formData['HumanName'], $formData['MachineName'], $formData['GitOwner'], $formData['GitRepo'], $formData['Description'], $lastCommit, $formData['Url'], $lib), true);
            if ($saved['success'])
                return $this->redirect($this->generateUrl('codebender_library_view_library', array("authorizationKey" => $this->container->getParameter('authorizationKey'), "library" => $formData["MachineName"], "disabled" => 1)));
            return new Response(json_encode($saved));

        }
        return $this->render('CodebenderLibraryBundle:Default:newLibForm.html.twig', array(
            'authorizationKey' => $authorizationKey,
            'form' => $form->createView()
        ));
    }

    public function viewLibraryAction($authorizationKey)
    {
        if ($authorizationKey !== $this->container->getParameter('authorizationKey')) {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        $handler = $this->get('codebender_library.handler');

        $request = $this->getRequest();
        $library = $request->get('library');
        $disabled = $request->get('disabled');
        if ($disabled != 1)
            $disabled = 0;
        else
            $disabled = 1;

        $response = $handler->getLibraryCode($library, $disabled, true);

        $response = json_decode($response->getContent(), true);
        if ($response["success"] === false)
            return new Response(json_encode($response));

        return $this->render('CodebenderLibraryBundle:Default:libraryView.html.twig', array(
            "library" => $response["library"],
            "files" => $response["files"],
            "examples" => $response["examples"],
            "meta" => $response["meta"]
        ));
    }

    public function gitUpdatesAction($authorizationKey)
    {
        if ($authorizationKey !== $this->container->getParameter('authorizationKey')) {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        $handler = $this->get('codebender_library.handler');

        $handlerResponse = $handler->checkGithubUpdates();

        if ($handlerResponse["success"] === false) {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        //TODO: create the twig and render it on return
        return $handlerResponse;
//        return $this->render('CodebenderLibraryBundle:Default:gitUpdatesView.html.twig', array(
//            "library" => $filename,
//            "files" => $response,
//            "examples" => $examples,
//            "meta" => $meta
//        ));
    }

    public function searchAction($authorizationKey)
    {
        if ($authorizationKey !== $this->container->getParameter('authorizationKey')) {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        $request = $this->getRequest();
        $query = $request->query->get('q');
        $json = $request->query->get('json');
        $names = array();

        if ($query !== NULL && $query != "") {
            $em = $this->getDoctrine()->getManager();
            $repository = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary');
            $libraries = $repository->createQueryBuilder('p')->where('p.machineName LIKE :token')->setParameter('token', "%" . $query . "%")->getQuery()->getResult();


            foreach ($libraries as $lib) {
                if ($lib->getActive())
                    $names[] = $lib->getMachineName();
            }
        }
        if ($json !== NULL && $json = true)
            return new Response(json_encode(array("success" => true, "libs" => $names)));
        else
            return $this->render('CodebenderLibraryBundle:Default:search.html.twig', array("authorizationKey" => $authorizationKey, "libs" => $names));
    }

    public function changeLibraryStatusAction($authorizationKey, $library)
    {
        if ($authorizationKey !== $this->container->getParameter('authorizationKey')) {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        if ($this->getRequest()->getMethod() != 'POST') {
            return new Response(json_encode(array("success" => false, "message" => "POST should be used.")));
        }
//            $library = $this->get('request')->request->get('library');

        $handler = $this->get('codebender_library.handler');
        $exists = json_decode($handler->checkIfExternalExists($library, true), true);

        if ($exists['success'] === false) {
            return new Response(json_encode(array("success" => false, "message" => "Library not found.")));
        }

        $em = $this->getDoctrine()->getManager();
        $lib = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
        if ($lib[0]->getActive())
            $lib[0]->setActive(0);
        else
            $lib[0]->setActive(1);
        $em->persist($lib[0]);
        $em->flush();

        return new Response(json_encode(array("success" => true)));
    }

    public function downloadAction($authorizationKey, $library)
    {
        if ($authorizationKey !== $this->container->getParameter('authorizationKey')) {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        $htmlcode = 200;
        $value = "";

        $arduino_library_files = $this->container->getParameter('arduino_library_directory') . "/";
        $finder = new Finder();
        $exampleFinder = new Finder();

        $filename = $library;

        $directory = "";

        $last_slash = strrpos($library, "/");
        if ($last_slash !== false) {
            $filename = substr($library, $last_slash + 1);
            $vendor = substr($library, 0, $last_slash);
        }

        $handler = $this->get('codebender_library.handler');
        $isBuiltIn = json_decode($handler->checkIfBuiltInExists($filename), true);
        if ($isBuiltIn["success"])
            $path = $arduino_library_files . "/libraries/" . $filename;
        else {
            $isExternal = json_decode($handler->checkIfExternalExists($filename), true);
            if ($isExternal["success"]) {
                $path = $arduino_library_files . "/external-libraries/" . $filename;
            } else {
                $value = "";
                $htmlcode = 404;
                return new Response($value, $htmlcode);
            }
        }

        $files = $handler->fetchLibraryFiles($finder, $path);
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
                    $zip->addFromString($library . "/" . $file["filename"], $file["content"]);
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

    private function saveNewLibrary($humanName, $machineName, $gitOwner, $gitRepo, $description, $lastCommit, $url, $libfiles)
    {
        $handler = $this->get('codebender_library.handler');
        $exists = json_decode($handler->checkIfExternalExists($machineName), true);
        if ($exists['success'])
            return json_encode(array("success" => false, "message" => "Library named " . $machineName . " already exists."));

        $create = json_decode($this->createLibFiles($machineName, $libfiles), true);
        if (!$create['success'])
            return json_encode($create);

        $lib = new ExternalLibrary();
        $lib->setHumanName($humanName);
        $lib->setMachineName($machineName);
        $lib->setDescription($description);
        $lib->setOwner($gitOwner);
        $lib->setRepo($gitRepo);
        $lib->setVerified(false);
        $lib->setActive(false);
        $lib->setLastCommit($lastCommit);
        $lib->setUrl($url);

        $em = $this->getDoctrine()->getManager();
        $em->persist($lib);
        $em->flush();

        $arduino_library_files = $this->container->getParameter('arduino_library_directory');
        $examples = $handler->fetchLibraryExamples(new Finder(), $arduino_library_files . "/external-libraries/" . $machineName);

//        $libfilesForCompilation = $this->fetchLibraryFiles(new Finder(), $arduino_library_files."/external-libraries/".$machineName);

        foreach ($examples as $example) {

//            $filesForCompilation = $libfilesForCompilation;
            $path_parts = pathinfo($example['filename']);
//            $filesForCompilation[]  = array("filename"=>$path_parts['filename'].'.ino', "content" => $example['content']);
//            $boards = json_decode($this->getBoardsForExample($filesForCompilation), true);
//            $this->saveExampleMeta($path_parts['filename'], $lib, $machineName."/".$example['filename'],json_encode($boards['boards']));
            $this->saveExampleMeta($path_parts['filename'], $lib, $machineName . "/" . $example['filename'], NULL);
        }


        return json_encode(array("success" => true));

    }

    private function createLibFiles($machineName, $lib)
    {
        $libBaseDir = $this->container->getParameter('arduino_library_directory') . "/external-libraries/" . $machineName . "/";
        return ($this->createLibDirectory($libBaseDir, $libBaseDir, $lib['contents']));
    }

    private function createLibDirectory($base, $path, $files)
    {

        if (is_dir($path))
            return json_encode(array("success" => false, "message" => "Library directory already exists"));
        if (!mkdir($path))
            return json_encode(array("success" => false, "message" => "Cannot Save Library"));

        foreach ($files as $file) {
            if ($file['type'] == 'dir') {
                $create = json_decode($this->createLibDirectory($base, $base . $file['name'] . "/", $file['contents']), true);
                if (!$create['success'])
                    return (json_encode($create));
            } else {
                file_put_contents($path . $file['name'], $file['contents']);
            }
        }

        return json_encode(array('success' => true));
    }

    private function saveExampleMeta($name, $lib, $path, $boards)
    {
        //TODO make it better. You know, return things and shit
        $example = new Example();
        $example->setName($name);
        $example->setLibrary($lib);
        $example->setPath($path);
        $example->setBoards($boards);
        $em = $this->getDoctrine()->getManager();
        $em->persist($example);
        $em->flush();
    }


    private function getLibFromZipFile($file)
    {
        if (is_dir('/tmp/lib'))
            $this->destroy_dir('/tmp/lib');
        $zip = new \ZipArchive;
        $opened = $zip->open($file);
        if ($opened === TRUE) {
            $handler = $this->get('codebender_library.handler');
            $zip->extractTo('/tmp/lib/');
            $zip->close();
            $dir = json_decode($this->processZipDir('/tmp/lib'), true);

            if (!$dir['success'])
                return json_encode($dir);
            else
                $dir = $dir['directory'];
            $baseDir = json_decode($handler->findBaseDir($dir), true);
            if (!$baseDir['success'])
                return json_encode($baseDir);
            else
                $baseDir = $baseDir['directory'];

            return json_encode(array("success" => true, "library" => $baseDir));
        } else {
            return json_encode(array("success" => false, "message" => "Could not unzip Archive. Code: " . $opened));
        }
    }

    private function processZipDir($path)
    {
        $files = array();
        $dir = preg_grep('/^([^.])/', scandir($path));
        foreach ($dir as $file) {
            if ($file === "__MACOSX")
                continue;

            if (is_dir($path . '/' . $file)) {
                $subdir = json_decode($this->processZipDir($path . '/' . $file), true);
                if ($subdir['success'])
                    array_push($files, $subdir['directory']);
                else
                    return json_encode($subdir);
            } else {
                $file = json_decode($this->processZipFile($path . '/' . $file), true);
                if ($file['success'])
                    array_push($files, $file['file']);
                else if ($file['message'] != "Bad Encoding")
                    return json_encode($file);
            }
        }
        return json_encode(array("success" => true, "directory" => array("name" => substr($path, 9), "type" => "dir", "contents" => $files)));
    }

    private function processZipFile($path)
    {
        $contents = file_get_contents($path);
        if (!mb_check_encoding($contents, 'UTF-8')) {
            $contents = utf8_encode($contents);
        }
        if ($contents === NULL)
            return json_encode(array("success" => false, "message" => "Could not read file " . basename($path)));

        return json_encode(array("success" => true, "file" => array("name" => basename($path), "type" => "file", "contents" => $contents)));
    }

    private function destroy_dir($dir)
    {
        if (!is_dir($dir) || is_link($dir)) return unlink($dir);
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') continue;
            if (!$this->destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!$this->destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) return false;
            };
        }
        return rmdir($dir);
    }

}
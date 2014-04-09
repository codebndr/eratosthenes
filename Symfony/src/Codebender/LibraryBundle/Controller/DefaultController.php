<?php

namespace Codebender\LibraryBundle\Controller;

use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Codebender\LibraryBundle\Form\NewLibraryForm;
use ZipArchive;

class DefaultController extends Controller
{
	public function statusAction()
	{
		return new Response(json_encode(array("success" => true, "status" => "OK")));
	}

	public function testAction($auth_key)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
		}

		set_time_limit(0); // make the script execution time unlimited (otherwise the request may time out)

		// change the current Symfony root dir
		chdir($this->get('kernel')->getRootDir()."/../");

		//TODO: replace this with a less horrible way to handle phpunit
		exec("phpunit -c app --stderr 2>&1", $output, $return_val);

		return new Response(json_encode(array("success" => (bool) !$return_val, "message" => implode("\n", $output))));
	}

	public function listAction($auth_key, $version)
    {
	    if ($auth_key !== $this->container->getParameter('auth_key'))
	    {
		    return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
	    }

	    if ($version == "v1")
	    {
		    $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

            $built_examples = $this->getLibariesListFromDir($arduino_library_files."examples");
            $included_libraries = $this->getLibariesListFromDir($arduino_library_files."libraries");
            $external_libraries = $this->getExternalLibrariesList();

		    ksort($built_examples);
		    ksort($included_libraries);
		    ksort($external_libraries);

		    return new Response(json_encode(array("success" => true,
			    "text" => "Successful Request!",
			    "categories" => array("Examples" => $built_examples,
				    "Builtin Libraries" => $included_libraries,
				    "External Libraries" => $external_libraries))));
	    }
	    else
	    {
		    return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
	    }
    }

	public function getExampleCodeAction($auth_key, $version, $library, $example)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
		}

		if ($version == "v1")
		{
            $type = json_decode($this->getLibraryType($library), true);
            if(!$type['success'])
                return new Response(json_encode($type));

            switch($type['type'])
            {
                case 'builtin':
                    $dir = $this->container->getParameter('arduino_library_directory')."/libraries/";
                    $example = $this->getExampleCodeFromDir($dir, $library, $example);
                    break;
                case 'external':
                    $example = $this->getExternalExampleCode($library, $example);
                    break;
                case 'example':
                    $dir = $this->container->getParameter('arduino_library_directory')."/examples/";
                    $example = $this->getExampleCodeFromDir($dir, $library, $example);
                    break;
            }

            return new Response($example, 200,  array('content-type' => 'application/json'));
		}
		else
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
		}
	}

	public function getLibraryCodeAction($auth_key, $version, $renderView = false)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
		}

		if ($version == "v1")
		{
			$arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

			$finder = new Finder();
            $exampleFinder = new Finder();

			$request = $this->getRequest();

			// retrieve GET and POST variables respectively
			$library = $request->query->get('library');
            $disabled = $request->query->get('disabled');
            if($disabled != 1)
                $getDisabled = false;
            else
                $getDisabled = true;



			$filename = $library;
			$directory = "";

			$last_slash = strrpos($library, "/");
			if($last_slash !== false )
			{
				$filename = substr($library, $last_slash + 1);
				$vendor = substr($library, 0, $last_slash);
			}

            //TODO handle the case of different .h filenames and folder names
            if($filename == "ArduinoRobot")
                $filename = "Robot_Control";
            else if($filename == "ArduinoRobotMotorBoard")
                $filename = "Robot_Motor";

            $exists = json_decode($this->checkIfBuiltInExists($filename),true);

            if($exists["success"])
            {
                $response = $this->fetchLibraryFiles($finder, $arduino_library_files."/libraries/".$filename);

                if($renderView)
                {
                    $examples = $this->fetchLibraryExamples($exampleFinder, $arduino_library_files."/libraries/".$filename);
                    $meta = array();
                }
            }

            else
            {
                $response = json_decode($this->checkIfExternalExists($filename,$getDisabled),true);
                if(!$response['success'])
                {
                    return new Response(json_encode($response));
                }
                else
                {
                    $response = $this->fetchLibraryFiles($finder, $arduino_library_files."/external-libraries/".$filename);
                    if(empty($response))
                        return new Response(json_encode(array("success" => false, "message" => "No files for Library named ".$library." found.")));
                    if($renderView)
                    {
                        $examples = $this->fetchLibraryExamples($exampleFinder, $arduino_library_files."/external-libraries/".$filename);
                        $em = $this->getDoctrine()->getManager();
                        $libmeta = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $filename));
                        $filename = $libmeta[0]->getMachineName();
                        $meta = array("humanName" => $libmeta[0]->getHumanName(), "description" => $libmeta[0]->getDescription(), "verified" => $libmeta[0]->getVerified(), "gitOwner" => $libmeta[0]->getOwner(), "gitRepo" => $libmeta[0]->getRepo(), "url" => $libmeta[0]->getUrl(), "active" => $libmeta[0]->getActive());

                    }
                }
            }
            if(!$renderView)
			    return new Response(json_encode(array("success" => true, "message" => "Library found", "files" => $response)));
            else
            {

                return $this->render('CodebenderLibraryBundle:Default:libraryView.html.twig', array(
                    "library" => $filename,
                    "files" => $response,
                    "examples" => $examples,
                    "meta" => $meta
                ));
            }
		}
		else
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
		}
	}

    public function getLibraryGitMetaAction()
    {
//        if ($auth_key !== $this->container->getParameter('auth_key'))
//        {
//            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
//        }
//        if ($version == "v1")
//        {
        if ($this->getRequest()->getMethod() == 'POST') {

            $owner = $this->get('request')->request->get('gitOwner');
            $repo = $this->get('request')->request->get('gitRepo');
            $lib = json_decode($this->getLibFromGithub($owner, $repo, true), true);
            if (!$lib['success'])
            {
                $response =  new Response(json_encode($lib));
                $response->headers->set('Content-Type', 'application/json');
                return $response;
            }

            else
                $lib = $lib['library'];

            $headers = $this->findHeadersFromLibFiles($lib['contents']);
            $names = $this->getLibNamesFromHeaders($headers);
            $response =  new Response(json_encode(array("success" => true, "names" => $names )));
            $response->headers->set('Content-Type', 'application/json');
            return $response;


            return $response;
        } else {
            return new Response(json_encode(array("success" => false)));
        }
    }

    public function changeLibraryStatusAction()
    {
        if ($this->getRequest()->getMethod() == 'POST')
        {
            $library = $this->get('request')->request->get('library');

            $exists = json_decode($this->checkIfExternalExists($library, true), true);

            if($exists['success'])
            {
                $em = $this->getDoctrine()->getManager();
                $lib = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
                if($lib[0]->getActive())
                    $lib[0]->setActive(0);
                else
                    $lib[0]->setActive(1);
                $em->persist($lib[0]);
                $em->flush();

                return new Response(json_encode(array("success" => true)));
            }
            else
            {
                return new Response(json_encode(array("success" => false, "message" => "Library not found.")));
            }
        }
        return new Response(json_encode(array("success" => false, "message" => "POST should be used.")));
    }

    public function newLibraryAction($auth_key, $version)
    {

        if ($auth_key !== $this->container->getParameter('auth_key')) {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }
        if ($version == "v1") {
            $form = $this->createForm(new NewLibraryForm());

            $form->handleRequest($this->getRequest());

            if ($form->isValid()) {

                $formData = $form->getData();

                if($formData["GitOwner"] === NULL && $formData["GitRepo"]===NULL && $formData["Zip"]!==NULL)
                    $lib = json_decode($this->getLibFromZipFile($formData["Zip"]) ,true);
                else
                    $lib = json_decode($this->getLibFromGithub($formData["GitOwner"], $formData["GitRepo"]), true);
                if (!$lib['success'])
                    return new Response(json_encode($lib));
                else
                    $lib = $lib['library'];
                if($formData["GitOwner"] === NULL && $formData["GitRepo"]===NULL && $formData["Zip"]!==NULL)
                    $lastCommit=NULL;
                else
                    $lastCommit=$this->getLastCommitFromGithub($formData['GitOwner'], $formData['GitRepo']);

                $saved = json_decode($this->saveNewLibrary($formData['HumanName'], $formData['MachineName'], $formData['GitOwner'], $formData['GitRepo'], $formData['Description'], $lastCommit, $formData['Url'], $lib), true);
                if($saved['success'])
                    return $this->redirect($this->generateUrl('codebender_library_view_library', array("auth_key" => $this->container->getParameter('auth_key'), "version"=>"v1","library" => $formData["MachineName"], "disabled"=>1)));
                return new Response(json_encode($saved));

            }
            return $this->render('CodebenderLibraryBundle:Default:newLibForm.html.twig', array(
                'form' => $form->createView()
            ));

        } else {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
        }

    }

    public function checkForExternalUpdatesAction($auth_key, $version)
    {
        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }
        if ($version == "v1")
        {
            $needToUpdate = array();
            $em = $this->getDoctrine()->getManager();
            $libraries = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findAll();

            foreach($libraries as $lib)
            {
                $gitOwner = $lib->getOwner();
                $gitRepo = $lib->getRepo();

                if($gitOwner!==null and $gitRepo!==null)
                {
                    $lastCommitFromGithub = $this->getLastCommitFromGithub($gitOwner, $gitRepo);
                    if($lastCommitFromGithub !== $lib->getLastCommit())
                        $needToUpdate[]=array('Machine Name' => $lib->getMachineName(), "Human Name" => $lib->getHumanName(), "Git Owner" => $lib->getOwner(), "Git Repo" => $lib->getRepo());
                }
            }
            if(empty($needToUpdate))
                $response = array("success" => true, "message" => "No Libraries need to update");
            else
                $response = array("success" => true, "message" => "There are Libraries that need to update", "libraries" => $needToUpdate);

            return new Response(json_encode($response));
        }
        else
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
        }

    }

    public function downloadAction($auth_key, $version)
    {
        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        if ($version == "v1")
        {
            $htmlcode = 200;
            $value = "";

            $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
            $finder = new Finder();
            $exampleFinder = new Finder();


            $request = $this->getRequest();

            $library = $request->query->get('library');

            $filename = $library;

            $directory = "";

            $last_slash = strrpos($library, "/");
            if($last_slash !== false )
            {
                $filename = substr($library, $last_slash + 1);
                $vendor = substr($library, 0, $last_slash);
            }

            $isBuiltIn = json_decode($this->checkIfBuiltInExists($filename), true);
            if($isBuiltIn["success"])
                $path = $arduino_library_files."/libraries/".$filename;
            else
            {
                $isExternal = json_decode($this->checkIfExternalExists($filename), true);
                if($isExternal["success"])
                {
                    $path = $arduino_library_files."/external-libraries/".$filename;
                }
                else
                {
                    $value = "";
                    $htmlcode = 404;
                    return new Response($value, $htmlcode);
                }
            }

            $files = $this->fetchLibraryFiles($finder, $path);
            $examples = $this->fetchLibraryExamples($exampleFinder, $path);

            $zipname = "/tmp/asd.zip";

            $zip = new ZipArchive();

            if ($zip->open($zipname, ZIPARCHIVE::CREATE)===false)
            {
                $value = "";
                $htmlcode = 404;
            }
            else
            {
                if($zip->addEmptyDir($filename)!==true)
                {
                    $value = "";
                    $htmlcode = 404;
                }
                else
                {
                    foreach($files as $file)
                    {
                        $zip->addFromString($library."/".$file["filename"], $file["content"]);
                    }
                    foreach($examples as $file)
                    {
                        $zip->addFromString($library."/".$file["filename"], $file["content"]);
                    }
                    $zip->close();
                    $value = file_get_contents($zipname);
                }
                unlink($zipname);
            }

            $headers = array('Content-Type'		=> 'application/octet-stream',
            'Content-Disposition' => 'attachment;filename="'.$filename.'.zip"');

            return new Response($value, $htmlcode, $headers);

        }
        else
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
        }
    }

    public function searchAction()
    {
        $request = $this->getRequest();
        $query = $request->query->get('q');
        $json = $request->query->get('json');
        $names = array();

        if($query!== NULL && $query!="")
        {
            $em = $this->getDoctrine()->getManager();
            $repository = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary');
            $libraries = $repository->createQueryBuilder('p')->where('p.machineName LIKE :token')->setParameter('token', "%".$query."%")->getQuery()->getResult();


            foreach($libraries as $lib)
            {
                if($lib->getActive())
                    $names[] = $lib->getMachineName();
            }
        }
        if($json!==NULL && $json = true)
            return new Response(json_encode(array("success" => true, "libs" => $names)));
        else
            return $this->render('CodebenderLibraryBundle:Default:search.html.twig' , array("libs" => $names));
    }
//
//    public function compileLibraryExamplesAction($auth_key, $version)
//    {
//        if ($auth_key !== $this->container->getParameter('auth_key'))
//        {
//            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
//        }
//
//        if ($version == "v1")
//        {
//            $request = $this->getRequest();
//            $library = $request->query->get('library');
//            if($library === NULL)
//                return new Response(json_encode(array('success' => false, 'message' => 'Library name not given.')));
//            $response = $this->compileLibraryExamples($library);
//
//            return new Response($response);
//        }
//        else
//        {
//            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
//        }
//    }
//
//    private function compileLibraryExamples($library)
//    {
//        $examples = json_decode($this->getLibraryExamples($library), true);
//        if($examples['success'])
//        {
//            $response = array();
//            foreach($examples['examples'] as $example => $files)
//            {
//                $response[$example] = json_decode($this->getBoardsForExample($files), true);
//                $this->updateBoardsForExample($library, $example, $response[$example]['boards']);
//            }
//
//            return json_encode($response);
//        }
//        else
//        {
//            return json_encode($examples);
//        }
//
//    }
//
//    private function updateBoardsForExample($library, $exampleName, $boards)
//    {
//        $em = $this->getDoctrine()->getManager();
//        $lib = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
//
//        if(!empty($lib))
//        {
//            $ex = $em->getRepository('CodebenderLibraryBundle:Example')->findBy(array('library' => $lib[0], 'name' => $exampleName));
//            if(!empty($ex))
//            {
//                $ex[0]->setBoards(json_encode($boards));
//
//                $em->persist($ex[0]);
//                $em->flush();
//            }
//        }
//    }
//    private function getBoardsForExample($files)
//    {
//        $boards = array();
//        $builds = array();
//        $codebender_boards = json_decode($this->curlRequest($this->container->getParameter('boards_url')), true);
//        foreach($codebender_boards as $cb_b)
//        {
//            $builds[$cb_b['name']] =$cb_b['build'];
//        }
//        foreach ($builds as $board => $build) {
//            $compiles = json_decode($this->compileExampleForBoard($files, $build), true);
//            if($compiles['success'])
//            {
//                $boards[] = $board;
//            }
//        }
//        return json_encode(array("success" => true, "boards" => $boards));
//    }
//
//    private function compileExampleForBoard($files, $build)
//    {
//        $v = "105";
//        $format = "binary";
//        $libsToInclude = array();
//        foreach ($files as $file) {
//            $libsToInclude = array_merge($libsToInclude, $this->read_headers($file['content']));
//        }
//        $libraries = $this->constructLibraryFiles($libsToInclude);
//        $request_data = json_encode(array('files' => $files, 'libraries' => $libraries, 'format' => $format, 'version' => $v, 'build' => $build));
//        $compilation = $this->curlRequest($compiler_url = $this->container->getParameter('compiler_url'), $post_request_data = $request_data);
//
//        return $compilation;
//    }
//
    private function getLibraryExamples($library)
    {
        $exists = json_decode($this->getLibraryType($library), true);
        if ($exists['success'])
        {
            $examples = array();
            $path = "";
            if($exists['type'] == 'external')
            {
                $path = $this->container->getParameter('arduino_library_directory')."/external-libraries/".$library;
            }
            else if($exists['type'] = 'builtin')
            {
                $path = $this->container->getParameter('arduino_library_directory')."/libraries/".$library;
            }
            $inoFinder = new Finder();
            $inoFinder->in($path);
            $inoFinder->name('*.ino')->name('*.pde');

            foreach ($inoFinder as $example)
            {
                $files = array();

                $content = $example->getContents();
                $path_info = pathinfo($example->getBaseName());
                $files[] = array("filename"=>$path_info['filename'].'.ino', "content" => $content);

                $h_finder = new Finder();
                $h_finder->files()->name('*.h')->name('*.cpp');
                $h_finder->in($path."/".$example->getRelativePath());

                foreach($h_finder as $header)
                {
                    $files[] = array("filename"=>$header->getBaseName(), "content" => $header->getContents());
                }

                $examples[$path_info['filename']]=$files;
            }

            return json_encode(array('success' => true, 'examples' => $examples));

        }
        else
        {
            return json_encode($exists);
        }
    }

    private function getLibraryType($library)
    {
        $isExternal = json_decode($this->checkIfExternalExists($library), true);
        if($isExternal['success'])
        {
            return json_encode(array('success' => true, 'type' => 'external'));
        }
        else
        {
            $isBuiltIn = json_decode($this->checkIfBuiltInExists($library), true);
            if ($isBuiltIn['success'])
            {
                return json_encode(array('success' => true, 'type' => 'builtin'));
            }
            else
            {
                $isExample = json_decode($this->checkIfBuiltInExampleFolderExists($library), true);
                if ($isExample['success'])
                {
                    return json_encode(array('success' => true, 'type' => 'example'));
                }
            }
        }

        return json_encode(array('success' => false, 'message' => 'Library named '.$library.' not found.'));
    }

//    private function read_headers($code)
//{
//    // Matches preprocessor include directives, has high tolerance to
//    // spaces. The actual header (without the postfix .h) is stored in
//    // register 1.
//    //
//    // Examples:
//    // #include<stdio.h>
//    // # include "proto.h"
//    $REGEX = "/^\s*#\s*include\s*[<\"]\s*(\w*)\.h\s*[>\"]/";
//
//    $headers = array();
//    foreach (explode("\n", $code) as $line)
//        if (preg_match($REGEX, $line, $matches))
//            $headers[] = $matches[1];
//
//    return $headers;
//}
//
//    private function constructLibraryFiles($libnames)
//    {
//        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
//        $libraries = array();
//
//        foreach($libnames as $lib)
//        {
//           $finder = new Finder;
//           $builtIn = json_decode($this->checkIfBuiltInExists($lib), true);
//           if($builtIn['success'])
//               $path = $arduino_library_files."/libraries/".$lib;
//           else
//           {
//               $exists = json_decode($this->checkIfExternalExists($lib), true);
//               if($exists['success'])
//                   $path = $arduino_library_files."/external-libraries/".$lib;
//               else continue;
//           }
//           $files = $this->fetchLibraryFiles($finder, $path);
//           $libraries[$lib] = $files;
//        }
//        return $libraries;
//    }

    private function getExternalExampleCode($library, $example)
    {
        $em = $this->getDoctrine()->getManager();
        $libMeta = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));

        $exampleMeta = $em->getRepository('CodebenderLibraryBundle:Example')->findBy(array('library' => $libMeta[0], 'name' => $example));
        if(count($exampleMeta) == 0)
        {
            $example =  str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $exampleMeta = $em->getRepository('CodebenderLibraryBundle:Example')->findBy(array('library' => $libMeta[0], 'name' => $filename));
            if(count($exampleMeta) > 1)
            {
                $meta = NULL;
                foreach($exampleMeta as $e)
                {
                    $path = $e -> getPath();
                    if(!(strpos($path, $example)===false))
                    {
                        $meta = $e;
                        break;
                    }
                }
                if(!$meta)
                {
                    return json_encode(array('success' => false));
                }
            }
            else if(count($exampleMeta) == 0)
                return json_encode(array('success' => false));
            else
                $meta = $exampleMeta[0];
        }
        else
        {
            $meta = $exampleMeta[0];
        }
        $fullPath = $this->container->getParameter('arduino_library_directory')."/external-libraries/".$meta->getPath();

        $path = pathinfo($fullPath, PATHINFO_DIRNAME);
        $files = $this->getExampleFilesFromDir($path);
        return $files;

    }

    private function getExampleCodeFromDir($dir, $library, $example)
    {
        $finder = new Finder();
        $finder->in($dir.$library);
        $finder->name($example.".ino", $example.".pde");

        if(iterator_count($finder) == 0)
        {
            $example =  str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $finder->name($filename.".ino", $filename.".pde");
            if(iterator_count($finder) > 1)
            {
                $filesPath = NULL;
                foreach($finder as $e)
                {
                    $path = $e -> getPath();
                    if(!(strpos($path, $example)===false))
                    {
                        $filesPath = $e;
                        break;
                    }
                }
                if(!$filesPath)
                {
                    return json_encode(array('success' => false));
                }
            }
            else if(iterator_count($finder) == 0)
                return json_encode(array('success' => false));
            else
            {
                $filesPathIterator = iterator_to_array($finder, false);
                $filesPath = $filesPathIterator[0]->getPath();
            }
        }

        else
        {

            $filesPathIterator = iterator_to_array($finder, false);
            $filesPath = $filesPathIterator[0]->getPath();
        }
        $files = $this->getExampleFilesFromDir($filesPath);
        return $files;
    }

    private function getExampleFilesFromDir($dir)
    {
        $filesFinder = new Finder();
        $filesFinder->in($dir);
        $filesFinder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S')->name('*.pde')->name('*.ino');

        $files = array();
        foreach($filesFinder as $file)
        {
            if($file->getExtension() == "pde")
                $name = $file->getBasename("pde")."ino";
            else
                $name = $file->getFilename();

            $files[]=array("filename" => $name, "code" => $file->getContents());

        }

        return json_encode(array('success' => true, "files" => $files));
    }
    private function checkIfBuiltInExists($library)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
        if(is_dir($arduino_library_files."/libraries/".$library))
            return json_encode(array("success" => true, "message" => "Library found"));
        else
            return json_encode(array("success" => false, "message" => "No Library named ".$library." found."));
    }

    private function checkIfBuiltInExampleFolderExists($library)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
        if(is_dir($arduino_library_files."/examples/".$library))
            return json_encode(array("success" => true, "message" => "Library found"));
        else
            return json_encode(array("success" => false, "message" => "No Library named ".$library." found."));
    }

    private function checkIfExternalExists($library, $getDisabled = false)
    {
        $em = $this->getDoctrine()->getManager();
        $lib = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
        if(empty($lib) || (!$getDisabled && !$lib[0]->getActive()))
        {
            return json_encode(array("success" => false, "message" => "No Library named ".$library." found."));
        }
        else
        {
            return json_encode(array("success" => true, "message" => "Library found"));
        }

    }


	private function fetchLibraryFiles($finder, $directory, $getContent = true)
	{
		if (is_dir($directory))
		{
			$finder->in($directory)->exclude('examples')->exclude('Examples');
			$finder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S');

			$response = array();
			foreach ($finder as $file)
			{
                if($getContent)
				    $response[] = array("filename" => $file->getRelativePathname(), "content" => $file->getContents());
                else
                    $response[] = array("filename" => $file->getRelativePathname());
			}
			return $response;
		}

	}

    private function fetchLibraryExamples($finder, $directory)
    {
        if (is_dir($directory))
        {
            $finder->in($directory);
            $finder->name('*.pde')->name('*.ino');

            $response = array();
            foreach ($finder as $file)
            {
                    $response[] = array("filename" => $file->getRelativePathname(), "content" => $file->getContents());
            }

                return $response;
        }

    }

    private function getExternalLibrariesList()
    {
        $em = $this->getDoctrine()->getManager();
        $externalMeta = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('active' => true));

        $libraries = array();
        foreach($externalMeta as $lib)
        {
            $libname = $lib->getMachineName();
            if(!isset($libraries[$libname]))
            {
                if($lib->getOwner() !== NULL && $lib->getRepo() !== NULL)
                    $libraries[$libname] = array("description" => $lib->getDescription(), "url" => "http://github.com/".$lib->getOwner()."/".$lib->getRepo(), "examples" => array());
                else
                    $libraries[$libname] = array("description" => $lib->getDescription(), "examples" => array());
            }
            $examples = $em->getRepository('CodebenderLibraryBundle:Example')->findBy(array('library' => $lib));
            foreach($examples as $example)
            {
                $names = $this->getExampleAndLibNameFromRelativePath(pathinfo($example->getPath(), PATHINFO_DIRNAME), $example->getName());

                $libraries[$libname]['examples'][] = array('name' => $names['example_name']);
            }


        }

        return $libraries;
    }
	private function getLibariesListFromDir($path)
	{

        $finder = new Finder();
        $finder->files()->name('*.ino')->name('*.pde');
        $finder->in($path);

		$libraries = array();

		foreach ($finder as $file)
		{
            $names = $this->getExampleAndLibNameFromRelativePath($file->getRelativePath(), $file->getBasename(".".$file->getExtension()));

			if(!isset($libraries[$names['library_name']]))
			{
				$libraries[$names['library_name']] = array("description"=> "", "examples" => array());
			}
            $libraries[$names['library_name']]['examples'][] = array('name' => $names['example_name']);

		}
		return $libraries;
	}

    private function getExampleAndLibNameFromRelativePath($path, $filename)
    {
        $type = "";
        $library_name = strtok($path, "/");

        $tmp = strtok("/");

        while($tmp!= "" && !($tmp === false))
        {
            if($tmp != 'examples' && $tmp != 'Examples' && $tmp != $filename)
            {
                if($type == "")
                    $type = $tmp;
                else
                    $type = $type.":".$tmp;
            }
            $tmp = strtok("/");


        }
        $example_name= ($type == "" ?$filename : $type.":".$filename);
        return(array('library_name' => $library_name, 'example_name' => $example_name));
    }


    private function saveNewLibrary($humanName, $machineName, $gitOwner, $gitRepo, $description, $lastCommit, $url, $libfiles)
    {
        $exists = json_decode($this->checkIfExternalExists($machineName), true);
        if($exists['success'])
            return json_encode(array("success" => false, "message" => "Library named ".$machineName." already exists."));

        $create = json_decode($this->createLibFiles($machineName, $libfiles), true);
        if(!$create['success'])
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
        $examples = $this->fetchLibraryExamples(new Finder(), $arduino_library_files."/external-libraries/".$machineName);

//        $libfilesForCompilation = $this->fetchLibraryFiles(new Finder(), $arduino_library_files."/external-libraries/".$machineName);

        foreach($examples as $example)
        {

//            $filesForCompilation = $libfilesForCompilation;
            $path_parts = pathinfo($example['filename']);
//            $filesForCompilation[]  = array("filename"=>$path_parts['filename'].'.ino', "content" => $example['content']);
//            $boards = json_decode($this->getBoardsForExample($filesForCompilation), true);
//            $this->saveExampleMeta($path_parts['filename'], $lib, $machineName."/".$example['filename'],json_encode($boards['boards']));
            $this->saveExampleMeta($path_parts['filename'], $lib, $machineName."/".$example['filename'], NULL);
        }


        return json_encode(array("success" => true));

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

    private function getLastCommitFromGithub($gitOwner, $gitRepo)
    {
        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');
        $github_app_name = $this->container->getParameter('github_app_name');
        $url =  "https://api.github.com/repos/".$gitOwner."/".$gitRepo."/commits"."?client_id=".$client_id."&client_secret=".$client_secret;
        $json_contents = json_decode($this->curlRequest($url, NULL, array('User-Agent: '.$github_app_name)), true);

        return $json_contents[0]['sha'];
    }

    private function createLibFiles($machineName, $lib)
    {
        $libBaseDir = $this->container->getParameter('arduino_library_directory')."/external-libraries/".$machineName."/";
        return($this->createLibDirectory($libBaseDir, $libBaseDir, $lib['contents']));
    }

    private function createLibDirectory($base, $path, $files)
    {

        if(is_dir($path))
            return json_encode(array("success" => false, "message" => "Library directory already exists"));
        if(!mkdir($path))
            return json_encode(array("success" => false, "message" => "Cannot Save Library"));

        foreach($files as $file)
        {
            if($file['type'] == 'dir')
            {
                $create = json_decode($this->createLibDirectory($base, $base.$file['name']."/", $file['contents']), true);
                if(!$create['success'])
                    return(json_encode($create));
            }
            else
            {
                file_put_contents($path.$file['name'], $file['contents']);
            }
        }

        return json_encode(array('success' => true));
    }
    private function getLibNamesFromHeaders($headers)
    {
        $names = array();
        foreach($headers as $header)
        {
            $dot = strpos($header['name'],'.');
            $name = substr($header['name'], 0,$dot);
            array_push($names, $name);
        }

        return $names;
    }
    private function getLibFromGithub($owner, $repo, $onlyMeta = false)
    {

        $url = "https://api.github.com/repos/".$owner."/".$repo."/contents";
        $dir = json_decode($this->processGitDir($url, "", $onlyMeta),true);

        if(!$dir['success'])
            return json_encode($dir);
        else
            $dir = $dir['directory'];
        $baseDir = json_decode($this->findBaseDir($dir),true);
        if(!$baseDir['success'])
            return json_encode($baseDir);
        else
            $baseDir = $baseDir['directory'];

        return json_encode(array("success" => true, "library" => $baseDir));
    }

    private function findBaseDir($dir)
    {
        foreach($dir['contents'] as $file)
        {
            if($file['type'] == 'file' && strpos($file['name'], ".h") !== false)
                return json_encode(array('success' => true, 'directory' => $dir));

        }

        foreach($dir['contents'] as $file)
        {
            if($file['type'] == 'dir')
            {
                foreach($file['contents'] as $f)
                {
                    if($f['type'] == 'file' && strpos($f['name'], ".h") !== false)
                    {
                        $file = $this->fixDirName($file);
                        return json_encode(array('success' => true, 'directory' => $file));
                    }
                }
            }
        }
    }

    private function fixDirName($dir)
    {
        foreach ($dir['contents'] as &$f)
        {
            if($f['type'] == 'dir')
            {
                $first_slash = strpos($f['name'],"/");
                $f['name'] = substr($f['name'], $first_slash + 1);
                $f = $this->fixDirName($f);
            }
        }
        return $dir;
    }

    private function findHeadersFromLibFiles($libFiles)
    {
        $headers = array();
        foreach($libFiles as $file)
        {
            if($file['type'] == 'file' && substr($file['name'], -2) === ".h" )
            {
                $headers[] = $file;
            }
        }
        return $headers;
    }
    private function processGitDir($baseurl, $path, $onlyMeta = false)
    {

        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');
        $github_app_name = $this->container->getParameter('github_app_name');
        $url = ($path == "" ?  $baseurl : $baseurl."/".$path)."?client_id=".$client_id."&client_secret=".$client_secret;

        $json_contents = json_decode($this->curlRequest($url, NULL, array('User-Agent: '.$github_app_name)), true);

        if(array_key_exists('message', $json_contents))
        {
            return json_encode(array("success" => false, "message" => $json_contents["message"]));
        }
        $files = array();
        foreach($json_contents as $c)
        {

            if($c['type'] == "file")
            {
                $file = json_decode($this->processGitFile($baseurl,$c, $onlyMeta), true);
                if($file['success'])
                    array_push($files, $file['file']);
                else if($file['message']!="Bad Encoding")
                    return json_encode($file);
            }
            else if($c['type'] == "dir")
            {
                $subdir = json_decode($this->processGitDir($baseurl, $c['path'], $onlyMeta), true);
                if($subdir['success'])
                    array_push($files, $subdir['directory']);
                else
                    return json_encode($subdir);
            }
        }

        $name = ($path == "" ? "base" : $path);
        return json_encode(array("success" => true, "directory" =>array("name" => $name, "type" => "dir", "contents"=>$files)));
    }

    private function processGitFile($baseurl, $file, $onlyMeta = false)
    {
        if(!$onlyMeta)
        {
            $client_id = $this->container->getParameter('github_app_client_id');
            $client_secret = $this->container->getParameter('github_app_client_secret');
            $github_app_name = $this->container->getParameter('github_app_name');
            $url = ($baseurl."/".$file['path'])."?client_id=".$client_id."&client_secret=".$client_secret;

            $contents = $this->curlRequest($url, NULL, array('Accept: application/vnd.github.v3.raw', 'User-Agent: '.$github_app_name));
            $json_contents = json_decode($contents,true);

            if($json_contents === NULL)
            {
                if(! mb_check_encoding($contents, 'UTF-8'))
                    return json_encode(array('success'=>false, 'message' => "Bad Encoding"));

                return json_encode(array("success" => true, "file" => array("name" => $file['name'], "type" => "file", "contents" => $contents)));
            }
            else
            {
                return json_encode(array("success" => false, "message" => $json_contents['message']));
            }
        }
        else
        {
            return json_encode(array("success" => true, "file" => array("name" => $file['name'], "type" => "file")));
        }
    }

    private function getLibFromZipFile($file)
    {
        if(is_dir('/tmp/lib'))
            $this->destroy_dir('/tmp/lib');
        $zip = new \ZipArchive;
        $opened = $zip->open($file);
        if($opened === TRUE)
        {
            $zip->extractTo('/tmp/lib/');
            $zip->close();
            $dir = json_decode($this->processZipDir('/tmp/lib'), true);

            if(!$dir['success'])
                return json_encode($dir);
            else
                $dir = $dir['directory'];
            $baseDir = json_decode($this->findBaseDir($dir),true);
            if(!$baseDir['success'])
                return json_encode($baseDir);
            else
                $baseDir = $baseDir['directory'];

            return json_encode(array("success" => true, "library" => $baseDir));
        }

        else
        {
            return json_encode(array("success" => false, "message" => "Could not unzip Archive. Code: ".$opened));
        }
    }
    private function processZipDir($path)
    {
        $files = array();
        $dir = preg_grep('/^([^.])/', scandir($path));
        foreach($dir as $file)
        {
            if($file === "__MACOSX")
                continue;

            if(is_dir($path.'/'.$file))
            {
                $subdir = json_decode($this->processZipDir($path.'/'.$file), true);
                if($subdir['success'])
                    array_push($files, $subdir['directory']);
                else
                    return json_encode($subdir);
            }
            else
            {
                $file = json_decode( $this->processZipFile($path.'/'.$file), true);
                if($file['success'])
                    array_push($files, $file['file']);
                else if($file['message']!="Bad Encoding")
                    return json_encode($file);
            }
        }
        return json_encode(array("success" => true, "directory" =>array("name" => substr($path, 9), "type" => "dir", "contents"=>$files)));
    }

    private function processZipFile($path)
    {
        $contents = file_get_contents($path);
        if(! mb_check_encoding($contents, 'UTF-8')){
            $contents = utf8_encode($contents);
        }
        if($contents === NULL)
            return json_encode(array("success" => false, "message"=>"Could not read file ".basename($path)));

        return json_encode(array("success" => true, "file" => array("name" => basename($path),"type" => "file", "contents" => $contents)));
    }

    private function destroy_dir($dir) {
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

    private function curlRequest($url, $post_request_data = NULL, $http_header = NULL)
    {
        $curl_req = curl_init();
        curl_setopt_array($curl_req, array (
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ));
        if($post_request_data!==NULL)
            curl_setopt($curl_req, CURLOPT_POSTFIELDS, $post_request_data);

        if($http_header!==NULL)
            curl_setopt($curl_req, CURLOPT_HTTPHEADER, $http_header);

        $contents = curl_exec($curl_req);

        curl_close($curl_req);
        return $contents;
    }
	
	
    public function getKeywordsAction()
    {
        $request = $this->getRequest();
        $library= $request->query->get('library');
		
		if( $library == null ) {
			
            return new Response(json_encode(array("success"=>false)));
			
		}

        $exists = json_decode($this->getLibraryType($library), true);
		
        if ($exists['success'])
        {
			
            $path = "";
            if($exists['type'] == 'external')
            {
                $path = $this->container->getParameter('arduino_library_directory')."/external-libraries/".$library;
            }
            else if($exists['type'] = 'builtin')
            {
                $path = $this->container->getParameter('arduino_library_directory')."/libraries/".$library;
            }
			
			$keywords=array();
			
            $finder = new Finder();
            $finder->in($path);
            $finder->name( '/keywords\.txt/i' );
			
            foreach ($finder as $file) {
				
                $content = $file->getContents();
				
				$lines = preg_split('/\r\n|\r|\n/', $content);
				
				foreach($lines as $rawline){
					
					$line=trim($rawline);
					$parts = preg_split('/\s+/', $line);
					
					$totalParts=count($parts);
					
					if( ($totalParts == 2) || ($totalParts == 3) ) {
						
						if( (substr($parts[1],0,7) == "KEYWORD") ) {
							$keywords[$parts[1]][] = $parts[0];
						}
						
						if( (substr($parts[1],0,7) == "LITERAL") ) {
							$keywords["KEYWORD3"][] = $parts[0];
						}
						
					}
					
				}
				
				break;
            }

            return new Response(json_encode(array('success' => true, 'keywords' => $keywords)));

        }
        else
        {
            return new Response(json_encode($exists));
        }
		
	}	
		
	


}

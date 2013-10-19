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

		    $finder = new Finder();
		    $finder2 = new Finder();

		    $finder->files()->name('*.ino')->name('*.pde');
		    $finder2->files()->name('*.ino')->name('*.pde');

		    $built_examples = array();
		    if (is_dir($arduino_library_files."examples"))
		    {
			    $finder->in($arduino_library_files."examples");
			    $built_examples = $this->iterateDir($finder, "v1");
		    }

		    $included_libraries = array();
		    if (is_dir($arduino_library_files."libraries"))
		    {
			    $finder2->in($arduino_library_files."libraries");
			    $included_libraries = $this->iterateDir($finder2, "v1");
		    }

		    $external_libraries = array();
            $em = $this->getDoctrine()->getManager();
            $externalMeta = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findAll();
            $external_libraries = $this->getExternalInfo($externalMeta, $version);

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

	public function getExampleCodeAction($auth_key, $version)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
		}

		if ($version == "v1")
		{
			$arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

			$finder = new Finder();

			$request = $this->getRequest();

			// retrieve GET and POST variables respectively
			$file = $request->query->get('file');

			$last_slash = strrpos($file, "/");
			$filename = substr($file, $last_slash + 1);
			$directory = substr($file, 0, $last_slash);
            $first_slash = strpos($file, "/");
            $libname = substr($file, 0, $first_slash);


			$finder->files()->name($filename);
			if (is_dir($arduino_library_files."examples"))
			{
				$finder->in($arduino_library_files."examples");
			}

			if (is_dir($arduino_library_files."libraries"))
			{
				$finder->in($arduino_library_files."libraries");
			}

			if (is_dir($arduino_library_files."external-libraries"))
			{
                $exists = json_decode($this->checkIfExternalExists($libname),true);
                if($exists['success'])
                {
                    $finder->in($arduino_library_files."external-libraries");
                }
			}

			$finder->path($directory);

			$response = "";
			foreach ($finder as $file)
			{
				$response = $file->getContents();
			}
			return new Response($response);
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

			$filename = $library;
			$directory = "";

			$last_slash = strrpos($library, "/");
			if($last_slash !== false )
			{
				$filename = substr($library, $last_slash + 1);
				$vendor = substr($library, 0, $last_slash);
			}

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
                $response = json_decode($this->checkIfExternalExists($filename),true);
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
                        $meta = array("humanName" => $libmeta[0]->getHumanName(), "description" => $libmeta[0]->getDescription(), "verified" => $libmeta[0]->getVerified(), "gitOwner" => $libmeta[0]->getOwner(), "gitRepo" => $libmeta[0]->getRepo());

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

                $saved = json_decode($this->saveNewLibrary($formData['HumanName'], $formData['MachineName'], $formData['GitOwner'], $formData['GitRepo'], $formData['Description'], $lastCommit , $lib), true);
                if($saved['success'])
                    return $this->redirect($this->generateUrl('codebender_library_view_library', array("auth_key" => "authKey", "version"=>"v1","library" => $formData["MachineName"])));
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
            $isBuiltIn = json_encode($this->checkIfBuiltInExists($library), true);
            if ($isBuiltIn['success'])
            {
                return json_encode(array('success' => true, 'type' => 'builtin'));
            }
        }

        return json_encode(array('success' => false, 'message' => 'Library named '.$library.' not found.'));
    }

    private function read_headers($code)
{
    // Matches preprocessor include directives, has high tolerance to
    // spaces. The actual header (without the postfix .h) is stored in
    // register 1.
    //
    // Examples:
    // #include<stdio.h>
    // # include "proto.h"
    $REGEX = "/^\s*#\s*include\s*[<\"]\s*(\w*)\.h\s*[>\"]/";

    $headers = array();
    foreach (explode("\n", $code) as $line)
        if (preg_match($REGEX, $line, $matches))
            $headers[] = $matches[1];

    return $headers;
}

    private function constructLibraryFiles($libnames)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
        $libraries = array();

        foreach($libnames as $lib)
        {
           $finder = new Finder;
           $builtIn = json_decode($this->checkIfBuiltInExists($lib), true);
           if($builtIn['success'])
               $path = $arduino_library_files."/libraries/".$lib;
           else
           {
               $exists = json_decode($this->checkIfExternalExists($lib), true);
               if($exists['success'])
                   $path = $arduino_library_files."/external-libraries/".$lib;
               else continue;
           }
           $files = $this->fetchLibraryFiles($finder, $path);
           $libraries[$lib] = $files;
        }
        return $libraries;
    }

    private function checkIfBuiltInExists($library)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
        if(is_dir($arduino_library_files."/libraries/".$library))
            return json_encode(array("success" => true, "message" => "Library found"));
        else
            return json_encode(array("success" => false, "message" => "No Library named ".$library." found."));
    }

    private function checkIfExternalExists($library)
    {
        $em = $this->getDoctrine()->getManager();
        $lib = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
        if(empty($lib) || !$lib[0]->getActive())
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

    private function getExternalInfo($libsmeta, $version)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
        $libraries = array();
        foreach($libsmeta as $lib)
        {
            $libname = $lib->getMachineName();
            if(!isset($libraries[$libname]))
            {
                $libraries[$libname] = array("description" => $lib->getDescription(), "examples" => array());
            }
            if(is_dir($arduino_library_files."EXTERNAL-libraries/".$libname))
            {
                $finder = new Finder();
                $finder->files()->name('*.ino')->name('*.pde');
                $finder->in($arduino_library_files."external-libraries/".$libname);

                foreach($finder as $file)
                {
                    $url = $this->get('router')->generate('codebender_library_get_example_code', array("auth_key" => $this->container->getParameter('auth_key'),"version" => $version),true).'?file='.$libname."/".$file->getRelativePathname();
                    $libraries[$libname]["examples"][] = array("name" => strtok($file->getFilename(), "."), "filename" => $file->getFilename(), "url" => $url);
                }

            }
        }
        return $libraries;
    }

	private function iterateDir($finder, $version)
	{
		$libraries = array();

		foreach ($finder as $file)
		{
//			if (strpos($file->getRelativePath(), "/examples/") === false)
//				continue;

			// Print the absolute path
//		    print $file->getRealpath()."<br />\n";

			// Print the relative path to the file, omitting the filename
//			print $file->getRelativePath()."<br />\n";

			// Print the relative path to the file
//			print $file->getRelativePathname()."<br />\n";

			$path = str_ireplace("/examples/", "/", $file->getRelativePath());
			$library_name = strtok($path, "/");
			$example_name = strtok("/");
			$url = $this->get('router')->generate('codebender_library_get_example_code', array("auth_key" => $this->container->getParameter('auth_key'),"version" => $version),true).'?file='.$file->getRelativePathname();

			if(!isset($libraries[$library_name]))
			{
				$libraries[$library_name] = array("description"=> "", "examples" => array());
			}
			$libraries[$library_name]["examples"][] = array("name" => $example_name, "filename" => $file->getFilename(), "url" => $url);

//			print $library_name."<br />\n";
//			print $example_name."<br />\n";

			// Print the relative path to the file
//			print $file->getFilename()."<br />\n";
		}
		return $libraries;
	}


    private function saveNewLibrary($humanName, $machineName, $gitOwner, $gitRepo, $description, $lastCommit, $libfiles)
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

        $em = $this->getDoctrine()->getManager();
        $em->persist($lib);
        $em->flush();

        $arduino_library_files = $this->container->getParameter('arduino_library_directory');
        $examples = $this->fetchLibraryExamples(new Finder(), $arduino_library_files."/external-libraries/".$machineName);
        foreach($examples as $example)
        {
            $path_parts = pathinfo($example['filename']);
            $this->saveExampleMeta($path_parts['filename'], $lib, $example['filename'], NULL);
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
        $url =  "https://api.github.com/repos/".$gitOwner."/".$gitRepo."/commits"."?client_id=".$client_id."&client_secret=".$client_secret;
        $json_contents = json_decode($this->curlRequest($url), true);

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
        $url = ($path == "" ?  $baseurl : $baseurl."/".$path)."?client_id=".$client_id."&client_secret=".$client_secret;

        $json_contents = json_decode($this->curlRequest($url), true);

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
            $url = ($baseurl."/".$file['path'])."?client_id=".$client_id."&client_secret=".$client_secret;

            $contents = $this->curlRequest($url, NULL, array('Accept: application/vnd.github.v3.raw'));
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
        if($zip->open($file) === TRUE)
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
            return json_encode(array("success" => false, "message" => "Could not unzip Archive."));
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
            return json_encode(array('success'=>false, 'message' => "Bad Encoding"));
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
            curl_setopt($curl_req, CURLOPT_HTTPHEADER, array('Accept: application/vnd.github.v3.raw'));

        $contents = curl_exec($curl_req);

        curl_close($curl_req);
        return $contents;
    }


}

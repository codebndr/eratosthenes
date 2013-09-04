<?php

namespace Codebender\LibraryBundle\Controller;

use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;


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

	public function getLibraryCodeAction($auth_key, $version)
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
			$library = $request->query->get('library');

			$filename = $library;
			$directory = "";

			$last_slash = strrpos($library, "/");
			if($last_slash !== false )
			{
				$filename = substr($library, $last_slash + 1);
				$vendor = substr($library, 0, $last_slash);
			}

			$response = $this->fetchLibraryFiles($finder, $arduino_library_files."/libraries/".$filename);
			if(empty($response))
            {
                $response = json_decode($this->checkIfExternalExists($library),true);
                if(!$response['success'])
                {
                    return new Response(json_encode($response));
                }
                else
                {
                    $response = $this->fetchLibraryFiles($finder, $arduino_library_files."/external-libraries/".$filename);
                    if(empty($response))
                        return new Response(json_encode(array("success" => false, "message" => "No files for Library named ".$library." found.")));
                }
            }
			return new Response(json_encode(array("success" => true, "message" => "Library found", "files" => $response)));

		}
		else
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
		}
	}


    public function newLibraryAction($data = NULL)
    {

        if($data == NULL){
            $form = $this->createFormBuilder()
                ->add('GitOwner', 'text')
                ->add('GitRepo', 'text')
                ->add('HumanName', 'text')
                ->add('Description', 'text')
                ->add('MachineName', 'hidden')
                ->add('Go!', 'submit')
                ->getForm();

            $form->handleRequest($this->getRequest());

            if ($form->isValid()) {

                $formData = $form->getData();

                $lib = json_decode($this->getLibFromGithub($formData["GitOwner"], $formData["GitRepo"]), true);
                if (!$lib['success'])
                    return new Response(json_encode($lib));
                else
                    $lib = $lib['library'];

                if($formData["MachineName"] != NULL)
                {
                    $saved = json_decode($this->saveNewLibrary($formData['HumanName'], $formData['MachineName'], $formData['GitOwner'], $formData['GitRepo'], $formData['Description'], $lib), true);
                    return new Response(json_encode($saved));
                }
                $headers = $this->findHeadersFromLibFiles($lib['contents']);
                $names = $this->getLibNamesFromHeaders($headers);
                if (count($names) == 1) {
                    $machineName = $names[0];
                    $saved = json_decode($this->saveNewLibrary($formData['HumanName'], $machineName, $formData['GitOwner'], $formData['GitRepo'], $formData['Description'], $lib), true);
                    return new Response(json_encode($saved));
                }
                else
                {
                    $response = $this->forward('CodebenderLibraryBundle:Default:newLibrary', array(
                        'data'  => array('GitOwner' => $formData['GitOwner'], 'GitRepo' =>$formData['GitRepo'], 'HumanName' => $formData["HumanName"], 'Description' => $formData['Description'], 'MachineNames' => $names)
                    ));

                    return $response;
                }
            }
        }
        else
        {
            $machineNames = array();
            foreach($data["MachineNames"] as $mn)
            {
                $machineNames[$mn] = $mn;
            }

            $this->get('session')->getFlashBag()->add(
                'notice',
                'More than one header files found in directory. Please select the main one from the list below.'
            );

            $form = $this->createFormBuilder()
                ->add('MachineName', 'choice', array('choices' => $machineNames))
                ->add('GitOwner', 'text', array('read_only' => true, 'data' => $data['GitOwner']))
                ->add('GitRepo', 'text', array('read_only' => true, 'data' => $data['GitRepo']))
                ->add('HumanName', 'text', array('read_only' => true, 'data' => $data['HumanName']))
                ->add('Description', 'text', array('read_only' => true, 'data' => $data['Description']))
                ->add('Go!', 'submit')
                ->getForm();

        }
        return $this->render('CodebenderLibraryBundle:Default:newLibForm.html.twig', array(
            'form' => $form->createView()
        ));



    }



    public function verifyBuiltInExamplesAction()
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

        $finder = new Finder();
        $finder->files()->name('*.ino')->name('*.pde');
        $finder->in($arduino_library_files."examples/");

        $version = "105";
        $format = "syntax";
        $build = array("mcu"=>"atmega328p", "f_cpu"=>"16000000L", "core"=>"arduino", "variant"=>"standard");
        $files = array();

        $response = array();

        foreach ($finder as $file)
        {
            $files[] = array("filename"=>$file->getBaseName(), "content" => $file->getContents());
            $h_finder = new Finder();
            $h_finder->files()->name('*.h');
            $h_finder->in($arduino_library_files."examples/".$file->getRelativePath());

            foreach($h_finder as $header)
            {
                $files[] = array("filename"=>$header->getBaseName(), "content" => $header->getContents());
            }

            $libraries = array();
            $request_data = json_encode(array('files' => $files, 'libraries' => $libraries, 'format' => $format, 'version' => $version, 'build' => $build));

            $response[$file->getRelativePathName()] = json_decode($this->verifyReq($request_data),true);

            $files = array();
        }

        return new Response(strip_tags(json_encode($response)));

    }

    private function verifyReq($request_data)
    {
        $compiler_url = $this->container->getParameter('compiler_url');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $compiler_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);

        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }



    private function checkIfExternalExists($library)
    {
        $em = $this->getDoctrine()->getManager();
        $lib = $em->getRepository('CodebenderLibraryBundle:ExternalLibrary')->findBy(array('machineName' => $library));
        if(empty($lib))
        {
            return json_encode(array("success" => false, "message" => "No Library named ".$library." found."));
        }
        else
        {
            return json_encode(array("success" => true, "message" => "Library found"));
        }

    }


	private function fetchLibraryFiles($finder, $directory)
	{
		if (is_dir($directory))
		{
			$finder->in($directory)->exclude('examples')->exclude('Examples');
			$finder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S');

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
                    $libraries[$libname]["examples"][] = array("name" => strtok($file->getRelativePathname(), "/"), "filename" => $file->getFilename(), "url" => $url);
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


    private function saveNewLibrary($humanName, $machineName, $gitOwner, $gitRepo, $description, $libfiles)
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

        $em = $this->getDoctrine()->getManager();
        $em->persist($lib);
        $em->flush();

        return json_encode(array("success" => true));

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
            $blah=$path.$file['name'] ;
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
    private function getLibFromGithub($owner, $repo)
    {

        $url = "https://api.github.com/repos/".$owner."/".$repo."/contents";
        $dir = json_decode($this->processGitDir($url, ""),true);

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
            if($file['type'] == 'file' && strpos($file['name'], ".h") !== false)
            {
                $headers[] = $file;
            }
        }

        return $headers;
    }
    private function processGitDir($baseurl, $path)
    {

        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');

        $curl_req = curl_init();
        curl_setopt_array($curl_req, array (
            CURLOPT_URL => ($path == "" ?  $baseurl : $baseurl."/".$path)."?client_id=".$client_id."&client_secret=".$client_secret,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ));

        $contents = curl_exec($curl_req);
        $json_contents = json_decode($contents, true);


        if(array_key_exists('message', $json_contents))
        {
            return json_encode(array("success" => false, "message" => $json_contents["message"]));
        }
        $files = array();
        foreach($json_contents as $c)
        {

            if($c['type'] == "file")
            {
                $file = json_decode($this->processGitFile($baseurl,$c), true);
                if($file['success'])
                    array_push($files, $file['file']);
                else if($file['message']!="Bad Encoding")
                    return json_encode($file);
            }
            else if($c['type'] == "dir")
            {
                $subdir = json_decode($this->processGitDir($baseurl, $c['path']), true);
                if($subdir['success'])
                    array_push($files, $subdir['directory']);
                else
                    return json_encode($subdir);
            }
        }

        $name = ($path == "" ? "base" : $path);
        return json_encode(array("success" => true, "directory" =>array("name" => $name, "type" => "dir", "contents"=>$files)));
    }

    private function processGitFile($baseurl, $file)
    {
        $client_id = $this->container->getParameter('github_app_client_id');
        $client_secret = $this->container->getParameter('github_app_client_secret');

        $curl_req = curl_init();
        curl_setopt_array($curl_req, array (
            CURLOPT_URL => ($baseurl."/".$file['path'])."?client_id=".$client_id."&client_secret=".$client_secret,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => array('Accept: application/vnd.github.v3.raw')
        ));

        $contents = curl_exec($curl_req);

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

}

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
}

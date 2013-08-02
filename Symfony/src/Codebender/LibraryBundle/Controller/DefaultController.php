<?php

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;


class DefaultController extends Controller
{
	public function listAction()
    {
		$arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

	    $finder = new Finder();
	    $finder2 = new Finder();
	    $finder3 = new Finder();

	    $finder->files()->name('*.ino')->name('*.pde');
	    $finder2->files()->name('*.ino')->name('*.pde');
	    $finder3->files()->name('*.ino')->name('*.pde');

	    $built_examples = array();
	    if (is_dir($arduino_library_files."examples"))
	    {
		    $finder->in($arduino_library_files."examples");
		    $built_examples = $this->iterateDir($finder);
	    }

	    $included_libraries = array();
	    if(is_dir($arduino_library_files."libraries"))
	    {
		    $finder2->in($arduino_library_files."libraries");
		    $included_libraries = $this->iterateDir($finder2);
	    }

	    $external_libraries = array();
	    if (is_dir($arduino_library_files."external-libraries"))
	    {
		    $finder3->in($arduino_library_files."external-libraries");
		    $external_libraries = $this->iterateDir($finder3);
	    }


	    ksort($built_examples);
	    ksort($included_libraries);
	    ksort($external_libraries);

	    return new Response(json_encode(array("success" => true,
									"text" => "Successful Request!",
							  "categories" => array("Examples" => $built_examples,
										   "Builtin Libraries" => $included_libraries,
					                      "External Libraries" => $external_libraries))));

    }

	public function getCodeAction()
	{
		$arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

		$finder = new Finder();

		$request = Request::createFromGlobals();

		// retrieve GET and POST variables respectively
		$file = $request->query->get('file');

		$last_slash = strrpos($file, "/");
		$filename = substr($file, $last_slash+1);
		$directory = substr($file, 0, $last_slash);

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
			$finder->in($arduino_library_files."external-libraries");
		}

		$finder->path($directory);

		$response = "";
		foreach($finder as $file)
		{
			$response = $file->getContents();
		}
		return new Response($response);
	}

	private function iterateDir($finder)
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
			$url = $this->get('router')->generate('codebender_library_getcode', array(),true).'?file='.$file->getRelativePathname();

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

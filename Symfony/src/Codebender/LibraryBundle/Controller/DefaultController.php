<?php

namespace Codebender\LibraryBundle\Controller;

use Codebender\LibraryBundle\Entity\Example;
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

    public function apiHandlerAction($auth_key, $version)
    {
        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "message" => "Invalid library manager authorization key.")));
        }

        if ($version != "v1")
        {
            return new Response(json_encode(array("success" => false, "message" => "Invalid library manager API version.")));
        }

        $request = $this->getRequest();
        $content = $request->getContent();

        $content = json_decode($content, true);
        if ($content === NULL )
        {
            return new Response(json_encode(array("success" => false, "message" => "Wrong data")));
        }

        // TODO: add a "testIsValid" for the request contents
        switch ($content["type"])
        {
            case "list":
                return $this->listAll();
            case "getExampleCode":
                return $this->getExampleCode($content["library"], $content["example"]);
            case "getExamples":
                return $this->getLibraryExamples($content["library"]);
            case "fetch":
                $handler = $this->get('codebender_library.hanlder');
                return $handler->getLibraryCode($content["library"], 0);
            case "checkGithubUpdates":
                $handler = $this->get('codebender_library.hanlder');
                return $handler->checkGithubUpdates();
            case "getKeywords":
                return $this->getKeywords($content["library"]);
            default:
                return new Response(json_encode(array("success" => false, "message" => "No action defined")));
        }
    }

	public function listAll()
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

	public function getExampleCode($library, $example)
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

    public function getLibraryGitMetaAction($auth_key)
    {
        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        if ($this->getRequest()->getMethod() == 'POST') {
            $handler = $this->get('codebender_library.hanlder');

            $owner = $this->get('request')->request->get('gitOwner');
            $repo = $this->get('request')->request->get('gitRepo');
            $lib = json_decode($handler->getLibFromGithub($owner, $repo, true), true);
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

        } else {
            return new Response(json_encode(array("success" => false)));
        }
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

                $content = (!mb_check_encoding($example->getContents(), 'UTF-8')) ? mb_convert_encoding($example->getContents(), "UTF-8") : $example->getContents();
                $path_info = pathinfo($example->getBaseName());
                $files[] = array("filename"=>$path_info['filename'].'.ino', "content" => (!mb_check_encoding($content, 'UTF-8')) ? mb_convert_encoding($content, "UTF-8") : $content);

                $h_finder = new Finder();
                $h_finder->files()->name('*.h')->name('*.cpp');
                $h_finder->in($path."/".$example->getRelativePath());

                foreach($h_finder as $header)
                {
                    $files[] = array("filename"=>$header->getBaseName(), "content" => (!mb_check_encoding($header->getContents(), 'UTF-8')) ? mb_convert_encoding($header->getContents(), "UTF-8") : $header->getContents());
                }

                $dir = preg_replace( '/[E|e]xamples\//', '', $example->getRelativePath());
                $dir = str_replace( $path_info['filename'], '', $dir);
                $dir = str_replace('/', ':', $dir);
                if ($dir != '' && substr($dir, -1) != ':')
                    $dir .= ':';


                $examples[$dir . $path_info['filename']] = $files;
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
        $handler = $this->get('codebender_library.handler');
        $isExternal = json_decode($handler->checkIfExternalExists($library), true);
        if($isExternal['success'])
        {
            return json_encode(array('success' => true, 'type' => 'external'));
        }
        else
        {
            $isBuiltIn = json_decode($handler->checkIfBuiltInExists($library), true);
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

            $files[]=array("filename" => $name, "code" => (!mb_check_encoding($file->getContents(), 'UTF-8'))? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents());

        }

        return json_encode(array('success' => true, "files" => $files));
    }

    private function checkIfBuiltInExampleFolderExists($library)
    {
        $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
        if(is_dir($arduino_library_files."/examples/".$library))
            return json_encode(array("success" => true, "message" => "Library found"));
        else
            return json_encode(array("success" => false, "message" => "No Library named ".$library." found."));
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
                    $libraries[$libname] = array("description" => $lib->getDescription(), "humanName" => $lib->getHumanName(), "url" => "http://github.com/".$lib->getOwner()."/".$lib->getRepo(), "examples" => array());
                else
                    $libraries[$libname] = array("description" => $lib->getDescription(), "humanName" => $lib->getHumanName(), "examples" => array());
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

    public function getKeywords($library)
    {
		if( $library === NULL ) {

            return new Response(json_encode(array("success"=>false)));

		}

        $exists = json_decode($this->getLibraryType($library), true);

        if ($exists['success'] === false)
        {
            return new Response(json_encode($exists));
        }

        $path = "";
        if($exists['type'] == 'external')
        {
            $path = $this->container->getParameter('arduino_library_directory')."/external-libraries/".$library;
        }
        else if($exists['type'] = 'builtin')
        {
            $path = $this->container->getParameter('arduino_library_directory')."/libraries/".$library;
        }
        else return new Response(json_encode(array("success"=>false)));

        $keywords=array();

        $finder = new Finder();
        $finder->in($path);
        $finder->name( '/keywords\.txt/i' );

        foreach ($finder as $file) {

            $content = (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents();

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




}

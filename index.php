<?php
header("Cache-Control: must-revalidate, max-age=3600");
header("Vary: Accept-Encoding");

$response = array('success' => 0, 'text' => "NO DATA!");

if(!isset($_REQUEST['data']))
	die(json_encode($response));

$data = $_REQUEST['data'];


putenv('HOME=/home/ubuntu/');
require_once 'AWSSDKforPHP/sdk.class.php';

// Instantiate the class
$handler = new LibraryHandler();

$response["success"] = 1;
$response["text"] = "Successful Request!";

if($data == "builtin")
{
	$response["list"] = $handler->getBuiltinExamples();
}
else if($data == "included")
{
	$response["list"] = $handler->getIncludedExamples();
}
else if($data == "external")
{
	$response["list"] = $handler->getExternalExamples();	
}
else if($data == "list")
{
	$response["list"] = array("builtin" => $handler->listBuiltin());
	$response["list"]["included"] = $handler->listIncluded();
	$response["list"]["external"] = $handler->listExternal();
}
else if($data == "all")
{
	$response["categories"] = array("Examples" => $handler->fetchBuiltinInfo(),
									"Builtin Libraries" => $handler->fetchInfo('libraries/'),
									"External Libraries" => $handler->fetchInfo('external-libraries/'));
}
else
{
	$response["success"] = 0;
	$response["text"] = "WRONG DATA REQUESTED!";
}

echo json_encode($response);


class LibraryHandler
{
	private $s3;
	private $bucket = 'codebender_libraries';
	
	function __construct() 
	{
		$this->s3 = new AmazonS3();
	}
	
	public function getBuiltinExamples()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'examples', 'pcre' => '/\.ino/'));

		$response = $this->generateUrls($response);
		$response = $this->generateExamples($response, 2);
		return $response;
	}
	
	public function getIncludedExamples()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'libraries', 'pcre' => '/\.ino/'));

		$response = $this->generateUrls($response);
		$response = $this->generateExamples($response, 3);
		return $response;
	}

	public function getExternalExamples()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'external-libraries', 'pcre' => '/(\.ino|\.pde)/i'));

		$response = $this->generateUrls($response);
		$response = $this->generateExamples($response, 3);
		return $response;
	}
	
	public function listBuiltin()
	{
		$list = array();
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'examples/', "delimiter" => "/"));
		foreach($response as $key=>$value)
		{
			$array = explode("/", $value);
			$list[] = $array[1];
		}
		return $list;
	}

	public function listIncluded()
	{
		$list = array();
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'libraries/', "delimiter" => "/"));
		foreach($response as $key=>$value)
		{
			$array = explode("/", $value);
			$list[] = $array[1];
		}
		return $list;
	}

	public function listExternal()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'external-libraries/', "delimiter" => "/"));
		$list = array();
		foreach($response as $key=>$value)
		{
			$array = explode("/", $value);
			$list[] = $array[1];
		}
		return $list;
	}

	public function fetchBuiltinInfo()
	{
		$dir = "examples/";
		$response="";
		$array = array();

		$list = $this->s3->get_object_list($this->bucket, array('prefix' => $dir, "delimiter" => "/"));

		$libraries = array();
		foreach($list as $library)
		{
			$info = array();

			$array = explode("/", $library);
			$name = $array[count($array)-1];
			// echo $name;
			$info["name"] = $name;
			$info["description"] = $this->getDescription($library);

			$response = $this->s3->get_object($this->bucket, $library.'/URL.txt');
			if(!$response->isOK())
				$response = $this->s3->get_object($this->bucket, $library.'/url.txt');
			if($response->isOK())
			{
					$info["url"] = $response->body;
			}

			$response = $this->s3->get_object_list($this->bucket, array('prefix' => $library."/", 'pcre' => '/(\.ino|\.pde)/i'));

			$response = $this->generateUrls($response);
			$response = $this->generateExamples($response, 3);
			if($response)
				$info["examples"] = $response;

			$libraries[] = $info;
		}

		return $libraries;
	}

	public function fetchInfo($dir)
	{
		$response="";
		$array = array();

		$list = $this->s3->get_object_list($this->bucket, array('prefix' => $dir, "delimiter" => "/"));

		$libraries = array();
		foreach($list as $library)
		{
			$info = array();

			$array = explode("/", $library);
			$name = $array[count($array)-1];
			// echo $name;
			$info["name"] = $name;
			$info["description"] = $this->getDescription($library);

			$response = $this->s3->get_object($this->bucket, $library.'/URL.txt');
			if(!$response->isOK())
				$response = $this->s3->get_object($this->bucket, $library.'/url.txt');
			if($response->isOK())
			{
					$info["url"] = $response->body;
			}

			$response = $this->s3->get_object_list($this->bucket, array('prefix' => $library."/examples", 'pcre' => '/(\.ino|\.pde)/i'));

			$response = $this->generateUrls($response);
			$response = $this->generateExamples($response, 3);
			if($response)
				$info["examples"] = $response;

			$libraries[] = $info;
		}

		return $libraries;
	}

	private function getDescription($library)
	{
		$list = $this->s3->get_object_list($this->bucket, array('prefix' => $library."/", "delimiter" => "/", 'pcre' => '/(README\.)/i'));
		$description = "none";
		foreach($list as $key => $filename)
		{
			if(strpos(strtolower($filename), strtolower("README.")) !== FALSE)
			{
				$response = $this->s3->get_object($this->bucket, $list[$key]);
				if($response->isOK())
				{
					if(strpos($filename, "html") !== FALSE)
					{
						// $description = $response->body;
					}
					else if(strpos($filename, "md") !== FALSE)
					{
						// $description = $response->body;
						// include_once "markdown.php";
						// $description = Markdown($description);
					}
					else if(strpos($filename, "txt") !== FALSE)
						$description = $response->body;
				}
				break;
			}
		}
		return $description;
	}

	private function generateUrls($examples)
	{
		foreach($examples as &$example)
		{
			$url = $this->s3->get_object_url($this->bucket,$example);
			$example = array("path" => $example, "url" => $url);
		}
		return $examples;
	}

	private function generateExamples($examples, $cat_no)
	{
		$list = array();
		foreach($examples as $example)
		{
			$array = explode("/", $example["path"]);
			$list[] = array("name" => $array[$cat_no], "url" => $example["url"]);
		}

		return $list;
	}
	
}

?>

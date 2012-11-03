<?php

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
else if($data == "list-included")
{
	$response["list"] = $handler->listIncluded();	
}
else if($data == "list-external")
{
	$response["list"] = $handler->listExternal();
}
else if($data == "fetch-description-external")
{
	$name = $_REQUEST['name'];
	$response = array_merge($response,$handler->fetchDescriptionExternal($name));
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

	public function fetchDescriptionExternal($name)
	{
		$response="";
		$array = array();
		$list = $this->s3->get_object_list($this->bucket, array('prefix' => 'external-libraries/'.$name.'/', "delimiter" => "/", 'pcre' => '/(README\.)/i'));
		$description = "none";
		foreach($list as $key => $filename)
		{
			if(strpos(strtolower($filename), strtolower("README.")) !== FALSE)
			{
				$response = $this->s3->get_object($this->bucket, $list[$key]);
				if($response->isOK())
				{
					if(strpos($filename, "html") !== FALSE)
						$description = $response->body;
					else if(strpos($filename, "md") !== FALSE)
					{
						$description = $response->body;
						include_once "markdown.php";
						$description = Markdown($description);
					}
					else if(strpos($filename, "txt") !== FALSE)
						$description = $response->body;
				}
				break;
			}
		}
		$array["description"] = $description;

		$response = $this->s3->get_object($this->bucket, 'external-libraries/'.$name.'/URL.txt');
		if(!$response->isOK())
			$response = $this->s3->get_object($this->bucket, 'external-libraries/'.$name.'/url.txt');
		if($response->isOK())
		{
				$array["url"] = $response->body;
		}
		return $array;
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
				// $example = array("category" => $array[2], "name" => $array[$cat_no], "url" => $example["url"]);
				if(!isset($list[$array[1]]))
					$list[$array[1]] = array();
				$list[$array[1]] = array_merge((array) $list[$array[1]], array(array("name" => $array[$cat_no], "url" => $example["url"])));
				// var_dump($example);
		}

		return $list;
	}
	
}

?>

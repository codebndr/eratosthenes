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
$response["text"] = "Successfuly Compiled!";

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
else
{
	$response["success"] = 0;
	$response["text"] = "WRONG DATA REQUESTED!";
}

echo json_encode($response);


class LibraryHandler
{
	private $s3;
	private $bucket = 'codebender-testing';
	
	function __construct() 
	{
		$this->s3 = new AmazonS3();
	}
	
	public function getBuiltinExamples()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'arduino-files-static/examples', 'pcre' => '/\.ino/'));

		$response = $this->generateUrls($response);
		$response = $this->generateExamples($response, 3);
		return $response;
	}
	
	public function getIncludedExamples()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'arduino-files-static/libraries', 'pcre' => '/\.ino/'));

		$response = $this->generateUrls($response);
		$response = $this->generateExamples($response, 4);
		return $response;
	}

	public function getExternalExamples()
	{
		$response = $this->s3->get_object_list($this->bucket, array('prefix' => 'arduino-files-static/extra-libraries', 'pcre' => '/(\.ino|\.pde)/i'));

		$response = $this->generateUrls($response);
		$response = $this->generateExamples($response, 4);
		return $response;
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
		foreach($examples as &$example)
		{
			$array = explode("/", $example["path"]);
			// $example = array("category" => $array[2], "name" => $array[$cat_no], "url" => $example["url"]);
			$list[$array[2]] = array_merge((array) $list[$array[2]], array(array("name" => $array[$cat_no], "url" => $example["url"])));
			// var_dump($example);
		}

		return $list;
	}
	
}

?>

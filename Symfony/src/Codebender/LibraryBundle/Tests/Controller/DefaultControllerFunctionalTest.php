<?php

namespace Codebender\LibraryBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
	public function testStatus()
	{
		$client = static::createClient();

		$client->request('GET', '/status');

		$this->assertEquals($client->getResponse()->getContent(), '{"success":true,"status":"OK"}');

	}

	public function testInvalidKey()
	{
		$client = static::createClient();

		$client->request('GET', '/inValidKey/v1');

		$this->assertEquals($client->getResponse()->getContent(), '{"success":false,"step":0,"message":"Invalid authorization key."}');

	}

	public function testInvalidAPI()
	{
		$client = static::createClient();

		$auth_key = $client->getContainer()->getParameter("auth_key");

		$client->request('GET', '/'.$auth_key.'/v666');

		$this->assertEquals($client->getResponse()->getContent(), '{"success":false,"step":0,"message":"Invalid API version."}');

	}

	public function testList()
	{
		$client = static::createClient();

		$auth_key = $client->getContainer()->getParameter("auth_key");

		$client->request('GET', '/'.$auth_key.'/v1');

		$response = $client->getResponse()->getContent();
		$response = json_decode($response, true);

		$this->assertArrayHasKey("success", $response);
		$this->assertTrue($response["success"]);

		$this->assertArrayHasKey("categories", $response);
		$categories = $response["categories"];

		$this->assertArrayHasKey("Examples", $categories);
		$this->assertNotEmpty($categories["Examples"]);

		$this->assertArrayHasKey("Builtin Libraries", $categories);
		$this->assertNotEmpty($categories["Builtin Libraries"]);

		$this->assertArrayHasKey("External Libraries", $categories);
		$this->assertNotEmpty($categories["External Libraries"]);

		$basic_examples = $categories["Examples"]["01.Basics"]["examples"];


		//Check for a specific, known example
		$example_found = false;
		foreach($basic_examples as $example)
		{
			if($example["name"] == "AnalogReadSerial")
			{
				$this->assertEquals($example["name"], "AnalogReadSerial");
				$this->assertEquals($example["filename"], "AnalogReadSerial.ino");
				$this->assertContains("get?file=01.Basics/AnalogReadSerial/AnalogReadSerial.ino", $example["url"]);
				$example_found = true;
			}
		}

		//Make sure the example was found
		$this->assertTrue($example_found);
	}

	public function testGetFile()
	{
		$client = static::createClient();

		$auth_key = $client->getContainer()->getParameter("auth_key");

		$client->request('GET', '/'.$auth_key.'/v1/get', array('file' => '01.Basics/AnalogReadSerial/AnalogReadSerial.ino'));

//		$response = $client->getResponse();
//		var_dump($response);

		$this->markTestIncomplete("This test is not ready, for some reason response has no output");
	}

	public function testIncorrectInputs()
	{
		$this->markTestIncomplete("No tests for invalid inputs yet");
	}
}

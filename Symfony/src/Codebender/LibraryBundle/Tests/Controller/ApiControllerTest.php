<?php

namespace Codebender\LibraryBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiControllerTest extends WebTestCase
{
    /**
     * This method tests the basic functionality of the API dispatcher
     */
    public function testInvalidApi()
    {
        // Successful call
        $response = $this->postApiType('status');
        $this->assertTrue($response['success']);

        // Unsuccessful call
        $response = $this->postApiType('noSuchApiExists');
        $this->assertFalse($response['success']);

        $response = $this->postApiType('98am(DW*340D(#*5$%');
        $this->assertFalse($response['success']);
    }

    /**
     * Test getting external example code for getExampleCode API
     */
    public function testGetExternalExampleCode()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"SubCateg", "version":"1.0.0", "example":"subcateg_example_one"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals('subcateg_example_one.ino', $response['files'][0]['filename']);
        $this->assertContains('void setup()', $response['files'][0]['code']);

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"SubCateg", "version":"1.0.0", "example":"experienceBased:Beginners:subcateg_example_two"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals('subcateg_example_two.ino', $response['files'][0]['filename']);
        $this->assertContains('void setup()', $response['files'][0]['code']);
    }

    /**
     * Test getting built-in library's example code for getExampleCode API
     */
    public function testGetBuiltInLibraryExampleCode()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"EEPROM", "example":"eeprom_read"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals('eeprom_read.ino', $response['files'][0]['filename']);
        $this->assertContains('Reads the value of each byte of the EEPROM', $response['files'][0]['code']);

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"WiFi", "example":"WiFiWebClient"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals('WiFiWebClient.ino', $response['files'][0]['filename']);
        $this->assertContains('This sketch connects to a website', $response['files'][0]['code']);
    }

    /**
     * Test getting built-in example code for getExampleCode API
     */
    public function testGetBuiltInExampleCode()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"09.USB", "example":"KeyboardAndMouseControl"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals('KeyboardAndMouseControl.ino', $response['files'][0]['filename']);
        $this->assertContains('Controls the mouse from five pushbuttons on an Arduino', $response['files'][0]['code']);

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"09.USB", "example":"Keyboard:KeyboardSerial"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals('KeyboardSerial.ino', $response['files'][0]['filename']);
        $this->assertContains('Reads a byte from the serial port, sends a keystroke', $response['files'][0]['code']);
    }

    /**
     * Test invalid request for getExampleCode API
     */
    public function testGetExampleCodeInvalidRequest()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // No library and example in request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Incorrect request fields', $response['message']);

        // No example in request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"EEPROM"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Incorrect request fields', $response['message']);

        // Invalid library in request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"NoSuchLibrary", "example":"NoSuchExample"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Requested library named NoSuchLibrary not found', $response['message']);

        // Invalid example of built-in library in request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"EEPROM", "example":"NoSuchExample"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Could not retrieve the requested example', $response['message']);

        // Invalid version of external library in request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"SubCateg", "version":"9.9.9", "example":"subcateg_example_one"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Requested library (version) does not exist', $response['message']);

        // Invalid example of external library in request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode", "library":"SubCateg", "version":"1.0.0", "example":"NoSuchExample"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Could not retrieve the requested example', $response['message']);
    }

    /**
     * Use this method for library manager API requests with POST data
     *
     * @param Client $client
     * @param string $authKey
     * @param string $data
     * @return Client
     */
    private function postApiRequest(Client $client, $authKey, $data)
    {
        $client->request(
            'POST',
            '/' . $authKey . '/v2',
            [],
            [],
            [],
            $data,
            true
        );

        return $client;
    }

    private function postApiType($type)
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"' .$type. '"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        return $response;
    }
}

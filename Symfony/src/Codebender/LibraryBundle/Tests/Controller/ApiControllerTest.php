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
     * Test for the getExamples API
     */
    public function testGetExternalLibraryExamples()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExamples", "version" : "1.0.0", "library" : "MultiIno"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('multi_ino_example:methods', $response['examples']);
        $this->assertArrayHasKey('multi_ino_example', $response['examples']);
    }

    /**
     * Test for getting built-in library's examples for getExamples API
     */
    public function testGetBuiltInLibraryExamples()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExamples", "library" : "EEPROM"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('eeprom_clear', $response['examples']);
        $this->assertArrayHasKey('eeprom_write', $response['examples']);
        $this->assertArrayHasKey('eeprom_read', $response['examples']);
    }

    /**
     * Test for getting built-in examples for getExamples API
     */
    public function testGetBuiltInExamples()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExamples", "library" : "01.Basics"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('Fade', $response['examples']);
        $this->assertArrayHasKey('AnalogReadSerial', $response['examples']);
        $this->assertArrayHasKey('BareMinimum', $response['examples']);
        $this->assertArrayHasKey('ReadAnalogVoltage', $response['examples']);
        $this->assertArrayHasKey('DigitalReadSerial', $response['examples']);
        $this->assertArrayHasKey('Blink', $response['examples']);
    }

    /**
     * Test for failure cases for getExamples API
     */
    public function testInvalidGetExamplesRequest()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // Invalid request data
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExamples"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Incorrect request fields', $response['message']);

        // Invalid library name
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExamples", "version" : "1.0.0", "library" : "NoSuchLib"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Requested library named NoSuchLib not found', $response['message']);

        // Invalid library version
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExamples", "version" : "9.9.9", "library" : "MultiIno"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Requested version for library MultiIno not found', $response['message']);
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

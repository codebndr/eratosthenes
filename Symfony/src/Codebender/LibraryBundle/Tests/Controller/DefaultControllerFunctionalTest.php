<?php

namespace Codebender\LibraryBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerFunctionalTest extends WebTestCase
{
    public function testStatus()
    {
        $client = static::createClient();

        $client->request('GET', '/status');

        $this->assertEquals('{"success":true,"status":"OK"}', $client->getResponse()->getContent());

    }

    public function testInvalidMethod()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request('GET', "/$authorizationKey/v1");

        $this->assertEquals(405, $client->getResponse()->getStatusCode());

    }

    public function testList()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request('POST', '/' . $authorizationKey . '/v1', [], [], [], '{"type":"list"}', true);

        $response = $client->getResponse()->getContent();
        $response = json_decode($response, true);

        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);

        $this->assertArrayHasKey('categories', $response);
        $categories = $response['categories'];

        $this->assertArrayHasKey('Examples', $categories);
        $this->assertNotEmpty($categories['Examples']);

        $this->assertArrayHasKey('Builtin Libraries', $categories);
        $this->assertNotEmpty($categories['Builtin Libraries']);

        $this->assertArrayHasKey('External Libraries', $categories);
        $this->assertNotEmpty($categories['External Libraries']);

        $basicExamples = $categories['Examples']['01.Basics']['examples'];


        // Check for a specific, known example
        $example_found = false;
        foreach ($basicExamples as $example) {
            if ($example['name'] == 'AnalogReadSerial') {
                $this->assertEquals($example['name'], 'AnalogReadSerial');
                $example_found = true;
            }
        }

        // Make sure the example was found
        $this->assertTrue($example_found);
    }

    public function testGetExampleCode()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"getExampleCode","library":"EEPROM","example":"eeprom_read"}',
            true)
        ;

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('eeprom_read.ino', $response['files'][0]['filename']);
        $this->assertContains('void setup()', $response['files'][0]['code']);
    }

    public function testGetExamples()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"getExamples","library":"EEPROM"}',
            true
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('eeprom_clear', $response['examples']);
        $this->assertArrayHasKey('eeprom_read', $response['examples']);
        $this->assertArrayHasKey('eeprom_write', $response['examples']);
    }

    public function testFetchLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"fetch","library":"EEPROM"}',
            true
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Library found', $response['message']);
        $this->assertEquals('EEPROM.cpp', $response['files'][0]['filename']);
        $this->assertEquals('EEPROM.h', $response['files'][1]['filename']);
        $this->assertEquals('keywords.txt', $response['files'][2]['filename']);
    }

    public function testCheckGithubUpdates()
    {
        $this->markTestIncomplete('Need to setup testing with Github credentials');
    }

    public function testGetKeywords()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"getKeywords","library":"EEPROM"}',
            true
        );

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('keywords', $response);
        $this->assertArrayHasKey('KEYWORD1', $response['keywords']);
        $this->assertEquals('EEPROM', $response['keywords']['KEYWORD1'][0]);
    }

}

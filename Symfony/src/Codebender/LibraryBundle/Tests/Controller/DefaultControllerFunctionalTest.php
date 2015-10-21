<?php

namespace Codebender\LibraryBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class DefaultControllerFunctionalTest
 * @package Codebender\LibraryBundle\Tests\Controller
 * @SuppressWarnings(PHPMD)
 */
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
        $foundExample = array_filter($basicExamples, function($element) {
            if ($element['name'] == 'AnalogReadSerial') {
                return true;
            }
        });

        // Make sure the example was found
        $this->assertEquals('AnalogReadSerial', $foundExample[0]['name']);
    }


    public function testGetFixtureLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"fetch","library":"default"}',
            true)
        ;

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);

        /*
         * default.cpp file is supposed to come before default.h
         */
        $this->assertEquals('default.cpp', $response['files'][0]['filename']);
        $this->assertEquals('default.h', $response['files'][1]['filename']);

        $baseLibraryPath = $client->getKernel()->locateResource('@CodebenderLibraryBundle/Resources/library_files/default');
        $this->assertEquals(
            file_get_contents($baseLibraryPath . '/default.cpp'),
            $response['files'][0]['content']
        );

        $this->assertEquals(
            file_get_contents($baseLibraryPath . '/default.h'),
            $response['files'][1]['content']
        );
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
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"checkGithubUpdates"}',
            true
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('1 external libraries need to be updated', $response['message']);
        /*
         * DynamicArrayHelper library's last commit is not the same as its origin.
         */
        $this->assertEquals('DynamicArrayHelper', $response['libraries'][0]['Machine Name']);

        /*
         * Disabling the library should make it not be returned in the list.
         */
        $client->request('POST', '/' . $authorizationKey . '/toggleStatus/DynamicArrayHelper');
        $client->request(
            'POST',
            '/' . $authorizationKey . '/v1',
            [],
            [],
            [],
            '{"type":"checkGithubUpdates"}',
            true
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('No external libraries need to be updated', $response['message']);

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

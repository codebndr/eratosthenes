<?php

namespace Codebender\LibraryBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
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

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"list"}');

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

        $this->assertArrayHasKey('url', $categories['External Libraries']['MultiIno']);
        $this->assertArrayHasKey('url', $categories['External Libraries']['default']);

        // Check for a specific, known example
        $foundExample = array_filter($basicExamples, function($element) {
            if ($element['name'] == 'AnalogReadSerial') {
                return true;
            }
            return false;
        });

        $foundExample = array_values($foundExample);

        // Make sure the example was found
        $this->assertEquals('AnalogReadSerial', $foundExample[0]['name']);
    }


    public function testGetFixtureLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"default"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);

        $filenames = array_column($response['files'], 'filename');

        $this->assertContains('default.cpp', $filenames);
        $this->assertContains('default.h', $filenames);
        $this->assertContains('inc_file.inc', $filenames);
        $this->assertContains('hpp_file.hpp', $filenames);
        $this->assertContains('assembly_file.S', $filenames);

        $baseLibraryPath = $client->getKernel()->locateResource('@CodebenderLibraryBundle/Resources/library_files/default');

        $contents = array_column($response['files'], 'content');

        $this->assertContains(file_get_contents($baseLibraryPath . '/default.cpp'), $contents);

        $this->assertContains(file_get_contents($baseLibraryPath . '/default.h'), $contents);

    }

    public function testGetExampleCode()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getExampleCode","library":"default","example":"example_one"}'
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('example_one.ino', $response['files'][0]['filename']);
        $this->assertContains('void setup()', $response['files'][0]['code']);
    }

    public function testGetExamples()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getExamples","library":"SubCateg"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('subcateg_example_one', $response['examples']);
        $this->assertArrayHasKey('experienceBased:Beginners:subcateg_example_two', $response['examples']);
        $this->assertArrayHasKey('experienceBased:Advanced:Experts:subcateg_example_three', $response['examples']);
    }

    public function testFetchLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"EEPROM"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Library found', $response['message']);

        $filenames = array_column($response['files'], 'filename');
        $this->assertContains('EEPROM.cpp', $filenames);
        $this->assertContains('EEPROM.h', $filenames);
        $this->assertContains('keywords.txt', $filenames);

    }

    public function testCheckGithubUpdates()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"checkGithubUpdates"}');

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
        $handler = $this->getService('codebender_library.apiHandler');
        $handler->toggleLibraryStatus('DynamicArrayHelper');
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"checkGithubUpdates"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('No external libraries need to be updated', $response['message']);

        $handler->toggleLibraryStatus('DynamicArrayHelper');
    }

    public function testGetKeywords()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getKeywords","library":"EEPROM"}');

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('keywords', $response);
        $this->assertArrayHasKey('KEYWORD1', $response['keywords']);
        $this->assertEquals('EEPROM', $response['keywords']['KEYWORD1'][0]);
    }

    public function testMultiInoLibraryExampleFetching()
    {
        /*
         * Tests that the code and examples of the MultiIno library are fetched correctly
         */
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getExamples","library":"MultiIno"}');

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        /*
         * This example contains two ino files (which is valid on Arduino IDE).
         * The library manager fetches examples by searching for .ino or .pde files,
         * as a result we get two examples instead of one (and there's more).
         * Thus the test is marked as incomplete.
         */
        $this->assertArrayHasKey('multi_ino_example:methods', $response['examples']);
        $this->assertArrayHasKey('multi_ino_example', $response['examples']);

        $this->markTestIncomplete('Multi ino examples are not fetched correctly. Need to fix this.');
    }

    public function testLibraryExamplesWithSubcategories()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        /*
         * The keys of examples contained in subcategories should contain the name
         * of the subcategory each example belongs to (nesting level > 1 supported)
         */
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getExamples","library":"SubCateg"}');

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('subcateg_example_one', $response['examples']);
        $this->assertArrayHasKey('experienceBased:Beginners:subcateg_example_two', $response['examples']);
        $this->assertArrayHasKey('experienceBased:Advanced:Experts:subcateg_example_three', $response['examples']);
    }

    public function testLibraryWithHiddenFiles()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        /*
         * This library contains hidden files in its code and examples.
         * These hidden elements should not be sent to the client during
         * either library code or library examples fetching.
         */
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"Hidden"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(2, count($response['files']));
        $filenames = array_column($response['files'], 'filename');
        $this->assertContains('Hidden.h', $filenames);
        $this->assertContains('library.properties', $filenames);

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getExampleCode","library":"Hidden","example":"hidden_files_example"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, count($response['files']));
        $this->assertEquals('hidden_files_example.ino', $response['files'][0]['filename']);
    }

    public function testInvalidEncodingLibrary()
    {
        $client = static::createClient();

        $encodeLibraryPath = $client->getContainer()->getParameter('external_libraries') . '/Encode/';
        $headerFile = file_get_contents($encodeLibraryPath . 'Encode.h');
        $exampleFile = file_get_contents($encodeLibraryPath . 'examples/encoded_example/encoded_example.ino');
        $malformedJson = json_encode(['header' => $headerFile, 'example' => $exampleFile]);

        /*
         * PHP's json_encode expects its argument to be encoded using UTF-8.
         * Otherwise, it fails. The assertions below demonstrate how the original files
         * uploaded to the library manager will fail to encode, unless properly handled.
         */
        $this->assertFalse($malformedJson);
        $this->assertEquals('Malformed UTF-8 characters, possibly incorrectly encoded', json_last_error_msg());

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"Encode"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        /*
         * A successful response means the content of the library was properly handled.
         * However, any characters in the library files that (due to data loss) cannot
         * be converted to UTF-8 will be represented by irrelevant UTF-8 characters.
         */
        $this->assertTrue($response['success']);
        $this->assertContains('åëëçíéêÜ', $response['files'][0]['content']); // misinterpreted characters
        $this->assertContains('This file uses Greek (ISO 8859-7)', $response['files'][0]['content']);

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getExampleCode","library":"Encode","example":"encoded_example"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        /*
         * The same applies to the example code fetching
         */
        $this->assertTrue($response['success']);
        $this->assertContains('åëëçíéêÜ', $response['files'][0]['code']); // misinterpreted characters
        $this->assertContains('using Greek (ISO 8859-7) encoding', $response['files'][0]['code']);
    }

    public function testHtmlLibraryContents()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"HtmlLib"}');
        $response = json_decode($client->getResponse()->getContent(), true);

        /*
         * HTML contents successfully JSON encoded-decoded
         * Won't verify their content
         */
        $this->assertTrue($response['success']);
    }

    public function testLibraryWithNonTextFiles()
    {
        /*
         * Demonstrates how non-text library files are replaced by a comment
         */
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"Binary"}');
        $response = json_decode($client->getResponse()->getContent(), true);

        /*
         * This library contains several non-text files such as .exe and .zip files
         */
        $this->assertTrue($response['success']);

        $filenames = array_column($response['files'], 'filename');

        $this->assertContains('file.zip', $filenames);
        $this->assertContains('icon48.png', $filenames);
        $this->assertContains('windows_executable.exe', $filenames);

        foreach ($response['files'] as $file) {
            if (in_array($file['filename'], ['file.zip', 'icon48.png', 'windows_executable.exe'])) {
                $this->assertContains('Such files are currently not supported', $file['content']);
            }
        }

    }

    public function testLibraryExamplesWithNonTextFiles()
    {
        $this->markTestIncomplete('Not implemented yet. Non-text files of examples are not handled properly');
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
            '/' . $authKey . '/v1',
            [],
            [],
            [],
            $data,
            true
        );

        return $client;
    }

    /**
     * This method returns a given service from its name.
     *
     * @param $service
     * @return the requested service
     */
    private function getService($service)
    {
        $kernel = static::createKernel();
        $kernel->boot();

        $container = $kernel->getContainer();
        return $container->get($service);
    }
}

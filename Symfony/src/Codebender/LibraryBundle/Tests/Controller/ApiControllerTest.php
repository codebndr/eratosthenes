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

        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest($client, $authorizationKey, '{}'); // no request data
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
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

        $basicExamples = $categories['Examples']['01.Basics']['examples'];

        // Check for a specific, known example
        $foundExample = array_filter($basicExamples, function($element) {
            if ($element['name'] == 'AnalogReadSerial') {
                return true;
            }
        });

        $foundExample = array_values($foundExample);

        // Make sure the example was found
        $this->assertEquals('AnalogReadSerial', $foundExample[0]['name']);

        $this->assertArrayHasKey('External Libraries', $categories);
        $this->assertNotEmpty($categories['External Libraries']);

        $this->assertArrayHasKey('1.0.0', $categories['External Libraries']['MultiIno']);
        $this->assertArrayHasKey('2.0.0', $categories['External Libraries']['MultiIno']);

        $this->assertTrue(in_array('multi_ino_example', $categories['External Libraries']['MultiIno']['1.0.0']['examples']));
    }

    /**
     * This method tests the getVersions API.
     */
    public function testGetVersions()
    {
        // Test successful getVersions calls
        $this->assertSuccessfulGetVersions('default', ['1.0.0', '1.1.0']);
        $this->assertSuccessfulGetVersions('DynamicArrayHelper', ['1.0.0']);
        $this->assertSuccessfulGetVersions('HtmlLib', []);

        // Test invalid getVersions calls
        $this->assertFailedGetVersions('nonExistentLib');
        $this->assertFailedGetVersions('');
        $this->assertFailedGetVersions(null);
    }
    
    /**
     * This method test the getKeywords API.
     */
    public function testGetKeywords()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getKeywords", "library":"EEPROM"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('keywords', $response);
        $this->assertArrayHasKey('KEYWORD1', $response['keywords']);
        $this->assertEquals('EEPROM', $response['keywords']['KEYWORD1'][0]);

        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getKeywords", "library":"default", "version" : "1.0.0"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('keywords', $response);
    }

    /**
     * This method test the failure cases of getKeywords API.
     */
    public function testGetKeywordsInvalidRequest()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // no library in the request
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getKeywords"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Incorrect request fields', $response['message']);

        // library version not found
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getKeywords", "library" : "noSuchLib", "version": "1.0.0"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Version 1.0.0 of library named noSuchLib not found.', $response['message']);

        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getKeywords", "library" : "default", "version": "9.9.9"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Version 9.9.9 of library named default not found.', $response['message']);

        // unsupported library
        $client = $this->postApiRequest(
            $client,
            $authorizationKey,
            '{"type":"getKeywords", "library" : "01.Basics"}'
        );
        $response = json_decode($client->getResponse()->getContent(), true);
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

    public function testFetchApiCommand()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"default","version":"1.1.0"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Library found', $response['message']);

        $this->assertArrayHasKey('1.1.0', $response['files']);

        $filenames = array_column($response['files']['1.1.0'], 'filename');
        $this->assertContains('default.cpp', $filenames);
        $this->assertContains('default.h', $filenames);
        $this->assertContains('inc_file.inc', $filenames);
        $this->assertContains('assembly_file.S', $filenames);
    }

    /**
     * This method tests the checkGithubUpdates API.
     */
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
    }

    /**
     * This method tests the authentication mechanism of Eratosthenes
     */
    public function testApiAuthentication()
    {
        $client = static::createClient();
        $invalidAuthorizationKey = 'thisIsAnInvalidAuthorizationKey';
        $validV1AuthorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $validV2AuthorizationKey = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Partner')
            ->findOneBy(['name' => 'arduino.cc'])
            ->getAuthKey();
        $this->assertFailAuthorization($invalidAuthorizationKey);
        $this->assertPassAuthorization($validV1AuthorizationKey);
        $this->assertPassAuthorization($validV2AuthorizationKey);
    }

    private function assertFailAuthorization($authorizationKey) {
        $client = static::createClient();
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"status"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(false, $response['success']);
    }

    private function assertPassAuthorization($authorizationKey) {
        $client = static::createClient();
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"status"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(true, $response['success']);
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

    /**
     * This method submits a POST request to the getVersions API
     * and returns its response.
     *
     * @param $defaultHeader
     * @return $response
     */
    private function postGetVersionsApi($defaultHeader)
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"getVersions","library":"' . $defaultHeader . '"}');
        $response = json_decode($client->getResponse()->getContent(), true);
        return $response;
    }

    /**
     * This method checks if the response of a single getVersions
     * API call is successful and returns the correct versions.
     *
     * @param $defaultHeader
     * @param $expectedVersions
     */
    private function assertSuccessfulGetVersions($defaultHeader, $expectedVersions)
    {
        $response = $this->postGetVersionsApi($defaultHeader);
        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('versions', $response);
        $this->assertTrue($this->areSimilarArrays($expectedVersions, $response['versions']));
    }

    /**
     * This method checks if the response of a single getVersions
     * API call is unsuccessful.
     *
     * @param $defaultHeader
     */
    private function assertFailedGetVersions($defaultHeader)
    {
        $response = $this->postGetVersionsApi($defaultHeader);
        $this->assertEquals(false, $response['success']);
    }

    /**
     * This method checks if two arrays, $array1 and $array2,
     * has the same elements.
     *
     * @param $array1
     * @param $array2
     * @return bool
     */
    private function areSimilarArrays($array1, $array2)
    {
        $arrayDiff1 = array_diff($array1, $array2);
        $arrayDiff2 = array_diff($array2, $array1);
        $totalDifferences = array_merge($arrayDiff1, $arrayDiff2);

        return empty($totalDifferences);
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

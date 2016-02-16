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

    public function testFetchApiCommand()
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"fetch","library":"default","version":"1.1.0"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Library found', $response['message']);

        $filenames = array_column($response['files'], 'filename');
        $this->assertContains('default.cpp', $filenames);
        $this->assertContains('default.h', $filenames);
        $this->assertContains('inc_file.inc', $filenames);
        $this->assertContains('assembly_file.S', $filenames);
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

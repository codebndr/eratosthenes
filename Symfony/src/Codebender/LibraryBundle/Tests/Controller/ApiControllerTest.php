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
        $handler = $this->getService('codebender_api.checkGithubUpdates');
        $handler->toggleLibraryStatus('DynamicArrayHelper');
        $client = $this->postApiRequest($client, $authorizationKey, '{"type":"checkGithubUpdates"}');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('No external libraries need to be updated', $response['message']);
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

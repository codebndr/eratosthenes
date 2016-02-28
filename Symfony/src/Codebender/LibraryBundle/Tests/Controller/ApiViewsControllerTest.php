<?php

namespace Codebender\LibraryBundle\Tests\Controller;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class ApiViewsControllerTest
 * @package Codebender\LibraryBundle\Tests\Controller
 * @SuppressWarnings(PHPMD)
 */
class ApiViewsControllerTest extends WebTestCase
{

    public function testViewFixtureLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        /*
         * Default library is already enabled, disable it and try to view it (should fail)
         */
        $client->request('POST', '/' . $authorizationKey . '/v2/toggleStatus/default');

        $this->assertEquals('{"success":true}', $client->getResponse()->getContent());

        $client->request('GET', '/' . $authorizationKey . '/v2/view?library=default');

        $this->assertEquals(
            '{"success":false,"message":"No Library named default found."}',
            $client->getResponse()->getContent()
        );

        /*
         * Get the response with flag `disabled=1` (should view the library, although it's disabled)
         */
        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/view?library=default&disabled=1');
        $this->assertEquals(1, $crawler->filter('h2:contains("Default Arduino Library")')->count());
        $this->assertEquals(
            1,
            $crawler->filter('button:contains("Library disabled on codebender. Click to enable.")')->count()
        );

        /*
         * Enable default library again
         */
        $client->request('POST', '/' . $authorizationKey . '/v2/toggleStatus/default');

        $this->assertEquals('{"success":true}', $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/view?library=default');

        $this->assertEquals(1, $crawler->filter('h2:contains("Default Arduino Library")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("(main header: default.h)")')->count());

        // check for versions
        $this->assertEquals(1, $crawler->filter('a.collapsed:contains("Version - 1.0.0")')->count());
        $this->assertEquals(1, $crawler->filter('a.collapsed:contains("Version - 1.1.0")')->count());
        $this->assertEquals(
            1,
            $crawler->filter('button:contains("Library enabled on codebender. Click to disable.")')->count()
        );

        /*
         * Test the source url of the library is as expected.
         */
        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="http://localhost/library/url"]:contains("Default Arduino Library is hosted here")')->count()
        );

        $this->assertEquals(
            1,
            $crawler->filter('p:contains("The default Arduino library (in fact it\'s Adafruit\'s GPS library)")')->count()
        );

        $this->assertEquals(
            1,
            $crawler->filter('p:contains("No notes provided for this library")')->count()
        );

        $this->assertEquals(
            1,
            $crawler->filter('p:contains("Showing 2 version(s)")')->count()
        );

        // Check for two versions
        $this->assertEquals(
            2,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/v2/download/default/1.0.0"]')->count()
        );

        $this->assertEquals(
            2,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/v2/download/default/1.1.0"]')->count()
        );

        $this->assertEquals(2, $crawler->filter('a.collapsed:contains("default.cpp")')->count());
        $this->assertEquals(2, $crawler->filter('a.collapsed:contains("default.h")')->count());
        $this->assertEquals(2, $crawler->filter('a.collapsed:contains("example_one.ino")')->count());
        $this->assertEquals(2, $crawler->filter('h3:contains("Files:")')->count());
        $this->assertEquals(2, $crawler->filter('h3:contains("Examples (1 found): ")')->count());
    }

}

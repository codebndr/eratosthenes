<?php

namespace Codebender\LibraryBundle\Tests\Controller;


use Codebender\LibraryBundle\Entity\Version;
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

    public function testAddGitLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/new');
        /*
         * Need to get the CSRF token from the crawler and submit it with the form,
         * otherwise the form might be invalid.
         */
        $token = $crawler->filter('input[id="newLibrary__token"]')->attr('value');

        /*
         * Fill in the form values and submit the form
         */
        $form = $crawler->selectButton('Go')->form();
        $values = [
            'newLibrary[GitOwner]' => 'codebendercc',
            'newLibrary[GitRepo]' => 'WebSerial',
            'newLibrary[GitBranch]' => 'master',
            'newLibrary[GitPath]' => 'WebSerial',
            'newLibrary[Name]' => 'WebSerial Arduino Library',
            'newLibrary[DefaultHeader]' => 'WebSerial',
            'newLibrary[Description]' => 'Arduino WebSerial Library',
            'newLibrary[Url]' => 'https://github.com/codebendercc/webserial',
            'newLibrary[Version]' => '1.0.0',
            'newLibrary[VersionDescription]' => 'The very first version',
            'newLibrary[SourceUrl]' => 'https://github.com/codebendercc/WebSerial/archive/master.zip',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /*
         * Since this is an integration test, the library will actually be downloaded
         * from Github. Then, we can make sure all the data is properly stored in the database,
         * and the files have been saved in the filesystem
         */
        /* @var \Codebender\LibraryBundle\Entity\Library $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findOneBy(['default_header' => 'WebSerial']);

        $this->assertEquals('codebendercc', $libraryEntity->getOwner());
        $this->assertEquals('WebSerial Arduino Library', $libraryEntity->getName());
        $this->assertEquals('master', $libraryEntity->getBranch());
        $this->assertEquals('WebSerial', $libraryEntity->getDefaultHeader());
        $this->assertEquals('', $libraryEntity->getInRepoPath());
        $this->assertEquals('https://github.com/codebendercc/webserial', $libraryEntity->getUrl());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals('Arduino WebSerial Library', $libraryEntity->getDescription());
        $this->assertEquals('WebSerial', $libraryEntity->getRepo());
        $this->assertEquals('', $libraryEntity->getNotes());
        /*
         * No need to check the validity of the last commit here,
         * another test does that.
         */
        $this->assertNotEquals('', $libraryEntity->getLastCommit());

        /*
         * Check that the version attributes are correctly set
         * TODO: Check version notes
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $versionEntity */
        $versionEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Version')
            ->findOneBy(['library' => $libraryEntity, 'version' => '1.0.0']);
        $this->assertEquals('The very first version', $versionEntity->getDescription());
        $this->assertEquals(
            'https://github.com/codebendercc/WebSerial/archive/master.zip',
            $versionEntity->getSourceUrl()
        );

        /*
         * Check the examples' metadata have been stored correctly in the database
         */
        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['name' => 'WebASCIITable']);
        $this->assertEquals($libraryEntity, $example->getVersion()->getLibrary());
        $this->assertEquals('examples/WebASCIITable/WebASCIITable.ino', $example->getPath());

        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['name' => 'WebSerialEcho']);
        $this->assertEquals($libraryEntity, $example->getVersion()->getLibrary());
        $this->assertEquals('examples/WebSerialEcho/WebSerialEcho.ino', $example->getPath());


        /*
         * Check the files of the library have been stored on the filesystem.
         * TODO: Add a test for the validity of the files' contents.
         */
        $externalLibrariesPath = $client->getContainer()->getParameter('external_libraries_new');
        $libraryFolderName = $libraryEntity->getFolderName();
        $versionFolderName = $example->getVersion()->getFolderName();
        $versionPath = $externalLibrariesPath . '/' . $libraryFolderName . '/' . $versionFolderName . '/';
        $this->assertTrue(file_exists($versionPath . 'README.md'));
        $this->assertTrue(file_exists($versionPath . 'WebSerial.cpp'));
        $this->assertTrue(file_exists($versionPath . 'WebSerial.h'));
        $this->assertTrue(file_exists($versionPath . 'examples/WebASCIITable/WebASCIITable.ino'));
        $this->assertTrue(file_exists($versionPath . 'examples/WebSerialEcho/WebSerialEcho.ino'));
    }

    public function testSearchExternalLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // Get HTML response
        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/search?q=default');
        $this->assertEquals(1, $crawler->filter('a:contains("default")')->count());

        // Get JSON response
        $client->request('GET', '/' . $authorizationKey . '/v2/search?q=default&json=true');
        $this->assertEquals(
            '{"success":true,"libs":["default"]}',
            $client->getResponse()->getContent()
        );
    }

    public function testDownloadLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request('GET', '/' . $authorizationKey . '/v2/download/default/1.0.0');

        $this->assertEquals($client->getResponse()->headers->get('content-type'), 'application/octet-stream');
        $this->assertEquals($client->getResponse()->headers->get('content-disposition'), 'attachment;filename="default.zip"');
    }
}

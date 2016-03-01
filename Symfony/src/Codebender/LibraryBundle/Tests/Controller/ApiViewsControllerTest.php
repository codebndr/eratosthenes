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
            'newLibrary[Notes]' => 'Some notes about Arduino WebSerial Library',
            'newLibrary[Url]' => 'https://github.com/codebendercc/webserial',
            'newLibrary[Version]' => '1.0.0',
            'newLibrary[VersionDescription]' => 'The very first version',
            'newLibrary[VersionNotes]' => 'Some notes about Arduino WebSerial v1.0.0',
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
        $this->assertEquals('Some notes about Arduino WebSerial Library', $libraryEntity->getNotes());
        $this->assertEquals('WebSerial', $libraryEntity->getRepo());
        /*
         * No need to check the validity of the last commit here,
         * another test does that.
         */
        $this->assertNotEquals('', $libraryEntity->getLastCommit());

        /*
         * Check that the version attributes are correctly set
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $versionEntity */
        $versionEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Version')
            ->findOneBy(['library' => $libraryEntity, 'version' => '1.0.0']);
        $this->assertEquals('The very first version', $versionEntity->getDescription());
        $this->assertEquals('Some notes about Arduino WebSerial v1.0.0', $versionEntity->getNotes());
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
        $versionFolderName = $versionEntity->getFolderName();
        $versionPath = $externalLibrariesPath . '/' . $libraryFolderName . '/' . $versionFolderName . '/';
        $this->assertTrue(file_exists($versionPath . 'README.md'));
        $this->assertTrue(file_exists($versionPath . 'WebSerial.cpp'));
        $this->assertTrue(file_exists($versionPath . 'WebSerial.h'));
        $this->assertTrue(file_exists($versionPath . 'examples/WebASCIITable/WebASCIITable.ino'));
        $this->assertTrue(file_exists($versionPath . 'examples/WebSerialEcho/WebSerialEcho.ino'));
    }

    public function testAddGitRelease()
    {
        $this->addWebSerialRelease('WebSerialRelease', 'v1.0.0');

        /*
         * Since this is an integration test, the library will actually be downloaded
         * from Github. Then, we can make sure all the data is properly stored in the database,
         * and the files have been saved in the filesystem
         */
        /* @var \Codebender\LibraryBundle\Entity\Library $libraryEntity */
        $client = static::createClient();
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findOneBy(['default_header' => 'WebSerialRelease']);

        $this->assertEquals('nus-fboa2016-CB', $libraryEntity->getOwner());
        $this->assertEquals('WebSerial Arduino Library', $libraryEntity->getName());
        $this->assertEquals('master', $libraryEntity->getBranch());
        $this->assertEquals('WebSerialRelease', $libraryEntity->getDefaultHeader());
        $this->assertEquals('', $libraryEntity->getInRepoPath());
        $this->assertEquals('https://github.com/nus-fboa2016-CB/WebSerial', $libraryEntity->getUrl());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals('Arduino WebSerial Library', $libraryEntity->getDescription());
        $this->assertEquals('Some notes about Arduino WebSerial Library', $libraryEntity->getNotes());
        $this->assertEquals('WebSerial', $libraryEntity->getRepo());
        $this->assertEquals('2dd7838fe42d36ea9b322e731fd654a6b0f176de', $libraryEntity->getLastCommit());

        /*
         * Check that the version attributes are correctly set
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $versionEntity */
        $versionEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Version')
            ->findOneBy(['library' => $libraryEntity, 'version' => 'v1.0.0']);
        $this->assertEquals('WebSerial v1.0.0', $versionEntity->getDescription());
        $this->assertEquals('Some notes about Arduino WebSerial v1.0.0', $versionEntity->getNotes());
        $this->assertEquals(
            'https://api.github.com/repos/nus-fboa2016-CB/WebSerial/zipball/v1.0.0',
            $versionEntity->getSourceUrl()
        );

        /*
         * Check the examples' metadata have been stored correctly in the database
         */
        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['version' => $versionEntity, 'name' => 'WebASCIITable']);
        $this->assertEquals($libraryEntity, $example->getVersion()->getLibrary());
        $this->assertEquals('examples/WebASCIITable/WebASCIITable.ino', $example->getPath());

        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['version' => $versionEntity, 'name' => 'WebSerialEcho']);
        $this->assertEquals($libraryEntity, $example->getVersion()->getLibrary());
        $this->assertEquals('examples/WebSerialEcho/WebSerialEcho.ino', $example->getPath());


        /*
         * Check the files of the library have been stored on the filesystem.
         * TODO: Add a test for the validity of the files' contents.
         */
        $externalLibrariesPath = $client->getContainer()->getParameter('external_libraries_new');
        $libraryFolderName = $libraryEntity->getFolderName();
        $versionFolderName = $versionEntity->getFolderName();
        $versionPath = $externalLibrariesPath . '/' . $libraryFolderName . '/' . $versionFolderName . '/';
        $this->assertTrue(file_exists($versionPath . 'README.md'));
        $this->assertTrue(file_exists($versionPath . 'WebSerial.cpp'));
        $this->assertTrue(file_exists($versionPath . 'WebSerial.h'));
        $this->assertTrue(file_exists($versionPath . 'examples/WebASCIITable/WebASCIITable.ino'));
        $this->assertTrue(file_exists($versionPath . 'examples/WebSerialEcho/WebSerialEcho.ino'));
    }

    public function testUpdateLastCommit()
    {
        /*
         * Add the older release, then the newer one.
         * lastCommit should be the commit of the latest release (i.e. v1.1.0)
         */
        $defaultHeader = 'WebSerialUpdate1';
        $release1 = 'v1.0.0';
        $release2 = 'v1.1.0';
        $this->addWebSerialRelease($defaultHeader, $release1);
        $this->addWebSerialRelease($defaultHeader, $release2);

        $client = static::createClient();
        $libraryRepo = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Library');

        /* @var \Codebender\LibraryBundle\Entity\Library $libraryEntity */
        $libraryEntity = $libraryRepo->findOneBy(['default_header' => $defaultHeader]);
        $this->assertEquals('06f083efbc9226e13319691ddc202f686d07c118', $libraryEntity->getLastCommit());

        /*
         * Add the newer release before the older release.
         * lastCommit should still be the commit of the latest release (i.e. v1.1.0)
         */
        $defaultHeader = 'WebSerialUpdate2';
        $this->addWebSerialRelease($defaultHeader, $release2);
        $this->addWebSerialRelease($defaultHeader, $release1);

        $libraryEntity = $libraryRepo->findOneBy(['default_header' => $defaultHeader]);
        $this->assertEquals('06f083efbc9226e13319691ddc202f686d07c118', $libraryEntity->getLastCommit());
    }

    public function testAddZipLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/new');
        $token = $crawler->filter('input[id="newLibrary__token"]')->attr('value');

        $form = $crawler->selectButton('Go')->form();
        $zipFilePath = $client->getKernel()
            ->locateResource('@CodebenderLibraryBundle/Resources/zip_data/EMIC2.zip');

        /*
         * Symfony's way of uploading files to forms during tests.
         */
        $form['newLibrary[Zip]']->upload($zipFilePath);

        /*
         * Fill in the zip-upload related data and submit the form
         */
        $values = [
            'newLibrary[Name]' => 'EMIC2 Arduino Library',
            'newLibrary[DefaultHeader]' => 'EMIC2',
            'newLibrary[Description]' => 'An Arduino library for interfacing with Emic 2 Text-to-Speech modules.',
            'newLibrary[Notes]' => 'Some notes about EMIC2',
            'newLibrary[Url]' => 'https://github.com/pAIgn10/EMIC2',
            'newLibrary[Version]' => '1.0.0',
            'newLibrary[VersionDescription]' => 'The very first version',
            'newLibrary[VersionNotes]' => 'Some notes about EMIC2 v1.0.0',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /* @var \Codebender\LibraryBundle\Entity\Library $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findOneBy(['default_header' => 'EMIC2']);

        $this->assertEquals('EMIC2', $libraryEntity->getDefaultHeader());
        $this->assertEquals('EMIC2 Arduino Library', $libraryEntity->getName());
        $this->assertNull($libraryEntity->getOwner());
        $this->assertNull($libraryEntity->getRepo());
        $this->assertEmpty($libraryEntity->getInRepoPath());
        $this->assertNull($libraryEntity->getBranch());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals(
            'An Arduino library for interfacing with Emic 2 Text-to-Speech modules.',
            $libraryEntity->getDescription()
        );
        $this->assertEquals('Some notes about EMIC2', $libraryEntity->getNotes());
        $this->assertEquals('https://github.com/pAIgn10/EMIC2', $libraryEntity->getUrl());

        /*
         * Check that the version attributes are correctly set
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $versionEntity */
        $versionEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Version')
            ->findOneBy(['library' => $libraryEntity, 'version' => '1.0.0']);
        $this->assertEquals('The very first version', $versionEntity->getDescription());
        $this->assertEquals('Some notes about EMIC2 v1.0.0', $versionEntity->getNotes());
        $this->assertNull($versionEntity->getSourceUrl());

        /*
         * Check the examples' metadata have been stored correctly in the database
         */
        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['name' => 'SpeakMessage']);
        $this->assertEquals($libraryEntity, $example->getVersion()->getLibrary());
        $this->assertEquals('examples/SpeakMessage/SpeakMessage.ino', $example->getPath());

        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['name' => 'SpeakMsgFromSD']);
        $this->assertEquals($libraryEntity, $example->getVersion()->getLibrary());
        $this->assertEquals('examples/SpeakMsgFromSD/SpeakMsgFromSD.ino', $example->getPath());

        /*
         * Check the files of the library have been stored on the filesystem.
         * TODO: Add a test for the validity of the files' contents.
         */
        $externalLibrariesPath = $client->getContainer()->getParameter('external_libraries_new');
        $libraryFolderName = $libraryEntity->getFolderName();
        $versionFolderName = $versionEntity->getFolderName();
        $versionPath = $externalLibrariesPath . '/' . $libraryFolderName . '/' . $versionFolderName . '/';
        $this->assertTrue(file_exists($versionPath . 'README.md'));
        $this->assertTrue(file_exists($versionPath . 'EMIC2.cpp'));
        $this->assertTrue(file_exists($versionPath . 'EMIC2.h'));
        $this->assertTrue(file_exists($versionPath . 'keywords.txt'));
        $this->assertTrue(file_exists($versionPath . 'LICENSE'));
        $this->assertTrue(file_exists($versionPath . 'examples/SpeakMessage/SpeakMessage.ino'));
        $this->assertTrue(file_exists($versionPath . 'examples/SpeakMsgFromSD/SpeakMsgFromSD.ino'));
    }

    public function testAddGitLibraryFromSubfolder()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/new');
        $token = $crawler->filter('input[id="newLibrary__token"]')->attr('value');

        /*
         * Fill in the form values and submit the form
         */
        $form = $crawler->selectButton('Go')->form();
        $values = [
            'newLibrary[GitOwner]' => 'codebendercc',
            'newLibrary[GitRepo]' => 'arduino-library-files',
            'newLibrary[GitBranch]' => 'testing',
            'newLibrary[GitPath]' => 'arduino-library-files/libraries/EEPROM2',
            'newLibrary[Name]' => 'EEPROM2 Arduino Library',
            'newLibrary[DefaultHeader]' => 'EEPROM2',
            'newLibrary[Description]' => 'arduino files for use both by the compiler and the main symfony project',
            'newLibrary[Notes]' => 'Some notes about EEPROM2',
            'newLibrary[Version]' => '1.0.0',
            'newLibrary[VersionDescription]' => 'The very first version',
            'newLibrary[VersionNotes]' => 'Some notes about EEPROM2 v1.0.0',
            'newLibrary[Url]' => 'https://github.com/codebendercc/arduino-library-files',
            'newLibrary[SourceUrl]' => 'https://github.com/codebendercc/arduino-library-files/archive/testing.zip',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /* @var \Codebender\LibraryBundle\Entity\Library $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findOneBy(['default_header' => 'EEPROM2']);

        /*
         * Make sure the library metadata has correclty been stored in the database
         */
        $this->assertEquals('codebendercc', $libraryEntity->getOwner());
        $this->assertEquals('EEPROM2 Arduino Library', $libraryEntity->getName());
        $this->assertEquals('testing', $libraryEntity->getBranch());
        $this->assertEquals('EEPROM2', $libraryEntity->getDefaultHeader());
        $this->assertEquals('libraries/EEPROM2', $libraryEntity->getInRepoPath());
        $this->assertEquals('https://github.com/codebendercc/arduino-library-files', $libraryEntity->getUrl());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals(
            'arduino files for use both by the compiler and the main symfony project',
            $libraryEntity->getDescription()
        );
        $this->assertEquals('arduino-library-files', $libraryEntity->getRepo());
        $this->assertEquals('Some notes about EEPROM2', $libraryEntity->getNotes());
        $this->assertEquals('c5e3ae9847f77cdad9d1353b2ff838b09d0f5e66', $libraryEntity->getLastCommit());

        /*
         * Check that the version attributes are correctly set
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $versionEntity */
        $versionEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Version')
            ->findOneBy(['library' => $libraryEntity, 'version' => '1.0.0']);
        $this->assertEquals('The very first version', $versionEntity->getDescription());
        $this->assertEquals('Some notes about EEPROM2 v1.0.0', $versionEntity->getNotes());
        $this->assertEquals(
            'https://github.com/codebendercc/arduino-library-files/archive/testing.zip',
            $versionEntity->getSourceUrl()
        );

        /*
         * The same applies to the library example
         */
        /* @var \Codebender\LibraryBundle\Entity\LibraryExample $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:LibraryExample')
            ->findOneBy(['version' => $versionEntity]);
        $this->assertEquals('examples/eeprom2_clear/eeprom2_clear.ino', $example->getPath());
    }

    public function testLibraryWithFilesBiggerThanOneMegaByte()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/new');
        $token = $crawler->filter('input[id="newLibrary__token"]')->attr('value');

        /*
         * Fill in the form values and submit the form
         */
        $form = $crawler->selectButton('Go')->form();
        $values = [
            'newLibrary[GitOwner]' => 'codebendercc',
            'newLibrary[GitRepo]' => 'maxFileSize',
            'newLibrary[GitBranch]' => 'master',
            'newLibrary[GitPath]' => 'maxFileSize',
            'newLibrary[Name]' => 'Library with big files',
            'newLibrary[DefaultHeader]' => 'max_size',
            'newLibrary[Description]' => 'A repo used for testing fetching files with size > 1MB from Github API',
            'newLibrary[Version]' => '1.0.0',
            'newLibrary[VersionDescription]' => 'The very first version',
            'newLibrary[Url]' => 'https://github.com/codebendercc/maxFileSize',
            'newLibrary[SourceUrl]' => 'https://github.com/codebendercc/maxFileSize/archive/master.zip',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /* @var \Codebender\LibraryBundle\Entity\Library $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findOneBy(['default_header' => 'max_size']);

        /*
         * Make sure the library metadata has correclty been stored in the database
         */
        $this->assertEquals('codebendercc', $libraryEntity->getOwner());
        $this->assertEquals('Library with big files', $libraryEntity->getName());
        $this->assertEquals('master', $libraryEntity->getBranch());
        $this->assertEquals('max_size', $libraryEntity->getDefaultHeader());
        $this->assertEquals('', $libraryEntity->getInRepoPath());
        $this->assertEquals('https://github.com/codebendercc/maxFileSize', $libraryEntity->getUrl());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals(
            'A repo used for testing fetching files with size > 1MB from Github API',
            $libraryEntity->getDescription()
        );
        $this->assertEquals('maxFileSize', $libraryEntity->getRepo());
        $this->assertEquals('', $libraryEntity->getNotes());
        $this->assertEquals('4f8ca699e3be8d013a189fcbb79f1ceebc1b22ba', $libraryEntity->getLastCommit());

        /*
         * Check that the version attributes are correctly set
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $versionEntity */
        $versionEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Version')
            ->findOneBy(['library' => $libraryEntity, 'version' => '1.0.0']);
        $this->assertEquals('The very first version', $versionEntity->getDescription());
        $this->assertEquals('', $versionEntity->getNotes());
        $this->assertEquals(
            'https://github.com/codebendercc/maxFileSize/archive/master.zip',
            $versionEntity->getSourceUrl()
        );

        $filesAndExamples = [
            'code.cpp',
            'README.md',
            'logfile.log',
            'max_size.h'
        ];

        $externalLibrariesPath = $client->getContainer()->getParameter('external_libraries_new');
        $libraryFolderName = $libraryEntity->getFolderName();
        $versionFolderName = $versionEntity->getFolderName();
        $versionPath = $externalLibrariesPath . '/' . $libraryFolderName . '/' . $versionFolderName . '/';
        foreach ($filesAndExamples as $file) {
            $this->assertTrue(file_exists($versionPath . $file));
        }
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

    /**
     * This methods adds a WebSerial release with the given defaultHeader and release.
     *
     * @param $defaultHeader
     * @param $release
     */
    private function addWebSerialRelease($defaultHeader, $release) {
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
            'newLibrary[GitOwner]' => 'nus-fboa2016-CB',
            'newLibrary[GitRepo]' => 'WebSerial',
            'newLibrary[GitBranch]' => 'master',
            'newLibrary[GitRelease]' => $release,
            'newLibrary[GitPath]' => 'WebSerial',
            'newLibrary[Name]' => 'WebSerial Arduino Library',
            'newLibrary[DefaultHeader]' => $defaultHeader,
            'newLibrary[Description]' => 'Arduino WebSerial Library',
            'newLibrary[Notes]' => 'Some notes about Arduino WebSerial Library',
            'newLibrary[Url]' => 'https://github.com/nus-fboa2016-CB/WebSerial',
            'newLibrary[Version]' => $release,
            'newLibrary[VersionDescription]' => 'WebSerial ' . $release,
            'newLibrary[VersionNotes]' => 'Some notes about Arduino WebSerial ' . $release,
            'newLibrary[SourceUrl]' => 'https://api.github.com/repos/nus-fboa2016-CB/WebSerial/zipball/' . $release,
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);
    }

    public function testViewBuiltinExample()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/view?library=EEPROM');

        $this->assertEquals(1, $crawler->filter('h2:contains("EEPROM")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("main header: EEPROM.h")')->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/download/EEPROM"]:contains("Download from Eratosthenes")'
            )->count());

        $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("EEPROM.h")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("EEPROM.cpp")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("keywords.txt")')->count());

        $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("examples/eeprom_clear/eeprom_clear.ino")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("examples/eeprom_read/eeprom_read.ino")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("examples/eeprom_write/eeprom_write.ino")')->count());

    }

    public function testViewExternalZipLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // Using `disabled` flag because the library was not activated on upload
        $crawler = $client->request('GET', '/' . $authorizationKey . '/v2/view?library=EMIC2&disabled=1');

        $this->assertEquals(1, $crawler->filter('h2:contains("EMIC2 Arduino Library")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("main header: EMIC2.h")')->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/v2/download/EMIC2/1.0.0"]:contains("Version - 1.0.0")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="https://github.com/pAIgn10/EMIC2"]:contains("EMIC2 Arduino Library is hosted here")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'button[id="statusbutton"]:contains("Library disabled on codebender. Click to enable.")'
            )->count());

        $this->assertEquals(1, $crawler->filter('span:contains("Not a Github library (might need manual update)")')->count());

        $this->assertEquals(1, $crawler->filter('div[class="well"]:contains("An Arduino library for interfacing with Emic 2 Text-to-Speech modules.")')->count());

        $filesAndExamples = [
            'Version - 1.0.0',
            'EMIC2.cpp',
            'EMIC2.h',
            'keywords.txt',
            'README.md',
            'examples/SpeakMessage/SpeakMessage.ino',
            'examples/SpeakMsgFromSD/SpeakMsgFromSD.ino '
        ];

        foreach ($filesAndExamples as $file) {
            $this->assertEquals(1, $crawler->filter('a[class="collapsed"]:contains("' . $file . '")')->count());
        }
    }
}

<?php

namespace Codebender\LibraryBundle\Tests\Controller;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class ViewsControllerFunctionalTest
 * @package Codebender\LibraryBundle\Tests\Controller
 * @SuppressWarnings(PHPMD)
 */
class ViewsControllerFunctionalTest extends WebTestCase
{

    public function testViewFixtureLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        /*
         * Default library is already enabled, disable it and try to view it (should fail)
         */
        $client->request('POST', '/' . $authorizationKey . '/toggleStatus/default');

        $this->assertEquals('{"success":true}', $client->getResponse()->getContent());

        $client->request('GET', '/' . $authorizationKey . '/view?library=default');

        $this->assertEquals(
            '{"success":false,"message":"No Library named default found."}',
            $client->getResponse()->getContent()
        );

        /*
         * Get the response with flag `disabled=1` (should view the library, although it's disabled)
         */
        $crawler = $client->request('GET', '/' . $authorizationKey . '/view?library=default&disabled=1');
        $this->assertEquals(1, $crawler->filter('h2:contains("Default Arduino Library")')->count());

        /*
         * Enable default library again
         */
        $client->request('POST', '/' . $authorizationKey . '/toggleStatus/default');

        $this->assertEquals('{"success":true}', $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/' . $authorizationKey . '/view?library=default');

        $this->assertEquals(1, $crawler->filter('h2:contains("Default Arduino Library")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("(main header: default.h)")')->count());
        $this->assertEquals(
            1,
            $crawler->filter('button:contains("Library enabled on codebender. Click to disable.")'
            )->count());

        /*
         * Test the source url of the library is as expected.
         */
        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="http://localhost/library/url"]:contains("Default Arduino Library is hosted here")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/download/default"]:contains("Download from Eratosthenes")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter('p:contains("The default Arduino library (in fact it\'s Adafruit\'s GPS library)")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter('p:contains("No notes provided for this library")'
            )->count());

        $this->assertEquals(1, $crawler->filter('div[class="accordion-heading"]:contains("default.cpp")')->count());
        $this->assertEquals(1, $crawler->filter('div[class="accordion-heading"]:contains("default.h")')->count());
        $this->assertEquals(1, $crawler->filter('div[class="accordion-heading"]:contains("example_one.ino")')->count());

        $this->assertEquals(1, $crawler->filter('h3:contains("Files:")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("Examples (1 found): ")')->count());
    }

    public function testAddGitLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/new');
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
            'newLibrary[HumanName]' => 'WebSerial Arduino Library',
            'newLibrary[MachineName]' => 'WebSerial',
            'newLibrary[Description]' => 'Arduino WebSerial Library',
            'newLibrary[Url]' => 'https://github.com/codebendercc/webserial',
            'newLibrary[SourceUrl]' => 'https://github.com/codebendercc/WebSerial/archive/master.zip',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /*
         * Since this is an integration test, the library will actually be downloaded
         * from Github. Then, we can make sure all the data is properly stored in the database,
         * and the files have been saved in the filesystem
         */
        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:ExternalLibrary')
            ->findOneBy(['machineName' => 'WebSerial']);

        $this->assertEquals('codebendercc', $libraryEntity->getOwner());
        $this->assertEquals('WebSerial Arduino Library', $libraryEntity->getHumanName());
        $this->assertEquals('master', $libraryEntity->getBranch());
        $this->assertEquals('WebSerial', $libraryEntity->getMachineName());
        $this->assertEquals('', $libraryEntity->getInRepoPath());
        $this->assertEquals('https://github.com/codebendercc/webserial', $libraryEntity->getUrl());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals(
            'https://github.com/codebendercc/WebSerial/archive/master.zip',
            $libraryEntity->getSourceUrl()
        );
        $this->assertEquals('Arduino WebSerial Library', $libraryEntity->getDescription());
        $this->assertEquals('WebSerial', $libraryEntity->getRepo());
        $this->assertEquals('', $libraryEntity->getNotes());
        /*
         * No need to check the validity of the last commit here,
         * another test does that.
         */
        $this->assertNotEquals('', $libraryEntity->getLastCommit());

        /*
         * Check the examples' metadata have been stored correctly in the database
         */
        /* @var \Codebender\LibraryBundle\Entity\Example $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Example')
            ->findOneBy(['name' => 'WebASCIITable']);
        $this->assertEquals($libraryEntity, $example->getLibrary());
        $this->assertEquals('WebSerial/examples/WebASCIITable/WebASCIITable.ino', $example->getPath());

        /* @var \Codebender\LibraryBundle\Entity\Example $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Example')
            ->findOneBy(['name' => 'WebSerialEcho']);
        $this->assertEquals($libraryEntity, $example->getLibrary());
        $this->assertEquals('WebSerial/examples/WebSerialEcho/WebSerialEcho.ino', $example->getPath());


        /*
         * Check the files of the library have been stored on the filesystem.
         * TODO: Add a test for the validity of the files' contents.
         */
        $externalLibrariesPath = $client->getContainer()->getParameter('external_libraries') . '/';
        $this->assertTrue(file_exists($externalLibrariesPath . 'WebSerial/README.md'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'WebSerial/WebSerial.cpp'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'WebSerial/WebSerial.h'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'WebSerial/examples/WebASCIITable/WebASCIITable.ino'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'WebSerial/examples/WebSerialEcho/WebSerialEcho.ino'));
    }

    public function testAddZipLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/new');
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
            'newLibrary[HumanName]' => 'EMIC2 Arduino Library',
            'newLibrary[MachineName]' => 'EMIC2',
            'newLibrary[Description]' => 'An Arduino library for interfacing with Emic 2 Text-to-Speech modules.',
            'newLibrary[Url]' => 'https://github.com/pAIgn10/EMIC2',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:ExternalLibrary')
            ->findOneBy(['machineName' => 'EMIC2']);

        $this->assertEquals('EMIC2', $libraryEntity->getMachineName());
        $this->assertEquals('EMIC2 Arduino Library', $libraryEntity->getHumanName());
        $this->assertNull($libraryEntity->getOwner());
        $this->assertNull($libraryEntity->getRepo());
        $this->assertEmpty($libraryEntity->getInRepoPath());
        $this->assertNull($libraryEntity->getBranch());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertNull($libraryEntity->getSourceUrl());
        $this->assertEquals(
            'An Arduino library for interfacing with Emic 2 Text-to-Speech modules.',
            $libraryEntity->getDescription()
            );
        $this->assertEquals('', $libraryEntity->getNotes());
        $this->assertEquals('https://github.com/pAIgn10/EMIC2', $libraryEntity->getUrl());

        /*
         * Check the examples' metadata have been stored correctly in the database
         */
        /* @var \Codebender\LibraryBundle\Entity\Example $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Example')
            ->findOneBy(['name' => 'SpeakMessage']);
        $this->assertEquals($libraryEntity, $example->getLibrary());
        $this->assertEquals('EMIC2/examples/SpeakMessage/SpeakMessage.ino', $example->getPath());

        /* @var \Codebender\LibraryBundle\Entity\Example $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Example')
            ->findOneBy(['name' => 'SpeakMsgFromSD']);
        $this->assertEquals($libraryEntity, $example->getLibrary());
        $this->assertEquals('EMIC2/examples/SpeakMsgFromSD/SpeakMsgFromSD.ino', $example->getPath());

        /*
         * Check the files of the library have been stored on the filesystem.
         * TODO: Add a test for the validity of the files' contents.
         */
        $externalLibrariesPath = $client->getContainer()->getParameter('external_libraries') . '/';
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/README.md'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/EMIC2.cpp'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/EMIC2.h'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/keywords.txt'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/LICENSE'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/examples/SpeakMessage/SpeakMessage.ino'));
        $this->assertTrue(file_exists($externalLibrariesPath . 'EMIC2/examples/SpeakMsgFromSD/SpeakMsgFromSD.ino'));
    }

    public function testViewBuiltinExample()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/view?library=EEPROM');

        $this->assertEquals(1, $crawler->filter('h2:contains("EEPROM")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("main header: EEPROM.h")')->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/download/EEPROM"]:contains("Download from Eratosthenes")'
            )->count());

        $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("EEPROM.h")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("EEPROM.cpp")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("keywords.txt")')->count());

        $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("examples/eeprom_clear/eeprom_clear.ino")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("examples/eeprom_read/eeprom_read.ino")')->count());
        $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("examples/eeprom_write/eeprom_write.ino")')->count());

    }

    public function testViewExternalZipLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // Using `disabled` flag because the library was not activated on upload
        $crawler = $client->request('GET', '/' . $authorizationKey . '/view?library=EMIC2&disabled=1');

        $this->assertEquals(1, $crawler->filter('h2:contains("EMIC2 Arduino Library")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("main header: EMIC2.h")')->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/download/EMIC2"]:contains("Download from Eratosthenes")'
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
            'EMIC2.cpp',
            'EMIC2.h',
            'keywords.txt',
            'README.md',
            'examples/SpeakMessage/SpeakMessage.ino',
            'examples/SpeakMsgFromSD/SpeakMsgFromSD.ino '
        ];

        foreach ($filesAndExamples as $file) {
            $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("' . $file . '")')->count());
        }
    }

    public function testViewExternalGitLibrary()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        // Using `disabled` flag because the library was not activated on upload
        $crawler = $client->request('GET', '/' . $authorizationKey . '/view?library=WebSerial&disabled=1');

        $this->assertEquals(1, $crawler->filter('h2:contains("WebSerial Arduino Library")')->count());
        $this->assertEquals(1, $crawler->filter('h3:contains("main header: WebSerial.h")')->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="/' . $authorizationKey . '/download/WebSerial"]:contains("Download from Eratosthenes")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="https://github.com/codebendercc/webserial"]:contains("Github Repository")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'a[href="https://github.com/codebendercc/WebSerial/archive/master.zip"]:contains("WebSerial Arduino Library source code")'
            )->count());

        $this->assertEquals(
            1,
            $crawler->filter(
                'button[id="statusbutton"]:contains("Library disabled on codebender. Click to enable.")'
            )->count());

        $this->assertEquals(1, $crawler->filter('span:contains("In sync with master branch.")')->count());

        $this->assertEquals(1, $crawler->filter('div[class="well"]:contains("Arduino WebSerial Library")')->count());

        $filesAndExamples = [
            'WebSerial.cpp',
            'WebSerial.h',
            'README.md',
            'examples/WebASCIITable/WebASCIITable.ino',
            'examples/WebSerialEcho/WebSerialEcho.ino'
        ];

        foreach ($filesAndExamples as $file) {
            $this->assertEquals(1, $crawler->filter('a[class="accordion-toggle"]:contains("' . $file . '")')->count());
        }
    }

    public function testAddGitLibraryFromSubfolder()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $crawler = $client->request('GET', '/' . $authorizationKey . '/new');
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
            'newLibrary[HumanName]' => 'EEPROM2 Arduino Library',
            'newLibrary[MachineName]' => 'EEPROM2',
            'newLibrary[Description]' => 'arduino files for use both by the compiler and the main symfony project',
            'newLibrary[Url]' => 'https://github.com/codebendercc/arduino-library-files',
            'newLibrary[SourceUrl]' => 'https://github.com/codebendercc/arduino-library-files/archive/testing.zip',
            'newLibrary[_token]' => $token
        ];

        $client->submit($form, $values);

        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $libraryEntity */
        $libraryEntity = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:ExternalLibrary')
            ->findOneBy(['machineName' => 'EEPROM2']);

        /*
         * Make sure the library metadata has correclty been stored in the database
         */
        $this->assertEquals('codebendercc', $libraryEntity->getOwner());
        $this->assertEquals('EEPROM2 Arduino Library', $libraryEntity->getHumanName());
        $this->assertEquals('testing', $libraryEntity->getBranch());
        $this->assertEquals('EEPROM2', $libraryEntity->getMachineName());
        $this->assertEquals('libraries/EEPROM2', $libraryEntity->getInRepoPath());
        $this->assertEquals('https://github.com/codebendercc/arduino-library-files', $libraryEntity->getUrl());
        $this->assertFalse($libraryEntity->getActive());
        $this->assertFalse($libraryEntity->getVerified());
        $this->assertEquals(
            'https://github.com/codebendercc/arduino-library-files/archive/testing.zip',
            $libraryEntity->getSourceUrl()
        );
        $this->assertEquals(
            'arduino files for use both by the compiler and the main symfony project',
            $libraryEntity->getDescription()
        );
        $this->assertEquals('arduino-library-files', $libraryEntity->getRepo());
        $this->assertEquals('', $libraryEntity->getNotes());
        $this->assertEquals('c5e3ae9847f77cdad9d1353b2ff838b09d0f5e66', $libraryEntity->getLastCommit());

        /*
         * The same applies to the library example
         */
        /* @var \Codebender\LibraryBundle\Entity\Example $example */
        $example = $client->getContainer()->get('Doctrine')
            ->getRepository('CodebenderLibraryBundle:Example')
            ->findOneBy(['library' => $libraryEntity]);
        $this->assertEquals('EEPROM2/examples/eeprom2_clear/eeprom2_clear.ino', $example->getPath());
    }
}

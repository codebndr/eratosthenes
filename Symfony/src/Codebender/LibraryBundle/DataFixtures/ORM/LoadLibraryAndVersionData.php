<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\Version;

/**
 * Class LoadLibraryData
 * Provides default libraries data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures
 *
 * @SuppressWarnings(PHPMD)
 */
class LoadLibraryAndVersionData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for libraries objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        // A fake version of the Adafruit GPS library
        $defaultLibrary = new Library();
        $defaultLibrary->setName('Default Arduino Library');
        $defaultLibrary->setDefaultHeader('default');
        $defaultLibrary->setActive(true);
        $defaultLibrary->setVerified(false);
        $defaultLibrary->setDescription('The default Arduino library (in fact it\'s Adafruit\'s GPS library)');
        $defaultLibrary->setNotes('No notes provided for this library');
        $defaultLibrary->setUrl('http://localhost/library/url');
        $defaultLibrary->setFolderName('default');

        /*
         * Create mock version 1.0.0 for default library
         */
        $defaultLibraryVersion1 = new Version();
        $defaultLibraryVersion1->setVersion('1.0.0');
        $defaultLibraryVersion1->setLibrary($defaultLibrary);
        $defaultLibraryVersion1->setDescription('Version 1.0.0');
        $defaultLibraryVersion1->setFolderName('1.0.0');

        /*
         * Create mock version 1.1.0 for default library
         */
        $defaultLibraryVersion2 = new Version();
        $defaultLibraryVersion2->setVersion('1.1.0');
        $defaultLibraryVersion2->setLibrary($defaultLibrary);
        $defaultLibraryVersion2->setDescription('Version 1.1.0');
        $defaultLibraryVersion2->setFolderName('1.1.0');

        /*
         * Set the latest version for the library
         */
        $defaultLibrary->setLatestVersion($defaultLibraryVersion2);

        /*
         * Set references and persist
         */
        $this->setReference('defaultLibrary', $defaultLibrary);
        $this->setReference('defaultLibraryVersion1', $defaultLibraryVersion1);
        $this->setReference('defaultLibraryVersion2', $defaultLibraryVersion2);
        $objectManager->persist($defaultLibraryVersion1);
        $objectManager->persist($defaultLibraryVersion2);
        $objectManager->persist($defaultLibrary);


        // Dynamic Array Helper library hosted on codebender's Github organistion
        $dahLibrary = new Library();
        $dahLibrary->setName('Dynamic Array Helper Arduino Library');
        $dahLibrary->setDefaultHeader('DynamicArrayHelper');
        $dahLibrary->setActive(true);
        $dahLibrary->setVerified(false);
        $dahLibrary->setDescription('DynamicArrayHelper Arduino Library from the Arduino Playground');
        $dahLibrary->setUrl('https://github.com/codebendercc/DynamicArrayHelper-Arduino-Library');
        $dahLibrary->setOwner('codebendercc');
        $dahLibrary->setRepo('DynamicArrayHelper-Arduino-Library');
        $dahLibrary->setBranch('1.0.x');
        $dahLibrary->setInRepoPath('');
        $dahLibrary->setLastCommit('72b8865ee53b3edf159f22f5ff6f9a6dafa7ee1b'); // This is not the last commit of the branch
        $dahLibrary->setFolderName('DynamicArrayHelper');

        /*
         * Create mock version 1.0.0 for Dynamic Array Helper library
         */
        $dahLibraryVersion1 = new Version();
        $dahLibraryVersion1->setVersion('1.0.0');
        $dahLibraryVersion1->setLibrary($dahLibrary);
        $dahLibraryVersion1->setDescription('Version 1.0.0');
        $dahLibraryVersion1->setFolderName('1.0.0');
        $dahLibraryVersion1->setReleaseCommit('1751ccb9f8a1c5d9161fe18d97a03415e3517235');
        $dahLibraryVersion1->setSourceUrl('https://github.com/codebendercc/DynamicArrayHelper-Arduino-Library/archive/1.0.x.zip');

        /*
         * Set the latest version for the library
         */
        $dahLibrary->setLatestVersion($dahLibraryVersion1);

        /*
         * Set references and persist
         */
        $this->setReference('dynamicArrayHelperLibrary', $dahLibrary);
        $this->setReference('dynamicArrayHelperLibraryVersion1', $dahLibraryVersion1);
        $objectManager->persist($dahLibrary);
        $objectManager->persist($dahLibraryVersion1);


        // A fake library with multi-ino examples
        $multiIno = new Library();
        $multiIno->setName('MultiIno Arduino Library');
        $multiIno->setDefaultHeader('MultiIno');
        $multiIno->setActive(true);
        $multiIno->setVerified(false);
        $multiIno->setDescription('A library containing multi-ino examples which should be correctly fetched');
        $multiIno->setUrl('https://some/url.com');
        $multiIno->setFolderName('MultiIno');

        /*
         * Create mock version 1.0.0 for Multi Ino library
         */
        $multiInoLibraryVersion1 = new Version();
        $multiInoLibraryVersion1->setVersion('1.0.0');
        $multiInoLibraryVersion1->setLibrary($multiIno);
        $multiInoLibraryVersion1->setDescription('Version 1.0.0');
        $multiInoLibraryVersion1->setFolderName('1.0.0');

        /*
         * Create mock version 2.0.0 for Multi Ino library
         */
        $multiInoLibraryVersion2 = new Version();
        $multiInoLibraryVersion2->setVersion('2.0.0');
        $multiInoLibraryVersion2->setLibrary($multiIno);
        $multiInoLibraryVersion2->setDescription('Version 2.0.0');
        $multiInoLibraryVersion2->setFolderName('2.0.0');

        /*
         * Set the latest version for the library
         */
        $multiIno->setLatestVersion($multiInoLibraryVersion2);

        /*
         * Set references and persist
         */
        $this->setReference('MultiInoLibrary', $multiIno);
        $this->setReference('MultiInoLibraryVersion1', $multiInoLibraryVersion1);
        $this->setReference('MultiInoLibraryVersion2', $multiInoLibraryVersion2);
        $objectManager->persist($multiIno);
        $objectManager->persist($multiInoLibraryVersion1);
        $objectManager->persist($multiInoLibraryVersion2);


        // A fake library with examples contained in subcategories
        $subcategLibrary = new Library();
        $subcategLibrary->setName('SubCategories Arduino Library');
        $subcategLibrary->setDefaultHeader('SubCateg');
        $subcategLibrary->setActive(true);
        $subcategLibrary->setVerified(false);
        $subcategLibrary->setDescription('A library containing examples sorted in categories');
        $subcategLibrary->setUrl('https://some/url.com');
        $subcategLibrary->setFolderName('SubCateg');

        /*
         * Create mock version 1.0.0 for Sub Category library
         */
        $subcategLibraryVersion1 = new Version();
        $subcategLibraryVersion1->setVersion('1.0.0');
        $subcategLibraryVersion1->setLibrary($subcategLibrary);
        $subcategLibraryVersion1->setDescription('Version 1.0.0');
        $subcategLibraryVersion1->setFolderName('1.0.0');

        /*
         * Create mock version 1.5.2 for Sub Category library
         */
        $subcategLibraryVersion2 = new Version();
        $subcategLibraryVersion2->setVersion('1.5.2');
        $subcategLibraryVersion2->setLibrary($subcategLibrary);
        $subcategLibraryVersion2->setDescription('Version 1.5.2');
        $subcategLibraryVersion2->setFolderName('1.5.2');

        /*
         * Set the latest version for the library
         */
        $subcategLibrary->setLatestVersion($subcategLibraryVersion2);

        /*
         * Set references and persist
         */
        $this->setReference('SubCategLibrary', $subcategLibrary);
        $this->setReference('SubCategLibraryVersion1', $subcategLibraryVersion1);
        $this->setReference('SubCategLibraryVersion2', $subcategLibraryVersion2);
        $objectManager->persist($subcategLibrary);
        $objectManager->persist($subcategLibraryVersion1);
        $objectManager->persist($subcategLibraryVersion2);


        // A fake library containing hidden files
        $hiddenFilesLibrary = new Library();
        $hiddenFilesLibrary->setName('Hidden');
        $hiddenFilesLibrary->setDefaultHeader('Hidden');
        $hiddenFilesLibrary->setActive(true);
        $hiddenFilesLibrary->setVerified(false);
        $hiddenFilesLibrary->setDescription('A library containing hidden files and directories in its code & examples');
        $hiddenFilesLibrary->setUrl('https://some/url.com');
        $hiddenFilesLibrary->setFolderName('Hidden');

        /*
         * Create mock version 1.0.0 for Hidden library
         */
        $hiddenFilesLibraryVersion1 = new Version();
        $hiddenFilesLibraryVersion1->setVersion('1.0.0');
        $hiddenFilesLibraryVersion1->setLibrary($hiddenFilesLibrary);
        $hiddenFilesLibraryVersion1->setDescription('Version 1.0.0');
        $hiddenFilesLibraryVersion1->setFolderName('1.0.0');

        /*
         * Set the latest version for the library
         */
        $hiddenFilesLibrary->setLatestVersion($hiddenFilesLibraryVersion1);

        /*
         * Set references and persist
         */
        $this->setReference('HiddenLibrary', $hiddenFilesLibrary);
        $this->setReference('HiddenLibraryVersion1', $hiddenFilesLibraryVersion1);
        $objectManager->persist($hiddenFilesLibrary);
        $objectManager->persist($hiddenFilesLibraryVersion1);


        // A fake library with non UTF-8 encoded content.
        $invalidEncodingLibrary = new Library();
        $invalidEncodingLibrary->setName('Invalid Encoding Library');
        $invalidEncodingLibrary->setDefaultHeader('Encode');
        $invalidEncodingLibrary->setActive(true);
        $invalidEncodingLibrary->setVerified(false);
        $invalidEncodingLibrary->setDescription('A library containing characters with invalid encoding in it code & examples');
        $invalidEncodingLibrary->setUrl('https://some/url.com');
        $invalidEncodingLibrary->setFolderName('Encode');

        /*
         * Create mock version 1.0.0 for Encode library
         */
        $encodeLibraryVersion1 = new Version();
        $encodeLibraryVersion1->setVersion('1.0.0');
        $encodeLibraryVersion1->setLibrary($invalidEncodingLibrary);
        $encodeLibraryVersion1->setDescription('Version 1.0.0');
        $encodeLibraryVersion1->setFolderName('1.0.0');

        /*
         * Set the latest version for the library
         */
        $invalidEncodingLibrary->setLatestVersion($encodeLibraryVersion1);

        /*
         * Set references and persist
         */
        $this->setReference('EncodeLibrary', $invalidEncodingLibrary);
        $this->setReference('EncodeLibraryVersion1', $encodeLibraryVersion1);
        $objectManager->persist($invalidEncodingLibrary);
        $objectManager->persist($encodeLibraryVersion1);

        /*
         * Set references and persist
         */
        $htmlLibrary = new Library();
        $htmlLibrary->setName('HTML content Library');
        $htmlLibrary->setDefaultHeader('HtmlLib');
        $htmlLibrary->setActive(true);
        $htmlLibrary->setVerified(false);
        $htmlLibrary->setDescription('A library containing HTML in its files');
        $htmlLibrary->setUrl('https://some/url.com');
        $htmlLibrary->setFolderName('HtmlLib');

        /*
         * Create mock version 1.0.0 for Binary library
         */
        $htmlLbraryVersion1 = new Version();
        $htmlLbraryVersion1->setVersion('1.0.0');
        $htmlLbraryVersion1->setLibrary($htmlLibrary);
        $htmlLbraryVersion1->setDescription('Version 1.0.0');
        $htmlLbraryVersion1->setFolderName('1.0.0');

        /*
         * Set the latest version for the library
         */
        $htmlLibrary->setLatestVersion($htmlLbraryVersion1);

        $this->setReference('HtmlLibrary', $htmlLibrary);
        $this->setReference('HtmlLibraryVersion1', $htmlLbraryVersion1);
        $objectManager->persist($htmlLibrary);
        $objectManager->persist($htmlLbraryVersion1);


        // A fake library with non-text contents.
        $binaryLbrary = new Library();
        $binaryLbrary->setName('Binary content Library');
        $binaryLbrary->setDefaultHeader('Binary');
        $binaryLbrary->setActive(true);
        $binaryLbrary->setVerified(false);
        $binaryLbrary->setDescription('A library containing non-text files');
        $binaryLbrary->setUrl('https://some/url.com');
        $binaryLbrary->setFolderName('Binary');

        /*
         * Create mock version 1.0.0 for Binary library
         */
        $binaryLbraryVersion1 = new Version();
        $binaryLbraryVersion1->setVersion('1.0.0');
        $binaryLbraryVersion1->setLibrary($binaryLbrary);
        $binaryLbraryVersion1->setDescription('Version 1.0.0');
        $binaryLbraryVersion1->setFolderName('1.0.0');

        /*
         * Set the latest version for the library
         */
        $binaryLbrary->setLatestVersion($binaryLbraryVersion1);

        /*
         * Set references and persist
         */
        $this->setReference('BinaryLibrary', $binaryLbrary);
        $this->setReference('BinaryLibraryVersion1', $binaryLbraryVersion1);
        $objectManager->persist($binaryLbrary);
        $objectManager->persist($binaryLbraryVersion1);


        /*
         * After all fixture objects have been added to the ObjectManager (`persist` operation),
         * it's time to flush the contents of the ObjectManager
         */
        $objectManager->flush();


        // From here on add all the internal libraries
        $builtInLibs = ["EEPROM", "Ethernet", "GSM", "Robot_Control", "SD", "SoftwareSerial", "Stepper", "WiFi",
            "Esplora", "Firmata", "LiquidCrystal", "Robot_Motor", "Servo", "SPI", "TFT", "Wire"];
        $builtInDefaultVersion = 'default';
        foreach ($builtInLibs as $name) {
            $builtInLib = new Library();
            $builtInLib->setName($name);
            $builtInLib->setDefaultHeader($name);
            $builtInLib->setActive(true);
            $builtInLib->setVerified(true);
            $builtInLib->setDescription("Built-in library " . $name);
            $builtInLib->setFolderName($name);
            $builtInLib->setIsBuiltIn(true);

            $builtInLibVersion = new Version();
            $builtInLibVersion->setVersion($builtInDefaultVersion);
            $builtInLibVersion->setLibrary($builtInLib);
            $builtInLibVersion->setDescription("Built-in library " . $name . " default version.");
            $builtInLibVersion->setFolderName($builtInDefaultVersion);

            $builtInLib->setLatestVersion($builtInLibVersion);

            $this->setReference($name . 'Library', $builtInLib);
            $this->setReference($name . ucfirst($builtInDefaultVersion) . 'Version', $builtInLibVersion);
            $objectManager->persist($builtInLib);
            $objectManager->persist($builtInLibVersion);
        }

        /*
         * After all fixture objects have been added to the ObjectManager (`persist` operation),
         * it's time to flush the contents of the ObjectManager
         */
        $objectManager->flush();
    }

    /**
     * Returns the order in which the current fixture will be loaded,
     * compared to other fixture classes.
     *
     * @return int
     */
    public function getOrder()
    {
        return 5;
    }
}
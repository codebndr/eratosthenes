<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\ExternalLibrary;

/**
 * Class LoadExternalLibraryData
 * Provides default external libraries data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures
 *
 * @SuppressWarnings(PHPMD)
 */
class LoadExternalLibraryData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for external libraries objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        // A fake version of the Adafruit GPS library
        $defaultLibrary = new ExternalLibrary();
        $defaultLibrary->setHumanName('Default Arduino Library');
        $defaultLibrary->setMachineName('default');
        $defaultLibrary->setActive(true);
        $defaultLibrary->setVerified(false);
        $defaultLibrary->setDescription('The default Arduino library (in fact it\'s Adafruit\'s GPS library)');
        $defaultLibrary->setNotes('No notes provided for this library');
        $defaultLibrary->setUrl('http://localhost/library/url');

        /*
         * Set a reference for the library and add it to the database using
         * the object manager interface
         */
        $this->setReference('defaultLibrary', $defaultLibrary);
        $objectManager->persist($defaultLibrary);

        // Dynamic Array Helper library hosted on codebender's Github organistion
        $dahLibrary = new ExternalLibrary();
        $dahLibrary->setHumanName('Dynamic Array Helper Arduino Library');
        $dahLibrary->setMachineName('DynamicArrayHelper');
        $dahLibrary->setActive(true);
        $dahLibrary->setVerified(false);
        $dahLibrary->setDescription('DynamicArrayHelper Arduino Library from the Arduino Playground');
        $dahLibrary->setUrl('https://github.com/codebendercc/DynamicArrayHelper-Arduino-Library');
        $dahLibrary->setSourceUrl('https://github.com/codebendercc/DynamicArrayHelper-Arduino-Library/archive/1.0.x.zip');
        $dahLibrary->setBranch('1.0.x');
        $dahLibrary->setOwner('codebendercc');
        $dahLibrary->setRepo('DynamicArrayHelper-Arduino-Library');
        $dahLibrary->setLastCommit('72b8865ee53b3edf159f22f5ff6f9a6dafa7ee1b'); // This is not the last commit of the branch

        // Reference to DynamicArrayHelper library
        $this->setReference('dynamicArrayHelperLibrary', $dahLibrary);
        $objectManager->persist($dahLibrary);

        // A fake library with multi-ino examples
        $multiIno = new ExternalLibrary();
        $multiIno->setHumanName('MultiIno Arduino Library');
        $multiIno->setMachineName('MultiIno');
        $multiIno->setActive(true);
        $multiIno->setVerified(false);
        $multiIno->setDescription('A library containing multi-ino examples which should be correctly fetched');
        $multiIno->setUrl('https://some/url.com');
        $multiIno->setSourceUrl('https://some/source/url.com');

        // Reference to MultiIno library
        $this->setReference('MultiInoLibrary', $multiIno);
        $objectManager->persist($multiIno);

        // A fake library with examples contained in subcategories
        $subcategLibrary = new ExternalLibrary();
        $subcategLibrary->setHumanName('SubCategories Arduino Library');
        $subcategLibrary->setMachineName('SubCateg');
        $subcategLibrary->setActive(true);
        $subcategLibrary->setVerified(false);
        $subcategLibrary->setDescription('A library containing examples sorted in categories');
        $subcategLibrary->setUrl('https://some/url.com');
        $subcategLibrary->setSourceUrl('https://some/source/url.com');

        // Reference to SubCateg library
        $this->setReference('SubCategLibrary', $subcategLibrary);
        $objectManager->persist($subcategLibrary);

        // A fake library containing hidden files
        $hiddenFilesLibrary = new ExternalLibrary();
        $hiddenFilesLibrary->setHumanName('Hidden');
        $hiddenFilesLibrary->setMachineName('Hidden');
        $hiddenFilesLibrary->setActive(true);
        $hiddenFilesLibrary->setVerified(false);
        $hiddenFilesLibrary->setDescription('A library containing hidden files and directories in its code & examples');
        $hiddenFilesLibrary->setUrl('https://some/url.com');
        $hiddenFilesLibrary->setSourceUrl('https://some/source/url.com');

        // Reference to Hidden library
        $this->setReference('HiddenLibrary', $hiddenFilesLibrary);
        $objectManager->persist($hiddenFilesLibrary);

        // A fake library with non UTF-8 encoded content.
        $invalidEncodingLibrary = new ExternalLibrary();
        $invalidEncodingLibrary->setHumanName('Invalid Encoding Library');
        $invalidEncodingLibrary->setMachineName('Encode');
        $invalidEncodingLibrary->setActive(true);
        $invalidEncodingLibrary->setVerified(false);
        $invalidEncodingLibrary->setDescription('A library containing characters with invalid encoding in it code & examples');
        $invalidEncodingLibrary->setUrl('https://some/url.com');
        $invalidEncodingLibrary->setSourceUrl('https://some/source/url.com');

        // Reference to Encode library
        $this->setReference('EncodeLibrary', $invalidEncodingLibrary);
        $objectManager->persist($invalidEncodingLibrary);

        // A fake library with HTML doc files.
        $htmlLibrary = new ExternalLibrary();
        $htmlLibrary->setHumanName('HTML content Library');
        $htmlLibrary->setMachineName('HtmlLib');
        $htmlLibrary->setActive(true);
        $htmlLibrary->setVerified(false);
        $htmlLibrary->setDescription('A library containing HTML in its files');
        $htmlLibrary->setUrl('https://some/url.com');
        $htmlLibrary->setSourceUrl('https://some/source/url.com');

        $objectManager->persist($htmlLibrary);

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
        return 1;
    }
}
<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Library;

/**
 * Class LoadLibraryData
 * Provides default libraries data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures
 *
 * @SuppressWarnings(PHPMD)
 */
class LoadLibraryData extends AbstractFixture implements OrderedFixtureInterface
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
         * Set a reference for the library and add it to the database using
         * the object manager interface
         */
        $this->setReference('defaultLibrary', $defaultLibrary);
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

        // Reference to DynamicArrayHelper library
        $this->setReference('dynamicArrayHelperLibrary', $dahLibrary);
        $objectManager->persist($dahLibrary);

        // A fake library with multi-ino examples
        $multiIno = new Library();
        $multiIno->setName('MultiIno Arduino Library');
        $multiIno->setDefaultHeader('MultiIno');
        $multiIno->setActive(true);
        $multiIno->setVerified(false);
        $multiIno->setDescription('A library containing multi-ino examples which should be correctly fetched');
        $multiIno->setUrl('https://some/url.com');
        $multiIno->setFolderName('MultiIno');

        // Reference to MultiIno library
        $this->setReference('MultiInoLibrary', $multiIno);
        $objectManager->persist($multiIno);

        // A fake library with examples contained in subcategories
        $subcategLibrary = new Library();
        $subcategLibrary->setName('SubCategories Arduino Library');
        $subcategLibrary->setDefaultHeader('SubCateg');
        $subcategLibrary->setActive(true);
        $subcategLibrary->setVerified(false);
        $subcategLibrary->setDescription('A library containing examples sorted in categories');
        $subcategLibrary->setUrl('https://some/url.com');
        $subcategLibrary->setFolderName('SubCateg');

        // Reference to SubCateg library
        $this->setReference('SubCategLibrary', $subcategLibrary);
        $objectManager->persist($subcategLibrary);

        // A fake library containing hidden files
        $hiddenFilesLibrary = new Library();
        $hiddenFilesLibrary->setName('Hidden');
        $hiddenFilesLibrary->setDefaultHeader('Hidden');
        $hiddenFilesLibrary->setActive(true);
        $hiddenFilesLibrary->setVerified(false);
        $hiddenFilesLibrary->setDescription('A library containing hidden files and directories in its code & examples');
        $hiddenFilesLibrary->setUrl('https://some/url.com');
        $hiddenFilesLibrary->setFolderName('Hidden');

        // Reference to Hidden library
        $this->setReference('HiddenLibrary', $hiddenFilesLibrary);
        $objectManager->persist($hiddenFilesLibrary);

        // A fake library with non UTF-8 encoded content.
        $invalidEncodingLibrary = new Library();
        $invalidEncodingLibrary->setName('Invalid Encoding Library');
        $invalidEncodingLibrary->setDefaultHeader('Encode');
        $invalidEncodingLibrary->setActive(true);
        $invalidEncodingLibrary->setVerified(false);
        $invalidEncodingLibrary->setDescription('A library containing characters with invalid encoding in it code & examples');
        $invalidEncodingLibrary->setUrl('https://some/url.com');
        $invalidEncodingLibrary->setFolderName('Encode');

        // Reference to Encode library
        $this->setReference('EncodeLibrary', $invalidEncodingLibrary);
        $objectManager->persist($invalidEncodingLibrary);

        // A fake library with HTML doc files.
        $htmlLibrary = new Library();
        $htmlLibrary->setName('HTML content Library');
        $htmlLibrary->setDefaultHeader('HtmlLib');
        $htmlLibrary->setActive(true);
        $htmlLibrary->setVerified(false);
        $htmlLibrary->setDescription('A library containing HTML in its files');
        $htmlLibrary->setUrl('https://some/url.com');
        $htmlLibrary->setFolderName('HtmlLib');

        $objectManager->persist($htmlLibrary);

        // A fake library with non-text contents.
        $binaryLbrary = new Library();
        $binaryLbrary->setName('Binary content Library');
        $binaryLbrary->setDefaultHeader('Binary');
        $binaryLbrary->setActive(true);
        $binaryLbrary->setVerified(false);
        $binaryLbrary->setDescription('A library containing non-text files');
        $binaryLbrary->setUrl('https://some/url.com');
        $binaryLbrary->setFolderName('Binary');

        // Persist the new library
        $objectManager->persist($binaryLbrary);

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
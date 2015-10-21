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

        $this->setReference('dynamicArrayHelperLibrary', $dahLibrary);
        $objectManager->persist($dahLibrary);

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
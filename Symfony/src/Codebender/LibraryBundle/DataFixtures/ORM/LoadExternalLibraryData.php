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
<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Architecture;

/**
 * Class LoadArchitectureData
 * Provides default architecture data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadArchitectureData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for Architecture objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        $avrArchitecture = new Architecture();
        $avrArchitecture->setName('AVR');

        /*
         * Set a reference for each architecture and add it to the database using
         * the object manager interface
         */
        $this->setReference('AvrArchitecture', $avrArchitecture);

        $objectManager->persist($avrArchitecture);

        $esp8266Architecture = new Architecture();
        $esp8266Architecture->setName('ESP8266');

        /*
         * Set a reference for each architecture and add it to the database using
         * the object manager interface
         */
        $this->setReference('ESP8266Architecture', $esp8266Architecture);

        $objectManager->persist($esp8266Architecture);

        $edisonArchitecture = new Architecture();
        $edisonArchitecture->setName('Intel Edison');

        /*
         * Set a reference for each architecture and add it to the database using
         * the object manager interface
         */
        $this->setReference('EdisonArchitecture', $edisonArchitecture);

        $objectManager->persist($edisonArchitecture);

        $teensyArchitecture = new Architecture();
        $teensyArchitecture->setName('Teensy');

        /*
         * Set a reference for each architecture and add it to the database using
         * the object manager interface
         */
        $this->setReference('TeensyArchitecture', $teensyArchitecture);

        $objectManager->persist($teensyArchitecture);

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
        return 3;
    }
}

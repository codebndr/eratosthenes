<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Partner;

/**
 * Class LoadPartnerData
 * Provides default Partner data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadPartnerData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for Partner objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        // partner 1 : codebender
        $codebender = new Partner();
        $codebender->setName('codebender');
        $codebender->setAuthKey('youMustChangeThis');
        $this->setReference('PartnerCodebender', $codebender);
        $objectManager->persist($codebender);

        // partner 2 : arduino.cc
        $arduinoCc = new Partner();
        $arduinoCc->setName('arduino.cc');
        $arduinoCc->setAuthKey('authKey');
        $this->setReference('PartnerArduinoCc', $arduinoCc);
        $objectManager->persist($arduinoCc);

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
        return 4;
    }
}
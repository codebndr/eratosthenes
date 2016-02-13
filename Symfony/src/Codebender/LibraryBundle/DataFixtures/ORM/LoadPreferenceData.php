<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Codebender\LibraryBundle\Entity\Preference;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\Version;
use Codebender\LibraryBundle\Entity\Partner;

/**
 * Class LoadPreferenceData
 * Provides partner's default used versions for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadPreferenceData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for Prefernce objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        /**
         * Get reference for our mock partner data
         */
        $codebender = $this->getReference('PartnerCodebender');
        $arduinoCc = $this->getReference('PartnerArduinoCc');

        /**
         * Get reference for our mock version data
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $defaultLibraryVersion2 */
        $defaultLibraryVersion2 = $this->getReference('defaultLibraryVersion2');

        /* @var \Codebender\LibraryBundle\Entity\Version $dahLibraryVersion1 */
        $dahLibraryVersion1 = $this->getReference('dynamicArrayHelperLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $multiInoLibraryVersion2 */
        $multiInoLibraryVersion2 = $this->getReference('MultiInoLibraryVersion2');

        /* @var \Codebender\LibraryBundle\Entity\Version $subcategLibraryVersion2 */
        $subcategLibraryVersion2 = $this->getReference('SubCategLibraryVersion2');

        /* @var \Codebender\LibraryBundle\Entity\Version $hiddenFilesLibraryVersion1 */
        $hiddenFilesLibraryVersion1 = $this->getReference('HiddenLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $encodeLibraryVersion1 */
        $encodeLibraryVersion1 = $this->getReference('EncodeLibraryVersion1');

        /*
         * Add mock preference for partner: codebender
         */
        $preference1 = new Preference();
        $preference1->setLibrary($defaultLibraryVersion2->getLibrary());
        $preference1->setVersion($defaultLibraryVersion2);
        $preference1->setPartner($codebender);
        $objectManager->persist($preference1);

        $preference2 = new Preference();
        $preference2->setLibrary($dahLibraryVersion1->getLibrary());
        $preference2->setVersion($dahLibraryVersion1);
        $preference2->setPartner($codebender);
        $objectManager->persist($preference2);

        $preference3 = new Preference();
        $preference3->setLibrary($multiInoLibraryVersion2->getLibrary());
        $preference3->setVersion($multiInoLibraryVersion2);
        $preference3->setPartner($codebender);
        $objectManager->persist($preference3);

        $preference4 = new Preference();
        $preference4->setLibrary($subcategLibraryVersion2->getLibrary());
        $preference4->setVersion($subcategLibraryVersion2);
        $preference4->setPartner($codebender);
        $objectManager->persist($preference4);

        $preference5 = new Preference();
        $preference5->setLibrary($hiddenFilesLibraryVersion1->getLibrary());
        $preference5->setVersion($hiddenFilesLibraryVersion1);
        $preference5->setPartner($codebender);
        $objectManager->persist($preference5);

        $preference6 = new Preference();
        $preference6->setLibrary($encodeLibraryVersion1->getLibrary());
        $preference6->setVersion($encodeLibraryVersion1);
        $preference6->setPartner($codebender);
        $objectManager->persist($preference6);

        /*
         * Add mock preference for partner: arduino.cc
         */
        $preference7 = new Preference();
        $preference7->setLibrary($defaultLibraryVersion2->getLibrary());
        $preference7->setVersion($defaultLibraryVersion2);
        $preference7->setPartner($arduinoCc);
        $objectManager->persist($preference7);

        $preference8 = new Preference();
        $preference8->setLibrary($dahLibraryVersion1->getLibrary());
        $preference8->setVersion($dahLibraryVersion1);
        $preference8->setPartner($arduinoCc);
        $objectManager->persist($preference8);

        $preference9 = new Preference();
        $preference9->setLibrary($multiInoLibraryVersion2->getLibrary());
        $preference9->setVersion($multiInoLibraryVersion2);
        $preference9->setPartner($arduinoCc);
        $objectManager->persist($preference9);

        $preference10 = new Preference();
        $preference10->setLibrary($subcategLibraryVersion2->getLibrary());
        $preference10->setVersion($subcategLibraryVersion2);
        $preference10->setPartner($arduinoCc);
        $objectManager->persist($preference10);

        $preference11 = new Preference();
        $preference11->setLibrary($hiddenFilesLibraryVersion1->getLibrary());
        $preference11->setVersion($hiddenFilesLibraryVersion1);
        $preference11->setPartner($arduinoCc);
        $objectManager->persist($preference11);

        $preference12 = new Preference();
        $preference12->setLibrary($encodeLibraryVersion1->getLibrary());
        $preference12->setVersion($encodeLibraryVersion1);
        $preference12->setPartner($arduinoCc);
        $objectManager->persist($preference12);

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
        return 9;
    }
}

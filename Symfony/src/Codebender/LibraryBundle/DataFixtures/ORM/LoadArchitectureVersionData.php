<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Architecture;
use Codebender\LibraryBundle\Entity\Version;

/**
 * Class LoadArchitectureVersionData
 * Provides version's supported architecture data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadArchitectureVersionData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for Architecture-Version objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        /**
         * Get reference for our mock architecture data
         */
        /* @var \Codebender\LibraryBundle\Entity\Architecture $avrArchitecture */
        $avrArchitecture = $this->getReference('AvrArchitecture');

        /* @var \Codebender\LibraryBundle\Entity\Architecture $esp8266Architecture */
        $esp8266Architecture = $this->getReference('ESP8266Architecture');

        /* @var \Codebender\LibraryBundle\Entity\Architecture $edisonArchitecture */
        $edisonArchitecture = $this->getReference('EdisonArchitecture');

        /* @var \Codebender\LibraryBundle\Entity\Architecture $teensyArchitecture */
        $teensyArchitecture = $this->getReference('TeensyArchitecture');

        /**
         * Get reference for our mock version data
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $defaultLibraryVersion1 */
        $defaultLibraryVersion1 = $this->getReference('defaultLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $defaultLibraryVersion2 */
        $defaultLibraryVersion2 = $this->getReference('defaultLibraryVersion2');

        /* @var \Codebender\LibraryBundle\Entity\Version $dahLibraryVersion1 */
        $dahLibraryVersion1 = $this->getReference('dynamicArrayHelperLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $multiInoLibraryVersion1 */
        $multiInoLibraryVersion1 = $this->getReference('MultiInoLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $multiInoLibraryVersion2 */
        $multiInoLibraryVersion2 = $this->getReference('MultiInoLibraryVersion2');

        /* @var \Codebender\LibraryBundle\Entity\Version $subcategLibraryVersion1 */
        $subcategLibraryVersion1 = $this->getReference('SubCategLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $subcategLibraryVersion2 */
        $subcategLibraryVersion2 = $this->getReference('SubCategLibraryVersion2');

        /* @var \Codebender\LibraryBundle\Entity\Version $hiddenFilesLibraryVersion1 */
        $hiddenFilesLibraryVersion1 = $this->getReference('HiddenLibraryVersion1');

        /* @var \Codebender\LibraryBundle\Entity\Version $encodeLibraryVersion1 */
        $encodeLibraryVersion1 = $this->getReference('EncodeLibraryVersion1');

        /*
         * Add mock architectures for each versions
         */
        $defaultLibraryVersion1->addArchitecture($avrArchitecture);
        $defaultLibraryVersion1->addArchitecture($esp8266Architecture);
        $objectManager->persist($defaultLibraryVersion1);

        $defaultLibraryVersion2->addArchitecture($avrArchitecture);
        $defaultLibraryVersion2->addArchitecture($esp8266Architecture);
        $defaultLibraryVersion2->addArchitecture($edisonArchitecture);
        $defaultLibraryVersion2->addArchitecture($teensyArchitecture);
        $objectManager->persist($defaultLibraryVersion2);

        $dahLibraryVersion1->addArchitecture($avrArchitecture);
        $dahLibraryVersion1->addArchitecture($esp8266Architecture);
        $dahLibraryVersion1->addArchitecture($edisonArchitecture);
        $dahLibraryVersion1->addArchitecture($teensyArchitecture);
        $objectManager->persist($dahLibraryVersion1);

        $multiInoLibraryVersion1->addArchitecture($avrArchitecture);
        $multiInoLibraryVersion1->addArchitecture($esp8266Architecture);
        $objectManager->persist($multiInoLibraryVersion1);

        $multiInoLibraryVersion2->addArchitecture($avrArchitecture);
        $multiInoLibraryVersion2->addArchitecture($esp8266Architecture);
        $multiInoLibraryVersion2->addArchitecture($edisonArchitecture);
        $multiInoLibraryVersion2->addArchitecture($teensyArchitecture);
        $objectManager->persist($multiInoLibraryVersion2);

        $subcategLibraryVersion1->addArchitecture($avrArchitecture);
        $subcategLibraryVersion1->addArchitecture($esp8266Architecture);
        $subcategLibraryVersion1->addArchitecture($edisonArchitecture);
        $subcategLibraryVersion1->addArchitecture($teensyArchitecture);
        $objectManager->persist($subcategLibraryVersion1);

        $subcategLibraryVersion2->addArchitecture($avrArchitecture);
        $subcategLibraryVersion2->addArchitecture($esp8266Architecture);
        $subcategLibraryVersion2->addArchitecture($edisonArchitecture);
        $subcategLibraryVersion2->addArchitecture($teensyArchitecture);
        $objectManager->persist($subcategLibraryVersion2);

        $hiddenFilesLibraryVersion1->addArchitecture($avrArchitecture);
        $hiddenFilesLibraryVersion1->addArchitecture($esp8266Architecture);
        $hiddenFilesLibraryVersion1->addArchitecture($edisonArchitecture);
        $hiddenFilesLibraryVersion1->addArchitecture($teensyArchitecture);
        $objectManager->persist($hiddenFilesLibraryVersion1);

        $encodeLibraryVersion1->addArchitecture($avrArchitecture);
        $encodeLibraryVersion1->addArchitecture($esp8266Architecture);
        $encodeLibraryVersion1->addArchitecture($edisonArchitecture);
        $encodeLibraryVersion1->addArchitecture($teensyArchitecture);
        $objectManager->persist($encodeLibraryVersion1);

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
        return 8;
    }
}

<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Version;

/**
 * Class LoadVersionData
 * Provides default external library version data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadVersionData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for external libraries' versions objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        /* @var \Codebender\LibraryBundle\Entity\Library $defaultLibrary */
        $defaultLibrary = $this->getReference('defaultLibrary');

        /*
         * Create mock version 1.0.0 for default library
         */
        $defaultLibraryVersion1 = new Version();
        $defaultLibraryVersion1->setVersion('1.0.0');
        $defaultLibraryVersion1->setLibrary($defaultLibrary);
        $defaultLibraryVersion1->setDescription('Version 1.0.0');
        $defaultLibraryVersion1->setFolderName('1.0.0');

        /*
         * Set a reference for each version and add it to the database using
         * the object manager interface
         */
        $this->setReference('defaultLibraryVersion1', $defaultLibraryVersion1);
        $objectManager->persist($defaultLibraryVersion1);

        /*
         * Create mock version 1.1.0 for default library
         */
        $defaultLibraryVersion2 = new Version();
        $defaultLibraryVersion2->setVersion('1.1.0');
        $defaultLibraryVersion2->setLibrary($defaultLibrary);
        $defaultLibraryVersion2->setDescription('Version 1.1.0');
        $defaultLibraryVersion2->setFolderName('1.1.0');

        /*
         * Set a reference for each version and add it to the database using
         * the object manager interface
         */
        $this->setReference('defaultLibraryVersion2', $defaultLibraryVersion2);
        $objectManager->persist($defaultLibraryVersion2);

        /* @var \Codebender\LibraryBundle\Entity\Library $dahLibrary */
        $dahLibrary = $this->getReference('dynamicArrayHelperLibrary');

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

        $this->setReference('dynamicArrayHelperLibraryVersion1', $dahLibraryVersion1);
        $objectManager->persist($dahLibraryVersion1);

        /* @var \Codebender\LibraryBundle\Entity\Library $multiInoLibrary */
        $multiInoLibrary = $this->getReference('MultiInoLibrary');

        /*
         * Create mock version 1.0.0 for Multi Ino library
         */
        $multiInoLibraryVersion1 = new Version();
        $multiInoLibraryVersion1->setVersion('1.0.0');
        $multiInoLibraryVersion1->setLibrary($multiInoLibrary);
        $multiInoLibraryVersion1->setDescription('Version 1.0.0');
        $multiInoLibraryVersion1->setFolderName('1.0.0');

        $this->setReference('MultiInoLibraryVersion1', $multiInoLibraryVersion1);
        $objectManager->persist($multiInoLibraryVersion1);

        /*
         * Create mock version 2.0.0 for Multi Ino library
         */
        $multiInoLibraryVersion2 = new Version();
        $multiInoLibraryVersion2->setVersion('2.0.0');
        $multiInoLibraryVersion2->setLibrary($multiInoLibrary);
        $multiInoLibraryVersion2->setDescription('Version 2.0.0');
        $multiInoLibraryVersion2->setFolderName('2.0.0');

        $this->setReference('MultiInoLibraryVersion2', $multiInoLibraryVersion2);
        $objectManager->persist($multiInoLibraryVersion2);

        /* @var \Codebender\LibraryBundle\Entity\Library $subcategLibrary */
        $subcategLibrary = $this->getReference('SubCategLibrary');

        /*
         * Create mock version 1.0.0 for Sub Category library
         */
        $subcategLibraryVersion1 = new Version();
        $subcategLibraryVersion1->setVersion('1.0.0');
        $subcategLibraryVersion1->setLibrary($subcategLibrary);
        $subcategLibraryVersion1->setDescription('Version 1.0.0');
        $subcategLibraryVersion1->setFolderName('1.0.0');

        $this->setReference('SubCategLibraryVersion1', $subcategLibraryVersion1);
        $objectManager->persist($subcategLibraryVersion1);

        /*
         * Create mock version 1.5.2 for Sub Category library
         */
        $subcategLibraryVersion2 = new Version();
        $subcategLibraryVersion2->setVersion('1.5.2');
        $subcategLibraryVersion2->setLibrary($subcategLibrary);
        $subcategLibraryVersion2->setDescription('Version 1.5.2');
        $subcategLibraryVersion2->setFolderName('1.5.2');

        $this->setReference('SubCategLibraryVersion2', $subcategLibraryVersion2);
        $objectManager->persist($subcategLibraryVersion2);

        /* @var \Codebender\LibraryBundle\Entity\Library $hiddenFilesLibrary */
        $hiddenFilesLibrary = $this->getReference('HiddenLibrary');

        /*
         * Create mock version 1.0.0 for Hidden library
         */
        $hiddenFilesLibraryVersion1 = new Version();
        $hiddenFilesLibraryVersion1->setVersion('1.0.0');
        $hiddenFilesLibraryVersion1->setLibrary($hiddenFilesLibrary);
        $hiddenFilesLibraryVersion1->setDescription('Version 1.0.0');
        $hiddenFilesLibraryVersion1->setFolderName('1.0.0');

        $this->setReference('HiddenLibraryVersion1', $hiddenFilesLibraryVersion1);
        $objectManager->persist($hiddenFilesLibraryVersion1);

        /* @var \Codebender\LibraryBundle\Entity\Library $encodeLibrary */
        $encodeLibrary = $this->getReference('EncodeLibrary');

        /*
         * Create mock version 1.0.0 for Encode library
         */
        $encodeLibraryVersion1 = new Version();
        $encodeLibraryVersion1->setVersion('1.0.0');
        $encodeLibraryVersion1->setLibrary($encodeLibrary);
        $encodeLibraryVersion1->setDescription('Version 1.0.0');
        $encodeLibraryVersion1->setFolderName('1.0.0');

        $this->setReference('EncodeLibraryVersion1', $encodeLibraryVersion1);
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
     * Examples database data should be provided after the library data,
     * because there is a foreign key constraint between them which should
     * be satisfied.
     *
     * @return int
     */
    public function getOrder()
    {
        return 6;
    }
}

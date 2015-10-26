<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\Example;

/**
 * Class LoadExternalLibraryExamplesData
 * Provides default external library examples data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadExternalLibraryExamplesData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for external libraries' examples objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $defaultLibrary */
        $defaultLibrary = $this->getReference('defaultLibrary');

        $defaultExample = new Example();
        $defaultExample->setName('example_one');
        $defaultExample->setLibrary($defaultLibrary);
        $defaultExample->setPath('default/examples/example_one/example_one.ino');
        $defaultExample->setBoards(null);

        /*
         * Set a reference for each example and add it to the database using
         * the object manager interface
         */
        $this->setReference('defaultLibraryExample', $defaultExample);
        $objectManager->persist($defaultExample);

        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $multiInoLibrary */
        $multiInoLibrary = $this->getReference('MultiInoLibrary');

        $example = new Example();
        $example->setName('example_one');
        $example->setLibrary($multiInoLibrary);
        $example->setPath('MultiIno/examples/multi_ino_example/multi_ino_example.ino');
        $example->setBoards(null);

        // Persist the new example
        $objectManager->persist($example);

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
        return 2;
    }
}

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

        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $subcategLibrary */
        $subcategLibrary = $this->getReference('SubCategLibrary');

        $exampleDefaultCateg = new Example();
        $exampleDefaultCateg->setName('subcateg_example_one');
        $exampleDefaultCateg->setLibrary($subcategLibrary);
        $exampleDefaultCateg->setPath('SubCateg/Examples/subcateg_example_one/subcateg_example_one.ino');
        $exampleDefaultCateg->setBoards(null);

        // Persist the new example
        $objectManager->persist($exampleDefaultCateg);

        $exampleBeginnerCateg = new Example();
        $exampleBeginnerCateg->setName('subcateg_example_two');
        $exampleBeginnerCateg->setLibrary($subcategLibrary);
        $exampleBeginnerCateg->setPath('SubCateg/Examples/experienceBased/Beginners/subcateg_example_two/subcateg_example_two.ino');
        $exampleBeginnerCateg->setBoards(null);

        // Persist the new example
        $objectManager->persist($exampleBeginnerCateg);

        $exampleExperiencedCateg = new Example();
        $exampleExperiencedCateg->setName('subcateg_example_three');
        $exampleExperiencedCateg->setLibrary($subcategLibrary);
        $exampleExperiencedCateg->setPath('SubCateg/Examples/experienceBased/Advanced/Experts/subcateg_example_three/subcateg_example_three.ino');
        $exampleExperiencedCateg->setBoards(null);

        // Persist the new example
        $objectManager->persist($exampleExperiencedCateg);

        /* @var \Codebender\LibraryBundle\Entity\ExternalLibrary $hiddenFilesLibrary */
        $hiddenFilesLibrary = $this->getReference('HiddenLibrary');

        $hiddenFilesExample = new Example();
        $hiddenFilesExample->setName('hidden_files_example');
        $hiddenFilesExample->setLibrary($hiddenFilesLibrary);
        $hiddenFilesExample->setPath('Hidden/examples/hidden_files_example/hidden_files_example.ino');
        $hiddenFilesExample->setBoards(null);

        // Persist the new example
        $objectManager->persist($hiddenFilesExample);

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

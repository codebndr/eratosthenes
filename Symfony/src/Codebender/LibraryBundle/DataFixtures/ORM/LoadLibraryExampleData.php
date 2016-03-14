<?php

namespace Codebender\LibraryBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Codebender\LibraryBundle\Entity\LibraryExample;

/**
 * Class LoadLibraryExamplesData
 * Provides default library examples data for the database
 *
 * @package Codebender\LibraryBundle\DataFixtures\ORM
 * @SuppressWarnings(PHPMD)
 */
class LoadLibraryExamplesData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * Sets up fixture database data for libraries' examples objects
     *
     * @param ObjectManager $objectManager
     */
    public function load(ObjectManager $objectManager)
    {
        /*
         * Get mock version 1.0.0 of default Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $defaultVersion1 */
        $defaultVersion1 = $this->getReference('defaultLibraryVersion1');

        /*
         * Mock a new library example
         */
        $defaultExample1 = new LibraryExample();
        $defaultExample1->setName('example_one');
        $defaultExample1->setVersion($defaultVersion1);
        $defaultExample1->setPath('examples/example_one/example_one.ino');
        $defaultExample1->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($defaultExample1);

        /*
         * Get mock version 1.1.0 of default Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $defaultVersion2 */
        $defaultVersion2 = $this->getReference('defaultLibraryVersion2');

        $defaultExample2 = new LibraryExample();
        $defaultExample2->setName('example_one');
        $defaultExample2->setVersion($defaultVersion2);
        $defaultExample2->setPath('examples/example_one/example_one.ino');
        $defaultExample2->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($defaultExample2);

        /*
         * Get mock version 1.0.0 of Multi Ino Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $multiInoLibraryVersion1 */
        $multiInoLibraryVersion1 = $this->getReference('MultiInoLibraryVersion1');

        /*
         * Mock a new library example
         */
        $multiInoLibraryExample1 = new LibraryExample();
        $multiInoLibraryExample1->setName('multi_ino_example');
        $multiInoLibraryExample1->setVersion($multiInoLibraryVersion1);
        $multiInoLibraryExample1->setPath('examples/multi_ino_example/multi_ino_example.ino');
        $multiInoLibraryExample1->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($multiInoLibraryExample1);

        /*
         * Get mock version 2.0.0 of Multi Ino Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $multiInoLibraryVersion2 */
        $multiInoLibraryVersion2 = $this->getReference('MultiInoLibraryVersion2');

        /*
         * Mock a new library example
         */
        $multiInoLibraryExample2 = new LibraryExample();
        $multiInoLibraryExample2->setName('multi_ino_example');
        $multiInoLibraryExample2->setVersion($multiInoLibraryVersion2);
        $multiInoLibraryExample2->setPath('examples/multi_ino_example/multi_ino_example.ino');
        $multiInoLibraryExample2->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($multiInoLibraryExample2);

        /*
         * Get mock version 1 of Sub Category Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $subcategLibraryVersion1 */
        $subcategLibraryVersion1 = $this->getReference('SubCategLibraryVersion1');

        /*
         * Mock a new library example
         */
        $subcategLibraryExample1 = new LibraryExample();
        $subcategLibraryExample1->setName('subcateg_example_one');
        $subcategLibraryExample1->setVersion($subcategLibraryVersion1);
        $subcategLibraryExample1->setPath('Examples/subcateg_example_one/subcateg_example_one.ino');
        $subcategLibraryExample1->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($subcategLibraryExample1);

        /*
         * Mock a new library example
         */
        $subcategLibraryExample2 = new LibraryExample();
        $subcategLibraryExample2->setName('subcateg_example_two');
        $subcategLibraryExample2->setVersion($subcategLibraryVersion1);
        $subcategLibraryExample2->setPath('Examples/experienceBased/Beginners/subcateg_example_two/subcateg_example_two.ino');
        $subcategLibraryExample2->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($subcategLibraryExample2);

        /*
         * Mock a new library example
         */
        $subcategLibraryExample3 = new LibraryExample();
        $subcategLibraryExample3->setName('subcateg_example_three');
        $subcategLibraryExample3->setVersion($subcategLibraryVersion1);
        $subcategLibraryExample3->setPath('Examples/experienceBased/Advanced/Experts/subcateg_example_three/subcateg_example_three.ino');
        $subcategLibraryExample3->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($subcategLibraryExample3);

        /*
         * Get mock version 1.5.2 of Sub Category Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $subcategLibraryVersion2 */
        $subcategLibraryVersion2 = $this->getReference('SubCategLibraryVersion2');

        /*
         * Mock a new library example
         */
        $subcategLibraryExample4 = new LibraryExample();
        $subcategLibraryExample4->setName('subcateg_example_one');
        $subcategLibraryExample4->setVersion($subcategLibraryVersion2);
        $subcategLibraryExample4->setPath('Examples/subcateg_example_one/subcateg_example_one.ino');
        $subcategLibraryExample4->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($subcategLibraryExample4);

        /*
         * Mock a new library example
         */
        $subcategLibraryExample5 = new LibraryExample();
        $subcategLibraryExample5->setName('subcateg_example_two');
        $subcategLibraryExample5->setVersion($subcategLibraryVersion2);
        $subcategLibraryExample5->setPath('Examples/experienceBased/Beginners/subcateg_example_two/subcateg_example_two.ino');
        $subcategLibraryExample5->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($subcategLibraryExample5);

        /*
         * Mock a new library example
         */
        $subcategLibraryExample6 = new LibraryExample();
        $subcategLibraryExample6->setName('subcateg_example_three');
        $subcategLibraryExample6->setVersion($subcategLibraryVersion2);
        $subcategLibraryExample6->setPath('Examples/experienceBased/Advanced/Experts/subcateg_example_three/subcateg_example_three.ino');
        $subcategLibraryExample6->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($subcategLibraryExample6);

        /*
         * Get Version 1.0.0 of Hidden Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $hiddenFilesLibraryVersion1 */
        $hiddenFilesLibraryVersion1 = $this->getReference('HiddenLibraryVersion1');

        /*
         * Mock a new library example
         */
        $hiddenFilesLibraryExample = new LibraryExample();
        $hiddenFilesLibraryExample->setName('hidden_files_example');
        $hiddenFilesLibraryExample->setVersion($hiddenFilesLibraryVersion1);
        $hiddenFilesLibraryExample->setPath('examples/hidden_files_example/hidden_files_example.ino');
        $hiddenFilesLibraryExample->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($hiddenFilesLibraryExample);

        /*
         * Get Version 1.0.0 of Encode Library
         */
        /* @var \Codebender\LibraryBundle\Entity\Version $encodeLibraryVersion1 */
        $encodeLibraryVersion1 = $this->getReference('EncodeLibraryVersion1');

        /*
         * Mock a new library example
         */
        $encodeLibraryExample = new LibraryExample();
        $encodeLibraryExample->setName('encoded_example');
        $encodeLibraryExample->setVersion($encodeLibraryVersion1);
        $encodeLibraryExample->setPath('examples/encoded_example/encoded_example.ino');
        $encodeLibraryExample->setBoards(null);

        /*
         * Add newly created example to the database using the object manager interface
         */
        $objectManager->persist($encodeLibraryExample);


        // From here on add the internal library examples. Only few are added.
        $builtInLibs = [
            "EEPROM" => ["eeprom_clear", "eeprom_read", "eeprom_write"],
            "Robot_Control" => ["explore", "learn"],
            "WiFi" => ["ConnectNoEncryption", "ScanNetworks", "WiFiPachubeClient", "WiFiUdpNtpClient", "WiFiWebClientRepeating",
                "ConnectWithWEP", "SimpleWebServerWiFi", "WiFiPachubeClientString", "WiFiUdpSendReceiveString", "WiFiWebServer",
                "ConnectWithWPA", "WiFiChatServer", "WiFiTwitterClient", "WiFiWebClient"]
        ];
        $builtInDefaultVersion = 'default';
        foreach ($builtInLibs as $name => $examples) {
            $builtInLibVersion = $this->getReference($name . ucfirst($builtInDefaultVersion) . 'Version');

            foreach ($examples as $example) {
                $builtInLibExample = new LibraryExample();
                $builtInLibExample->setName($example);
                $builtInLibExample->setVersion($builtInLibVersion);
                $builtInLibExample->setPath('examples/' . $example . '/' . $example . '.ino');
                $builtInLibExample->setBoards(null);

                $objectManager->persist($builtInLibExample);
            }
        }

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
        return 7;
    }
}

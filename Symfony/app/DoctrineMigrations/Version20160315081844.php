<?php

namespace Application\Migrations;

use Codebender\LibraryBundle\Entity\Architecture;
use Codebender\LibraryBundle\Entity\Example;
use Codebender\LibraryBundle\Entity\ExternalLibrary;
use Codebender\LibraryBundle\Entity\Library;
use Codebender\LibraryBundle\Entity\LibraryExample;
use Codebender\LibraryBundle\Entity\Partner;
use Codebender\LibraryBundle\Entity\Preference;
use Codebender\LibraryBundle\Entity\Version;
use Codebender\LibraryBundle\Handler\DefaultHandler;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160315081844 extends AbstractMigration implements ContainerAwareInterface
{
    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Library ADD is_built_in TINYINT(1) NOT NULL');
    }

    public function postUp(Schema $schema)
    {
        /* @var EntityManager $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        /*
         * 1. Create the AVR architecture that is supported by all existing libraries
         */
        $avrArchitecture = new Architecture();
        $avrArchitecture->setName('AVR');
        $entityManager->persist($avrArchitecture);
        $entityManager->flush();

        /*
         * 2. Migrate existing external libraries and add AVR architecture to them
         */
        $externalLibraries = $entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')
            ->findAll();
        /* @var ExternalLibrary $externalLibrary */
        foreach ($externalLibraries as $externalLibrary) {
            $defaultHeader = $externalLibrary->getMachineName();
            print("Migrating external lib: $defaultHeader\n");

            // Do not migrate the SD external library
            if ($defaultHeader === 'SD') continue;

            /*
             * Migrate all the existing attributes
             */
            $library = new Library();
            $library->setName($externalLibrary->getHumanName());
            $library->setDefaultHeader($externalLibrary->getMachineName());
            $library->setFolderName($externalLibrary->getMachineName());
            $library->setDescription($externalLibrary->getDescription());
            $library->setOwner($externalLibrary->getOwner());
            $library->setRepo($externalLibrary->getRepo());
            $library->setBranch($externalLibrary->getBranch());
            $library->setInRepoPath($externalLibrary->getInRepoPath());
            $library->setNotes($externalLibrary->getNotes());
            $library->setVerified($externalLibrary->getVerified());
            $library->setActive($externalLibrary->getActive());
            $library->setLastCommit($externalLibrary->getLastCommit());
            $library->setUrl($externalLibrary->getUrl());
            $library->setIsBuiltIn(False);

            $version = new Version();
            $versionField = '1.0.0';
            $version->setLibrary($library);
            $version->setVersion($versionField);
            $version->setDescription($externalLibrary->getDescription());
            $version->setNotes($externalLibrary->getNotes());
            $version->setSourceUrl($externalLibrary->getSourceUrl());
            $version->setFolderName($versionField);
            $version->addArchitecture($avrArchitecture);

            $examples = $entityManager->getRepository('CodebenderLibraryBundle:Example')
                ->findBy(['library' => $externalLibrary]);
            /* @var Example $example */
            foreach ($examples as $example) {
                $position = strpos($example->getPath(), '/');
                $newExamplePath = substr($example->getPath(), $position + 1);

                $libraryExample = new LibraryExample();
                $libraryExample->setName($example->getName());
                $libraryExample->setVersion($version);
                $libraryExample->setBoards($example->getBoards());
                $libraryExample->setPath($newExamplePath);

                $entityManager->persist($libraryExample);
            }

            $library->addVersion($version);
            $library->setLatestVersion($version);

            /*
             * Persist and move library files
             */
            $entityManager->persist($library);
            $entityManager->persist($version);
            $this->moveExternalLibraryFiles($defaultHeader, $versionField);
        }
        $entityManager->flush();

        /*
         * 3. Migrate existing built-in libraries as external libraries and add AVR architecture to them
         */
        $builtInVersion = '1.0.5';
        $builtInLibrariesPath = $this->container->getParameter('builtin_libraries') . '/libraries';
        $finder = new Finder();
        $finder->depth(0);
        /* @var SplFileInfo $builtInLibrary */
        foreach ($finder->in($builtInLibrariesPath) as $builtInLibrary) {
            /*
             * Migrate any existing attributes
             */
            $defaultHeader = $builtInLibrary->getFilename();
            print("Migrating built-in lib: $defaultHeader\n");

            $library = new Library();
            $library->setName($defaultHeader);
            $library->setDefaultHeader($defaultHeader);
            $library->setFolderName($defaultHeader);
            $library->setDescription($defaultHeader . ' v' . $builtInVersion);
            $library->setVerified(True);
            $library->setActive(True);
            $library->setIsBuiltIn(True);

            $version = new Version();
            $version->setLibrary($library);
            $version->setVersion($builtInVersion);
            $version->setFolderName($builtInVersion);
            $version->addArchitecture($avrArchitecture);

            /* @var DefaultHandler $handler */
            $handler = $this->container->get('codebender_library.handler');
            $examples = $handler->fetchLibraryExamples(new Finder(), $builtInLibrary->getPathname());
            foreach ($examples as $example) {
                $libraryExample = new LibraryExample();
                $libraryExample->setVersion($version);
                $libraryExample->setName(pathinfo($example['filename'])['filename']);
                $libraryExample->setPath($example['filename']);
                $libraryExample->setBoards(null);

                $entityManager->persist($libraryExample);
            }

            $library->addVersion($version);
            $library->setLatestVersion($version);

            /*
             * Persist and move library files
             */
            $entityManager->persist($library);
            $entityManager->persist($version);
            $this->moveBuiltInLibraryFiles($builtInLibrary, $builtInVersion);
        }
        $entityManager->flush();

        /*
         * 4. Migrate existing authorization key
         */
        $authorizationKey = $this->container->getParameter('authorizationKey');
        $codebender = new Partner();
        $codebender->setName('Codebender');
        $codebender->setAuthKey($authorizationKey);
        $entityManager->persist($codebender);
        $entityManager->flush();

        /*
         * 5. Set all existing versions as the preferred version for Codebender
         */
        $libraries = $entityManager->getRepository('CodebenderLibraryBundle:Library')->findAll();
        /* @var Library $library */
        foreach ($libraries as $library) {
            $preference = new Preference();
            $preference->setLibrary($library);
            $preference->setVersion($library->getLatestVersion());
            $codebender->addPreference($preference);

            $entityManager->persist($preference);
            $entityManager->persist($codebender);
        }
        $entityManager->flush();
    }

    /**
     * This method moves an existing built-in library folder from the given sourceFolder to its
     * new location. The old directory is removed after this operation.
     *
     * @param SplFileInfo $sourceFolder
     * @param $version
     */
    private function moveBuiltInLibraryFiles(SplFileInfo $sourceFolder, $version)
    {
        $defaultHeader = $sourceFolder->getFilename();

        /* @var Filesystem $filesystem */
        $filesystem = new Filesystem();
        $sourcePath = $sourceFolder->getPathname();
        $destinationRootDirectory = $this->container->getParameter('external_libraries_new');
        $destinationPath = $destinationRootDirectory . '/' . $defaultHeader . '/' . $version;
        $filesystem->mirror($sourcePath, $destinationPath);
        $filesystem->remove($sourcePath);
    }

    /**
     * This method moves an existing external library folder from the existing location to its
     * new location. The old directory is removed after this operation.
     *
     * @param $defaultHeader
     * @param $version
     */
    private function moveExternalLibraryFiles($defaultHeader, $version)
    {
        /* @var Filesystem $filesystem */
        $filesystem = new Filesystem();
        $sourceRootDirectory = $this->container->getParameter('external_libraries');
        $destinationRootDirectory = $this->container->getParameter('external_libraries_new');
        $sourcePath = $sourceRootDirectory . '/' . $defaultHeader;
        $destinationPath = $destinationRootDirectory . '/' . $defaultHeader . '/' . $version;
        $filesystem->mirror($sourcePath, $destinationPath);
        $filesystem->remove($sourcePath);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Library DROP is_built_in');
    }
}

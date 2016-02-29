<?php

namespace Codebender\LibraryBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;

/**
 * Class LoadExternalLibraryFilesCommand
 * Moves the fixture library & example files to the external libraries directory
 * In order to run the command, insert the following on the command line:
 * `php app/console codebender:library_files:install`
 *
 * @package Codebender\LibraryBundle\Command
 * @SuppressWarnings(PHPMD)
 */
class LoadExternalLibraryFilesCommand extends ContainerAwareCommand
{
    /**
     * Configures the command name, usage, etc
     */
    public function configure()
    {
        $this->setName('codebender:library_files:install')
            ->setDescription('Copies fixture library & example files to the external libraries directory');
    }

    /**
     * Copies fixture library files to the `external_libraries` directory.
     * Throws an InvalidConfigurationException if the external libraries path
     * parameter is not properly configured.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     * @throws InvalidConfigurationException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var ContainerInterface $container */
        $container = $this->getContainer();
        $paths = ['external_libraries' => 'library_files', 'external_libraries_new' => 'library_files_new'];
        foreach ($paths as $path => $source) {
            $externalLibrariesPath = $container->getParameter($path);
            if ($externalLibrariesPath === null || $externalLibrariesPath == '') {
                throw new InvalidConfigurationException('Parameter `' . $path . '` is not properly set. Please double check your configuration files.');
            }

            $copyResult = $this->copyExternalLibraryFiles($externalLibrariesPath, $source);

            if ($copyResult['success'] != true) {
                $output->writeln('<error>' . $copyResult['error'] . '</error>');
                return;
            }
            $output->writeln('<info>Fixture libraries data moved successfully to the `' . $path . '` directory.</info>');
        }
        return;
    }

    /**
     * Performs the actual copying of fixture library files using Symfony's FileSystem component.
     * Deletes all libraries from the libraries directory, and the copies there all the fixtures.
     * Thus, we can be sure that the test data will always be as expected.
     *
     * @param $externalLibrariesPath
     * @return array
     */
    protected function copyExternalLibraryFiles($externalLibrariesPath, $externalLibrariesSource)
    {
        $fixturesPath = $this->getApplication()->getKernel()
            ->locateResource('@CodebenderLibraryBundle/Resources/' . $externalLibrariesSource);

        $filesystem = new Filesystem();

        /*
         * Locate all the libraries in the external libraries directory and remove them.
         */
        $finder = new Finder();
        $finder->depth(0);
        try {
            foreach ($finder->in($externalLibrariesPath) as $existingLibrary) {
                /*
                 * Each iteration provides an SplFileInfo object.
                 */
                $filesystem->remove($externalLibrariesPath . '/' . $existingLibrary->getFilename());
            }

            /*
             * Then copy all the files from the fixtures directory to the actual
             * external libraries directory.
             */
            $filesystem->mirror($fixturesPath, $externalLibrariesPath);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'An error occured while copying the fixture files: ' . $e->getMessage()];
        }

        return ['success' => true];
    }
}
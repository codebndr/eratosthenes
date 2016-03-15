<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Symfony\Component\Finder\Finder;

class ListApiCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        /*
         * External libraries list is fetched from the database, because we need to list
         * active libraries only
         */
        $externalLibraries = $this->getLibraryList();

        $arduinoLibraryFiles = $this->container->getParameter('builtin_libraries') . "/";
        $builtinExamples = $this->getLibariesListFromDir($arduinoLibraryFiles . "examples");

        ksort($builtinExamples);
        ksort($externalLibraries['Builtin Libraries']);
        ksort($externalLibraries['External Libraries']);

        return [
            'success' => true,
            'text' => 'Successful Request!',
            'categories' => [
                'Examples' => $builtinExamples,
                'Builtin Libraries' => $externalLibraries['Builtin Libraries'],
                'External Libraries' => $externalLibraries['External Libraries']
            ]
        ];
    }

    private function getLibariesListFromDir($path)
    {
        $finder = new Finder();
        $finder->files()->name('*.ino')->name('*.pde');
        $finder->in($path);
        $libraries = array();
        foreach ($finder as $file) {
            $names = $this
                ->getExampleAndLibNameFromRelativePath(
                    $file->getRelativePath(),
                    $file->getBasename("." . $file->getExtension())
                );
            if (!isset($libraries[$names['library_name']])) {
                $libraries[$names['library_name']] = array("description" => "", "examples" => array());
            }
            $libraries[$names['library_name']]['examples'][] = array('name' => $names['example_name']);
        }
        return $libraries;
    }

    private function getLibraryList()
    {
        $entityManager = $this->getDoctrine()->getManager();
        $externalMeta = $entityManager
            ->getRepository('CodebenderLibraryBundle:Library')
            ->findBy(array('active' => true));

        $libraries = ['Builtin Libraries' => [], 'External Libraries' => []];
        foreach ($externalMeta as $library) {
            if ($library->isBuiltIn()) {
                $category = 'Builtin Libraries';
            } else {
                $category = 'External Libraries';
            }

            $defaultHeader = $library->getDefaultHeader();

            $libraries[$category][$defaultHeader] = array();

            $versions = $library->getVersions();
            foreach ($versions as $version) {
                $libraries[$category][$defaultHeader][$version->getVersion()] = array(
                    "description" => $library->getDescription(),
                    "name" => $library->getName(),
                    "url" => "http://github.com/" . $library->getOwner() . "/" . $library->getRepo(),
                    "examples" => array()
                );

                $examples = $entityManager
                    ->getRepository('CodebenderLibraryBundle:LibraryExample')
                    ->findBy(array('version' => $version->getId()));

                foreach ($examples as $example) {
                    $names = $this
                        ->getExampleAndLibNameFromRelativePath(
                            pathinfo($example->getPath(), PATHINFO_DIRNAME),
                            $example->getName()
                        );

                    $libraries[$category][$defaultHeader][$version->getVersion()]['examples'][] = $names['example_name'];
                }
            }
        }

        return $libraries;
    }

    /*
     * Copied from DefaultController.php
     */
    private function getExampleAndLibNameFromRelativePath($path, $filename)
    {
        $type = "";
        $libraryName = strtok($path, "/");

        $tmp = strtok("/");

        while ($tmp != "" && !($tmp === false)) {
            if ($tmp != 'examples' && $tmp != 'Examples' && $tmp != $filename) {
                if ($type == "") {
                    $type = $tmp;
                } else {
                    $type = $type . ":" . $tmp;
                }
            }
            $tmp = strtok("/");
        }
        $exampleName = ($type == "" ? $filename : $type . ":" . $filename);
        return (array('library_name' => $libraryName, 'example_name' => $exampleName));
    }
}

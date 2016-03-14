<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Entity\Version;
use Codebender\LibraryBundle\Handler\ApiHandler;
use Symfony\Component\Finder\Finder;

class GetExampleCodeCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        if (!array_key_exists('library', $content) || !array_key_exists('example', $content)) {
            return ['success' => false, 'message' => 'Incorrect request fields'];
        }
        $library = $content['library'];
        $example = $content['example'];

        // TODO: use a default version if version is not given in the request
        $version = array_key_exists('version', $content) ? $content['version'] : '';

        /* @var ApiHandler $handler */
        $handler = $this->get('codebender_library.apiHandler');

        $type = $handler->getLibraryType($library);
        if ($type === 'unknown') {
            return ['success' => false, 'message' => 'Requested library named ' . $library . ' not found'];
        }

        if ($type === 'external' && !$handler->libraryVersionExists($library, $version)) {
            return ['success' => false, 'message' => 'Requested library (version) does not exist'];
        }

        switch ($type) {
            case 'builtin':
                $example = $this->getExternalExampleCode($library, $version, $example);
                break;
            case 'external':
                $example = $this->getExternalExampleCode($library, $version, $example);
                break;
            case 'example':
                $dir = $handler->getBuiltInLibraryExamplePath($library);
                $example = $this->getExampleCodeFromDir($dir, $example);
                break;
        }
        return $example;
    }

    /**
     * Retrieve example files data for the requested external library example
     *
     * @param $library
     * @param $version
     * @param $example
     * @return array
     */
    private function getExternalExampleCode($library, $version, $example)
    {
        /* @var ApiHandler $handler */
        $handler = $this->get('codebender_library.apiHandler');

        $exampleMeta = $handler->getExampleForExternalLibrary($library, $version, $example);

        if (count($exampleMeta) === 0) {
            $example = str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $exampleMeta = $handler->getExampleForExternalLibrary($library, $version, $filename);

            if (count($exampleMeta) > 1) {
                $meta = null;
                foreach ($exampleMeta as $e) {
                    $path = $e->getPath();
                    if (!(strpos($path, $example) === false)) {
                        $meta = $e;
                        break;
                    }
                }
                if (!$meta) {
                    return ['success' => false, 'message' => 'Could not retrieve the requested example'];
                }
            } elseif (count($exampleMeta) === 0) {
                return ['success' => false, 'message' => 'Could not retrieve the requested example'];
            } else {
                $meta = $exampleMeta[0];
            }
        } else {
            $meta = $exampleMeta[0];
        }

        $fullPath = $this->getPathForExternalExample($meta);
        $path = pathinfo($fullPath, PATHINFO_DIRNAME);
        $files = $this->getExampleFilesFromDir($path);
        return $files;
    }

    /**
     * Try retrieve example codes for given example name from
     * a specified directory
     *
     * @param $dir
     * @param $example
     * @return array
     */
    private function getExampleCodeFromDir($dir, $example)
    {
        $finder = new Finder();
        $finder->in($dir);
        $finder->name($example . ".ino", $example . ".pde");

        if (iterator_count($finder) === 0) {
            $example = str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $finder->name($filename . ".ino", $filename . ".pde");
            if (iterator_count($finder) > 1) {
                $filesPath = null;
                foreach ($finder as $e) {
                    $path = $e->getPath();
                    if (!(strpos($path, $example) === false)) {
                        $filesPath = $e;
                        break;
                    }
                }
                if (!$filesPath) {
                    return ['success' => false, 'message' => 'Could not retrieve the requested example'];
                }
            } elseif (iterator_count($finder) === 0) {
                return ['success' => false, 'message' => 'Could not retrieve the requested example'];
            } else {
                $filesPathIterator = iterator_to_array($finder, false);
                $filesPath = $filesPathIterator[0]->getPath();
            }
        } else {
            $filesPathIterator = iterator_to_array($finder, false);
            $filesPath = $filesPathIterator[0]->getPath();
        }
        $files = $this->getExampleFilesFromDir($filesPath);
        return $files;
    }

    /**
     * Retrieve example files data from the given directory
     *
     * @param $dir
     * @return array
     */
    private function getExampleFilesFromDir($dir)
    {
        $filesFinder = new Finder();
        $filesFinder->in($dir);
        $filesFinder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S')->name('*.pde')->name('*.ino');

        $files = array();
        foreach ($filesFinder as $file) {
            if ($file->getExtension() === "pde") {
                $name = $file->getBasename("pde") . "ino";
            } else {
                $name = $file->getFilename();
            }

            $files[] = array(
                "filename" => $name,
                "code" => (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents()
            );

        }

        return ['success' => true, "files" => $files];
    }

    /**
     * Construct the full path for a given example entity
     *
     * @param $example
     * @return string
     */
    private function getPathForExternalExample($example)
    {
        $externalLibraryPath = $this->container->getParameter('external_libraries_new');
        $libraryFolder = $example->getVersion()->getLibrary()->getFolderName();
        $versionFolder = $example->getVersion()->getFolderName();

        $fullPath = $externalLibraryPath . '/' . $libraryFolder . '/' . $versionFolder . '/' . $example->getPath();
        return $fullPath;
    }
}

<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Handler\ApiHandler;
use Symfony\Component\Finder\Finder;

class GetExamplesCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        if (!array_key_exists('library', $content)) {
            return ['success' => false, 'message' => 'Incorrect request fields'];
        }
        $library = $content['library'];

        // TODO: use a default version if version is not given in the request
        $version = array_key_exists('version', $content) ? $content['version'] : '';

        /* @var ApiHandler $handler */
        $handler = $this->get('codebender_library.apiHandler');
        $type = $handler->getLibraryType($library);

        if ($type === 'unknown') {
            return ['success' => false, 'message' => 'Requested library named ' . $library . ' not found'];
        }


        if (!$handler->libraryVersionExists($library, $version)) {
            return ['success' => false, 'message' => 'Requested version for library ' . $library . ' not found'];
        }

        $path = "";
        /*
         * Assume the requested library is an example
         */
        $path = $handler->getBuiltInLibraryExamplePath($library);
        if ($type === 'external') {
            $path = $handler->getExternalLibraryPath($library, $version);
        }
        if ($type === 'builtin') {
            $path = $handler->getBuiltInLibraryPath($library);
        }

        $examples = $this->getExampleFilesFromPath($path);

        return ['success' => true, 'examples' => $examples];
    }

    /**
     * Collect information of all example files from the given path
     *
     * @param $path
     * @return array
     */
    private function getExampleFilesFromPath($path)
    {

        $inoFinder = new Finder();
        $inoFinder->in($path);
        $inoFinder->name('*.ino')->name('*.pde');

        // TODO: Not only .h and .cpp files in Arduino examples
        $notInoFilesFinder = new Finder();
        $notInoFilesFinder->files()->name('*.h')->name('*.cpp');

        $examples = array();

        foreach ($inoFinder as $example) {
            $files = array();

            $content = (!mb_check_encoding($example->getContents(), 'UTF-8')) ? mb_convert_encoding($example->getContents(), "UTF-8") : $example->getContents();
            $pathInfo = pathinfo($example->getBaseName());
            $files[] = array(
                "filename" => $pathInfo['filename'] . '.ino',
                "content" => (!mb_check_encoding($content, 'UTF-8')) ? mb_convert_encoding($content, "UTF-8") : $content
            );

            // get non-ino files
            $notInoFilesFinder->in($path . "/" . $example->getRelativePath());

            foreach ($notInoFilesFinder as $nonInoFile) {
                $files[] = array(
                    "filename" => $nonInoFile->getBaseName(),
                    "content" => (!mb_check_encoding($nonInoFile->getContents(), 'UTF-8')) ? mb_convert_encoding($nonInoFile->getContents(), "UTF-8") : $nonInoFile->getContents()
                );
            }

            $dir = preg_replace('/[E|e]xamples\//', '', $example->getRelativePath());
            $dir = str_replace($pathInfo['filename'], '', $dir);
            $dir = str_replace('/', ':', $dir);
            if ($dir != '' && substr($dir, -1) != ':') {
                $dir .= ':';
            }

            $examples[$dir . $pathInfo['filename']] = $files;
        }

        return $examples;
    }
}

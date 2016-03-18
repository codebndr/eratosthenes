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
<<<<<<< HEAD
        $version = array_key_exists('version', $content) ? $content['version'] : '';
=======
        $version = '';
        if (array_key_exists('version', $content)) {
            $version = $content['version'];
        }
>>>>>>> origin/v2-api-development

        /* @var ApiHandler $handler */
        $handler = $this->get('codebender_library.apiHandler');
        $type = $handler->getLibraryType($library);

        if ($type === 'unknown') {
<<<<<<< HEAD
            return ['success' => false, 'message' => 'Requested library named ' . $library . ' not found'];
        }


        if (!$handler->libraryVersionExists($library, $version)) {
            return ['success' => false, 'message' => 'Requested version for library ' . $library . ' not found'];
        }

        $path = "";
=======
            return ['success' => false, 'message' => "Requested library named $library not found"];
        }

        if (!$handler->libraryVersionExists($library, $version)) {
            return ['success' => false, 'message' => "Requested version for library $library not found"];
        }

>>>>>>> origin/v2-api-development
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

<<<<<<< HEAD
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
=======
            $content = (!mb_check_encoding($example->getContents(), 'UTF-8')) ? mb_convert_encoding($example->getContents(), 'UTF-8') : $example->getContents();
            $pathInfo = pathinfo($example->getBaseName());
            $files[] = array(
                'filename' => $pathInfo['filename'] . '.ino',
                'content' => (!mb_check_encoding($content, 'UTF-8')) ? mb_convert_encoding($content, 'UTF-8') : $content
            );

            // get non-ino files
            $notInoFilesFinder->in($path . '/' . $example->getRelativePath());

            foreach ($notInoFilesFinder as $nonInoFile) {
                $files[] = array(
                    'filename' => $nonInoFile->getBaseName(),
                    'content' => (!mb_check_encoding($nonInoFile->getContents(), 'UTF-8')) ? mb_convert_encoding($nonInoFile->getContents(), 'UTF-8') : $nonInoFile->getContents()
>>>>>>> origin/v2-api-development
                );
            }

            $dir = preg_replace('/[E|e]xamples\//', '', $example->getRelativePath());
            $dir = str_replace($pathInfo['filename'], '', $dir);
            $dir = str_replace('/', ':', $dir);
<<<<<<< HEAD
            if ($dir != '' && substr($dir, -1) != ':') {
=======
            if ($dir !== '' && substr($dir, -1) !== ':') {
>>>>>>> origin/v2-api-development
                $dir .= ':';
            }

            $examples[$dir . $pathInfo['filename']] = $files;
        }

        return $examples;
    }
}

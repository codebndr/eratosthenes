<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Codebender\LibraryBundle\Entity\Version;
use Symfony\Component\Finder\Finder;

class FetchApiCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        $content = $this->setDefault($content);
        $filename = $content['library'];

        $last_slash = strrpos($filename, "/");
        if ($last_slash !== false) {
            $filename = substr($filename, $last_slash + 1);
        }

        $apiHandler = $this->container->get('codebender_library.apiHandler');

        $builtinLibrariesPath = $this->container->getParameter('builtin_libraries');
        $externalLibrariesPath = $this->container->getParameter('external_libraries_new');

        $finder = new Finder();
        $exampleFinder = new Finder();

        //TODO handle the case of different .h filenames and folder names
        $reservedNames = ["ArduinoRobot" => "Robot_Control", "ArduinoRobotMotorBoard" => "Robot_Motor",
            "BlynkSimpleSerial" => "BlynkSimpleEthernet", "BlynkSimpleCC3000" => "BlynkSimpleEthernet"];
        if (array_key_exists($filename, $reservedNames)) {
            $filename = $reservedNames[$filename];
        }

        if ($apiHandler->isBuiltInLibrary($filename)) {
            $response = $this->fetchLibraryFiles($finder, $builtinLibrariesPath . "/libraries/" . $filename);

            if ($content['renderView']) {
                $examples = $this->fetchLibraryExamples($exampleFinder, $builtinLibrariesPath . "/libraries/" . $filename);
                $meta = [];
                $versions = [];
            }
        } else {
            if (!$apiHandler->isExternalLibrary($filename, $content['disabled'])) {
                return ["success" => false, "message" => "No Library named " . $filename . " found."];
            }

            // check if requested (if any) version is valid
            if ($content['version'] !== null && !$apiHandler->libraryVersionExists($filename, $content['version'])) {
                return [
                    'success' => false,
                    'message' => 'No files for Library named `' . $filename . '` with version `' . $content['version'] . '` found.'
                ];
            }

            $versionObjects = $apiHandler->getAllVersionsFromDefaultHeader($filename);

            // use the requested version (if any) for fetching data
            // else fetch data for all versions
            $versions = $versionObjects->toArray();
            if ($content['version'] !== null) {
                $versionsCollection = $versionObjects->filter(function ($version) use ($content) {
                    return $version->getVersion() === $content['version'];
                });
                $versions = $versionsCollection->toArray();
            }

            // fetch library files for each version
            $response = [];
            $examples = [];
            foreach ($versions as $version) {
                /* @var Version $version */
                $libraryPath = $externalLibrariesPath . "/" . $filename . "/" . $version->getFolderName();

                // fetch library files for this version
                $fetchResponse = $this->fetchLibraryFiles($finder->create(), $libraryPath);
                if (!empty($fetchResponse)) {
                    $response[$version->getVersion()] = $fetchResponse;
                }

                if ($content['renderView']) {
                    // fetch example files for this version if it's rendering view
                    $exampleResponse = $this->fetchLibraryExamples($exampleFinder->create(), $libraryPath);
                    if (!empty($exampleResponse)) {
                        $examples[$version->getVersion()] = $exampleResponse;
                    }
                }
            }

            if ($content['renderView']) {
                $externalLibrary = $this->entityManager->getRepository('CodebenderLibraryBundle:Library')
                    ->findOneBy(array('default_header' => $filename));
                $filename = $externalLibrary->getDefaultHeader();
                $meta = $externalLibrary->getLiraryMeta();
                $versions = array_map(
                    function ($version) {
                        return $version->getVersion();
                    },
                    $versions
                );
            }
        }

        if (!$content['renderView']) {
            return ['success' => true, 'message' => 'Library found', 'files' => $response];
        }

        return [
            'success' => true,
            'library' => $filename,
            'versions' => $versions,
            'files' => $response,
            'examples' => $examples,
            'meta' => $meta
        ];
    }

    private function setDefault($content)
    {
        $content['disabled'] = (array_key_exists('disabled', $content) ? $content['disabled'] : false);
        $content['version'] = (array_key_exists('version', $content) ? $content['version'] : null);
        $content['renderView'] = (array_key_exists('renderView', $content) ? $content['renderView'] : false);
        return $content;
    }

    private function fetchLibraryFiles($finder, $directory, $getContent = true)
    {
        if (!is_dir($directory)) {
            return array();
        }
        $finder->in($directory)->exclude('examples')->exclude('Examples');
        $finder->name('*.*');
        $finder->files(); // fetch only files

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $response = array();
        foreach ($finder as $file) {
            if ($getContent) {
                $mimeType = finfo_file($finfo, $file);
                if (strpos($mimeType, "text/") === false) {
                    $content = "/*\n *\n * We detected that this is not a text file.\n * Such files are currently not supported by our editor.\n * We're sorry for the inconvenience.\n * \n */";
                } else {
                    $content = (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents();
                }
                $response[] = array("filename" => $file->getRelativePathname(), "content" => $content);
            } else {
                $response[] = array("filename" => $file->getRelativePathname());
            }
        }
        return $response;
    }

    private function fetchLibraryExamples($finder, $directory)
    {
        if (is_dir($directory)) {
            $finder->in($directory);
            $finder->name('*.pde')->name('*.ino');

            $response = array();
            foreach ($finder as $file) {
                $response[] = array("filename" => $file->getRelativePathname(), "content" => (!mb_check_encoding($file->getContents(), 'UTF-8')) ? mb_convert_encoding($file->getContents(), "UTF-8") : $file->getContents());
            }

            return $response;
        }

    }
}

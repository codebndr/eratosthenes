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
            $response = $apiHandler->fetchLibraryFiles($finder, $builtinLibrariesPath . "/libraries/" . $filename);

            if ($content['renderView']) {
                $examples = $apiHandler->fetchLibraryExamples($exampleFinder, $builtinLibrariesPath . "/libraries/" . $filename);
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
                $fetchResponse = $apiHandler->fetchLibraryFiles($finder->create(), $libraryPath);
                if (!empty($fetchResponse)) {
                    $response[$version->getVersion()] = $fetchResponse;
                }

                if ($content['renderView']) {
                    // fetch example files for this version if it's rendering view
                    $exampleResponse = $apiHandler->fetchLibraryExamples($exampleFinder->create(), $libraryPath);
                    if (!empty($exampleResponse)) {
                        $examples[$version->getVersion()] = $exampleResponse;
                    }
                }
            }

            if ($content['renderView']) {
                $externalLibrary = $this->entityManager->getRepository('CodebenderLibraryBundle:Library')
                    ->findOneBy(array('default_header' => $filename));
                $filename = $externalLibrary->getDefaultHeader();
                $meta = $externalLibrary->getLibraryMeta();
                $versions = array_map(
                    function ($version) {
                        return $version->getVersionMeta();
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
}

<?php

namespace Codebender\LibraryBundle\Handler\ApiCommand;

use Symfony\Component\Finder\Finder;

class FetchApiCommand extends AbstractApiCommand
{
    public function execute($content)
    {
        $content = $this->setDefault($content);

        $apiHandler = $this->container->get('codebender_library.apiHandler');

        $builtinLibrariesPath = $this->container->getParameter('builtin_libraries');
        $externalLibrariesPath = $this->container->getParameter('external_libraries_new');

        $finder = new Finder();
        $exampleFinder = new Finder();

        $filename = $content['library'];

        $last_slash = strrpos($content['library'], "/");
        if ($last_slash !== false) {
            $filename = substr($content['library'], $last_slash + 1);
        }

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
            }
        } else {
            if (!$apiHandler->isExternalLibrary($filename)) {
                return ["success" => false, "message" => "No Library named " . $filename . " found."];
            }

            $response = $this->fetchLibraryFiles($finder, $externalLibrariesPath . "/" . $filename . "/" . $content['version']);
            if (empty($response)) {
                return ['success' => false, 'message' => 'No files for Library named `' . $filename . '` with version `' . $content['version'] . '` found.'];
            }

            if ($content['renderView']) {
                $examples = $this->fetchLibraryExamples($exampleFinder, $externalLibrariesPath . "/" . $filename . "/" . $content['version']);

                $externalLibrary = $this->entityManager->getRepository('CodebenderLibraryBundle:ExternalLibrary')
                    ->findOneBy(array('machineName' => $filename));
                $filename = $externalLibrary->getMachineName();
                $meta = $externalLibrary->getLiraryMeta();
            }
        }

        if (!$content['renderView']) {
            return ['success' => true, 'message' => 'Library found', 'files' => $response];
        }

        return [
            'success' => true,
            'library' => $filename,
            'files' => $response,
            'examples' => $examples,
            'meta' => $meta
        ];
    }

    private function setDefault($content)
    {
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
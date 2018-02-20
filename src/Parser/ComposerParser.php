<?php
namespace Lexide\Pharmacist\Parser;

class ComposerParser
{
    public function parse($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception("No composer file exists at '{$filename}'");
        }

        $array = json_decode(file_get_contents($filename), true);

        if (!$array) {
            throw new \Exception("Could not decode '{$filename}'. ".json_last_error_msg());
        }

        $result = new ComposerParserResult();
        $result->setNamespace($this->getNamespace($array));
        $result->setDirectory(dirname($filename));
        $result->setSyringeConfig($this->getSyringeConfig($array));
        $result->setChildren($this->getPuzzleChildren(dirname($filename)));
        return $result;
    }

    protected function getPuzzleChildren($dirname)
    {
        $composerFiles = glob($dirname."/vendor/*/*/composer.json");
        $children = [];
        foreach ($composerFiles as $filename) {
            $parsedComposer = $this->parse($filename);
            if ($parsedComposer->usesSyringe()) {
                $children[] = $parsedComposer;
            }
        }
        return $children;
    }

    protected function getNamespace($array)
    {
        return str_replace("/", "_", $array["name"]);
    }

    protected function getSyringeConfig($array)
    {
        $paths = [
            "extra",
            ["lexide/puzzle-di", "downsider-puzzle-di"],
            "lexide/syringe",
            "!files",
            "path"
        ];

        foreach ($paths as $directories) {
            if (!is_array($directories)) {
                $directories = [$directories];
            }
            $newArray = null;
            $isOptional = false;
            foreach ($directories as $directory) {

                $isOptional = false;
                if ($directory[0] == "!") {
                    $directory = substr($directory, 1);
                    $isOptional = true;
                }
                if (!isset($array[$directory])) {
                    continue;
                }
                $newArray = $array[$directory];
                break;
            }
            if (!isset($newArray)) {
                if ($isOptional) {
                    $newArray = $array;
                } else {
                    return false;
                }
            }
            $array = $newArray;
        }

        // Will return something like "config/syringe.yml"
        return $array;
    }
}

<?php

// src/Service/FileUploader.php
// deprecated.. to be replaced by "Flysystem" and "Uploader" annotation

namespace Base\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Config\Definition\Exception\Exception;

class FileUploader {

    public const IS_FILENAME  = 0;
    public const IS_PATH      = 1;

    private $targetDirectory;

    public function __construct(string $uploadDir) {
        $this->targetDirectory = $uploadDir;
    }

    public function upload($file, string $output, $output_type = self::IS_FILENAME)
    {
        if($this->isAbsolute($output))
            throw new Exception("Absolute path not allowed: " . $output);

        switch ($output_type)
        {
            case self::IS_FILENAME:
                break;

            case self::IS_PATH:
            default:
                $output = $output . "/" . md5(uniqid()) . '.' . $file->guessExtension();
        }

        if($file instanceof UploadedFile) {

            try { $file->move($this->getTargetDirectory(), $output); }
            catch (FileException $e) { throw new Exception($e->getMessage()); }
            $output = $this->getTargetDirectory() . "/" . $output;

        } else {

            $output = $this->getTargetDirectory() . "/" . $output;
            $content = file_get_contents($file);
            if (!$content) throw new Exception('File download error');
            else {

                $directory = dirname($output);
                if(!is_dir($directory)) mkdir($directory);

                file_put_contents($output, $content);
            }
        }

        return str_replace(dirname($this->getTargetDirectory()), "", $output);
    }

    protected function isAbsolute($path):bool {
        return str_starts_with($path, "/");
    }

    protected function isSafe($output) {
        $this->getTargetDirectory() . "" . $output;
    }
    public function getTargetDirectory() {
        return $this->targetDirectory;
    }
}
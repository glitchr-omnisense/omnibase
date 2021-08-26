<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @Annotation
 */
class FileSize extends Constraint
{
    public $message = 'file.maxSize';

    protected string $max;

    public function getMaxSize() { return $this->max; }
    public function getMaxSizeStr() { return $this->getSizeStr($this->max); }

    public static function getSizeStr($size)
    {
        $factorStr = "";
        $factor = intval($size / 1024);

        switch( $factor ) {

            case 0:
                $factor = 0;
                $factorStr = "";
                break;
            case 1:
                $factor = 1;
                $factorStr = "K";
                break;
            case 2:
                $factor = 2;
                $factorStr = "M";
                break;

            default:
            case 3:
                $factor = 3;
                $factorStr = "G";
                break;
        }

        return ($factor > 0 ? intval( $size/(1024*$factor) ).$factorStr : $size);
    }

    private static function parseFilesize($size)
    {
        if ('' === $size) {
            return 0;
        }

        $size = strtolower($size);

        $max = ltrim($size, '+');
        if (0 === strpos($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($size, -1)) {
            case 't':
                $max *= 1024;
                // no break
            case 'g':
                $max *= 1024;
                // no break
            case 'm':
                $max *= 1024;
                // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }

    public function __construct(array $options = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->max = $options["max"] ?? UploadedFile::getMaxFilesize();
        $this->max = min($this->max,  UploadedFile::getMaxFilesize());
        $this->max = self::parseFilesize($this->max);
    }
}

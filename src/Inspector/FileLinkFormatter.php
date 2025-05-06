<?php

namespace Base\Inspector;

use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter as SymfonyFileLinkFormatter;

class FileLinkFormatter extends SymfonyFileLinkFormatter
{
    public function format(string $file, int $line): string|false
    {
        if ($fmt = $this->getFileLinkFormat()) {
            for ($i = 1; isset($fmt[$i]); ++$i) {
                if (str_starts_with($file, $k = $fmt[$i++])) {
                    $file = substr_replace($file, $fmt[$i], 0, \strlen($k));
                    break;
                }
            }

            $dict = ['%f' => $file, '%l' => $line, '%b' => $this->baseDir];
            $fmt0 = preg_replace_callback(
                '/([^\/]+)\[([^\,\:\;]+)[,:;]([^\,\:\;]+)\]/',
                function ($matches) use (&$dict, $file) {
                    $from = strtr($matches[2], $dict);
                    $to = strtr($matches[3], $dict);
                    return str_replace($from, $to, strtr($matches[1], $dict));
                },
                $fmt[0]
            );

            return strtr($fmt0, $dict);
        }

        return false;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        $this->fileLinkFormat = $this->getFileLinkFormat();

        return ['fileLinkFormat', 'baseDir'];
    }
}

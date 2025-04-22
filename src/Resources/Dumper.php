<?php

namespace Base\Resources;

class Dumper extends \Symfony\Component\VarDumper\Dumper\HtmlDumper
{
    protected function style(string $style, string $value, array $attr = []): string
    {
        if(array_key_exists("file", $attr) && \array_key_exists("project_dir", $attr))
        {
            $attr["file"] = \relative_path($attr["file"], $attr["project_dir"]);
            unset($attr["project_dir"]);
        }
        
        return parent::style($style, $value, $attr);
    }

    protected function dumpLine(int $depth, bool $endOfValue = false): void
    {
        $dumpPrefixBak = $this->dumpPrefix;
        $this->dumpPrefix .= "<span>".\benchmark()."</span><hr>";

        parent::dumpLine($depth, $endOfValue);
        $this->dumpPrefix = $dumpPrefixBak;
    }
}
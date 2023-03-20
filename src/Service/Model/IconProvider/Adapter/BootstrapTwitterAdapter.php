<?php

namespace Base\Service\Model\IconProvider\Adapter;

use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\AbstractIconAdapter;

class BootstrapTwitterAdapter extends AbstractIconAdapter
{
    public const STYLE_REGULAR = "regular";
    public const STYLE_FILL    = "filled";

    public static function getName(): string
    {
        return "bi";
    }
    public static function getOptions(): array
    {
        return [];
    }

    /** @var ?string */
    protected ?string $stylesheet;

    public function __construct(string $metadata, string $cacheDir, ?string $stylesheet = null)
    {
        $this->metadata = $metadata;
        $this->stylesheet = $stylesheet;

        parent::__construct($cacheDir);
    }

    public function getAssets(): array
    {
        return [$this->stylesheet];
    }

    public function supports(IconizeInterface|string|null $icon): bool
    {
        if ($icon === null) {
            return false;
        }

        if ($icon instanceof IconizeInterface) {
            $icon = $icon->__iconize() ?? $icon->__iconizeStatic();
            $icon = first($icon);
        }

        return count(array_filter(explode(" ", $icon), fn ($id) => $id == $this->getName()));
    }

    public function warmUp(string $cacheDir): bool
    {
        parent::warmUp($cacheDir);

        $this->version = $this->getCache("/Version", function () {
            if (!preg_match('/.*\/([0-9.]*(?:[-_]{1}[a-zA-Z0-9]*)?)\//', $this->metadata ?? "", $matches)) {
                return "unk.";
            }

            return $matches[1] ?? "";
        });

        return true;
    }

    public function getChoices(string $term = ""): array
    {
        $choices = [];
        foreach ($this->getEntries() as $key => $unicode) {
            $label = str_replace("-", " ", $key);
            $style = str_ends_with($key, "-fill") ? self::STYLE_FILL : self::STYLE_REGULAR;
            if ($style == self::STYLE_FILL) {
                $label  = substr($label, 0, strlen($label) - 5);
            }

            $label = ucwords($label);

            $term = mb_strtolower($term);
            $termFound = str_contains(mb_strtolower($label), $term);

            if (!$termFound) {
                continue;
            }
            $choices[mb_ucfirst($style)." Style"][$label] = $this->getName()." ".$this->getName()."-".$key;
        }

        return $choices;
    }

    public function getStyle(string $name)
    {
        return array_filter(explode(" ", $name), fn ($n) => in_array($n, [self::STYLE_FILL]))[0] ?? null;
    }

    public function getIdentifier(string $name)
    {
        return array_transforms(
            fn ($k, $v, $callback, $i): ?array => preg_match("/".$this->getName()."-(.*)/", $v, $matches) ? [$i, $matches[1]] : null,
            explode(" ", $name)
        )[0];
    }

    public function getValues()
    {
        return array_keys($this->contents);
    }
    public function getValue(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, $this->contents)) {
            return "";
        }
        return $identifier;
    }

    public function getLabels()
    {
        return array_map(function ($icon) {
            return $icon["label"];
        }, $this->contents);
    }
    public function getLabel(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, $this->contents)) {
            return "";
        }
        return $this->contents[$identifier]["label"];
    }
}

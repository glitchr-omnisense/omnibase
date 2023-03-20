<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Field\Type\ArrayType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\HyperpatternAdapterRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=HyperpatternAdapterRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "hyperpattern" )
 */

class HyperpatternAdapter extends AbstractAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fas fa-share-alt"];
    }

    public static function getType(): string
    {
        return ArrayType::class;
    }
    public function resolve(mixed $value): mixed
    {
        return !is_array($value) ? unserialize($value) : $value;
    }
    public function getOptions(): array
    {
        return [
            "pattern" => $this->getPattern(),
            "placeholder" => $this->getPlaceholder() ?? []
        ];
    }

    public function __construct(string $label = "", ?string $code = null, ?string $icon = "fas fa-laptop", string $pattern = "https://{0}")
    {
        parent::__construct($label, $code);
        $this->setIcon($icon);
        $this->setPattern($pattern);
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $pattern;
    public function getPattern(): string
    {
        return $this->pattern;
    }
    public function setPattern(string $pattern = "https://{0}")
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function getNumberOfArguments(): int
    {
        return preg_match_all('/\{[0-9]*\}/i', $this->getPattern());
    }
    public function generate(...$replace): ?string
    {
        $search = [];
        foreach ($replace as $index => $_) {
            $search[] = "{".$index."}";
        }

        $subject = $this->getPattern();
        $url = str_replace($search, $replace, $subject);

        return preg_match('/\{[0-9]*\}/', $url) ? null : $url; // Return null if missing entries
    }
}

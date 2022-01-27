<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ArrayType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\HyperpatternAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=HyperpatternAttributeRepository::class)
 * @DiscriminatorEntry( value = "hyperpattern" )
 */

class HyperpatternAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public static function getType(): string { return ArrayType::class; }
    public function resolve(mixed $value): mixed { return unserialize($value); }
    public function getOptions(): array { return [
        "pattern" => $this->getPattern(), 
        "placeholder" => $this->getPlaceholder() ?? []
    ]; }

    public function __construct(string $label, ?string $icon = "fas fa-laptop", string $pattern = "https://{0}")
    {
        parent::__construct($label, $icon);
        $this->setPattern($pattern);
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $pattern;
    public function getPattern(): string { return $this->pattern; }
    public function setPattern(string $pattern = "https://{0}")
    {
        $this->pattern = $pattern;
        return $this;
    }


    public function getNumberOfArguments():int { return preg_match_all('/\{[0-9]*\}/i', $this->getPattern()); }
    public function generateUrl(...$replace): string
    {
        $search = [];
        foreach($replace as $index => $_)
            $search[] = "{".$index."}";

        $subject = $this->getPattern();
        $url = str_replace($search, $replace, $subject);
        return preg_replace('/\{[0-9]*\}/', '', $url); // Remove missing entries
    }

    public function generateHtml(...$replace): string
    {
        $url = $this->generateUrl($replace);
        $icon = $this->getIcon();
        $class = $this->getAttribute("class");
        
        return "<a class='".$class."' href='".$url."'><i class='".$icon."'></a>";
    }
}

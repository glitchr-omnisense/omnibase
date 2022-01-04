<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Database\Traits\TranslationTrait;
use Base\Database\TranslationInterface;
use Base\Field\Type\ArrayType;
use Base\Model\IconizeInterface;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\Abstract\HyperpatternAttributeRepository;
use Base\Service\LocaleProvider;

/**
 * @ORM\Entity(repositoryClass=HyperpatternAttributeRepository::class)
 * @DiscriminatorEntry( value = "hyperpattern" )
 */

class HyperpatternAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __staticIconize() : ?array { return ["fas fa-share-alt"]; }

    public static function getType(): string { return ArrayType::class; }
    public function getOptions(): array { return ["pattern" => $this->getPattern(), "placeholder" => $this->getPlaceholder()]; }

    // public function __toString() { return $this->getPattern(); }
    public function __construct(?string $code = "website", ?string $icon = "fas fa-laptop", string $pattern = "https://{0}")
    {
        parent::__construct($code, $icon);

        $this->translate(LocaleProvider::getDefaultLocale())->setLabel(ucfirst($code));
        $this->setPattern($pattern);

        $this->hyperlinks = new ArrayCollection();
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

    public function getFormattedValue(string $value): mixed { return unserialize($value); }
    public function getNumberOfArguments():int { return preg_match_all('/\{[0-9]*\}/i', $this->getPattern()); }
    public function generateUrl(...$replace): string
    {
        $search = [];
        foreach($replace as $index => $_)
            $search[] = "{".$index."}";

        $subject = $this->getPattern();
        $url = str_replace($search, $replace, $subject);
        return preg_replace('\{[0-9]*\}', '', $url); // Remove missing entries
    }

    public function generateHtml(...$replace): string
    {
        $url = $this->generateUrl($replace);
        $icon = $this->getIcon();
        $class = $this->getAttribute("class");
        
        return "<a class='".$class."' href='".$url."'><i class='".$icon."'></a>";
    }
}

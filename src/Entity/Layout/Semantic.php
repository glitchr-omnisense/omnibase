<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\SemanticRepository;
use Base\Traits\BaseTrait;
use DomDocument;
use DOMXPath;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=SemanticRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 */
class Semantic implements TranslatableInterface, IconizeInterface
{
    use BaseTrait;
    use TranslatableTrait;

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-award"];
    }

    public function __toLink(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->generate($referenceType);
    }

    public function __construct(?string $routeName, array $routeParameters = [], ?string $label = null)
    {
        $this->routeName = $routeName;
        $this->routeParameters = $routeParameters;
        $this->setLabel($label);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $routeName;

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): self
    {
        $this->routeName = $routeName;
        return $this;
    }

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    protected $routeParameters;

    public function getRouteParameters(): ?array
    {
        return $this->routeParameters;
    }

    public function setRouteParameters(?array $routeParameters): self
    {
        $this->routeParameters = $routeParameters;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        $route = $this->getRouter()->getRouteCollection()->get($this->routeName);
        return $route?->getPath();
    }

    public function getRoute(): ?string
    {
        return $this->getRouter()->getRoute($this->getUrl());
    }

    /**
     * @return array|mixed|null
     */
    public function getRouteIcons()
    {
        return $this->getIconProvider()->getRouteIcons($this->routeName);
    }

    public function getUrl(): ?string
    {
        return $this->generate();
    }

    protected function match(string $keyword): bool
    {
        return in_array(strtolower($keyword), array_map(fn($k) => strtolower($k), $this->keywords));
    }

    protected function generate(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        try {
            return $this->getRouter()->generate($this->routeName, $this->routeParameters ?? [], $referenceType);
        } catch (Exception $e) {
        }

        return null;
    }

    /**
     * @param string $text
     * @param array $keywords
     * @param array $attributes
     * @return string
     */
    protected function doHighlight(string $text, array $keywords, array $attributes = [])
    {
        $keywords = array_filter($keywords);
        if (!$keywords) {
            return $text;
        }

        $dom = new DomDocument();
        $encoding = mb_detect_encoding($text);
        $dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', $encoding), LIBXML_NOERROR);

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//text()') as $text) {
            if (trim($text->nodeValue)) {
                $text->nodeValue = preg_replace(
                    "/(\b" . implode("\b|\b", $keywords) . "\b)/i",
                    "<a href='" . $this->generate() . "' " . html_attributes($attributes) . ">$1</a>",
                    $text->nodeValue
                );
            }
        }

        $node = $dom->getElementsByTagName('body')->item(0);
        return html_entity_decode(trim(implode(array_map([$node->ownerDocument, "saveHTML"], iterator_to_array($node->childNodes)))));
    }

    /**
     * @param string $text
     * @param array $attributes
     * @return string
     */
    public function highlight(string $text, array $attributes = [])
    {
        return $this->doHighlight($text, $this->getKeywords(), $attributes);
    }

    /**
     * @param string $text
     * @param array|string $keywords
     * @param array $attributes
     * @return string
     */
    public function highlightBy(string $text, array|string $keywords, array $attributes)
    {
        $keywords = array_filter(is_array($keywords) ? $keywords : [$keywords], fn($k) => $this->match($k));

        return $this->doHighlight($text, $keywords, $attributes);
    }
}

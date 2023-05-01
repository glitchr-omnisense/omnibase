<?php

namespace Base\Service\Model\Sharer;

use Base\Service\Model\IconizeInterface;
use Twig\Environment;

abstract class AbstractSharerAdapter implements SharerAdapterInterface, IconizeInterface
{
    /**
     * @var Environment
     */

    protected Environment $twig;

    public function __iconize(): ?array
    {
        return null;
    }

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function getTemplate(): string
    {
        return "@Base/sharer/default.html.twig";
    }

    public function generate(array $options, ?string $template = null): string
    {
        $options = array_filter($options);
        $search = array_map(fn($e) => "{" . $e . "}", array_keys($options));
        $replace = array_map(fn($o) => urlencode($o), array_values($options));

        return $this->twig->render(
            $template ?? $this->getTemplate(),
            array_merge($options, [
                "adapter" => $this,
                "sharer" => preg_replace("/\{[^\{\}]+\}/", "", str_replace($search, $replace, $this->getUrl()))
            ])
        );
    }
}

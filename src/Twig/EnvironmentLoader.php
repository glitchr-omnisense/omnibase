<?php

namespace Base\Twig;

use Base\Twig\Renderer\TagRendererInterface;
use Twig\Environment as TwigEnvironment;

/**
 *
 */
class EnvironmentLoader
{
    protected array $renderers;

    public function __construct()
    {
        $this->renderers = [];
    }

    public function addRenderer(TagRendererInterface $renderer)
    {
        $this->renderers[] = $renderer;
    }

    public function getRenderer(string $className): ?TagRendererInterface
    {
        foreach ($this->renderers as $renderer) {
            if (is_instanceof($renderer, $className)) {
                return $renderer;
            }
        }

        return null;
    }
}


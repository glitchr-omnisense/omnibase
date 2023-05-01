<?php

namespace Base\Twig\Extension;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 *
 */
final class ClipboardTwigExtension extends AbstractExtension
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'clipboard_extension';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('clipboard', [$this, 'clipboard'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('clipboard_target', [$this, 'clipboard_target'], ['needs_environment' => true, 'is_safe' => ['all']]),
        ];
    }

    public function clipboard(Environment $twig, string $template, ?string $value, array $attributes = []): ?string
    {
        if (!$value) {
            return null;
        }
        if (!str_contains($template, '/') && !str_ends_with($template, 'html.twig')) {
            $template = '@Base/clipboard/' . $template . '.html.twig';
        }

        return $twig->render($template, ['value' => $value, 'attr' => $attributes]);
    }

    public function clipboard_target(Environment $twig, string $template, ?string $target, array $attributes = []): ?string
    {
        if (!$target) {
            return null;
        }
        if (!str_starts_with($target, '#')) {
            throw new \Exception('Clipboard target "' . $target . '" must start with an hashtag #');
        }

        if (!str_contains($template, '/') && !str_ends_with($template, 'html.twig')) {
            $template = '@Base/clipboard/' . $template . '.html.twig';
        }

        $attributes['clipboard-target'] = $target;

        return $twig->render($template, ['value' => null, 'attr' => $attributes]);
    }
}

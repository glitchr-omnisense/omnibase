<?php

namespace Base\Service\Model\Sharing;

use Base\Service\Model\IconizeInterface;
use Omnibridge\Contract\Views;

abstract class AbstractSharingAdapter implements SharingAdapterInterface, IconizeInterface
{
    /** The views the button renders through (plan "omnibase agnostic", F5a; Twig's environment before). */
    protected Views $views;

    public function __iconize(): ?array
    {
        return null;
    }

    public function __construct(Views $views)
    {
        $this->views = $views;
    }

    public function getTemplate(): string
    {
        return "@Base/sharing/default.html.twig";
    }

    public function generate(array $options, ?string $template = null): string
    {
        $options = array_filter($options);
        $search = array_map(fn($e) => "{" . $e . "}", array_keys($options));
        $replace = array_map(fn($o) => urlencode($o), array_values($options));

        return $this->views->render(
            $template ?? $this->getTemplate(),
            array_merge($options, [
                "adapter" => $this,
                "sharing" => preg_replace("/\{[^\{\}]+\}/", "", str_replace($search, $replace, $this->getUrl()))
            ])
        );
    }
}

<?php

namespace Base\Twig\Extension;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ShareTwigExtension extends AbstractExtension
{
    public function getName() { return 'share_extension'; }
    public function getFilters()
    {
        return [
            new TwigFilter('share',            [$this, 'share'   ], ["needs_environment" => true, 'is_safe' => ['all']]),
        ];
    }

    public function share(Environment $twig, string $url,  array $options = [], ?string $template = null): ?string
    {
        $adapters = $this->baseService->get("base.share");
        dump($adapters);

        return null;
    }
}

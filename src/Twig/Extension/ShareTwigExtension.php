<?php

namespace Base\Twig\Extension;

use Base\Model\PaginationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ShareTwigExtension extends AbstractExtension
{
    public function getName() { return 'paginator_extension'; }

    public function __construct(Environment $twig, TranslatorInterface $translator)
    {
        $this->twig = $twig;
        $this->translator = $translator;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('share',            [$this, 'share'   ], ['is_safe' => ['all']]),
        ];
    }

    public function share(string $url,  array $options = [], ?array $shareList = null): ?string
    {
        if ($shareList === null)
            $shareList = $this->baseService->get("base.share");

        dump($shareList);

        return null;
    }
}

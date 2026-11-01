<?php

namespace Base\Twig\Extension;

use Base\Service\Embeds;
use Base\Service\MediaService;
use Base\Service\OpeningHours;
use Base\Service\Qr\QrCode;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Base\View\Commons;

/**
 * The shared bricks, in a template (docs/40-commons):
 *
 *     embed_url(url)          what a page can frame of an address (Base\Service\Embeds)
 *     qr_code(data, size)     a QR code as an SVG data: URI (Base\Service\Qr\QrCode)
 *     opening_hours()         Base\Service\OpeningHours; opening_hours(store), opening_hours('store:12'): one place's
 *     image|picture(w, h)     a stored picture to its URL (an upload through the
 *                             image resolver at that size, a URL or a public path as
 *                             it is, anything else under public/assets/)
 */
class CommonsTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly Embeds $embeds,
        protected readonly QrCode $qr,
        protected readonly OpeningHours $hours,
        protected readonly ?Packages $packages = null,
        protected readonly ?MediaService $media = null,
        #[Autowire('%kernel.project_dir%/public')] protected readonly string $publicDir = '',
    ) {
        $this->commons = new Commons($embeds, $qr, $hours, null === $packages ? null : static fn (string $path): string => $packages->getUrl($path), $media, $publicDir);
    }

    private readonly Commons $commons;

    /** The neutral helper behind this extension (the screens' Pictures contract: Base\Bridge\Symfony\MediaPictures). */
    public function commons(): Commons
    {
        return $this->commons;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('embed_url', $this->commons->embedUrl(...)),
            new TwigFunction('qr_code', $this->commons->qrCode(...)),
            new TwigFunction('opening_hours', $this->commons->openingHours(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('picture', [$this, 'picture']),
        ];
    }

    /** A stored picture to its URL (Base\View\Commons; moved here from the apps' App\Twig\ImageExtension). */
    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string
    {
        return $this->commons->picture($image, $width, $height);
    }
}

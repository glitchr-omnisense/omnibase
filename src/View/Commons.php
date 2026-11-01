<?php

namespace Base\View;

use Base\Contract\Pictures as PicturesContract;
use Base\Service\Embeds;
use Base\Service\MediaService;
use Base\Service\OpeningHours;
use Base\Service\Qr\QrCode;

/**
 * The shared bricks in a page (plan "omnibase agnostic", step F4; docs/40-commons): what a page
 * can frame of an address (Embeds), a QR code as an SVG data: URI, the opening hours (of the site
 * or of one place), and a stored picture to its URL - CommonsTwigExtension's embed_url(),
 * qr_code(), opening_hours() and |picture under Twig, the same code for Blade and a React page's
 * props. The Pictures contract of the screens (Base\Bridge\Symfony\MediaPictures) is its picture().
 */
final class Commons implements PicturesContract
{
    /**
     * @param (\Closure(string $path): string)|null $asset an asset's address in the build (symfony/asset's packages under Symfony); "/<path>" without it
     * @param string                                $publicDir the application's public folder, where the uploads' paths start
     */
    public function __construct(
        private readonly Embeds $embeds,
        private readonly QrCode $qr,
        private readonly OpeningHours $hours,
        private readonly ?\Closure $asset = null,
        private readonly ?MediaService $media = null,
        private readonly string $publicDir = '',
    ) {
    }

    /** @return array<string, mixed>|null */
    public function embedUrl(?string $url, bool $remote = true): ?array
    {
        return $this->embeds->resolve($url, $remote);
    }

    public function qrCode(string $data, int $size = 600): string
    {
        return $this->qr->dataUri($data, $size);
    }

    /** The site's opening hours, or one place's (an object, "store:12"). */
    public function openingHours(string|object|null $scope = null): OpeningHours
    {
        return null === $scope ? $this->hours : $this->hours->for($scope);
    }

    /**
     * A stored picture to its URL: an upload through the image resolver at that size, a URL or a
     * public path as it is, anything else under public/assets/.
     */
    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string
    {
        if (null === $image || '' === $image) {
            return null;
        }

        if ('' !== $this->publicDir && str_starts_with($image, $this->publicDir.'/')) {
            $thumbnail = $this->media?->thumbnail($image, $width, $height);

            return \is_string($thumbnail) ? $thumbnail : substr($image, \strlen($this->publicDir));
        }

        if (str_starts_with($image, '/') || preg_match('#^https?://#', $image)) {
            return $image;
        }

        return null !== $this->asset ? ($this->asset)('assets/'.$image) : '/assets/'.$image;
    }
}

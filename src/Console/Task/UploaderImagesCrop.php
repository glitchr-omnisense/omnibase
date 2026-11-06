<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Database\Attribute\Uploader;
use Base\Database\Repository\Repositories;
use Base\Entity\Layout\Image;
use Base\Entity\Layout\ImageCrop;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\ClassFinder;
use Imagine\Image\Box;
use Omnibridge\Persistence\UnitOfWork;

/**
 * uploader:images:crop (plan "omnibase agnostic", step F5e, slice 3): uploader:images on the images (Base\Entity\
 * Layout\Image and its App\ classes), each image's crops made in the cache too; and - asked, confirmed - the crops'
 * coordinates renormalised against a resolution (Base\Imagine\Filter\Basic\Definition\*).
 *
 * The crops are made by the host's media controller (its ImageWebp, Image and ImageCrop actions), given; without it
 * the warm-up of the crops is refused aloud.
 */
final class UploaderImagesCrop extends UploaderImages
{
    /** @param ?object $mediaController the host's media controller (ImageWebp, Image, ImageCrop) */
    public function __construct(
        Repositories $repositories,
        MediaServiceInterface $mediaService,
        array $defaultFormats = [],
        private readonly ?UnitOfWork $unitOfWork = null,
        private readonly ?object $mediaController = null,
    ) {
        parent::__construct($repositories, $mediaService, $defaultFormats);
    }

    /** @return list<string> the resolutions the crops can be renormalised against */
    public static function definitions(): array
    {
        return ClassFinder::listed(\dirname((new \ReflectionClass(\Base\Services::class))->getFileName()).'/Imagine/Filter/Basic/Definition');
    }

    /**
     * @param ?\Closure(string): bool $confirm the host's question to the user (the renormalisation is asked for
     *                                         confirmation); none: refused
     */
    public function run(UploaderEntitiesInput $input, ?\Closure $sink = null, ?\Closure $confirm = null): Report
    {
        $report = new Report($sink);
        $this->made = 0;

        $entityName = $input->entityName() ?: (string) str_strip(Image::class, ['App\\Entity\\', 'Base\\Entity\\']);
        if (!is_instanceof('App\\Entity\\'.$entityName, Image::class)) {
            self::block($report, ' [ERR] Entity must inherit from "'.Image::class.'" ', 'warning,bkg');

            return $report->fail();
        }

        if ($input instanceof UploaderImagesCropInput && $input->normalize && !$this->normalize($input, $report, $confirm)) {
            return $report->fail();
        }

        return $this->list(new UploaderImagesCropInput($input->source, $entityName, $input->property, $input->uuid, $input->orphans, $input->deleteOrphans, $input instanceof UploaderImagesInput && $input->warmup, $input instanceof UploaderImagesInput ? $input->batch : 0, $input instanceof UploaderImagesInput ? $input->format : null), $report);
    }

    private function normalize(UploaderImagesCropInput $input, Report $report, ?\Closure $confirm): bool
    {
        if (null === $input->maxDefinition) {
            return false;
        }

        $report->line("\n <info>Looking for \"".ImageCrop::class.'"</info> to normalize..');
        $imageCrops = $this->repositories->of(ImageCrop::class)->findAll();
        $nNormalizable = \count(array_filter($imageCrops, static fn ($imageCrop) => !$imageCrop->isNormalized()));

        $question = ' You are about to normalize '.$nNormalizable.' element(s) using '.class_name(\get_class($input->maxDefinition)).', do you want to continue? [y/n] ';
        if (null === $confirm || !$confirm($question)) {
            return false;
        }

        $iProcess = $iNormalized = 0;
        $nTotalCrops = \count($imageCrops);
        foreach ($imageCrops as $imageCrop) {
            $report->line('             <warning>* Image #'.$imageCrop->getImage()->getId().' "'.$imageCrop->getLabel().'" .. ('.($iProcess + 1).'/'.$nTotalCrops.')</warning>', Report::VERBOSE);

            if (!$imageCrop->isNormalized()) {
                $box = $input->maxDefinition->resize(new Box($imageCrop->getNaturalWidth(), $imageCrop->getNaturalHeight()));
                $imageCrop->setX0($imageCrop->getX() / $box->getWidth());
                $imageCrop->setY0($imageCrop->getY() / $box->getHeight());
                $imageCrop->setWidth0($imageCrop->getWidth() / $box->getWidth());
                $imageCrop->setHeight0($imageCrop->getHeight() / $box->getHeight());

                $this->unitOfWork?->flush();
                ++$iNormalized;
            }

            ++$iProcess;
        }

        if ($iNormalized) {
            $report->line('');
            $msg = ' [OK] Nothing to update - image crops are already in sync & normalized. ';
            $report->line('<info,bkg>'.str_blankspace(\strlen($msg)));
            $report->line($msg);
            $report->line(str_blankspace(\strlen($msg)).'</info,bkg>');
            $report->line('');
        } else {
            self::block($report, ' [OK] '.$nTotalCrops.' image crops found: '.$iProcess.' crop(s) processed ; '.$iNormalized.' crop(s) renormalized !', 'info,bkg');
        }

        return true;
    }

    protected function process(UploaderEntitiesInput $input, Report $report, string $class, string $field, Uploader $attribute, array $fileList): void
    {
        if ('source' != $field) {
            parent::process($input, $report, $class, $field, $attribute, $fileList);

            return;
        }
        if (!$input instanceof UploaderImagesInput || !$input->warmup) {
            return;
        }
        if (null === $this->mediaController) {
            $report->warning('The crops are made by the host\'s media controller, and this host has none: no crop warmed up.');

            return;
        }

        $images = $this->repositories->of($class)->findAll();
        $n = \count($images);
        for ($i = 0; $i < $n; ++$i) {
            $image = $images[$i];
            if ($this->made >= $input->batch && $input->batch > 0) {
                self::block($report, ' [WARN] Batch limit reached out - Set the `--batch` limit higher, if you wish. ', 'warning,bkg');
                $report->fail();

                return;
            }

            $publicDir = $attribute->getFlysystem()->getPublic('', $attribute->storage());
            $file = $image->getSource();
            if (null === $file) {
                $report->line("\t           <warning>* Image #".$image->getId().' missing.</warning>', Report::VERBOSE);
                continue;
            }

            $extension = first($this->mediaService->getExtensions($file));
            $dataWebp = $this->mediaService->image($file, ['webp' => true, 'local_cache' => true], []);
            $data = $this->mediaService->image($file, ['webp' => false, 'local_cache' => true, 'extension' => $extension], []);
            if ($this->isCached($data)) {
                $report->line('             <warning>* Already cached main image ".'.str_lstrip(realpath($file), realpath($publicDir)).'" .. ('.($i + 1).'/'.$n.')</warning>', Report::VERBOSE);
            } else {
                $report->line('             <ln>* Warming up main image ".'.str_lstrip(realpath($file), realpath($publicDir)).'" .. ('.($i + 1).'/'.$n.')</ln>', Report::VERBOSE);
                $report->line('                - Memory usage: '.round(memory_get_usage() / 1024 / 1024).'MB; File: '.implode(', ', $attribute->mimeTypes()).' (incl. WEBP); ', Report::DEBUG);

                $this->mediaController->ImageWebp($dataWebp);
                $this->mediaController->Image($data, $extension);
                ++$this->made;
            }

            foreach ($image->getCrops() as $imageCrop) {
                $identifier = $imageCrop->getSlug() ?? $imageCrop->getWidth().'x'.$imageCrop->getHeight();
                if ($this->isCropCached($data, $imageCrop)) {
                    $report->line('             <warning>  Already cached "'.str_lstrip($file, $publicDir).'" ('.$identifier.') .. ('.($i + 1).'/'.$n.')</warning>', Report::VERBOSE);
                } else {
                    $report->line('             <ln>  Warming up "'.str_lstrip($file, $publicDir).'" ('.$identifier.') .. ('.($i + 1).'/'.$n.')</ln>', Report::VERBOSE);
                    ++$this->made;

                    $this->mediaController->ImageCrop($dataWebp, $identifier, $extension);
                    $this->mediaController->ImageCrop($data, $identifier, $extension);
                }

                $report->line('                - Memory usage: '.round(memory_get_usage() / 1024 / 1024).'MB; File: '.implode(', ', $attribute->mimeTypes()).' (incl. WEBP); '.$identifier, Report::DEBUG);
            }
        }
    }

    /** Whether a crop of the image (an address of the media service's) is already in the cache. */
    public function isCropCached(string $data, ImageCrop $imageCrop): bool
    {
        $args = $this->mediaService->resolve($data);
        $path = $args['path'] ?? null;
        if (!$args || null === $path) {
            return false;
        }

        $imagesize = getimagesize($path);
        if (0 == ($imagesize[0] ?? 0) || 0 == ($imagesize[1] ?? 0)) {
            return true;
        }

        // Only cropping where an ImageCrop is found: otherwise a visitor could make an endless number of images.
        $filters = $args['filters'] ?? [];
        array_prepend($filters, new CropFilter($imageCrop->getX0(), $imageCrop->getY0(), $imageCrop->getWidth0(), $imageCrop->getHeight0()));

        $options = $args['options'] ?? [];
        $localCache = array_pop_key('local_cache', $options);
        $localCache = $args['local_cache'] ?? $localCache;

        return $this->mediaService->isCached($path, ['local_cache' => $localCache], new BitmapFilter(null, $filters, $options));
    }
}

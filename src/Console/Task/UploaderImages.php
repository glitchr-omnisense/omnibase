<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Database\Attribute\Uploader;
use Base\Database\Repository\Repositories;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Service\MediaServiceInterface;

/**
 * uploader:images (plan "omnibase agnostic", step F5e, slice 3): uploader:entities, and - asked - each image and its
 * thumbnails (the formats of its #[Uploader] and of base.uploader.formats) made in the cache, original and WebP, at
 * most a batch per run.
 */
class UploaderImages extends UploaderEntities
{
    protected int $made = 0;

    /** @param list<array<string, mixed>> $defaultFormats base.uploader.formats */
    public function __construct(Repositories $repositories, protected readonly MediaServiceInterface $mediaService, protected readonly array $defaultFormats = [])
    {
        parent::__construct($repositories);
    }

    public function run(UploaderEntitiesInput $input, ?\Closure $sink = null): Report
    {
        $this->made = 0;

        return parent::run($input, $sink);
    }

    /** Whether the image (an address of the media service's) is already in the cache, as WebP. */
    public function isCached(string $data): bool
    {
        $args = $this->mediaService->resolve($data);
        if (!$args) {
            return false;
        }

        $options = $args['options'] ?? [];
        $filters = $args['filters'] ?? [];
        $localCache = array_pop_key('local_cache', $options);
        $localCache = $args['local_cache'] ?? $localCache;

        $extension = first($this->mediaService->getExtensions($args['path'] ?? $data));
        $output = pathinfo_extension($data.'/image', $extension);

        return $this->mediaService->isCached($args['path'] ?? $data, ['webp' => true, 'local_cache' => $localCache, 'output' => $output], new WebpFilter(null, $filters, $options));
    }

    protected function process(UploaderEntitiesInput $input, Report $report, string $class, string $field, Uploader $attribute, array $fileList): void
    {
        if (!$input instanceof UploaderImagesInput || !$input->warmup) {
            return;
        }
        if (!$attribute->isImage()) {
            $report->line('             <warning>* Only images (mimetype = "'.implode(',', $attribute->mimeTypes()).'") can be warmed up.. </warning>');

            return;
        }

        $formats = [];
        foreach ($attribute->getFormats() as $format) {
            if (!\is_array($format)) {
                $format = explode('x', $format);
            }
            if (2 != \count($format)) {
                $report->line('             <warning>* Unexpected format provided '.implode('x', $format).'</warning>');
                continue;
            }
            if (null !== $input->format && $input->format != implode('x', $format)) {
                continue;
            }
            $formats[] = [(int) $format[0], (int) $format[1]];
        }

        foreach ($this->defaultFormats as $format) {
            if (!\array_key_exists('width', $format)) {
                $report->line('             <warning>* Width information missing in default configuration "'.serialize($format).'"</warning>');
                continue;
            }
            if (!\array_key_exists('height', $format)) {
                $report->line('             <warning>* Height information missing in default configuration "'.serialize($format).'"</warning>');
                continue;
            }
            if ((\array_key_exists('class', $format) && !is_instanceof($class, $format['class'])) || (\array_key_exists('property', $format) && !preg_match('/^'.$format['property'].'$/', $field))) {
                continue;
            }

            $format = [$format['width'], $format['height']];
            if (null !== $input->format && $input->format != implode('x', $format)) {
                continue;
            }
            $formats[] = $format;
        }

        $formatStr = implode(', ', array_map(static fn ($f) => implode('x', $f), $formats));
        $formatStr = $formatStr ? 'Formats: '.$formatStr : '';
        $publicDir = $attribute->getFlysystem()->getPublic('', $attribute->storage());

        $fileList = array_values($fileList);
        $n = \count($fileList);
        for ($i = 0; $i < $n; ++$i) {
            if ($this->made >= $input->batch && $input->batch > 0) {
                if (0 != $i) {
                    self::block($report, ' [WARN] Batch limit reached out - Set the `--batch` limit higher, if you wish. ', 'warning,bkg');
                }

                return;
            }

            $file = $fileList[$i];
            $shown = '".'.str_lstrip(realpath($file), realpath($publicDir)).'".. ('.($i + 1).'/'.$n.')';
            $extension = first($this->mediaService->getExtensions($file));

            $data = $this->mediaService->image($file, ['webp' => false, 'local_cache' => true, 'extension' => $extension]);
            if ($this->isCached($data)) {
                $report->line('             <warning>* Already cached '.$shown.'</warning>', Report::VERBOSE);
            } else {
                $report->line('             <info>* Warming up '.$shown.'</info>', Report::VERBOSE);
                ++$this->made;

                $this->mediaService->image($file, ['webp' => false, 'local_cache' => true, 'warmup' => true, 'extension' => $extension]);
                $this->mediaService->image($file, ['webp' => true, 'local_cache' => true, 'warmup' => true], []);
                foreach ($formats as [$width, $height]) {
                    $this->mediaService->thumbnail($file, $width, $height, ['webp' => false, 'local_cache' => true, 'warmup' => true, 'extension' => $extension]);
                    $this->mediaService->thumbnail($file, $width, $height, ['webp' => true, 'local_cache' => true, 'warmup' => true]);
                }
            }

            $report->line('                - Memory usage: '.round(memory_get_usage() / 1024 / 1024).'MB; File: '.implode(', ', $attribute->mimeTypes()).' (incl. WEBP); '.$formatStr, Report::VERY_VERBOSE);
        }
    }
}

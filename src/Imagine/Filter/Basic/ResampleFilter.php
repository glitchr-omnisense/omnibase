<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;
use Exception;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function constant;
use function defined;

class ResampleFilter implements FilterInterface
{
    /**
     * @var ImagineInterface
     */
    private ImagineInterface $imagine;


    public function __toString()
    {
        $md5sum = md5(serialize($this->options));
        return mod($this->angle, 360) ? "resample:" . $md5sum : "";
    }

    public function __construct(ImagineInterface $imagine, array $options = [])
    {
        $this->imagine = $imagine;
        $this->options = $this->resolveOptions($this->options);
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $tmpFile = $this->getTemporaryFile($this->options['temp_dir']);

        try {
            $image->save($tmpFile, $this->getImagineSaveOptions($this->options));
            $image = $this->imagine->open($tmpFile);
            $this->delTemporaryFile($tmpFile);
        } catch (Exception $exception) {
            $this->delTemporaryFile($tmpFile);
            throw new FileNotFoundException('Unable to save/open file in resample filter loader.');
        }

        return $image;
    }

    /**
     * @param string $path
     *
     * @return string
     * @throws RuntimeException
     *
     */
    private function getTemporaryFile(string $path)
    {
        if (!is_dir($path) || false === $file = tempnam($path, 'liip-imagine-bundle')) {
            throw new RuntimeException(sprintf('Unable to create temporary file in "%s" base path.', $path));
        }

        return $file;
    }

    /**
     * @param $file
     *
     * @throws RuntimeException
     */
    private function delTemporaryFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * @return array
     */
    private function getImagineSaveOptions(array $options)
    {
        $saveOptions = [
            'resolution-units' => $this->options['unit'],
            'resolution-x' => $this->options['x'],
            'resolution-y' => $this->options['y'],
        ];

        if (isset($this->options['filter'])) {
            $saveOptions['resampling-filter'] = $this->options['filter'];
        }

        return $saveOptions;
    }

    /**
     * @return array
     */
    private function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();

        $resolver->setRequired(['x', 'y', 'unit', 'temp_dir']);
        $resolver->setDefined(['filter']);
        $resolver->setDefault('temp_dir', sys_get_temp_dir());
        $resolver->setDefault('filter', 'UNDEFINED');

        $resolver->setAllowedTypes('x', ['int', 'float']);
        $resolver->setAllowedTypes('y', ['int', 'float']);
        $resolver->setAllowedTypes('temp_dir', ['string']);
        $resolver->setAllowedTypes('filter', ['string']);

        $resolver->setAllowedValues('unit', [
            ImageInterface::RESOLUTION_PIXELSPERINCH,
            ImageInterface::RESOLUTION_PIXELSPERCENTIMETER,
        ]);

        $resolver->setNormalizer('filter', function (Options $options, $value) {
            foreach (['\Imagine\Image\ImageInterface::FILTER_%s', '\Imagine\Image\ImageInterface::%s', '%s'] as $format) {
                if (defined($constant = sprintf($format, mb_strtoupper($value))) || defined($constant = sprintf($format, $value))) {
                    return constant($constant);
                }
            }

            throw new InvalidArgumentException('Invalid value for "filter" option: must be a valid constant resolvable using one of formats ' . '"\Imagine\Image\ImageInterface::FILTER_%s", "\Imagine\Image\ImageInterface::%s", or "%s".');
        });

        try {
            return $resolver->resolve($options);
        } catch (ExceptionInterface $exception) {
            throw new InvalidArgumentException(sprintf('Invalid option(s) passed to %s::load().', __CLASS__), $exception->getCode(), $exception);
        }
    }
}

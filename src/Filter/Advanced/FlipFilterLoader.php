<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Liip\ImagineBundle\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlipFilterLoader implements FilterInterface
{
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $this->options = $this->sanitizeOptions($this->options);

        return 'x' === $this->options['axis'] ? $image->flipHorizontally() : $image->flipVertically();
    }

    /**
     * @return array
     */
    private function sanitizeOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('axis', 'x');
        $resolver->setAllowedValues('axis', ['x', 'horizontal', 'y', 'vertical']);
        $resolver->setNormalizer('axis', function (Options $options, $value) {
            return 'horizontal' === $value ? 'x' : ('vertical' === $value ? 'y' : $value);
        });

        try {
            return $resolver->resolve($options);
        } catch (ExceptionInterface $e) {
            throw new InvalidArgumentException('The "axis" option must be set to "x", "horizontal", "y", or "vertical".');
        }
    }
}

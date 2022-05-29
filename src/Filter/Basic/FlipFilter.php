<?php

namespace Base\Filter\Basic;

use Base\Filter\FilterInterface;

use Imagine\Image\ImageInterface;
use InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlipFilter implements FilterInterface
{
    public function __toString() { return "flip:". ($this->options['axis'] ?? "y"); }

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

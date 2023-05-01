<?php

namespace Base\Twig\Extension;

use Countable;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MathTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sqrt', [$this, 'sqrt' ]),
            new TwigFunction('count', [$this, 'count']),
            new TwigFunction('round', [$this, 'round']),
        ];
    }

    public function sqrt($value): float
    {
        return sqrt($value);
    }
    public function count(Countable|array $value, int $mode = COUNT_NORMAL): int
    {
        return count($value, $mode);
    }
    public function round(int|float $num, int $precision = 0, int $mode = PHP_ROUND_HALF_UP): int
    {
        return round($num, $precision, $mode);
    }
}

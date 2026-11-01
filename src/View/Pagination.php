<?php

namespace Base\View;

use Base\Service\Model\PaginationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A pagination's controls (plan "omnibase agnostic", step F4): the links of the paginator_*()
 * functions and what the sliding control's template is given (paginator()), the same for Twig
 * (PaginatorTwigExtension calls it), Blade and a React page's props.
 */
final class Pagination
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * What the pagination's template (PaginationInterface::getTemplate()) is rendered with.
     *
     * @param array<string, mixed> $parameters the route's parameters
     *
     * @return array{pagination: PaginationInterface, path: string, path_parameters: array<string, mixed>, rewind: string, fastforward: string, separator: string}
     */
    public function slidingControl(PaginationInterface $pagination, string $name, array $parameters = []): array
    {
        return [
            'pagination' => $pagination,
            'path' => $name,
            'path_parameters' => $parameters,
            'rewind' => '«',
            'fastforward' => '»',
            'separator' => '...',
        ];
    }

    public function rewind(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if ($pagination->getPage() <= 1) {
            return '';
        }

        return self::link($pagination->getPath($name, 1, $parameters), $label ?? $this->translator->trans('messages.paginator.rewind'));
    }

    public function first(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if ($pagination->getPage() <= 1) {
            return '';
        }

        return self::link($pagination->getPath($name, 1, $parameters), $label ?? $this->translator->trans('messages.paginator.first'));
    }

    public function firstSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        return $pagination->getPage() - $pagination->getPageRange() > 1 ? $separator : null;
    }

    /** @return list<string> */
    public function firstOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): array
    {
        if ($pagination->getPage() <= 1) {
            return [];
        }

        $array = [];
        for ($i = 1, $N = min($pagination->getPageRange(), $pagination->getPage() - 1); $i <= $N; ++$i) {
            // The page number is the label when the caller gives none.
            $array[] = $this->numbered($pagination, $name, $parameters, $label, $i);
        }

        return $array;
    }

    /** @return list<string> */
    public function previousOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): array
    {
        if ($pagination->getPage() <= 1) {
            return [];
        }

        $array = [];
        for ($i = max(1, $pagination->getPage() - $pagination->getPageRange()), $N = $pagination->getPage(); $i < $N; ++$i) {
            $array[] = $this->numbered($pagination, $name, $parameters, $label, $i);
        }

        return $array;
    }

    public function previous(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if ($pagination->getPage() <= 1) {
            return '';
        }

        return self::link($pagination->getPath($name, $pagination->getPage() - 1, $parameters), $label ?? $this->translator->trans('messages.paginator.previous', [$pagination->getPage() - 1]));
    }

    public function current(PaginationInterface $pagination, ?string $label = null): ?string
    {
        return $label ?? $this->translator->trans('messages.paginator.current', [$pagination->getPage(), $pagination->getTotalPages()]);
    }

    public function next(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if ($pagination->getPage() >= $pagination->getTotalPages()) {
            return '';
        }

        return self::link($pagination->getPath($name, $pagination->getPage() + 1, $parameters), $label ?? $this->translator->trans('messages.paginator.next', [$pagination->getPage() + 1]));
    }

    /** @return list<string> */
    public function nextOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): array
    {
        if ($pagination->getPage() >= $pagination->getTotalPages()) {
            return [];
        }

        $array = [];
        for ($i = $pagination->getPage() + 1, $N = min($pagination->getTotalPages(), $pagination->getPage() + $pagination->getPageRange()) + 1; $i < $N; ++$i) {
            $array[] = $this->numbered($pagination, $name, $parameters, $label, $i);
        }

        return $array;
    }

    public function last(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if ($pagination->getPage() >= $pagination->getTotalPages()) {
            return '';
        }

        return self::link($pagination->getPath($name, $pagination->getTotalPages(), $parameters), $label ?? $this->translator->trans('messages.paginator.last'));
    }

    /** @return list<string> */
    public function lastOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): array
    {
        if ($pagination->getPage() >= $pagination->getTotalPages()) {
            return [];
        }

        $array = [];
        for ($i = max($pagination->getPage() + 1, $pagination->getTotalPages() - $pagination->getPageRange()), $N = $pagination->getTotalPages(); $i <= $N; ++$i) {
            $array[] = $this->numbered($pagination, $name, $parameters, $label, $i);
        }

        return $array;
    }

    public function lastSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        return $pagination->getPage() + $pagination->getPageRange() <= $pagination->getTotalPages() - 1 ? $separator : null;
    }

    public function fastForward(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if ($pagination->getPage() >= $pagination->getTotalPages()) {
            return '';
        }

        return self::link($pagination->getPath($name, $pagination->getTotalPages(), $parameters), $label ?? $this->translator->trans('messages.paginator.fastforward', [$pagination->getTotalPages()]));
    }

    private function numbered(PaginationInterface $pagination, string $name, array $parameters, ?string $label, int $i): string
    {
        return self::link($pagination->getPath($name, $i, $parameters), $label ? $this->translator->trans($label, [$i]) : (string) $i);
    }

    private static function link(mixed $href, mixed $label): string
    {
        return "<a href='".$href."'>".$label.'</a>';
    }
}

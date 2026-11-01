<?php

namespace Base\Twig\Extension;

use Base\Service\Model\PaginationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Base\View\Pagination;

final class PaginatorTwigExtension extends AbstractExtension
{
    protected TranslatorInterface $translator;

    private readonly Pagination $pagination;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->pagination = new Pagination($translator);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'paginator_extension';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('paginator', [$this, 'getSlidingControl'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('paginator_slidingcontrol', [$this, 'getSlidingControl'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('paginator_rewind', [$this, 'getRewind'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_first', [$this, 'getFirst'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_firstOnes', [$this, 'getFirstOnes'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_firstSeparator', [$this, 'getFirstSeparator'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_previousOnes', [$this, 'getPreviousOnes'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_previous', [$this, 'getPrevious'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_current', [$this, 'getCurrent'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_next', [$this, 'getNext'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_nextOnes', [$this, 'getNextOnes'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_lastSeparator', [$this, 'getLastSeparator'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_lastOnes', [$this, 'getLastOnes'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_last', [$this, 'getLast'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_fastforward', [$this, 'getFastForward'], ['is_safe' => ['all']]),
        ];
    }

    /** The pagination's template, rendered with what Base\View\Pagination gives it. */
    public function getSlidingControl(Environment $twig, PaginationInterface $pagination, string $name, array $parameters = []): ?string
    {
        return $twig->render($pagination->getTemplate(), $this->pagination->slidingControl($pagination, $name, $parameters));
    }

    public function getRewind(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        return $this->pagination->rewind($pagination, $name, $parameters, $label);
    }

    public function getFirst(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        return $this->pagination->first($pagination, $name, $parameters, $label);
    }

    public function getFirstSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        return $this->pagination->firstSeparator($pagination, $separator);
    }

    public function getFirstOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        return $this->pagination->firstOnes($pagination, $name, $parameters, $label);
    }

    public function getPreviousOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        return $this->pagination->previousOnes($pagination, $name, $parameters, $label);
    }

    public function getPrevious(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        return $this->pagination->previous($pagination, $name, $parameters, $label);
    }

    public function getCurrent(PaginationInterface $pagination, ?string $label = null): ?string
    {
        return $this->pagination->current($pagination, $label);
    }

    public function getNext(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        return $this->pagination->next($pagination, $name, $parameters, $label);
    }

    public function getNextOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        return $this->pagination->nextOnes($pagination, $name, $parameters, $label);
    }

    public function getLast(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        return $this->pagination->last($pagination, $name, $parameters, $label);
    }

    public function getLastOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        return $this->pagination->lastOnes($pagination, $name, $parameters, $label);
    }

    public function getLastSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        return $this->pagination->lastSeparator($pagination, $separator);
    }

    public function getFastForward(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        return $this->pagination->fastForward($pagination, $name, $parameters, $label);
    }
}

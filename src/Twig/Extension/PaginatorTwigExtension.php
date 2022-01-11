<?php

namespace Base\Twig\Extension;

use Base\Model\PaginationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaginatorTwigExtension extends AbstractExtension
{
    public function getName() { return 'paginator_extension'; }

    public function __construct(Environment $twig, TranslatorInterface $translator)
    {
        $this->twig = $twig;
        $this->translator = $translator;
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('paginator',                [$this, 'getSlidingControl'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_slidingcontrol', [$this, 'getSlidingControl'], ['is_safe' => ['all']]),

            new TwigFunction('paginator_rewind',         [$this, 'getRewind'],         ['is_safe' => ['all']]),
            new TwigFunction('paginator_first',          [$this, 'getFirst'],          ['is_safe' => ['all']]),

            new TwigFunction('paginator_firstOnes',      [$this, 'getFirstOnes'],      ['is_safe' => ['all']]),
            new TwigFunction('paginator_firstSeparator', [$this, 'getFirstSeparator'], ['is_safe' => ['all']]),
            new TwigFunction('paginator_previousOnes',   [$this, 'getPreviousOnes'],   ['is_safe' => ['all']]),
            new TwigFunction('paginator_previous',       [$this, 'getPrevious'],       ['is_safe' => ['all']]),

            new TwigFunction('paginator_current',        [$this, 'getCurrent'],        ['is_safe' => ['all']]),

            new TwigFunction('paginator_next',           [$this, 'getNext'],           ['is_safe' => ['all']]),
            new TwigFunction('paginator_nextOnes',       [$this, 'getNextOnes'],       ['is_safe' => ['all']]),
            new TwigFunction('paginator_lastSeparator',  [$this, 'getLastSeparator'],  ['is_safe' => ['all']]),
            new TwigFunction('paginator_lastOnes',       [$this, 'getLastOnes'],       ['is_safe' => ['all']]),

            new TwigFunction('paginator_last',           [$this, 'getLast'],           ['is_safe' => ['all']]),            
            new TwigFunction('paginator_fastforward',    [$this, 'getFastForward'],    ['is_safe' => ['all']])
        ];
    }

    public function getSlidingControl(PaginationInterface $pagination, string $name, array $parameters = []): ?string
    {
        return $this->twig->render($pagination->getTemplate(), [
            "pagination" => $pagination,
            "path" => $name,
            "path_parameters" => $parameters,
            "rewind" => "«",
            "fastforward" => "»",
            "separator" => "..."
        ]);
    }

    public function getRewind(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if($pagination->getPage() <= 1) return "";

        $label = $label ?? $this->translator->trans("messages.paginator.rewind");
        $str = "<a href='".$pagination->getPath($name, 1, $parameters)."'>".$label."</a>";

        return $str;
    }

    public function getFirst(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if($pagination->getPage() <= 1) return "";

        $label = $label ?? $this->translator->trans("messages.paginator.first");
        $str = "<a href='".$pagination->getPath($name, 1, $parameters)."'>".$label."</a>";
    
        return $str;
    }

    public function getFirstSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        if($pagination->getPage() - $pagination->getPageRange() > 1)
            return $separator;

        return null;
    }

    public function getFirstOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        if($pagination->getPage() <= 1) return [];

        $array = [];
        for($i = 1, $N = min($pagination->getPageRange(), $pagination->getPage()-1); $i <= $N; $i++)
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".($this->translator->trans($label, [$i]) ?? $i)."</a>";

        return $array;
    }

    public function getPreviousOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        if($pagination->getPage() <= 1) return [];

        $array = [];
        for($i = max(1, $pagination->getPage()-$pagination->getPageRange()), $N = $pagination->getPage(); $i < $N; $i++)
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".($this->translator->trans($label, [$i]) ?? $i)."</a>";

        return $array;
    }

    public function getPrevious(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if($pagination->getPage() <= 1) return "";

        $label = $label ?? $this->translator->trans("messages.paginator.previous", [$pagination->getPage()-1]);
        $str = "<a href='".$pagination->getPath($name, $pagination->getPage()-1, $parameters)."'>".$label."</a>";

        return $str;
    }
    
    public function getCurrent(PaginationInterface $pagination, ?string $label = null): ?string
    {
        return $label ?? $this->translator->trans("messages.paginator.current", [$pagination->getPage(), $pagination->getTotalPages()]);
    }

    public function getNext(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return "";

        $label = $label ?? $this->translator->trans("messages.paginator.next", [$pagination->getPage()+1]);
        $str = "<a href='".$pagination->getPath($name, $pagination->getPage()+1, $parameters)."'>".$label."</a>";
    
        return $str;
    }
    
    public function getNextOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return [];
        
        $array = [];
        for($i = $pagination->getPage()+1, $N = min($pagination->getTotalPages(), $pagination->getPage()+$pagination->getPageRange())+1; $i < $N; $i++) {
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".($this->translator->trans($label, [$i]) ?? $i)."</a>";
        }
    
        return $array;
    }

    public function getLast(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label): ?string
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return "";
        
        $label = $label ?? $this->translator->trans("messages.paginator.last");
        $str = "<a href='".$pagination->getPath($name, $pagination->getTotalPages(), $parameters)."'>".$label."</a>";
    
        return $str;
    }

    public function getLastOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?array
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return [];
        
        $array = [];
        for($i = max($pagination->getPage()+1, $pagination->getTotalPages()-$pagination->getPageRange()), $N = $pagination->getTotalPages(); $i <= $N; $i++)
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".($this->translator->trans($label, [$i]) ?? $i)."</a>";
    
        return $array;
    }

    public function getLastSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        if($pagination->getPage()+$pagination->getPageRange() <= $pagination->getTotalPages()-1)
            return $separator;
        
        return null;
    }

    public function getFastForward(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {   
        if($pagination->getPage() >= $pagination->getTotalPages()) return "";

        $label = $label ?? $this->translator->trans("messages.paginator.fastforward", [$pagination->getTotalPages()]);
        $str = "<a href='".$pagination->getPath($name, $pagination->getTotalPages(), $parameters)."'>".$label."</a>";
    
        return $str;
    }
}

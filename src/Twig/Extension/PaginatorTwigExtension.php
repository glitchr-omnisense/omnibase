<?php

namespace Base\Twig\Extension;

use Base\Form\FormProxy;
use Base\Model\PaginationInterface;
use Base\Service\BaseService;
use Base\Service\PaginatorInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaginatorTwigExtension extends AbstractExtension
{
    public function __construct(Environment $twig, BaseService $baseService)
    {
        $this->translator = $baseService->getTwigExtension();
        $this->twig = $twig;
    }

    public function getFunctions()
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
        
        $label = $label ?? $this->translator->trans2("messages.paginator.rewind");
        $str = "<a href='".$pagination->getPath($name, 1, $parameters)."'>".$label."</a>";
    
        return $str;
    }

    public function getFirst(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label): ?string
    {
        if($pagination->getPage() <= 1) return "";

        $label = $label ?? $this->translator->trans2("messages.paginator.first");
        $str = "<a href='".$pagination->getPath($name, 1, $parameters)."'>".$label."</a>";
    
        return $str;
    }

    public function getFirstSeparator(PaginationInterface $pagination, string $separator): ?string
    {
        if($pagination->getPage() - $pagination->getPageRange() > 1)
            return $separator;

        return null;
    }

    public function getFirstOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $_label = null): ?array
    {
        if($pagination->getPage() <= 1) return [];

        $array = [];
        for($i = 1, $N = min($pagination->getPageRange(), $pagination->getPage()-1); $i <= $N; $i++) {
            $label = $this->translator->trans2($_label, [$i]) ?? $i;
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".$label."</a>";
        }
        return $array;
    }

    public function getPreviousOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $_label = null): ?array
    {
        if($pagination->getPage() <= 1) return [];

        $array = [];
        for($i = max(1, $pagination->getPage()-$pagination->getPageRange()), $N = $pagination->getPage(); $i < $N; $i++) {
            $label = $this->translator->trans2($_label, [$i]) ?? $i;
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".$label."</a>";
        }

        return $array;
    }

    public function getPrevious(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if($pagination->getPage() <= 1) return "";

        $label = $label ?? $this->translator->trans2("messages.paginator.previous", [$pagination->getPage()-1]);
        $str = "<a href='".$pagination->getPath($name, $pagination->getPage()-1, $parameters)."'>".$label."</a>";

        return $str;
    }
    
    public function getCurrent(PaginationInterface $pagination, ?string $label = null): ?string
    {
        $label = $label ?? $this->translator->trans2("messages.paginator.current", [$pagination->getPage(), $pagination->getTotalPages()]);
    
        return $label;
    }

    public function getNext(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label = null): ?string
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return "";

        $label = $label ?? $this->translator->trans2("messages.paginator.next", [$pagination->getPage()+1]);
        $str = "<a href='".$pagination->getPath($name, $pagination->getPage()+1, $parameters)."'>".$label."</a>";
    
        return $str;
    }
    
    public function getNextOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $_label = null): ?array
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return [];
        
        $array = [];
        for($i = $pagination->getPage()+1, $N = min($pagination->getTotalPages(), $pagination->getPage()+$pagination->getPageRange())+1; $i < $N; $i++) {
            $label = $this->translator->trans2($_label, [$i]) ?? $i;
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".$label."</a>";
        }
    
        return $array;
    }

    public function getLast(PaginationInterface $pagination, string $name, array $parameters = [], ?string $label): ?string
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return "";
        
        $label = $label ?? $this->translator->trans2("messages.paginator.last");
        $str = "<a href='".$pagination->getPath($name, $pagination->getTotalPages(), $parameters)."'>".$label."</a>";
    
        return $str;
    }

    public function getLastOnes(PaginationInterface $pagination, string $name, array $parameters = [], ?string $_label = null): ?array
    {
        if($pagination->getPage() >= $pagination->getTotalPages()) return [];
        
        $array = [];
        for($i = max($pagination->getPage()+1, $pagination->getTotalPages()-$pagination->getPageRange()), $N = $pagination->getTotalPages(); $i <= $N; $i++) {
            $label = $this->translator->trans2($_label, [$i]) ?? $i;
            $array[] = "<a href='".$pagination->getPath($name, $i, $parameters)."'>".$label."</a>";
        }
    
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

        $label = $label ?? $this->translator->trans2("messages.paginator.fastforward", [$pagination->getTotalPages()]);
        $str = "<a href='".$pagination->getPath($name, $pagination->getTotalPages(), $parameters)."'>".$label."</a>";
    
        return $str;
    }

    public function getName()
    {
        return 'paginator_extension';
    }
}

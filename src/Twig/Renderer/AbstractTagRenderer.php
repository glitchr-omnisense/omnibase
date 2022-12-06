<?php

namespace Base\Twig\Renderer;

use Base\Service\LocaleProviderInterface;
use Base\Twig\Environment;

abstract class AbstractTagRenderer implements TagRendererInterface
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var LocaleProviderInterface
     */
    protected $localeProvider;
    
    public function __construct(Environment $twig, LocaleProviderInterface $localeProvider) 
    { 
        $this->twig = $twig;
        $this->localeProvider = $localeProvider;
    }
}
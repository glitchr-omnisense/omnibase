<?php

namespace Base\Twig\Renderer;

use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Twig\Environment;
use Symfony\Component\String\Slugger\SluggerInterface;

abstract class AbstractTagRenderer implements TagRendererInterface
{
    /**
     * @var Environment
     */
    protected Environment $twig;

    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;

    /**
     * @var SluggerInterface
     */
    protected SluggerInterface $slugger;

    /**
     * @var ParameterBag
     */
    protected ParameterBagInterface|ParameterBag $parameterBag;

    protected array $defaultScriptAttributes;
    protected array $defaultLinkAttributes;

    public function __construct(Environment $twig, LocalizerInterface $localizer, SluggerInterface $slugger, ParameterBagInterface $parameterBag)
    {
        $this->twig = $twig;
        $this->localizer = $localizer;
        $this->slugger = $slugger;

        $this->parameterBag = $parameterBag;
        $this->defaultScriptAttributes = $parameterBag->get("base.twig.script_attributes") ?? [];
        $this->defaultLinkAttributes = $parameterBag->get("base.twig.link_attributes") ?? [];
    }
}

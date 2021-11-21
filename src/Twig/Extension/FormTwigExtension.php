<?php

namespace Base\Twig\Extension;

use Base\Form\FormProxy;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FormTwigExtension extends AbstractExtension
{
    private $formProxy;
    public function __construct(FormProxy $formProxy)
    {
        $this->formProxy = $formProxy;
    }

    public function getFunctions()
    {
        return [new TwigFunction('form_proxy', [$this, 'getForm'])];
    }

    public function getForm(string $name): ?FormView
    {
        return $this->formProxy->getForm($name)->createView();
    }

    public function getName()
    {
        return 'form_extension';
    }
}

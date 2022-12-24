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
    public function __construct(FormProxy $formProxy) { $this->formProxy = $formProxy; }

    public function getName() { return 'form_extension'; }
    public function getFunctions() : array 
    { 
        return [
            new TwigFunction('use_advanced_form', [$this, 'advancedForm']),
            new TwigFunction('form_proxy', [$this, 'getForm']),
            new TwigFunction('form_view', [$this, 'getView']),
        ]; 
    }

    public function advancedForm(): bool { return $this->formProxy->advancedForm(); }
    public function getForm(string $name): ?FormInterface { return $this->formProxy->get($name); }
    public function getView(string $name): ?FormView
    {
        $form = $this->formProxy->get($name);
        if(!$form) return null;

        return $this->formProxy->get($name)->createView();
    }
}

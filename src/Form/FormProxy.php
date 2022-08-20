<?php

namespace Base\Form;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;

use Base\Traits\SingletonTrait;

class FormProxy implements FormProxyInterface
{
    use SingletonTrait;

    public function __construct()
    {
        self::$_instance = $this;
    }

    protected array $forms = [];
    public function getForms()
    {
        return $this->forms;
    }

    public function addForm(string $name, ?FormInterface $form): self
    {
        if (!$form) return $this;

        if (array_key_exists($name, $this->forms))
            throw new Exception("Form identifier \"$name\" already exists.");

        // Create dummy view to avoid error during twig rendering..
        $form->createView();

        if (!in_array($form, $this->forms))
            $this->forms[$name] = $form;

        return $this;
    }

    public function removeForm(string $name): self
    {
        if (array_key_exists($name, $this->forms))
            unset($this->forms[$name]);

        return $this;
    }

    public function getForm(string $name)
    {
        if(array_key_exists($name, $this->forms))
            return $this->forms[$name];

        return null;
    }

    public function hasForm(string $name):bool
    {
        return array_key_exists($name, $this->forms);
    }
}

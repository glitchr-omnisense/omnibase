<?php

namespace Base\Form;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

class FormProxy implements FormProxyInterface
{
    public function __construct(FormFactoryInterface $formFactory, FormProcessorInterface $formProcessor)
    {
        $this->formFactory   = $formFactory;
        $this->formProcessor = $formProcessor;
    }

    protected array $forms = [];
    public function all() { return $this->forms; }
    public function has(string $name):bool { return array_key_exists($name, $this->forms); }
    public function add(string $name, ?FormInterface $form): static
    {
        if (!$form) return $this;

        if (array_key_exists($name, $this->forms))
            throw new Exception("Form identifier \"$name\" already exists.");

        $form->createView(); // Create dummy view to avoid error during twig rendering..

        if (!in_array($form, $this->forms))
            $this->forms[$name] = $form;

        return $this;
    }

    public function remove(string $name): static
    {
        if (array_key_exists($name, $this->forms))
            unset($this->forms[$name]);

        return $this;
    }

    public function create(string $name, string $type = FormType::class, mixed $data = null, array $options = []): static 
    { 
        return $this->add($name, $this->formFactory->create($type, $options));
    }
    
    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface
    {
        return $this->get($name)?->submit($submittedData, $clearMissing);
    }
    
    public function get(string $name): ?FormInterface
    {
        if(array_key_exists($name, $this->forms))
            return $this->forms[$name];

        return null;
    }

    public function getData(string $name): mixed
    {
        if(array_key_exists($name, $this->forms))
            return $this->forms[$name]->getData();

        return null;
    }
}

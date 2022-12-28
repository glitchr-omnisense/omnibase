<?php

namespace Base\Form;

use Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;

class FormProxy implements FormProxyInterface
{
    /**
     * @var FormFactory
     */
    protected $formFactory;

    /**
     * @var array[FormProcessor]
     */
    protected $formProcessors;

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory  = $formFactory;
    }

    /** @var array */
    protected array $forms = [];

    public function all() { return $this->forms; }

    protected bool $advancedForm = false;
    public function advancedForm() { return $this->advancedForm; } 
    public function useAdvancedForm(bool $advancedForm = true) 
    { 
        $this->advancedForm = $advancedForm;
        return $this;
    }

    public function empty(): bool { return empty($this->forms); }
    public function has(string $name):bool { return array_key_exists($name, $this->forms); }
    
    public function getData    (string $name, ?string $childName = null): mixed {

        $data = null;
        if ($childName)
            $data = $this->forms[$name]?->get($childName)?->getData();
        
        if ($data == null) 
            $data = $this->forms[$name]?->getData();

        return $data;
    }
    public function setData    (string $name, mixed $data): self 
    { 
        $this->forms[$name]?->setData($data);
        return $this;
    }
    
    
    public function get(string $name): ?FormInterface { return $this->forms[$name] ?? null; }
    public function add(string $name,  ?FormInterface $form): self
    {
        if ($this->get($name) != null)
            throw new Exception("Form identifier \"$name\" already exists.");

        $this->forms[$name] = $form;
        return $this;
    }

    public function remove(string $name): self
    {
        if ($this->has($name))
            unset($this->forms[$name]);

        return $this;
    }

    public function create(string $name, string $type = FormType::class, mixed $data = null, array $options = [], array $listeners = []): FormInterface
    {
        if(array_key_exists($name, $this->forms))
            throw new Exception("Form \"$name\" already exists.");

        $this->forms[$name] = $this->formFactory->create($type, $data, $options, $listeners);
        return $this->forms[$name];
    }

    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface
    {
        return $this->get($name)?->submit($submittedData, $clearMissing);
    }

    public function getProcessor(string $name): ?FormProcessorInterface { return $this->formProcessors[$name] ?? null; }
    public function createProcessor(string $name, string $formTypeClass = FormType::class, array $options = [], array $listeners = []): ?FormProcessorInterface
    {
        $this->formProcessors[$name] = $this->formProcessors[$name] ?? new FormProcessor($this->get($name) ?? $this->create($name, $formTypeClass, null, $options, $listeners));
        return $this->formProcessors[$name];
    }
}

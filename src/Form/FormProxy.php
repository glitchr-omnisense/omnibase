<?php

namespace Base\Form;

use Base\Form\Common\FormModelInterface;
use Base\Form\Common\FormTypeInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface as SymfonyFormTypeInterface;

class FormProxy implements FormProxyInterface
{
    /**
     * @var FormFactory
     */
    protected $formFactory;

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory   = $formFactory;
    }

    protected array $forms = [];

    public function all() { return $this->forms; }
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
    
    
    public function get(string $name): ?FormInterface { return $this->has($name) ? $this->forms[$name] : null; }
    public function add(string $name, ?FormInterface $form): self
    {
        if ($this->get($name) != null)
            throw new Exception("Form identifier \"$name\" already exists.");

        // TBC.. Create dummy view to avoid error during twig rendering..
        $this->forms[$name] = $form;
        $this->forms[$name]->createView();

        return $this;
    }

    public function remove(string $name): self
    {
        if ($this->has($name))
            unset($this->forms[$name]);

        return $this;
    }

    public function create(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        $this->forms[$name] = $this->formFactory->create($type, $data, $options);
        return $this->forms[$name];
    }

    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface
    {
        return $this->get($name)?->submit($submittedData, $clearMissing);
    }

    public function createProcessor(string $name, string $formTypeClass = FormType::class, array $options = []): ?FormProcessorInterface
    {
        $formModelClass = null;
        if($options["data_class"] ?? null)
            $formModelClass = $options["data_class"];
        else if(class_implements_interface($formTypeClass, FormTypeInterface::class))
            $formModelClass = $formTypeClass::getModelClass();
        else if(class_implements_interface($formTypeClass, SymfonyFormTypeInterface::class))
            $formModelClass = str_replace("\\Type\\", "\\Model\\", str_rstrip($formTypeClass, "Type")."Model");
        
        if($formModelClass && !class_implements_interface($formModelClass, FormModelInterface::class))
            throw new Exception("Form model \"$formModelClass\" must exist and implement \"".FormModelInterface::class."\".");
        
        $form = $this->get($name) ?? $this->create($name, $formTypeClass, new $formModelClass(), $options);
        return $this->formFactory->createProcessor($form);
    }
}

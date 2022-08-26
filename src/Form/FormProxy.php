<?php

namespace Base\Form;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

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
    public function getData(string $name): mixed { return $this->get($name)?->getData(); }
    public function get(string $name): ?FormInterface { return $this->has($name) ? $this->forms[$name] : null; }
    public function add(string $name, ?FormInterface $form): static
    {
        if ($this->get($name) != null)
            throw new Exception("Form identifier \"$name\" already exists.");

        // TBC..
        // Create dummy view to avoid error during twig rendering..
        $this->forms[$name] = $form;
        $this->forms[$name]->createView();

        return $this;
    }

    public function remove(string $name): static
    {
        if ($this->has($name))
            unset($this->forms[$name]);

        return $this;
    }

    public function create(string $name, string $type = FormType::class, mixed $data = null, array $options = []): ?FormInterface
    {
        return $this->get($name, $this->formFactory->create($type, $data, $options));
    }

    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface
    {
        return $this->get($name)?->submit($submittedData, $clearMissing);
    }

    public function process(string $name, Request $request): ?FormInterface
    {
        return $this->has($name) ? $this->formProcessor->process($this->get($name), $request) : null;
    }

    public function hydrate(string $name, object $entity): mixed
    {
        if($this->has($name))
            $this->formProcessor->hydrate($this->getData($name), $entity);

        return $entity;
    }
}

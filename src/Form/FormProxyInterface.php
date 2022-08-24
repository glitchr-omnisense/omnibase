<?php

namespace Base\Form;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

interface FormProxyInterface
{
    public function all();
    public function add(string $name, ?FormInterface $form): static;
    public function remove(string $name): static;
    public function has(string $name):bool;
    public function get(string $name);
    public function getData(string $name): mixed;

    public function create(string $name, string $type = FormType::class, mixed $data = null, array $options = []): ?FormInterface;
    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface;
    public function process(string $name, Request $request): ?FormInterface;
    public function hydrate(string $name, object $entity): mixed;
}
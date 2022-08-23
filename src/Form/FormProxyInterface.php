<?php

namespace Base\Form;

use Symfony\Component\Form\FormInterface;

interface FormProxyInterface
{
    public function all();
    public function add(string $name, ?FormInterface $form): static;
    public function remove(string $name): static;
    public function has(string $name):bool;

    public function get(string $name);
    public function getData(string $name): mixed;

    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface;
}
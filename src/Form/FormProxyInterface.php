<?php

namespace Base\Form;

use Symfony\Component\Form\FormInterface;

interface FormProxyInterface
{
    public function getForms();
    public function addForm(string $name, ?FormInterface $form): self;
    public function removeForm(string $name): self;
    public function getForm(string $name);
    public function hasForm(string $name):bool;
}
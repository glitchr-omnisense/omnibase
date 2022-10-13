<?php

namespace Base\Form;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface FormProcessorInterface
{
    public function handleRequest(Request $request): static;
    public function hydrate(mixed $data): mixed;
    
    public function get(): FormInterface;
    public function getForm(): FormInterface;
    public function getData()  : mixed;
    public function setData(mixed $data): self;
    public function getOption (string $name):mixed;
    public function getOptions():array;
    public function getFormType():string;

    public function onDefault(callable    $callback): static;
    public function onInvalid(callable    $callback): static;
    public function onSubmit (callable ...$callbacks): static;

    public function getResponse(): Response;
}

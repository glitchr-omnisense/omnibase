<?php

namespace Base\Form;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Form;

interface FormProcessorInterface
{
    public function onDefault($callback);
    public function onSubmit($callback);

    public function get();
    public function getForm();

    public function set(Form $form);
    public function setForm(Form $form);

    public function getData();
    public function getOptions();
    public function getFormType();

    public function getSession();
    public function getPost();
    public function getFiles();

    public function getUploadedFiles();
    public function getExtras();

    public function appendPost();
    public function appendFiles();
    public function appendExtras($extras);

    public function process(Request $request): Response;
}

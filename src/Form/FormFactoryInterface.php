<?php

namespace Base\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;

interface FormFactoryInterface extends \Symfony\Component\Form\FormFactoryInterface
{
    public function guessClass(FormInterface|FormEvent $form, ?array $options = null) :?string;
    public function guessMultiple(FormInterface|FormEvent|FormBuilderInterface $form, ?array $options = null);
    public function guessSortable(FormInterface|FormEvent|FormBuilderInterface $form, ?array $options = null);
    public function guessChoices(FormInterface|FormBuilderInterface $form, ?array $options = null);
    public function guessChoiceAutocomplete(FormInterface|FormBuilderInterface $form, ?array $options = null);
    public function guessChoiceFilter(FormInterface|FormBuilderInterface $form, ?array $options = null, $data = null);
}
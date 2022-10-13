<?php

namespace Base\Form;

use Symfony\Component\Form\FormInterface;

interface FormFlowInterface extends FormInterface 
{
    public function getToken();
    public function followPropertyPath(array &$propertyPath): ?FormInterface;

    public function reset();

    public function addStep(callable $callback);
    public function addConfirmStep();
    public function getPreviousStep();
    public function getStepMax();
    public function getNextStep();
    public function setStep(int $step);
    public function getStep();
}
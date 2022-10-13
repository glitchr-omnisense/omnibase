<?php

namespace Base\Form;

use Base\Field\Type\TranslationType;
use Base\Form\FormFlowInterface;
use Base\Form\Traits\FormFlowTrait;
use Exception;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class FormFlow extends Form implements FormFlowInterface
{
    public $flowSessions = [];
    public $flowCallbacks = [];

    public function followPropertyPath(array &$propertyPath): ?FormInterface
    {
        foreach($propertyPath as $path) {

            if(!$this->has($path)) break;
            $childForm = $this->get($path);

            $formType = $childForm->getConfig()->getType()->getInnerType();
            if($formType instanceof TranslationType) {

                $availableLocales = array_keys($childForm->all());
                $locale = count($availableLocales) > 1 ? $formType->getDefaultLocale() : $availableLocales[0];
                $childForm = $childForm->get($locale);
            }

            array_shift($propertyPath);
        }

        return $childForm;
    }

    // Form flow methods
    public function reset()
    {
        $this->flowCallbacks = [];
        $this->setStep(0);
    }
    
    public function getToken()
    {
        $options = $this->getConfig()->getOptions();
        $name  = $options['flow_form_id'] ?? "";
        $token = $_POST[$name] ?? "";

        if (!empty($token) && preg_match("/(.*)#([0-9]*)/", $token, $matches)) return $matches[1];

        return random_str();
    }

    public function addStep(callable $callback)
    {
        $this->flowCallbacks[] = $callback;
    }

    public function addConfirmStep()
    {
        $options = $this->getConfig()->getOptions();
        return $this->addStep($options, function (FormBuilderInterface $builder, array $options) {});
    }

    public function getPreviousStep()
    {
        $step = $this->getStep();
        return ($step > 0) ? $step - 1 : 0;
    }

    public function getStepMax()
    {
        return count($this->flowCallbacks);
    }

    public function getNextStep()
    {
        $step = $this->getStep();
        $stepMax = $this->getStepMax();
        return ($step < $stepMax) ? $step + 1 : $stepMax;
    }

    public function setStep($step)
    {
        // $token = $this->getToken();

        // $name  = $options['flow_form_name'] ?? "";
        // if(empty($name)) throw new Exception("Unexpected option provided for \"flow_form_name\"");

        // $_POST[$name] = $token . "#" . $step;
    }

    public function getStep()
    {
        return 1;
    }
}
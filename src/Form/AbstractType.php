<?php

namespace Base\Form;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Doctrine\Common\Proxy\Proxy;

abstract class AbstractType extends \Symfony\Component\Form\AbstractType
{
    /**
     * @var MethodReflection
     */
    private $traits; // In this context, traits must be using static functions only.
                     // Didn't found a way to call $this in the child class..

    public function getAllTraits($that = null, $autoload = true)
    {
        if($that == null) $that = $this;

        $parentTraits = [];
        if( ($parent = get_parent_class($that)) )
            $parentTraits = $this->getAllTraits($parent, $autoload);

        $traits = (new \ReflectionClass($that))->getTraits();
        return array_unique(array_merge($parentTraits, $traits));
    }

    public function __broadcast($funcName, $args = [])
    {
        // Get traits
        if ($this->traits == null)
            $this->traits = $this->getAllTraits();

        // Check traits
        foreach ($this->traits as $trait) {

            // Check if the method exists
            if (!$trait->hasMethod($funcName)) continue;
            $method = $trait->getMethod($funcName);

            // Check if static
            $className = $trait->getName();
            $funcName  = $method->getName();

            if (!$method->isStatic())
                throw new Exception("Trait class \"$className::$funcName\" expected to be static in this context");

            // Copy common variables from $this to static trait
            foreach ($trait->getProperties() as $property) {

                if (!$property->isStatic()) continue;

                $propName = $property->getName();
                $className::$$propName = $this::$$propName;
            }

            // Execute trait function
            call_user_func_array($className."::".$funcName, $args);

            // Copy back common static variables from trait to this
            foreach ($trait->getProperties() as $property) {

                if (!$property->isStatic()) continue;

                $propName = $property->getName();
                $this::$$propName = $className::$$propName;
            }
        }
    }

    public function __call($funcName, $args)
    {
        return $this->__broadcast($funcName, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        return $this->__broadcast(__FUNCTION__, [$builder, $options]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        return $this->__broadcast(__FUNCTION__, [$view, $form, $options]);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        return $this->__broadcast(__FUNCTION__, [$view, $form, $options]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        return $this->__broadcast(__FUNCTION__, [$resolver]);
    }
}

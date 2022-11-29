<?php

namespace Base\Field\Type;

use Base\Twig\Environment;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends NumberType
{
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['allow_infinite'] = $options["allow_infinite"];
        $view->vars["stepUp"]   = $options["stepUp"];
        $view->vars["stepDown"] = $options["stepDown"];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'allow_infinite' => true,
            'stepUp'  => 1,
            'stepDown' => 1
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string { return 'stock'; }
}

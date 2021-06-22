<?php

namespace Base\Form\Type\Thread;

use App\Entity\Thread;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Base\Form\AbstractType;
use Base\Form\Traits\CsrfFormTrait;
use Base\Form\Traits\BootstrapFormTrait;

class SearchType extends AbstractType
{
    use BootstrapFormTrait;
    use CsrfFormTrait;

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => Thread::class,
            'csrf_token_id' => "thread_search"
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('text', \Symfony\Component\Form\Extension\Core\Type\SearchType::class);

        parent::buildForm($builder, $options);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
    }
}

<?php

namespace Base\Form\Extension;

use Base\Service\BaseService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Form\AbstractTypeExtension;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;


class FormTypeExtension extends AbstractTypeExtension
{
    protected $defaultEnabled;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'form2' => $this->baseService->getParameterBag("base.twig.use_form2")
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->browseView( $view, $form, $options);
    }

    public function browseView(FormView $view, FormInterface $form, array $options)
    {
        foreach($view->children as $field => $childView) {

            if (!$form->has($field))
                continue;
                
            $childForm = $form->get($field);
            $childOptions = $childForm->getConfig()->getOptions();

            if($options["form2"]) {

                // Add to all form custom base style.. 
                // It is named form2 and blocks are available in ./templates/form/form_div_layout.html.twig
                if (array_search("form" , $childView->vars['block_prefixes']) !== false && 
                    array_search("form2", $childView->vars['block_prefixes']) === false)
                {
                    array_splice($childView->vars['block_prefixes'], 1, 0, ["form2"]);
                }
            }
            
            $this->browseView($childView, $childForm, $childOptions);
        }
    }
}

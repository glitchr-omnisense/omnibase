<?php

namespace Base\Field\Type;

use Base\Form\FormFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\DataMapperInterface;

use Base\Service\BaseService;
use Base\Service\ParameterBagInterface;
use Base\Twig\Environment;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
final class ColorType extends AbstractType
{
    /** @var Environment */
    protected $twig;

    /** @var ParameterBag */
    protected $parameterBag;

    public function __construct(Environment $twig, ParameterBagInterface $parameterBag)
    {
        $this->twig = $twig;
        $this->parameterBag = $parameterBag;
    }

    public function getBlockPrefix(): string { return 'jscolor'; }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'jscolor'     => [],
            'jscolor-js'  => $this->parameterBag->get("base.vendor.jscolor.javascript"),
            'is_nullable' => true
        ]);
    }

    public function getParent() : ?string
    {
        return TextType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $event->setData(expandhex($event->getData(), true));

            if ($event->getData() === null)
                $event->setData("#00000000");
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {

            $event->setData(expandhex($event->getData(), true));

            if ($event->getData() == "#00000000" && $options["is_nullable"])
                $event->setData(null);

            $event->setData(shrinkhex($event->getData()));
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // JSColor requirement
        $view->vars['attr']['data-jscolor'] = json_encode($options["jscolor"]);

        // JSColor class for stylsheet
        $view->vars['attr']["class"] = "form-color";

        // Add alpha channel by default
        $options["value"] = expandhex($view->vars["value"], true);

        // Import JSColor
        $this->twig->addHtmlContent("javascripts:head", $options["jscolor-js"]);
    }
}

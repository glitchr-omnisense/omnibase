<?php

namespace Tests\Base\Http\Fixtures;

use Base\Field\Type\EmojiPickerType;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxy;
use Base\Form\Model\ContactModel;
use Base\Notifier\ContactMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** The pages of PagesTestKernel, in the host's layout. */
final class PagesController extends AbstractController
{
    /** At an address in each language, as a site's legal pages are (PageLanguageHttpTest). */
    public function language(): Response
    {
        return $this->render('@BaseTestPages/language.html.twig');
    }

    /** The same, at one address for every language: the visitor's choice, or the language they were last shown, decides. */
    public function welcome(): Response
    {
        return $this->render('@BaseTestPages/language.html.twig');
    }

    /** A field of the core whose script loads something: what it fetches comes from the site (NothingFromElsewhereHttpTest). */
    public function fields(FormFactoryInterface $forms): Response
    {
        $form = $forms->createNamedBuilder('fields')
            ->add('mood', EmojiPickerType::class, ['required' => false])
            ->getForm();

        return $this->render('@BaseTestPages/fields.html.twig', ['form' => $form->createView()]);
    }

    /**
     * What omnibridge/symfony's ContactController::Contact() does, with ContactType by its new name, as a site's
     * contact page over it names it, and the option `use_model` (ContactFormModelHttpTest); what is sent is answered
     * back rather than mailed (ContactMessage::send()).
     */
    public function contact(Request $request, FormProxy $forms): Response
    {
        return $forms
            ->createProcessor('contact', 'Omnibridge\\Symfony\\Omnibase\\Form\\Type\\ContactType', ['use_model' => true, 'csrf_protection' => false, 'guard' => false])
            ->onDefault(fn (FormProcessorInterface $processor) => $this->render(ContactMessage::TEMPLATE, ['user' => null, 'form' => $processor->getForm()->createView()]))
            ->onSubmit(function (FormProcessorInterface $processor): Response {
                $model = $processor->getData();
                if (!$model instanceof ContactModel) {
                    throw new \LogicException(sprintf('The contact form handed a %s.', get_debug_type($model)));
                }

                return new Response(sprintf('sent: %s <%s> %s', $model->name, $model->email, $model->message));
            })
            ->handleRequest($request)
            ->getResponse();
    }
}

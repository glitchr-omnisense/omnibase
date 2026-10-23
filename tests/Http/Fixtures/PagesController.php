<?php

namespace Tests\Base\Http\Fixtures;

use Base\Field\Type\EmojiPickerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
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
}

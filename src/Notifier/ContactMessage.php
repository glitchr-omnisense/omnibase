<?php

namespace Base\Notifier;

use Base\Form\Model\ContactModel;
use Base\Screen\Template;

/**
 * The contact page's message, written once for every host (plan "omnibase agnostic", step F5e, slice 5b; formerly
 * ContactController::Contact()'s submission): sent to the site's contact address (base.settings.mail.contact, else
 * the technical recipient), its confirmation to the sender, then the page that says so.
 */
final class ContactMessage
{
    public const TEMPLATE = 'client/contact/index.html.twig';
    public const SENT = 'client/contact/success.html.twig';

    /** @param Notifier $notifier the notifier whose contactEmail() and contactEmailConfirmation() make both */
    public function __construct(private readonly object $notifier)
    {
    }

    public function send(ContactModel $message): Template
    {
        $this->notifier->sendContactEmail($message);
        $this->notifier->sendContactEmailConfirmation($message);

        return new Template(self::SENT);
    }
}

<?php

namespace Base\Notifier;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Form\Model\ContactModel;
use Base\Notifier\Abstract\BaseNotifier;
use Symfony\Component\Routing\Router;

class Notifier extends BaseNotifier implements NotifierInterface
{
    public function testEmail(?User $user): Notification
    {
        $notification = new Notification("email.html.twig");
        $notification->setUser($user);

        $url = null;
        if(!str_ends_with($this->router->getRouteName(), "_send"))
            $url = $this->router->generate($this->router->getRouteName()."_send");

        $notification->setContext([
            "importance" => "high",
            "markdown"   => false,
            "exception" => null,

            "subject" => $_GET["subject"] ?? $this->translator->trans('@emails.itWorks'),

            "excerpt" => $_GET["excerpt"] ??
                "Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                    Proin blandit et lorem sed bibendum.
                    Nam eu urna placerat, rhoncus nulla id, mollis nisi.",

            "action_url" => $url,
            "action_text" => "Send email",

            "content" => $_GET["content"] ?? "<b>ABC.</b> Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                    Proin blandit et lorem sed bibendum.
                    Nam eu urna placerat, rhoncus nulla id, mollis nisi.
                    Nullam id elit sed ligula auctor egestas.
                    Vivamus tincidunt auctor mattis.
                    Sed turpis dui, laoreet non consequat eget, fermentum a neque.
                    Donec non aliquam arcu, nec dictum massa.
                    Aliquam dignissim nisl eu libero ultrices mattis.
                    Fusce aliquet sed quam id ultricies.
                    Duis at neque elementum, convallis orci et, scelerisque dolor.
                    Vivamus sit amet metus in velit imperdiet bibendum.
                    Pellentesque pretium dui ac justo elementum blandit.
                    Donec nibh erat, maximus in condimentum ac, condimentum eget lorem.
                    Sed hendrerit maximus ante, eu euismod purus tempor vel.",
            "footer_text" => $this->translator->trans('@emails.returnIndex', [$this->router->generate("/", [], Router::ABSOLUTE_URL)]) ?? null
        ]);

        return $notification;
    }

    public function userWelcomeMessage(User $user)
    {

    }

    public function userVerificationEmail(User $user)
    {

    }

    public function contactEmail(ContactModel $contactModel)
    {
        $notification = new Notification("email.html.twig");
        foreach($this->getAdminRecipients() as $adminRecipient)
            $notification->addRecipient($adminRecipient);

        foreach($contactModel->attachments ?? [] as $file)
            $notification->addAttachment($file);

        $notification->setContext([

            "importance" => "high",
            "replyTo" => $contactModel->getRecipient(),
            "subject" => $this->translator->trans("@emails.contact.subject"),
            "excerpt" => $this->translator->trans("@emails.contact.excerpt", [$contactModel->name]),
            "content" => $this->translator->trans("@emails.contact.content", [$contactModel->subject, $contactModel->message]),
        ]);

        return $notification;
    }

    public function contactEmailConfirmation(ContactModel $contactModel)
    {
        $notification = new Notification("email.html.twig");
        $notification->addRecipient($contactModel->getRecipient());

        $notification->setContext([
            "subject" => $this->translator->trans("@emails.contact_confirmation.subject", [$contactModel->name]),
            "excerpt" => $this->translator->trans("@emails.contact_confirmation.excerpt", []),
            "content" => $this->translator->trans("@emails.contact_confirmation.content", [$contactModel->subject, $contactModel->message]),
        ]);

        return $notification;
    }
}
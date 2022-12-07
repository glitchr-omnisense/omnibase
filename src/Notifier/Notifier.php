<?php

namespace Base\Notifier;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\Abstract\BaseNotifier;

class Notifier extends BaseNotifier
{
    public function testEmail(User $user): Notification
    {
        $notification = new Notification("email.html.twig");
        $notification->setUser($user);
        $notification->setContext([
            "importance" => "high",
            "markdown"   => false,
            "exception" => null,
            "raw" => true,

            "subject" => $_GET["subject"] ?? $this->translator->trans('@emails.itWorks'),
            
            "excerpt" => $_GET["excerpt"] ??
                "Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                    Proin blandit et lorem sed bibendum.
                    Nam eu urna placerat, rhoncus nulla id, mollis nisi.",

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
            "footer_text" => $this->translator->trans('@emails.returnIndex') ?? null
        ]);

        return $notification;
    }

    public function userWelcomeMessage(User $user)
    {

    }

    public function userVerificationEmail(User $user)
    {

    }
}
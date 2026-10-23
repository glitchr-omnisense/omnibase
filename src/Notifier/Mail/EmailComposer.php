<?php

namespace Base\Notifier\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * omnibase's e-mails, written the same way under every host (plan
 * "omniauth", step AU8b; R26 of the master plan's gaps): a notification's
 * template rendered by Twig in the e-mail frame, its subject the page's
 * <title>, its embedded pictures as inline parts. Framework-free: Twig and
 * symfony/mime, which every host has (Laravel's mail is Symfony's Mailer).
 *
 * Under Symfony Base\Bridge\Symfony\NotificationMessages::email() writes a
 * notification's e-mail with render() and alias() - the same lines it had -;
 * compose() writes an e-mail the same way without a notification and without
 * Symfony's Notifier, for a host that has neither (omnibridge/laravel): what
 * the Notifier's e-mail is for a notification with no attachment, sent to its
 * account (not a test address, not forwarded to the administrators).
 */
final class EmailComposer
{
    /** The e-mail frame: the HTML of a notification whose template is missing or not found. */
    public const FRAME = '@Base/notifier/email.html.twig';
    /** The e-mail's body: the HTML rendered, as it is (TemplatedEmail's htmlTemplate under Symfony). */
    public const BODY = '@Base/notifier/raw.html.twig';

    /**
     * The e-mail's text: Symfony's Twig bridge's notification text
     * (@email/zurb_2/notification/body.txt.twig, which NotificationEmail
     * renders under Symfony), its subject, then its notification's content -
     * the text part is not the HTML's.
     */
    private const TEXT = "{% autoescape false %}{% block lead %}\n{{ subject }}\n{% endblock %}\n\n{% block content %}\n{{ content }}\n{% endblock %}\n\n{% block action %}\n{% if action_url %}\n{{ action_text }}: {{ action_url }}\n{% endif %}\n{% endblock %}\n\n{% block exception %}\n{% if exception %}\nException stack trace attached.\n{{ exception }}\n{% endif %}\n{% endblock %}\n{% endautoescape %}";

    /** Before the subject of an e-mail to a test address. */
    public const TEST_PREFIX = "Test: ";

    /**
     * @param string|\Closure(): string             $from   the sender: the technical address (the Notifier's getTechnicalRecipient())
     * @param (\Closure(string $to): bool)|null      $isTest whether an e-mail to that address goes to the technical address instead (the Notifier's isTest(): base.notifier.technical_loopback, test_recipients)
     * @param (\Closure(string $id): string)|null    $trans  the words of a test e-mail (@emails.fake_test.*)
     * @param (\Closure(): array<string, mixed>)|null $globals what the templates read as globals where the host's Twig has none of omnibase's (`app`: MailVariables)
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly string|\Closure $from,
        private readonly ?\Closure $isTest = null,
        private readonly ?\Closure $trans = null,
        private readonly ?\Closure $globals = null,
    ) {
    }

    /**
     * Who an e-mail to a test address goes to: the technical address, under
     * the recipient's name, marked as a test ("[Test] Anne <support@...>").
     *
     * @param \Closure(string): string $trans
     */
    public static function testAddress(string $technical, string $to, \Closure $trans): string
    {
        $email = mailparse($to);
        $email = mailformat(mailparse($technical), first($email));

        return "[" . $trans("@emails.fake_test.author") . "] " . $email;
    }

    /**
     * A test e-mail's footer: the notice that it is one after it.
     *
     * @param \Closure(string): string $trans
     */
    public static function testFooter(?string $footer, \Closure $trans): string
    {
        $footer = [$footer, $trans("@emails.fake_test.notice")];

        return implode(" - ", array_filter($footer));
    }

    /**
     * The technical address as the Notifier writes it (getTechnicalRecipient()):
     * the settings' base.settings.mail and its name, else the parameter's
     * address (base.notifier.technical_recipient.email), named after its
     * own name or its local part. The administrators' first address, which
     * the Notifier tries between the two, is not asked here.
     */
    public static function technicalAddress(?string $mail, ?string $mailName, string $default): string
    {
        $defaultMail = mailparse($default);
        if (!$mail) {
            $mail = array_keys($defaultMail)[0] ?? $default;
        }
        $mail = trim(explode("<", $mail)[1] ?? $mail, ">");
        if (!$mailName) {
            $mailName = trim(explode("<", $mail)[0]);
        }
        if (!$mailName) {
            $mailName = first($defaultMail) ?? null;
        }
        if (!$mailName) {
            $mailName = trim(mb_ucwords(str_replace([".", "_"], [" ", " "], explode("@", $mail)[0])));
        }

        return $mailName . " <" . $mail . ">";
    }

    /**
     * The template rendered with its parameters - in the frame when it is
     * not found -, and the subject its <title> gives (null without one).
     *
     * @param array<string, mixed> $parameters
     *
     * @return array{html: string, subject: ?string}
     */
    public static function render(Environment $twig, ?string $template, array $parameters): array
    {
        // A notification without an HTML template of its own is written in the e-mail frame, as one whose
        // template is not found: "" was given to Twig, which refused it ("Failed to render template """).
        $template = $template ?: self::FRAME;
        try {
            $html = $twig->render($template, $parameters);
        } catch (LoaderError $e) {
            $html = $twig->render(self::FRAME, $parameters);
        } catch (\RuntimeException $e) { // @phpstan-ignore catch.neverThrown (as NotificationMessages caught it: a Twig extension's)
            throw new \UnexpectedValueException("Failed to render template \"$template\". See below", 500, $e);
        }

        $subject = null;
        if (preg_match('/<title>(.*)<\/title>/ims', $html, $matches)) {
            $subject = html_entity_decode(trim($matches[1]));
        }

        return ['html' => $html, 'subject' => $subject];
    }

    /**
     * The e-mail's embedded parts renamed (a random name, its extension
     * kept), and the HTML's cid: addresses with them.
     */
    public static function alias(Email $email, string $html): string
    {
        $search = [];
        $replacement = [];
        foreach ($email->getAttachments() as $attachment) {

            $ext = pathinfo((string) $attachment->getName(), PATHINFO_EXTENSION);
            $newFilename = rand_str(6) . ($ext ? ".".$ext : "");

            $filename = $attachment->getFilename();
            object_hydrate($attachment, ["name" => $newFilename, "filename" => $newFilename]);
            $search[] = "cid:".$filename;
            $replacement[] = "cid:".$newFilename;
        }

        return str_replace($search, $replacement, $html);
    }

    /**
     * An e-mail to $to, written as the Notifier writes a notification's:
     * the context it gives the template (raw, warmup, title, content,
     * footer_text, recipient), the template's own parameters over it, the
     * e-mail's pictures embedded (`email`: InlineImages, what
     * WrappedTemplatedEmail is under Symfony), the subject from the page's
     * <title> (else $subject), the body the frame's raw template prints, and
     * its text the notification's subject and content (TEXT), as NotificationEmail's.
     *
     * @param array<string, mixed> $parameters the template's own (Notification::setHtmlParameters())
     */
    public function compose(string $to, ?string $template, array $parameters, string $content = '', string $subject = '', mixed $recipient = null): Email
    {
        if (array_key_exists('email', $parameters)) {
            throw new \UnexpectedValueException('`email` is a reserved key. Cannot pass it through html parameters.');
        }
        $from = \is_string($this->from) ? $this->from : ($this->from)();
        $footer = '';
        $fwd = '';
        $recipient ??= $to;
        if (null !== $this->isTest && ($this->isTest)($to)) {
            $trans = $this->trans ?? static fn (string $id): string => $id;
            $fwd .= self::TEST_PREFIX;
            $to = self::testAddress($from, $to, $trans);
            $footer = self::testFooter($footer, $trans);
            if ($parameters['footer_text'] ?? null) {
                $parameters['footer_text'] = self::testFooter($parameters['footer_text'], $trans);
            }
        }

        $email = new Email();
        $context = (null !== $this->globals ? ($this->globals)() : []) + [
            'raw' => true,
            'warmup' => true,
            'title' => '',
            'content' => $content,
            'footer_text' => $footer,
            'recipient' => $recipient,
        ];
        $rendered = self::render($this->twig, $template, array_merge($context, $parameters, ['email' => new InlineImages($this->twig, $email)]));
        $html = self::alias($email, $rendered['html']);
        $body = $this->twig->render(self::BODY, array_merge($context, ['html' => $html]));

        $subject = $fwd.($rendered['subject'] ?? $subject);
        $text = $this->twig->createTemplate(self::TEXT, 'omnibase_email_text')->render(['subject' => $subject, 'content' => $content, 'action_url' => null, 'action_text' => null, 'exception' => false]);

        return $email
            ->subject($subject)
            ->from(Address::create($from))
            ->to(Address::create($to))
            // The Notifier's NotificationEmail, its importance "": the lowest priority, no "[IMPORTANCE]" before the subject.
            ->priority(Email::PRIORITY_LOW)
            ->html($body)
            ->text($text);
    }
}

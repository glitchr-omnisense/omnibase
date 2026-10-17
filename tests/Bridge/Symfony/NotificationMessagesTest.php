<?php

namespace Tests\Base\Bridge\Symfony;

use Base\Entity\User\Notification;
use Base\Notifier\Recipient\Recipient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Base\Entity\User\Notification cut in two (plan "omnibase agnostique", C3f):
 * the entity keeps what a notification is, and its Symfony glue - the e-mail
 * built on Twig's TemplatedEmail, the Response of render(), an ExceptionEvent
 * or a FlattenException made content - is Base\Bridge\Symfony\NotificationMessages'.
 * What comes out must not change: each case's content, render() and
 * asEmailMessage() (subject, addresses, importance, templates, context and
 * HTML) are compared with what they were before the cut, recorded in
 * fixtures/notification-messages.json by this test on the code of before
 * (NOTIFICATION_SNAPSHOT=1).
 */
final class NotificationMessagesTest extends KernelTestCase
{
    private const FIXTURE = __DIR__.'/fixtures/notification-messages.json';

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheMessagesAreThoseOfBeforeTheCut(): void
    {
        $answers = [];
        foreach ($this->notifications() as $name => $notification) {
            $answers[$name] = $this->answers($notification);
        }

        // The application they were recorded in: an e-mail prints its site (settings, logo, texts).
        $answers = ['application' => static::getContainer()->getParameter('kernel.project_dir')] + $answers;

        if (getenv('NOTIFICATION_SNAPSHOT')) {
            file_put_contents(self::FIXTURE, json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            self::markTestSkipped('Recorded in '.self::FIXTURE.'.');
        }

        self::assertFileExists(self::FIXTURE);
        $recorded = json_decode((string) file_get_contents(self::FIXTURE), true);
        if (($recorded['application'] ?? null) !== $answers['application']) {
            self::markTestSkipped('Recorded in the omnibase harness ('.($recorded['application'] ?? '?').'); this application\'s e-mails print its own site.');
        }
        // An e-mail without an HTML template failed before (Twig given ""): it is now written in the e-mail
        // frame, as the same notification with the frame as its template was - recorded beside it.
        foreach ($recorded as $name => $answer) {
            if (is_array($answer) && $answer['email'] === 'UnexpectedValueException: Failed to render template "". See below') {
                $recorded[$name]['email'] = $recorded[$name.', templated']['email'];
            }
        }
        self::assertSame($recorded, json_decode((string) json_encode($answers), true));
    }

    /** removeAttachment(), removeRecipient() and removeHtmlParameter() take out what was added, and only that. */
    public function testWhatIsRemovedIsTakenOut(): void
    {
        $notification = new Notification('Une notification');

        $file = tempnam(sys_get_temp_dir(), 'c6');
        file_put_contents($file, 'facture');
        try {
            $kept = new UploadedFile($file, 'facture.pdf', 'application/pdf', null, true);
            $removed = new \SplFileInfo(__DIR__);
            $notification->addAttachment($kept)->addAttachment($removed);
            $notification->removeAttachment($removed);
            self::assertSame([$kept], $notification->getAttachments());
            $notification->removeAttachment($kept);
            self::assertSame([], $notification->getAttachments());
        } finally {
            @unlink($file);
        }

        $ada = new Recipient('ada@example.org');
        $grace = new Recipient('grace@example.org');
        $notification->addRecipient($ada)->addRecipient($grace);
        $notification->removeRecipient($ada);
        self::assertSame([$grace], $notification->getRecipients());

        $notification->addHtmlParameter('footer_text', 'Le pied')->addHtmlParameter('signature', 'footer_text');
        $notification->removeHtmlParameter('footer_text');
        self::assertSame(['signature' => 'footer_text'], array_intersect_key($notification->getHtmlParameters(), ['footer_text' => 1, 'signature' => 1]));
    }

    /**
     * An attachment is any \SplFileInfo now (addAttachment()'s type, so that the entity names
     * nothing of HttpFoundation); the e-mail embeds the uploaded ones, as it did, and leaves the
     * others, as it left whatever was not an UploadedFile.
     */
    public function testTheEmailEmbedsTheUploadedAttachments(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'c3f');
        file_put_contents($file, 'facture');
        try {
            $notification = new Notification('@Base/notifier/email.html.twig');
            $notification->addAttachment(new UploadedFile($file, 'facture.pdf', 'application/pdf', null, true));
            $notification->addAttachment(new \SplFileInfo(sys_get_temp_dir()));
            self::assertCount(2, $notification->getAttachments());

            $without = (new Notification('@Base/notifier/email.html.twig'))->asEmailMessage(new Recipient('client@example.org'))->getMessage();
            $email = $notification->asEmailMessage(new Recipient('client@example.org'))->getMessage();
            self::assertCount(count($without->getAttachments()) + 1, $email->getAttachments(), 'the uploaded file only, beside what the template embeds');
            self::assertSame(['facture.pdf'], $email->getContext()['attachments']);
        } finally {
            @unlink($file);
        }
    }

    /** The entity and its interface name nothing of the framework: what needs it is the bridge's. */
    public function testTheEntityNamesNothingOfTheFramework(): void
    {
        $framework = '~\b(?:Symfony\\\\Component\\\\(?:HttpKernel|HttpFoundation|ErrorHandler|DependencyInjection|Routing|Security|Form|Console)|Symfony\\\\Bridge|Symfony\\\\Bundle)\\\\~';
        foreach ([Notification::class, \Base\Notifier\Abstract\BaseNotificationInterface::class] as $class) {
            $code = '';
            foreach (token_get_all((string) file_get_contents((string) (new \ReflectionClass($class))->getFileName())) as $token) {
                if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                    $code .= $token[1]."\n";
                }
            }
            self::assertDoesNotMatchRegularExpression($framework, $code, $class);
        }
        self::assertMatchesRegularExpression($framework, (string) file_get_contents((string) (new \ReflectionClass(\Base\Bridge\Symfony\NotificationMessages::class))->getFileName()), 'the bridge has it');
    }

    /**
     * Each case twice: as built, and with the e-mail's HTML template (an e-mail without one failed to
     * render, before the cut as after: recorded so; it is written in the frame since).
     *
     * @return array<string, Notification>
     */
    private function notifications(): array
    {
        $cases = [
            'plain' => function () {
                $plain = new Notification('Votre commande est payée');
                $plain->setSubject('Commande 111-2222');
                $plain->setTitle('Merci');
                $plain->setFooter('À bientôt');
                $plain->setImportance(Notification::IMPORTANCE_HIGH);
                $plain->addContextKey('replyTo', 'boutique@example.org');

                return $plain;
            },
            'template' => function () {
                $template = new Notification('@Base/notifier/email.html.twig');
                $template->setSubject('Un gabarit');
                $template->setHtmlParameters(['footer_text' => 'Le pied']);

                return $template;
            },
            'admin' => function () {
                $admin = new Notification('Un message transféré');
                $admin->setSubject('Transfert');
                $admin->setTitle('Le titre');
                $admin->markAsAdmin();

                return $admin;
            },
            'translated' => fn () => new Notification('@notifications.unknown_c3f', ['name' => 'Ada']),
            'throwable' => fn () => new Notification(new \RuntimeException('Boom')),
            'flatten' => fn () => new Notification(FlattenException::createFromThrowable(new \LogicException('Flat'))),
            'event' => fn () => new Notification(new ExceptionEvent(static::$kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, new \DomainException('Bang'))),
        ];

        $notifications = [];
        foreach ($cases as $name => $case) {
            $notifications[$name] = $case();
            $notifications[$name.', templated'] = $case()->setHtmlTemplate($notifications[$name]->getHtmlTemplate() ?: '@Base/notifier/email.html.twig');
        }

        return $notifications;
    }

    /** @return array<string, mixed> */
    private function answers(Notification $notification): array
    {
        $answers = [
            'content' => self::lines($notification->getContent()),
            'html template' => $notification->getHtmlTemplate(),
        ];

        $response = $notification->render();
        $answers['render'] = [get_class($response), $response->getStatusCode(), self::lines((string) $response->getContent())];

        try {
            $message = $notification->asEmailMessage(new Recipient('client@example.org'));
            $email = $message->getMessage();
            $context = $email->getContext();
            $answers['email'] = [
                'class' => get_class($email),
                'subject' => $email->getSubject(),
                'from' => array_map(fn ($address) => $address->toString(), $email->getFrom()),
                'to' => array_map(fn ($address) => $address->toString(), $email->getTo()),
                'reply to' => array_map(fn ($address) => $address->toString(), $email->getReplyTo()),
                'html template' => $email->getHtmlTemplate(),
                'context' => array_map(fn ($value) => is_scalar($value) || null === $value ? self::lines((string) $value) : get_debug_type($value), array_diff_key($context, ['html' => true])),
                'html' => self::lines($context['html'] ?? ''),
                'attachments' => count($email->getAttachments()),
                'html parameters' => array_keys($notification->getHtmlParameters()),
            ];
        } catch (\Throwable $e) {
            $answers['email'] = get_class($e).': '.$e->getMessage();
        }

        return $answers;
    }

    /** Lines without their indentation nor a line number (the test's own file and line move). */
    private static function lines(string $text): string
    {
        return preg_replace(['/\.php:\d+/', '/[ \t]*\n[ \t]*/'], ['.php:N', "\n"], trim($text));
    }
}

<?php

namespace Tests\Base\Notifier\Mail;

use Base\Notifier\Mail\EmailComposer;
use Base\Notifier\Mail\InlineImages;
use Omnibridge\Contract\Views;
use Omnibridge\Twig\TwigViews;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * omnibase's e-mails through omnibridge's Views (plan "omnibase agnostic", step F5a): the text part
 * written in PHP is what Twig wrote from EmailComposer::TEXT, a missing template falls back to the
 * frame, Twig's environment is still accepted (deprecated, wrapped in TwigViews), and an inline
 * picture is found through ViewSources - refused aloud by views that cannot say where it is.
 */
final class EmailComposerTest extends TestCase
{
    public function testTheTextIsWhatTwigWrote(): void
    {
        $twig = new Environment(new ArrayLoader());
        foreach ([['Hello', 'The content.'], ['Test: Welcome Anne', ''], ['', "Two\nlines"]] as [$subject, $content]) {
            self::assertSame(
                $twig->createTemplate(EmailComposer::TEXT)->render(['subject' => $subject, 'content' => $content, 'action_url' => null, 'action_text' => null, 'exception' => false]),
                EmailComposer::text($subject, $content),
            );
        }
    }

    public function testAMissingTemplateIsWrittenInTheFrame(): void
    {
        $views = new TwigViews(new Environment(new ArrayLoader([EmailComposer::FRAME => '<title>Frame</title>{{ content }}'])));
        self::assertSame(['html' => '<title>Frame</title>Hi', 'subject' => 'Frame'], EmailComposer::render($views, '@App/missing.html.twig', ['content' => 'Hi']));
        self::assertSame(['html' => '<title>Frame</title>Hi', 'subject' => 'Frame'], EmailComposer::render($views, null, ['content' => 'Hi']));
    }

    /** @group legacy */
    public function testTwigsEnvironmentIsStillAccepted(): void
    {
        $twig = new Environment(new ArrayLoader([
            'mail' => '<title>Hi {{ recipient }}</title>',
            EmailComposer::BODY => '{{ html|raw }}',
        ]));
        $email = (new EmailComposer($twig, 'Support <support@example.org>'))->compose('anne@example.org', 'mail', [], 'Hello');
        self::assertSame('Hi anne@example.org', $email->getSubject());
        self::assertSame("Hi anne@example.org\n\nHello\n\n\n", $email->getTextBody());
    }

    public function testAPictureIsEmbeddedThroughViewSources(): void
    {
        $dir = sys_get_temp_dir().'/omnibase-inline-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/logo.svg', '<svg/>');
        try {
            $filesystem = new FilesystemLoader();
            $filesystem->addPath($dir, 'Public');
            $email = new Email();
            $cid = (new InlineImages(new TwigViews(new Environment(new ChainLoader([$filesystem]))), $email))->image('@Public/logo.svg');
            self::assertStringStartsWith('cid:', $cid);
            self::assertCount(1, $email->getAttachments());
        } finally {
            unlink($dir.'/logo.svg');
            rmdir($dir);
        }

        $views = new class implements Views {
            public function render(string $name, array $context = []): string
            {
                return '';
            }

            public function exists(string $name): bool
            {
                return false;
            }
        };
        $this->expectException(\LogicException::class);
        (new InlineImages($views, new Email()))->image('@Public/logo.svg');
    }
}

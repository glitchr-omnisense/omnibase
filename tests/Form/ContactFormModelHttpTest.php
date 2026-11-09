<?php

namespace Tests\Base\Form;

use Base\Form\Common\FormModelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Http\Fixtures\PagesTestKernel;
use Tests\Base\Http\HttpTestTrait;

/**
 * The option `use_model`, which gives a form the model named beside its type: the core's form types moved to
 * omnibridge/symfony (Omnibridge\Symfony\Omnibase\Form\Type\*) and their models stayed here (Base\Form\Model\*), so
 * a page naming ContactType by its new name - a site's contact page over the bridge's ContactController - answered
 * 500 ("Form model Omnibridge\Symfony\Omnibase\Form\Model\ContactModel not found"). The bridge finds the core's
 * model; the contact page (Fixtures\PagesController::contact()) is shown, then sent, as a visitor does.
 */
class ContactFormModelHttpTest extends KernelTestCase
{
    use HttpTestTrait;

    private const CONTACT_TYPE = 'Omnibridge\Symfony\Omnibase\Form\Type\ContactType';

    protected static function getKernelClass(): string
    {
        return PagesTestKernel::class;
    }

    protected function setUp(): void
    {
        $this->bootHost();
        if (!class_exists(self::CONTACT_TYPE)) {
            self::markTestSkipped('omnibridge/symfony without the core\'s form types.');
        }
    }

    /** Each moved type with a model of the core finds it with `use_model`, by its new name as by its former one. */
    public function testTheMovedTypesFindTheirModelsInTheCore(): void
    {
        $forms = static::getContainer()->get('form.factory');
        $found = [];
        foreach (glob(\dirname((string) (new \ReflectionClass(self::CONTACT_TYPE))->getFileName()).'/*Type.php') ?: [] as $file) {
            $short = basename($file, 'Type.php');
            $model = 'Base\\Form\\Model\\'.$short.'Model';
            // A model the option can take (CommentModel, a plain class, is not one).
            if (!class_exists($model) || !is_subclass_of($model, FormModelInterface::class)) {
                continue;
            }
            foreach (['Omnibridge\\Symfony\\Omnibase\\Form\\Type\\'.$short.'Type', 'Base\\Form\\Type\\'.$short.'Type'] as $type) {
                $form = $forms->create($type, null, ['use_model' => true, 'csrf_protection' => false]);
                self::assertInstanceOf($model, $form->getData(), $type);
            }
            $found[] = $short;
        }
        self::assertContains('Contact', $found);
    }

    public function testTheContactPageByTheMovedTypeIsShownThenSent(): void
    {
        $page = $this->request('/base-test/contact');
        self::assertSame(200, $page->getStatusCode(), substr(strip_tags((string) $page->getContent()), 0, 400));
        self::assertSame(1, preg_match('/name="([^"\[]+)\[message\]"/', (string) $page->getContent(), $form), 'the contact form');
        self::assertStringContainsString('name="'.$form[1].'[email]"', (string) $page->getContent());

        // A kernel of its own, as the visitor's next request.
        self::ensureKernelShutdown();
        $this->bootHost();
        $sent = $this->request('/base-test/contact', null, 'POST', [$form[1] => ['name' => 'Anne', 'email' => 'anne@example.org', 'subject' => 'A table', 'message' => 'For four, on Friday.']], ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertSame(200, $sent->getStatusCode(), substr(strip_tags((string) $sent->getContent()), 0, 400));
        self::assertSame('sent: Anne <anne@example.org> For four, on Friday.', $sent->getContent());
    }
}

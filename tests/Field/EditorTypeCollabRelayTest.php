<?php

namespace Tests\Base\Field;

use Base\Field\Type\EditorType;
use Base\Form\Extension\FormTypeCollabExtension;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\Collab\CollabRoomResolver;
use Base\Service\Collab\CollabTicketFactory;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * collab_live (EditorType) and collab: "live" (any field) ask
 * ux_editorjs_collabTicket for a ticket when the page opens or the field
 * takes the focus. Without a relay (COLLAB_TICKET_SECRET and
 * COLLAB_RELAY_WS_URL) that action answers 503: the blog's post screen asked
 * for it on every site without one (a console error each time). The field is
 * offered without live collaboration there, and with it where a relay is set.
 */
class EditorTypeCollabRelayTest extends TestCase
{
    private function factory(CollabTicketFactory $tickets): FormFactoryInterface
    {
        $metadata = $this->createMock(ClassMetadataFactory::class);
        $metadata->method('isTransient')->willReturn(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($metadata);
        $rooms = new CollabRoomResolver($entityManager);

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name) => '/route/'.$name);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturnCallback(static fn (string $id) => new CsrfToken($id, 'token'));

        $obfuscator = $this->createMock(ObfuscatorInterface::class);
        $obfuscator->method('encode')->willReturn('data');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $tokenStorage = new TokenStorage();

        $editor = new EditorType(
            $this->createMock(ParameterBagInterface::class),
            $translator,
            $this->createMock(Environment::class),
            $router,
            $csrf,
            $obfuscator,
            $this->createMock(MediaEnhancerInterface::class),
            $rooms,
            $tokenStorage,
            $tickets,
        );

        return Forms::createFormFactoryBuilder()
            ->addType($editor)
            ->addTypeExtension(new FormTypeCollabExtension($rooms, $tokenStorage, $csrf, $router, $tickets))
            ->getFormFactory();
    }

    private function record(): object
    {
        return new class {
            public ?string $content = null;
            public ?string $title = null;

            public function getId(): int
            {
                return 7;
            }
        };
    }

    private function editorOptions(CollabTicketFactory $tickets, array $options): array
    {
        $view = $this->factory($tickets)
            ->createBuilder(FormType::class, $this->record())
            ->add('content', EditorType::class, $options)
            ->getForm()
            ->createView();

        return json_decode($view['content']->vars['editor'], true);
    }

    private function fieldAttributes(CollabTicketFactory $tickets): array
    {
        $view = $this->factory($tickets)
            ->createBuilder(FormType::class, $this->record())
            ->add('title', TextType::class, ['collab' => 'live'])
            ->getForm()
            ->createView();

        return $view['title']->vars['attr'];
    }

    public function testWithoutARelayThePostEditorAsksForNoTicket(): void
    {
        $options = $this->editorOptions(new CollabTicketFactory('', ''), ['collab_autosave' => true, 'collab_live' => true]);

        $this->assertFalse($options['collab']['live']);
        $this->assertNull($options['collab']['ticketUrl']);
        $this->assertTrue($options['collab']['autosave'], 'the autosave needs no relay and stays');
    }

    public function testWithoutARelayALiveOnlyEditorIsAPlainEditor(): void
    {
        $options = $this->editorOptions(new CollabTicketFactory('secret', ''), ['collab_live' => true]);

        $this->assertArrayNotHasKey('collab', $options);
    }

    public function testWithARelayThePostEditorCollaborates(): void
    {
        $options = $this->editorOptions(new CollabTicketFactory('secret', 'wss://collab.example.test'), ['collab_autosave' => true, 'collab_live' => true]);

        $this->assertTrue($options['collab']['live']);
        $this->assertSame('/route/ux_editorjs_collabTicket', $options['collab']['ticketUrl']);
    }

    public function testWithoutARelayALiveFieldShowsNoPresence(): void
    {
        $attributes = $this->fieldAttributes(new CollabTicketFactory('', ''));

        $this->assertArrayNotHasKey('data-collab-ticket-url', $attributes);
        $this->assertArrayNotHasKey('data-collab-mode', $attributes);
    }

    public function testWithARelayALiveFieldShowsPresence(): void
    {
        $attributes = $this->fieldAttributes(new CollabTicketFactory('secret', 'wss://collab.example.test'));

        $this->assertSame('live', $attributes['data-collab-mode']);
        $this->assertSame('/route/ux_editorjs_collabTicket', $attributes['data-collab-ticket-url']);
    }
}

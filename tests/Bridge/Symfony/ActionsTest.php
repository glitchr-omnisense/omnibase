<?php

namespace Tests\Base\Bridge\Symfony;

use Base\Controller\Action\Notification\Latest;
use Base\Controller\Action\Widget\Page;
use Base\Bridge\Symfony\ActionArguments;
use Base\Bridge\Symfony\ActionResults;
use Base\Controller\Client\NotificationController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Actions written once for every host under Symfony (audit "lesringostars face
 * au master plan", R27): their #[Route] read by Symfony's router (the core's
 * own under src/Controller/Action, which @BaseBundle/Controller imports), their result answered by the
 * ScreenResponder (ActionResults: null from an action is no such page), the
 * query string given as `array $query` (ActionArguments) - as
 * omnibridge/laravel's Routing\ActionRoutes and Screen\Responder do.
 */
final class ActionsTest extends KernelTestCase
{
    public function testAnActionsResultIsAnsweredAndNullIsNotFound(): void
    {
        $this->assertTrue(ActionResults::answers(Page::class));
        $this->assertTrue(ActionResults::answers(Latest::class.'::__invoke'));
        $this->assertTrue(ActionResults::answers([Page::class, '__invoke']));
        $this->assertFalse(ActionResults::answers(NotificationController::class.'::Index'), 'a controller answering a Response itself');
        $this->assertFalse(ActionResults::answers('No\\Such\\Action'));
    }

    public function testTheQueryStringIsGivenAsQuery(): void
    {
        $resolver = new ActionArguments();
        $request = Request::create('/page?limit=3&tags[]=a');

        $this->assertSame([['limit' => '3', 'tags' => ['a']]], [...$resolver->resolve($request, new ArgumentMetadata('query', 'array', false, true, []))]);
        $this->assertSame([], [...$resolver->resolve($request, new ArgumentMetadata('query', 'string', false, false, null))]);
        $this->assertSame([], [...$resolver->resolve($request, new ArgumentMetadata('limit', 'array', false, true, []))]);
        $request->attributes->set('query', 'a route\'s own');
        $this->assertSame([], [...$resolver->resolve($request, new ArgumentMetadata('query', 'array', false, true, []))]);
    }

    public function testTheCoresActionsAreRoutesAnsweredAsBefore(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        $kernel = self::bootKernel();
        $routes = static::getContainer()->get('router')->getRouteCollection();
        $this->assertSame(Latest::class, $routes->get('user_notifications_latest')->getDefault('_controller'));
        $this->assertSame(['GET'], $routes->get('user_notifications_latest')->getMethods());
        $this->assertSame(Page::class, $routes->get('widget_page')->getDefault('_controller'));

        $bell = $kernel->handle(Request::create('/notifications/latest'), HttpKernelInterface::MAIN_REQUEST, false);
        $this->assertSame(401, $bell->getStatusCode(), 'a visitor has no bell');
        $this->assertSame('{"unread":0,"items":[]}', $bell->getContent());

        try {
            $kernel->handle(Request::create('/page/no-such-page-r27'), HttpKernelInterface::MAIN_REQUEST, false);
            $this->fail('null from an action is no such page');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }
    }
}

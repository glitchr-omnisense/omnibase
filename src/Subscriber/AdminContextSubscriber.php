<?php

namespace Base\Subscriber;

use Base\Controller\Admin\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Backfills EA5 route attributes for custom #[Route]-annotated methods inside
 * an AbstractDashboardController subclass (e.g. Settings, ApiKey, Widgets).
 *
 * EA5's AdminRouterSubscriber only creates the AdminContext when the request
 * attribute EA::ROUTE_CREATED_BY_EASYADMIN is truthy — a flag set automatically
 * by EA's route generator for its own generated routes, but NOT for plain
 * Symfony routes defined via #[Route] on dashboard-controller methods.
 *
 * By setting these attributes at priority 5 (after RouterListener at 32, but
 * before AdminRouterSubscriber at 1), we let EA's own subscriber handle context
 * creation without duplicating that logic here.
 */
class AdminContextSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Already handled by EA's own route generator — nothing to do.
        if ($request->attributes->getBoolean(EA::ROUTE_CREATED_BY_EASYADMIN)) {
            return;
        }

        // The _controller attribute is available after RouterListener (priority 32).
        $controller = $request->attributes->get('_controller');
        if (!is_string($controller)) {
            return;
        }

        // Extract the class name ("Foo\Bar::method" → "Foo\Bar").
        $class = strstr($controller, '::', true) ?: $controller;

        if (!class_exists($class)) {
            return;
        }

        // Only act for controllers that are subclasses of the base-bundle dashboard.
        if (!is_a($class, AbstractDashboardController::class, true)) {
            return;
        }

        // Mark the request so AdminRouterSubscriber (priority 1) creates the context.
        $request->attributes->set(EA::ROUTE_CREATED_BY_EASYADMIN, true);
        $request->attributes->set(EA::DASHBOARD_CONTROLLER_FQCN, $class);
    }
}

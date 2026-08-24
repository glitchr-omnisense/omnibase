<?php

namespace Base\Subscriber;

use Base\Service\SecurityPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends a signed-in user to the enrolment prompt once, when the
 * administrator has made a second factor mandatory and this account has none.
 *
 * The redirect is deliberately narrow. It only ever happens on a plain
 * top-level GET of an HTML page, so nothing in-flight is interrupted: a form
 * post lands where it was going, an XHR gets its JSON, a download downloads.
 * And it never fires on the pages the user needs in order to *answer* it -
 * the settings pages, the login and logout paths, the two-factor check
 * itself. Getting that list wrong would lock the account out of the site
 * with no way back in, so the exemptions err generously.
 */
class SecurityEnrolmentSubscriber implements EventSubscriberInterface
{
    /**
     * Path prefixes the prompt must never interrupt: the settings pages hold
     * the answer, the security paths hold the way in and out, and /_ is the
     * profiler and friends.
     */
    private const EXEMPT_PREFIXES = [
        '/settings',
        '/login',
        '/logout',
        '/register',
        '/reset-password',
        '/connect/',
        '/_',
    ];

    public function __construct(
        private SecurityPolicy $securityPolicy,
        private Security $security,
        private UrlGeneratorInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('GET') || $request->isXmlHttpRequest()) {
            return;
        }

        // Anything that did not ask for a page - an image, a JSON endpoint,
        // a Turbo frame - gets no redirect it could not render anyway.
        $formats = $request->getAcceptableContentTypes();
        if ([] !== $formats && !in_array('text/html', $formats, true) && !in_array('*/*', $formats, true)) {
            return;
        }

        $path = $request->getPathInfo();
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        if ($request->getSession()->isStarted()
            && $request->getSession()->get(SecurityPolicy::SESSION_ENROLMENT_SKIPPED)) {
            return;
        }

        if (!$this->securityPolicy->needsEnrolment($this->security->getUser())) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('user_settings_enrolment')));
    }
}

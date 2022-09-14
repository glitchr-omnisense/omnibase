<?php

namespace Base\Subscriber;

use Base\BaseBundle;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Routing\RouterInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\Query;
use Google\Analytics\Service\GaService;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class AnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        TranslatorInterface $translator,
        Environment $twig,
        UserRepository $userRepository,
        ?GaService $googleAnalyticsService = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;

        $this->twig = $twig;
        $this->translator = $translator;

        $this->userRepository = $userRepository;

        $this->gaService = $googleAnalyticsService;
    }

    public static function getSubscribedEvents(): array
    {
        return [ KernelEvents::REQUEST  => [['onUserRequest', 8], ['onGoogleAnalyticsRequest', 7]] ];
    }

    public function onUserRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        if($this->router->isProfiler()) return;
        if(!$this->router->isEasyAdmin()) return;

        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;

        /**
         * @var Query
         */
        $onlineUsers = $user ? $this->userRepository->findByIdNotEqualToAndActiveAtYoungerThan($user->getId(), User::getOnlineDelay()) : $this->userRepository->findByActiveAtYoungerThan(User::getOnlineDelay());
        $onlineUsers = $onlineUsers->enableResultCache(User::getOnlineDelay())->getResult();
        $activeUsers = array_filter($onlineUsers, fn($u) => $u ? $u->isActive() : false);

        $this->twig->addGlobal("base.user_analytics", array_merge($this->twig->getGlobals()["base.user_analytics"] ?? [], [
            "label" => $this->translator->trans("@messages.user_analytics.label", [count($activeUsers)]),
        ]));

        if(count($onlineUsers)) {

            $this->twig->addGlobal("base.user_manager", [
                "online" => $onlineUsers,
                "active" => $activeUsers,
            ]);

            $this->twig->addGlobal("base.user_analytics", array_merge($this->twig->getGlobals()["base.user_analytics"] ?? [], [
                "default" => [
                    "active" => [
                        "label" => $this->translator->trans("@messages.user_analytics.active_users", [count($activeUsers)]),
                        "icon" => "fas fa-user"
                    ],
                    "online" => [
                        "label" => $this->translator->trans("@messages.user_analytics.online_users", [count($onlineUsers)]),
                        "icon" => "fas fa-user-clock"
                    ],
            ]]));
        }
    }

    public function onGoogleAnalyticsRequest(RequestEvent $event)
    {
        if (isset($this->gaService) && $this->gaService->isEnabled()) {

            $googleAnalytics = $this->gaService->getBasics();

            $entries = [];
            if($googleAnalytics["users"]) $entries = array_merge($entries, [
                "users"        => [
                    "label" => $this->translator->trans("@google_analytics.users", [$googleAnalytics["users"]]),
                    "icon"  => 'fas fa-user'
            ]]);
            if($googleAnalytics["users_1day"]) $entries = array_merge($entries, [
                "users_1day"   => [
                    "label" => $this->translator->trans("@google_analytics.users_1day", [$googleAnalytics["users_1day"]]),
                    "icon"  => 'fas fa-user-clock'
                ]
            ]);
            if($googleAnalytics["views"]) $entries = array_merge($entries, [
                "views"        => [
                    "label" => $this->translator->trans("@google_analytics.views", [$googleAnalytics["views"]]),
                    "icon"  => 'far fa-eye'
                ]
            ]);
            if($googleAnalytics["views_1day"]) $entries = array_merge($entries, [
                "views_1day"   => [
                    "label" => $this->translator->trans("@google_analytics.views_1day", [$googleAnalytics["views_1day"]]) ,
                    "icon"  => 'fas fa-eye'
                ]
            ]);
            if($googleAnalytics["sessions"]) $entries = array_merge($entries, [
                "sessions"     => [
                    "label" => $this->translator->trans("@google_analytics.sessions", [$googleAnalytics["sessions"]]),
                    "icon"  => 'fas fa-stopwatch'
                ]
            ]);
            if($googleAnalytics["bounces_1day"]) $entries = array_merge($entries, [
                "bounces_1day" => [
                    "label" => $this->translator->trans("@google_analytics.bounces_1day", [$googleAnalytics["bounces_1day"]]),
                    "icon"  => 'fas fa-meteor'
                ]
            ]);

            $this->twig->addGlobal("base.user_analytics", array_merge($this->twig->getGlobals()["base.user_analytics"] ?? [], [
                "google" => $entries
            ]));
        }
    }
}

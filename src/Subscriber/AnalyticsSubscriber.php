<?php

namespace Base\Subscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Service\BaseService;
use Base\Service\TranslatorInterface;
use Google\Analytics\Service\GaService;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class AnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService, TranslatorInterface $translator, Environment $twig, UserRepository $userRepository, ?GaService $googleAnalyticsService = null)
    {
        $this->baseService = $baseService;

        $this->twig = $twig;
        $this->translator = $translator;
        $this->userRepository = $userRepository;

        $this->gaService = $googleAnalyticsService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => [['onUserRequest', 1], ['onGoogleAnalyticsRequest']]
        ];
    }

    public function onUserRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        if($this->baseService->isProfiler()) return;
        if(!$this->baseService->isEasyAdmin()) return;

        $user = $this->baseService->getUser();

        $onlineUsers = $user ? $this->userRepository->findByIdNotEqualToAndActiveAtYoungerThan($user->getId(), User::getOnlineDelay())->getResult() : [];
        $activeUsers = array_filter($onlineUsers, fn($u) => $u->isActive());

        $this->twig->addGlobal("app.user_analytics", array_merge($this->twig->getGlobals()["app.user_analytics"] ?? [], [
            "label" => $this->translator->trans("@messages.user_analytics.label", [count($activeUsers)]),
        ]));

        if(count($onlineUsers)) {

            $this->twig->addGlobal("app.user_manager", [
                "online" => $onlineUsers,
                "active" => $activeUsers,
            ]);

            $this->twig->addGlobal("app.user_analytics", array_merge($this->twig->getGlobals()["app.user_analytics"] ?? [], [
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

            $this->twig->addGlobal("app.user_analytics", array_merge($this->twig->getGlobals()["app.user_analytics"] ?? [], [
                "google" => $entries
            ]));
        }
    }
}

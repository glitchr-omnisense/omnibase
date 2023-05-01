<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;

use App\Repository\UserRepository;
use Base\Routing\RouterInterface;
use Base\Service\TranslatorInterface;
use Google\Service\GaService;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 *
 */
class AnalyticsSubscriber implements EventSubscriberInterface
{
    /**
     * @var TokenStorageInterface
     */
    protected TokenStorageInterface $tokenStorage;
    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;
    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;
    /**
     * @var Environment
     */
    protected Environment $twig;
    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;
    /**
     * @var ?GaService
     */
    protected ?GaService $gaService;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RouterInterface       $router,
        TranslatorInterface   $translator,
        Environment           $twig,
        UserRepository        $userRepository,
        ?GaService            $googleAnalyticsService = null
    )
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
        return [KernelEvents::REQUEST => [['onUserRequest', 8], ['onGoogleAnalyticsRequest', 7]]];
    }

    public function onUserRequest(RequestEvent $event)
    {
        if (!is_instanceof(User::class, BaseUser::class)) {
            return;
        }
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->router->isProfiler()) {
            return;
        }
        if (!$this->router->isEasyAdmin()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // This request has variable datetime this will nto be cached
        // @TODO Round within absolute timegate; 15:00; 15:05, 15:10 etc..
        $onlineUsers = $user ?
            $this->userRepository->findByIdNotEqualToAndActiveAtYoungerThan($user->getId(), User::getOnlineDelay()) :
            $this->userRepository->findByActiveAtYoungerThan(User::getOnlineDelay());

        $onlineUsers = $onlineUsers->getResult();
        $activeUsers = array_filter($onlineUsers, fn($u) => $u ? $u->isActive() : false);

        $this->twig->addGlobal("user_analytics", array_merge($this->twig->getGlobals()["user_analytics"] ?? [], [
            "label" => $this->translator->trans("@messages.user_analytics.label", [count($activeUsers)]),
        ]));

        $this->twig->addGlobal("user_manager", []);
        if (count($onlineUsers)) {
            $this->twig->addGlobal("user_manager", [
                "online" => $onlineUsers,
                "active" => $activeUsers,
            ]);

            $this->twig->addGlobal("user_analytics", array_merge($this->twig->getGlobals()["user_analytics"] ?? [], [
                "default" => [
                    "active" => [
                        "label" => $this->translator->trans("@messages.user_analytics.active_users", [count($activeUsers)]),
                        "icon" => "fa-solid  fa-user"
                    ],
                    "online" => [
                        "label" => $this->translator->trans("@messages.user_analytics.online_users", [count($onlineUsers)]),
                        "icon" => "fa-solid  fa-user-clock"
                    ],
                ]]));
        }
    }

    public function onGoogleAnalyticsRequest(RequestEvent $event)
    {
        if (isset($this->gaService) && $this->gaService->isEnabled()) {
            $googleAnalytics = $this->gaService->getBasics();

            $entries = [];
            if ($googleAnalytics["users"]) {
                $entries = array_merge($entries, [
                    "users" => [
                        "label" => $this->translator->trans("@google_users", [$googleAnalytics["users"]]),
                        "icon" => 'fa-solid  fa-user'
                    ]]);
            }
            if ($googleAnalytics["users_1day"]) {
                $entries = array_merge($entries, [
                    "users_1day" => [
                        "label" => $this->translator->trans("@google_users_1day", [$googleAnalytics["users_1day"]]),
                        "icon" => 'fa-solid  fa-user-clock'
                    ]
                ]);
            }
            if ($googleAnalytics["views"]) {
                $entries = array_merge($entries, [
                    "views" => [
                        "label" => $this->translator->trans("@google_analytics.views", [$googleAnalytics["views"]]),
                        "icon" => 'fa-regular  fa-eye'
                    ]
                ]);
            }
            if ($googleAnalytics["views_1day"]) {
                $entries = array_merge($entries, [
                    "views_1day" => [
                        "label" => $this->translator->trans("@google_analytics.views_1day", [$googleAnalytics["views_1day"]]),
                        "icon" => 'fa-solid  fa-eye'
                    ]
                ]);
            }
            if ($googleAnalytics["sessions"]) {
                $entries = array_merge($entries, [
                    "sessions" => [
                        "label" => $this->translator->trans("@google_analytics.sessions", [$googleAnalytics["sessions"]]),
                        "icon" => 'fa-solid  fa-stopwatch'
                    ]
                ]);
            }
            if ($googleAnalytics["bounces_1day"]) {
                $entries = array_merge($entries, [
                    "bounces_1day" => [
                        "label" => $this->translator->trans("@google_analytics.bounces_1day", [$googleAnalytics["bounces_1day"]]),
                        "icon" => 'fa-solid  fa-meteor'
                    ]
                ]);
            }

            $this->twig->addGlobal("user_analytics", array_merge($this->twig->getGlobals()["user_analytics"] ?? [], [
                "google" => $entries
            ]));
        }
    }
}

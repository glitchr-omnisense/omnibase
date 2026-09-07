<?php

namespace Base\Controller\Client;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Repository\User\NotificationRepository;
use Base\Service\Push\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The notification center's HTTP surface: the list page, the read marks the
 * bell uses, the push subscription registry, and the service worker itself.
 *
 * Every mutation is a JSON POST carrying the "notifications" CSRF token in
 * its body (the toolbar renders it as data-csrf), same-origin only. They
 * answer JSON rather than redirecting because the bell updates in place.
 */
class NotificationController extends AbstractController
{
    public const CSRF = "notifications";

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly NotificationRepository $notificationRepository,
        protected readonly WebPushService $webPush,
        protected readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route("/notifications", name: "user_notifications")]
    public function Index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute("security_login");
        }

        $notifications = $this->notificationRepository->findBy(["user" => $user], ["sentAt" => "DESC", "id" => "DESC"], 100);

        return $this->render("client/user/notifications.html.twig", [
            "notifications" => $notifications,
            "unread" => $this->countUnread($user),
        ]);
    }

    /**
     * The bell's payload: unread count + latest entries, for a tab that has
     * been open a while (the server-rendered dropdown is only as fresh as the
     * page). Cheap enough to poll on focus, not meant for a timer.
     */
    #[Route("/notifications/latest", name: "user_notifications_latest", methods: ["GET"])]
    public function Latest(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(["unread" => 0, "items" => []], 401);
        }

        $limit = min(20, max(1, $request->query->getInt("limit", 8)));
        $items = array_map(fn (Notification $n) => $this->serialize($n),
            $this->notificationRepository->findBy(["user" => $user], ["sentAt" => "DESC", "id" => "DESC"], $limit));

        return new JsonResponse(["unread" => $this->countUnread($user), "items" => $items]);
    }

    /** Body: {token, id?} - one notification, or every unread one when id is absent. */
    #[Route("/notifications/read", name: "user_notifications_read", methods: ["POST"])]
    public function MarkRead(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $vars = $this->body($request);
        if (!$user instanceof User || !$this->csrfOk($vars)) {
            return new JsonResponse(["success" => false], 403);
        }

        if (!empty($vars["id"])) {
            $notification = $this->notificationRepository->findOneBy(["id" => (int) $vars["id"], "user" => $user]);
            if ($notification) {
                $notification->setIsRead(true);
            }
        } else {
            foreach ($this->notificationRepository->findBy(["user" => $user, "isRead" => false]) as $notification) {
                $notification->setIsRead(true);
            }
        }
        $this->entityManager->flush();

        return new JsonResponse(["success" => true, "unread" => $this->countUnread($user)]);
    }

    /** What the browser needs before it can subscribe: the VAPID public key, or null when push is off. */
    #[Route("/push/config", name: "push_config", methods: ["GET"])]
    public function PushConfig(): JsonResponse
    {
        return new JsonResponse([
            "enabled" => $this->webPush->isConfigured() && $this->getUser() instanceof User,
            "publicKey" => $this->webPush->getPublicKey(),
        ]);
    }

    /** Body: {token, subscription: PushSubscriptionJSON} */
    #[Route("/push/subscribe", name: "push_subscribe", methods: ["POST"])]
    public function PushSubscribe(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $vars = $this->body($request);
        if (!$user instanceof User || !$this->csrfOk($vars)) {
            return new JsonResponse(["success" => false], 403);
        }
        if (!$this->webPush->isConfigured()) {
            return new JsonResponse(["success" => false, "error" => "Push is not configured."], 503);
        }

        try {
            $this->webPush->subscribe($user, (array) ($vars["subscription"] ?? []), $request->headers->get("User-Agent"));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(["success" => false, "error" => $e->getMessage()], 400);
        }

        return new JsonResponse(["success" => true]);
    }

    /** Body: {token, endpoint} */
    #[Route("/push/unsubscribe", name: "push_unsubscribe", methods: ["POST"])]
    public function PushUnsubscribe(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $vars = $this->body($request);
        if (!$user instanceof User || !$this->csrfOk($vars)) {
            return new JsonResponse(["success" => false], 403);
        }

        $removed = $this->webPush->unsubscribe($user, (string) ($vars["endpoint"] ?? ""));
        return new JsonResponse(["success" => true, "removed" => $removed]);
    }

    /**
     * The service worker. Served from the origin root because a worker's
     * scope cannot exceed its own path: registered from /sw.js it covers the
     * whole site, admin included. Served by PHP rather than as a static
     * file so the bundle owns it - the app's public/ never has to carry a
     * copy. no-cache so a new version is picked up on the next check.
     */
    #[Route("/sw.js", name: "push_service_worker", methods: ["GET"])]
    public function ServiceWorker(): Response
    {
        $source = file_get_contents(__DIR__ . "/../../Resources/sw/sw.js");
        return new Response($source, 200, [
            "Content-Type" => "application/javascript; charset=UTF-8",
            "Cache-Control" => "no-cache",
            "Service-Worker-Allowed" => "/",
        ]);
    }

    protected function body(Request $request): array
    {
        $vars = json_decode($request->getContent(), true);
        return is_array($vars) ? $vars : $request->request->all();
    }

    protected function csrfOk(array $vars): bool
    {
        return !empty($vars["token"]) && $this->isCsrfTokenValid(self::CSRF, (string) $vars["token"]);
    }

    protected function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnreadFor($user);
    }

    public function serialize(Notification $n): array
    {
        return [
            "id" => $n->getId(),
            "title" => $n->getTitle() ?: $n->getSubject(),
            "content" => strip_tags($n->getContent()),
            "url" => $n->getUrl(),
            "importance" => $n->getImportance(),
            "isRead" => $n->isRead(),
            "sentAt" => $n->getSentAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}

<?php

namespace Base\Controller\Api;

use Base\Entity\Thread\Like;
use Base\Enum\ThreadState;
use Base\Repository\Thread\LikeRepository;
use Base\Repository\ThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\BaseService;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 *
 */
class ThreadController extends AbstractController
{
    /**
     * @var BaseService
     */
    protected $baseService;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ThreadRepository 
     */
    protected $threadRepository;

    /**
     * @var LikeRepository 
     */
    protected $likeRepository;

    public function __construct(BaseService $baseService, EntityManagerInterface $entityManager, TranslatorInterface $translator, ThreadRepository $threadRepository, LikeRepository $likeRepository)
    {
        $this->baseService = $baseService;
        $this->translator = $translator;        

        $this->entityManager = $entityManager;  
        $this->threadRepository = $threadRepository;
        $this->likeRepository = $likeRepository;
    }

    /**
     * @Route({"en": "/thread/{slug}/edit", "fr": "/fil/{slug}/editer"}, name="api_thread_edit")
     */
    public function Edit($slug): Response
    {
        $thread = $this->threadRepository->cacheOneBySlug($slug);
        if (!$thread) throw new NotFoundHttpException();

        return $this->redirect($this->baseService->crudify($thread));
    }

    /**
     * @Route({"en": "/thread/{slug}/publish", "fr": "/fil/{slug}/publier"}, name="api_thread_publish")
     */
    public function Publish(string $slug): Response
    {
        $thread = $this->threadRepository->cacheOneBySlug($slug);
        if (!$this->isGranted('ROLE_ADMIN')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $thread->setState(ThreadState::PUBLISH);
        $this->threadRepository->flush();

        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }

    /**
     * @Route({"en": "/thread/{slug}/hide", "fr": "/fil/{slug}/cacher"}, name="api_thread_hide")
     */
    public function Hide(string $slug): Response
    {
        $thread = $this->threadRepository->cacheOneBySlug($slug);
        if (!$this->isGranted('ROLE_ADMIN')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $thread->setState(ThreadState::SECRET);
        $this->threadRepository->flush();

        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }

    /**
     * @Route({"en": "/thread/{slug}/follow", "fr": "/fil/{slug}/suivre"}, name="api_thread_follow")
     */
    public function Follow(string $slug): Response
    {
        $thread = $this->threadRepository->cacheOneBySlug($slug);
        if (!$this->isGranted('ROLE_ADMIN')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $thread->setState(ThreadState::PUBLISH);
        $this->threadRepository->flush();

        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }

    /**
     * @Route({"en": "/thread/{slug}/follow", "fr": "/fil/{slug}/suivre"}, name="api_thread_unfollow")
     */
    public function Unfollow(string $slug): Response
    {
        $thread = $this->threadRepository->cacheOneBySlug($slug);
        if (!$this->isGranted('ROLE_ADMIN')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $thread->setState(ThreadState::PUBLISH);
        $this->threadRepository->flush();

        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }


    /**
     * @Route({"en": "/thread/{slug}/like", "fr": "/fil/{slug}/like"}, name="api_thread_like")
     */
    public function Like($slug): Response
    {
        $thread = $this->threadRepository->findOneBySlug($slug);
        if ($this->getUser() === null) {

            return JsonResponse::fromJsonString(json_encode([
                "code"  => 401, "response" => "Unknown user",
                "likes" => count($thread->getLikes())
            ]));
        }

        $like = $this->likeRepository->findOneByThreadAndUser($thread, $this->getUser());
        if(!$like) {

            $thread->addLike(new Like($this->getUser()));
            $this->threadRepository->flush();
        }

        $nlikes = count($thread->getLikes());

        $this->addFlash("info", $this->translator->trans("@controllers.thread.like"));

        return JsonResponse::fromJsonString(json_encode([
            "code"  => 200, "response" => "OK",
            "likes" => $nlikes
        ]));
    }

    /**
     * @Route({"en": "/thread/{slug}/unlike", "fr": "/fil/{slug}/unlike"}, name="api_thread_unlike")
     */
    public function Unlike($slug): Response
    {
        $thread = $this->threadRepository->findOneBySlug($slug);
        $nlikes = count($thread->getLikes());

        if ($this->getUser() === null)
            return JsonResponse::fromJsonString(json_encode([
                "code"  => 401, "response" => "Unknown user",
                "likes" => $nlikes
            ]));

        $like = $this->likeRepository->findOneByThreadAndUser($thread, $this->getUser());
        $thread->removeLike($like);

        $nlikes = count($thread->getLikes());
        $this->threadRepository->flush();

        $this->addFlash("info", $this->translator->trans("@controllers.thread.unlike"));
        
        return JsonResponse::fromJsonString(json_encode([
            "code"  => 200, "response" => "OK",
            "likes" => $nlikes
        ]));
    }
}
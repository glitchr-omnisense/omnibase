<?php

namespace Base\Controller\Frontend\User;

use App\Entity\User;
use App\Form\Type\UserProfileType;
use App\Repository\UserRepository;
use Base\Annotations\Annotation\Iconize;
use Base\Enum\UserRole;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 *
 */
class ProfileController extends AbstractController
{
    /**
     * @var FormProxyInterface
     */
    protected FormProxyInterface $formProxy;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    public function __construct(EntityManagerInterface $entityManager, FormProxyInterface $formProxy)
    {
        $this->formProxy = $formProxy;
        $this->entityManager = $entityManager;
        $this->userRepository = $entityManager->getRepository(User::class);
    }

    /**
     * @Route("/profile/{id}/edit/", name="user_profileEdit")
     */
    public function Edit(Request $request, int $id = -1)
    {
        if ($id > 0) {
            if (!($user = $this->userRepository->cacheOneById($id))) {
                throw $this->createNotFoundException('User not found.');
            }
        } else {
            if (!($user = $this->getUser()) || !$user->isPersistent()) {
                return $this->redirectToRoute('user_search');
            }
        }

        if ($user !== $this->getUser() && !$this->isGranted(UserRole::ADMIN)) {
            throw new AccessDeniedException();
        }

        return $this->formProxy
            ->createProcessor("profile:user", UserProfileType::class, ["use_model" => true])
            ->setData($user)
            ->onDefault(function (FormProcessorInterface $formProcessor) use ($user) {
                return $this->render('client/user/profile_edit.html.twig', [
                    'user' => $user,
                    "form" => $formProcessor->getForm()->createView()
                ]);
            })
            ->onSubmit(function (FormProcessorInterface $formProcessor, Request $request) use ($user) {
                $user = $formProcessor->hydrate($user);
                $this->entityManager->flush();

                return $this->redirectToRoute('user_profileEdit');
            })
            ->handleRequest($request)
            ->getResponse();
    }

    /**
     * @Route("/profile/{id}", name="user_profile")
     * @Iconize("fa-solid fa-fw fa-id-card")
     */
    public function Show(int $id = -1)
    {
        if ($id > 0) {
            if (!($user = $this->userRepository->cacheOneById($id))) {
                throw $this->createNotFoundException('User not found.');
            }
        } else {
            if (!($user = $this->getUser()) || !$user->isPersistent()) {
                return $this->redirectToRoute('user_search');
            }
        }

        return $this->render('client/user/profile_show.html.twig', ['user' => $user]);
    }
}

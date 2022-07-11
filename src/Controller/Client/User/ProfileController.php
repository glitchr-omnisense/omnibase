<?php

namespace Base\Controller\Client\User;

use App\Entity\User;
use App\Repository\UserRepository;

use Base\Annotations\Annotation\Iconize;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProfileController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->userRepository = $entityManager->getRepository(User::class);
    }

    /**
     * @Route("/profile/edit", name="user_profileEdit")
     */
    public function Edit()
    {
        if (!($user = $this->getUser()) || !$user->isPersistent())
            return $this->redirectToRoute('security_login');

        return $this->render('@Base/client/user/profile_edit.html.twig', ['user' => $user]);
    }

    /**
     * @Route("/profile", name="user_profile")
     * @Route("/profile/{id}", name="user_profileId")
     * @Iconize("fas fa-fw fa-id-card")
     */
    public function Show($id = -1)
    {
        if($id > 0) {

            if ( !($user = $this->userRepository->find($id)) )
                throw $this->createNotFoundException('Tag not found.');

        } else {

            if (!($user = $this->getUser()) || !$user->isPersistent())
                return $this->redirectToRoute('user_search');
        }

        return $this->render('@Base/client/user/profile_show.html.twig', ['user' => $user]);
    }
}

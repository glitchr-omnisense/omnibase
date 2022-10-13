<?php

namespace Base\Controller\Frontend\User;

use App\Entity\User;
use App\Repository\UserRepository;

use Base\Annotations\Annotation\Iconize;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProfileController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->userRepository = $entityManager->getRepository(User::class);
    }

    /**
     * @Route("/profile/{id}/edit", name="user_profileEdit")
     */
    public function Edit(int $id = -1)
    {
        if($id > 0) {

            if ( !($user = $this->userRepository->cacheById($id)) )
                throw $this->createNotFoundException('User not found.');

        } else {

            if (!($user = $this->getUser()) || !$user->isPersistent())
                return $this->redirectToRoute('user_search');
        }
        
        return $this->render('@Base/client/user/profile_edit.html.twig', ['user' => $user]);
    }

    /**
     * @Route("/profile/{id}", name="user_profile")
     * @Iconize("fas fa-fw fa-id-card")
     */
    public function Show(int $id = -1)
    {
        if($id > 0) {

            if ( !($user = $this->userRepository->cacheById($id)) )
                throw $this->createNotFoundException('User not found.');

        } else {

            if (!($user = $this->getUser()) || !$user->isPersistent())
                return $this->redirectToRoute('user_search');
        }

        return $this->render('@Base/client/user/profile_show.html.twig', ['user' => $user]);
    }
}

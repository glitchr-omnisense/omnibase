<?php

namespace Base\Controller\Client\User;
use Base\Service\BaseService;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\User\ProfileEditType;
use App\Form\User\ProfileSearchType;
use Endroid\QrCode\QrCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class ProfileController extends AbstractController
{
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/profile/edit", name="base_profile_edit")
     */
    public function Edit()
    {
        if (!($user = $this->getUser()) || !$user->isPersistent())
            return $this->redirectToRoute('base_login');

        return $this->render('@Base/client/user/profile_edit.html.twig', ['user' => $user]);
    }

    /**
     * @Route("/profile", name="base_profile")
     * @Route("/profile/{id}", name="base_profile_id")
     */
    public function Show($id = -1)
    {
        if($id > 0) {

            if ( !($user = $this->userRepository->find($id)) )
                throw $this->createNotFoundException('Tag not found.');

        } else {

            if (!($user = $this->getUser()) || !$user->isPersistent())
                return $this->redirectToRoute('base_search');
        }

        return $this->render('@Base/client/user/profile_show.html.twig', ['user' => $user]);
    }
}

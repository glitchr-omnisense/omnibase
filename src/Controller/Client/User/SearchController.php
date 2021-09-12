<?php

namespace Base\Controller\Client\User;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\User\ProfileEditType;
use App\Form\User\ProfileSearchType;
use Base\Entity\Thread;
use Base\Form\Type\Thread\SearchType;
use Endroid\QrCode\QrCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SearchController extends AbstractController
{
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/profile/search", name="base_user_search")
     */
    public function Index(Request $request)
    {
        $thread = new Thread();

        $form = $this->createForm(SearchType::class, $thread);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //TODO
            dump($form);
        }

        return $this->render('@Base/client/user/search.html.twig');
    }
}

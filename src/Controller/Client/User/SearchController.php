<?php

namespace Base\Controller\Client\User;

use App\Repository\UserRepository;
use Base\BaseBundle\BaseBundle;
use Base\Entity\Thread;
use Base\Form\Type\Thread\SearchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SearchController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        if(BaseBundle::hasDoctrine())
            $this->userRepository = $entityManager->getRepository(User::class);
    }

    /**
     * @Route("/profile/search", name="user_search")
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

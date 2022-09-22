<?php

namespace Base\Controller\Frontend\User;

use App\Entity\User;
use Base\Enum\UserState;
use Base\Form\Data\User\SearchData;
use Base\Form\FormProxyInterface;
use Base\Form\Type\User\SearchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;

class SearchController extends AbstractController
{
    protected $userRepository;

    public function __construct(FormProxyInterface $formProxy, EntityManagerInterface $entityManager)
    {
        $this->formProxy      = $formProxy;
        $this->entityManager  = $entityManager;
        $this->userRepository = $entityManager->getRepository(User::class);
    }

    /**
     * @Route("/search/user", name="user_search")
     */
    public function Main(Request $request, ?FormInterface $formSearch = null)
    {
        $formSearch = $formSearch ?? $this->formProxy->getForm("user:search") ?? $this->createForm(SearchType::class, new SearchData());
        $formSearch->handleRequest($request);

        $formattedData = null;
        if ($formSearch->isSubmitted() && $formSearch->isValid()) {

            $formattedData = clone $formSearch->getData();
            $formattedData->username = $formattedData->username;
        }

        $users = [];
        if($formattedData) {

            $users = array_map(fn($t) => $t->getTranslatable(), $this->userRepository->findByInsensitivePartialModel(
                ["username" => "%" . ($formattedData->username) . "%",],
                ["translatable.state" => UserState::VERIFIED])->getResult()
            );
        }
        
        return $this->render('@Base/client/user/search.html.twig', [
            "form" => $formSearch->createView(),
            "form_data" => $formattedData ?? new SearchData(),
            "users" => $users
        ]);
    }
}

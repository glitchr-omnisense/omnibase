<?php

namespace Base\Controller\Client;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Form\FormProxyInterface;
use Base\Form\Model\UserSearchModel;
use Base\Form\Type\UserSearchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;

class UserSearchController extends AbstractController
{
    protected FormProxyInterface $formProxy;
    protected EntityManagerInterface $entityManager;
    protected UserRepository $userRepository;

    public function __construct(FormProxyInterface $formProxy, EntityManagerInterface $entityManager)
    {
        $this->formProxy = $formProxy;
        $this->entityManager = $entityManager;
        $userRepository = $entityManager->getRepository(User::class);
        if (!$userRepository instanceof UserRepository) {
            throw new \LogicException(sprintf('The repository of %s is not a %s.', User::class, UserRepository::class));
        }
        $this->userRepository = $userRepository;
    }

    #[Route("/search/user", name:"user_search")]
    public function Main(Request $request, ?FormInterface $formSearch = null)
    {
        $formSearch = $formSearch ?? $this->formProxy->get("user:search") ?? $this->createForm(UserSearchType::class, new UserSearchModel());
        $formSearch->handleRequest($request);

        $formattedData = null;
        if ($formSearch->isSubmitted() && $formSearch->isValid()) {
            $formattedData = clone $formSearch->getData();
        }

        // Members are looked for by their username, when the application's user has one: never by
        // their e-mail address, which this page would show to anyone (base User's identifier).
        $users = [];
        $term = trim((string) ($formattedData?->username ?? ""));
        if ($term !== "" && $this->entityManager->getClassMetadata(User::class)->hasField("username")) {
            $users = $this->userRepository->createQueryBuilder("u")
                ->where("LOWER(u.username) LIKE :term")
                ->setParameter("term", "%" . addcslashes(mb_strtolower($term), "%_") . "%")
                ->orderBy("u.username", "ASC")
                ->setMaxResults(50)
                ->getQuery()->getResult();
            $users = array_values(array_filter($users, fn($u) => !$u->isBanned() && !$u->isGhost()));
        }

        return $this->render('client/user/search.html.twig', [
            "form" => $formSearch->createView(),
            "form_data" => $formattedData ?? new UserSearchModel(),
            "users" => $users
        ]);
    }
}

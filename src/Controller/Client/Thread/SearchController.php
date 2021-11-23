<?php

namespace Base\Controller\Client\Thread;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\User\ProfileEditType;
use App\Form\User\ProfileSearchType;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Form\Data\Thread\SearchData;
use Base\Form\Type\Thread\SearchbarType;
use Base\Form\Type\Thread\SearchType;
use Base\Repository\ThreadRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SearchController extends AbstractController
{
    protected $threadRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager    = $entityManager;
        $this->threadRepository = $entityManager->getRepository(Thread::class);
    }

    /**
     * @Route("/search", name="base_thread_search")
     */
    public function Main(Request $request, ?FormInterface $formSearch = null, ?FormInterface $formSearchbar = null)
    {
        $threads = [];

        $formSearch = $formSearch ?? $this->createForm(SearchType::class, new SearchData());
        $formSearch->handleRequest($request);
        $formSearchbar = $formSearchbar ?? $this->createForm(SearchbarType::class, new SearchData());
        $formSearchbar->handleRequest($request);

        $formSearchattedData = null;
        if ($formSearchbar->isSubmitted() && $formSearchbar->isValid()) {

            $formSearchattedData = clone $formSearchbar->getData();
            $formSearchattedData->content = $formSearchattedData->generic;
            $formSearchattedData->title   = $formSearchattedData->generic;
            $formSearchattedData->excerpt = $formSearchattedData->generic;

        } else if ($formSearch->isSubmitted() && $formSearch->isValid()) {

            $formSearchattedData = clone $formSearch->getData();
            $formSearchattedData->content = $formSearchattedData->content ?? $formSearchattedData->generic;
            $formSearchattedData->title   = $formSearchattedData->title   ?? $formSearchattedData->generic;
            $formSearchattedData->excerpt = $formSearchattedData->excerpt ?? $formSearchattedData->generic;
        }

        if($formSearchattedData) {
            
            $data = new SearchData();
            $data->content = "%" . ($formSearchattedData->content ?? $formSearchattedData->generic) . "%";
            $data->title   = "%" . ($formSearchattedData->title   ?? $formSearchattedData->generic ?? $formSearchattedData->content) . "%";
            $data->excerpt = "%" . ($formSearchattedData->excerpt ?? $formSearchattedData->generic ?? $formSearchattedData->content) . "%";
            $data->generic = null;

            $entityManager = $this->entityManager;
            $threads = $this->threadRepository->findByStateAndInsensitivePartialModel([ThreadState::PUBLISHED, ThreadState::APPROVED], $data)->getResult();
            usort($threads, function ($a, $b) use ($entityManager) {
                return $entityManager->getRepository(get_class($a))->getHierarchy() < $entityManager->getRepository(get_class($b))->getHierarchy() ? -1 : 1;
            });
        }

        return $this->render('@Base/client/thread/search.html.twig', [
            "form" => $formSearch->createView(),
            "form_data" => $formSearchattedData ?? new SearchData(),
            "threads" => $threads
        ]);
    }
}

<?php

namespace Base\Controller\Client\Thread;

use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Form\Data\Thread\SearchData;
use Base\Form\Type\Thread\SearchbarType;
use Base\Form\Type\Thread\SearchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;

class SearchController extends AbstractController
{
    protected $threadRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager    = $entityManager;
        $this->threadRepository = $entityManager->getRepository(Thread::class);
    }

    /**
     * @Route("/search", name="thread_search")
     */
    public function Main(Request $request, ?FormInterface $formSearch = null, ?FormInterface $formSearchbar = null)
    {
        $formSearch = $formSearch ?? $this->formProxy->getForm("thread:search") ?? $this->createForm(SearchType::class, new SearchData());
        $formSearch->handleRequest($request);
        $formSearchbar = $formSearchbar ?? $this->formProxy->getForm("thread:searchbar") ?? $this->createForm(SearchbarType::class, new SearchData());
        $formSearchbar->handleRequest($request);

        $formattedData = null;
        if ($formSearchbar->isSubmitted() && $formSearchbar->isValid()) {

            $formattedData = clone $formSearchbar->getData();
            $formattedData->content = $formattedData->generic;
            $formattedData->title   = $formattedData->generic;
            $formattedData->excerpt = $formattedData->generic;

        } else if ($formSearch->isSubmitted() && $formSearch->isValid()) {

            $formattedData = clone $formSearch->getData();
            $formattedData->content = $formattedData->content ?? $formattedData->generic;
            $formattedData->title   = $formattedData->title   ?? $formattedData->generic;
            $formattedData->excerpt = $formattedData->excerpt ?? $formattedData->generic;
        }

        $threads = [];

        if($formattedData) {

            $data = new SearchData();
            $data->content = "%" . ($formattedData->content ?? $formattedData->generic) . "%";
            $data->title   = "%" . ($formattedData->title   ?? $formattedData->generic ?? $formattedData->content) . "%";
            $data->excerpt = "%" . ($formattedData->excerpt ?? $formattedData->generic ?? $formattedData->content) . "%";
            $data->generic = null;

            $entityManager = $this->entityManager;
            $threads = $this->threadRepository->findByStateAndInsensitivePartialModel(ThreadState::PUBLISH, $data)->getResult();
            usort($threads, function ($a, $b) use ($entityManager) {
                return $entityManager->getRepository(get_class($a))->getHierarchy() < $entityManager->getRepository(get_class($b))->getHierarchy() ? -1 : 1;
            });
        }

        return $this->render('@Base/client/thread/search.html.twig', [
            "form" => $formSearch->createView(),
            "form_data" => $formattedData ?? new SearchData(),
            "threads" => $threads
        ]);
    }
}

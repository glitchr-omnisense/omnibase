<?php

namespace Base\Controller\Client\Thread;

use Base\Service\BaseService;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\User\ProfileEditType;
use App\Form\User\ProfileSearchType;
use Base\Entity\Thread;
use Base\Form\Data\Thread\SearchData;
use Base\Form\Type\Thread\SearchbarType;
use Base\Form\Type\Thread\SearchType;
use Base\Repository\ThreadRepository;
use Endroid\QrCode\QrCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SearchController extends AbstractController
{
    protected $baseService;
    protected $threadRepository;

    public function __construct(BaseService $baseService, ThreadRepository $threadRepository)
    {
        $this->baseService = $baseService;
        $this->threadRepository = $threadRepository;
    }

    /**
     * @Route("/search", name="base_search")
     */
    public function Main(Request $request, ?FormInterface $form = null)
    {
        $threads = [];

        $form = $form ?? $this->createForm(SearchType::class, new SearchData());
        $form->handleRequest($request);

        $formSearchbar = $this->createForm(SearchbarType::class, new SearchData());
        $formSearchbar->handleRequest($request);

        $formattedData = null;
        if ($formSearchbar->isSubmitted() && $formSearchbar->isValid()) {

            $formattedData = clone $formSearchbar->getData();
            $formattedData->content = $formattedData->generic;
            $formattedData->title   = $formattedData->generic;
            $formattedData->excerpt = $formattedData->generic;

        } else if ($form->isSubmitted() && $form->isValid()) {

            $formattedData = clone $form->getData();
            $formattedData->content = $formattedData->content ?? $formattedData->generic;
            $formattedData->title   = $formattedData->title   ?? $formattedData->generic;
            $formattedData->excerpt = $formattedData->excerpt ?? $formattedData->generic;
        }

        if($formattedData) {

            $data = new SearchData();
            $data->content = "%" . ($formattedData->content ?? $formattedData->generic) . "%";
            $data->title   = "%" . ($formattedData->title   ?? $formattedData->generic ?? $formattedData->content) . "%";
            $data->excerpt = "%" . ($formattedData->excerpt ?? $formattedData->generic ?? $formattedData->content) . "%";
            $data->generic = null;

            $threads = $this->threadRepository->findByStateAndInsensitivePartialModel([Thread::STATE_PUBLISHED, Thread::STATE_APPROVED], $data);
            usort($threads, function ($a, $b) {
                return $a->getSection() < $b->getSection() ? -1 : 1;
            });
        }

        return $this->render('@Base/client/thread/search.html.twig', [
            "form" => $form->createView(),
            "form_data" => $formattedData ?? new SearchData(),
            "threads" => $threads
        ]);
    }
}

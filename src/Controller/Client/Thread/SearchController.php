<?php

namespace Base\Controller\Client\Thread;

use Base\Service\BaseService;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\User\ProfileEditType;
use App\Form\User\ProfileSearchType;
use Base\Entity\Thread;
use Base\Form\Data\Thread\SearchData;
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
        $form = $form ?? $this->createForm(SearchType::class, new SearchData());
        $form->handleRequest($request);

        $threads = [];
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $data->content = "%" . $data->content . "%";
            $data->title   = $data->content;
            $data->excerpt = $data->content;

            $threads = $this->threadRepository->findByStateAndPartialModel(Thread::STATE_PUBLISHED, $data);
        }

        return $this->render('@Base/client/thread/search.html.twig', [
            "form" => $form->createView(),
            "threads" => $threads
        ]);
    }
}

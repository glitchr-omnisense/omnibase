<?php

namespace Base\Controller\Frontend\Thread;

use Base\Entity\ThreadIntl;
use Base\Enum\ThreadState;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\ThreadSearchType;
use Base\Repository\ThreadIntlRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 *
 */
class SearchController extends AbstractController
{
    protected FormProxyInterface $formProxy;
    protected EntityManagerInterface $entityManager;
    protected ThreadIntlRepository $threadIntlRepository;

    public function __construct(FormProxyInterface $formProxy, EntityManagerInterface $entityManager)
    {
        $this->formProxy = $formProxy;
        $this->entityManager = $entityManager;
        $this->threadIntlRepository = $entityManager->getRepository(ThreadIntl::class);
    }

    /**
     * @Route({"en": "/search", "fr": "/rechercher"}, name="thread_search")
     */
    public function Main(Request $request)
    {
        $formProcessor = $this->formProxy->createProcessor("thread:search", ThreadSearchType::class, []);
        $formProcessor
            ->setData($this->formProxy->get("thread:searchbar")?->getData())
            ->onDefault(function (FormProcessorInterface $formProcessor) {
                return $this->render('client/thread/search.html.twig', [
                    "form" => $formProcessor->getForm()->createView(),
                    "form_data" => $formProcessor->getForm()->getData()
                ]);
            })
            ->onDefault(function (FormProcessorInterface $formProcessor) use ($request) {
                $threads = [];
                $data = $formProcessor->getData() ? clone $formProcessor->getData() : null;
                if ($data) {
                    $data->content = $data->content ?? $data->generic ?? "";
                    $data->title = $data->title ?? $data->generic ?? "";
                    $data->excerpt = $data->excerpt ?? $data->generic ?? "";

                    $formattedData = clone $data;
                    $formattedData->content = str_strip("%" . $data->content . "%", "%%", "%%");
                    $formattedData->title = str_strip("%" . $data->title . "%", "%%", "%%");
                    $formattedData->excerpt = str_strip("%" . $data->excerpt . "%", "%%", "%%");
                    $formattedData->generic = str_strip("%" . $data->generic . "%", "%%", "%%");

                    $states = [ThreadState::PUBLISH];
                    if ($this->isGranted("ROLE_ADMIN")) {
                        $states = [];
                    }

                    $threads = array_map(fn($t) => $t->getTranslatable(), $this->threadIntlRepository->cacheByInsensitivePartialModel([
                        "content" => $formattedData->content,
                        "title" => $formattedData->title,
                        "excerpt" => $formattedData->excerpt,
                    ], ["translatable.state" => $states, "translatable.parent" => $formattedData->parent_id])->getResult());

                    usort($threads, function ($a, $b) {
                        $aRepository = $this->entityManager->getRepository(get_class($a));
                        $bRepository = $this->entityManager->getRepository(get_class($b));

                        return $aRepository->getHierarchy() < $bRepository->getHierarchy() ? -1 : 1;
                    });
                }

                return $this->render('client/thread/search.html.twig', [
                    "form" => $formProcessor->getForm()->createView(),
                    "model" => $data,
                    "threads" => $threads,
                ]);
            })
            ->handleRequest($request);

        return $formProcessor->getResponse();
    }
}

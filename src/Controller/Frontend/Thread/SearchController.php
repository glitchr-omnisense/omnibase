<?php

namespace Base\Controller\Frontend\Thread;

use Base\Entity\ThreadIntl;
use Base\Enum\ThreadState;
use Base\Form\Data\Thread\SearchData;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Model\ThreadSearchModel;
use Base\Form\Type\ThreadSearchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SearchController extends AbstractController
{
    protected $threadRepository;

    public function __construct(FormProxyInterface $formProxy, EntityManagerInterface $entityManager)
    {
        $this->formProxy        = $formProxy;
        $this->entityManager    = $entityManager;
        $this->threadIntlRepository = $entityManager->getRepository(ThreadIntl::class);
    }

    /**
     * @Route({"en": "/search", "fr": "/rechercher"}, name="thread_search")
    */
    public function Main(Request $request)
    {
        $formProcessor = $this->formProxy->getProcessor("thread:searchbar") ?? $this->formProxy->createProcessor("thread:search", ThreadSearchType::class, []);
        $formProcessor
            ->setData($this->formProxy->get("thread:searchbar")?->getData())

            ->onDefault(function(FormProcessorInterface $formProcessor) {

                $formattedData = $formProcessor->getData();
                $formattedData->generic = str_strip("%" . $formattedData->generic . "%", "%%", "%%");
                $formattedData->content = str_strip("%" . ($formattedData->content ?? $formattedData->generic) . "%", "%%", "%%");
                $formattedData->title   = str_strip("%" . ($formattedData->title   ?? $formattedData->generic) . "%", "%%", "%%");
                $formattedData->excerpt = str_strip("%" . ($formattedData->excerpt ?? $formattedData->generic) . "%", "%%", "%%");
                
                $threads = array_map(fn($t) => $t->getTranslatable(), $this->threadIntlRepository->cacheByInsensitivePartialModel([
                    "content" => $formattedData->content,
                    "title"   => $formattedData->title,
                    "excerpt" => $formattedData->excerpt,
                ], ["translatable.state" => ThreadState::PUBLISH, "translatable.parent" => $formattedData->parent_id])->getResult());
    
                usort($threads, function ($a, $b)
                {
                    $aRepository = $this->entityManager->getRepository(get_class($a));
                    $bRepository = $this->entityManager->getRepository(get_class($b));
                    
                    return $aRepository->getHierarchy() < $bRepository->getHierarchy() ? -1 : 1;
                });
                
                return $this->render('@Base/client/thread/search.html.twig', [
                    "form" => $formProcessor->getForm()->createView(),
                    "form_data" => $formProcessor->getForm()->getData(),
                    "threads" => $threads
                ]);
            })

            ->handleRequest($request);

        return $formProcessor->getResponse();
    }
}

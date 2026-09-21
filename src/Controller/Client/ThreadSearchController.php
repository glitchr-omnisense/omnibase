<?php

namespace Base\Controller\Client;

use Base\Entity\ThreadIntl;
use Base\Enum\ThreadState;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\ThreadSearchType;
use Base\Repository\ThreadIntlRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ThreadSearchController extends AbstractController
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

    #[Route(["en" => "/search", "fr" => "/rechercher"], name: "thread_search")]
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

                    // The "%term%" wrapping that used to happen here now lives
                    // in searchTranslations, next to the LIKE it belongs to.
                    // $data keeps the raw terms, which is what the template
                    // needs to highlight them in the results.

                    $states = [ThreadState::PUBLISH];
                    if ($this->isGranted("ROLE_ADMIN")) {
                        $states = [];
                    }

                    $threads = array_map(fn($t) => $t->getTranslatable(), $this->searchTranslations($data, $states));

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

    /**
     * Translations whose title, excerpt OR content matches the term, scoped
     * by thread state and parent.
     *
     * Hand-written instead of a magic finder because the shape is
     * "(a OR b OR c) AND scope" and the finder DSL has no parentheses: it
     * applies one separator to every criterion it is handed, so asking for
     * the three fields with "Or" would OR the state filter in with them and
     * return every thread on the site. This query used to go through the
     * DSL's Model clause, which is AND-only by design and since it began
     * requiring a select object rather than an array made the page a 500 -
     * and had it been given one, "the word appears in the title AND in the
     * excerpt AND in the body" would have matched almost nothing.
     */
    protected function searchTranslations(object $data, array $states): array
    {
        $terms = array_filter(
            ["title" => $data->title, "excerpt" => $data->excerpt, "content" => $data->content],
            fn($term) => is_string($term) && trim($term) !== ""
        );

        // An empty search matches nothing, rather than LIKE '%%' - i.e. the
        // entire site - which is what wrapping an empty term used to build.
        if (!$terms) {
            return [];
        }

        $queryBuilder = $this->threadIntlRepository->createQueryBuilder("i")->join("i.translatable", "t");

        $expr = [];
        foreach ($terms as $field => $term) {
            $expr[] = "LOWER(i." . $field . ") LIKE :term_" . $field;
            $queryBuilder->setParameter("term_" . $field, "%" . mb_strtolower(trim($term)) . "%");
        }

        $queryBuilder->andWhere(implode(" OR ", $expr));

        if ($states) {
            $queryBuilder->andWhere("t.state IN (:states)")->setParameter("states", $states);
        }

        if ($data->parent_id) {
            $queryBuilder->andWhere("t.parent = :parent")->setParameter("parent", $data->parent_id);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}

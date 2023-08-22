<?php

namespace Base\Controller\UX;

use Base\Database\Mapping\ClassMetadataManipulator;

use Base\Service\Model\Autocomplete;
use Base\Service\ObfuscatorInterface;
use Base\Service\PaginatorInterface;
use Base\Service\TradingMarketInterface;
use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exchanger\Exception\ChainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(priority = -1)
 * */
class AutocompleteController extends AbstractController
{
    use BaseTrait;

    /**
     * @var PaginatorInterface
     */
    protected PaginatorInterface $paginator;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;
    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;
    /**
     * @var Autocomplete
     */
    protected Autocomplete $autocomplete;

    /**
     * @var TradingMarketInterface
     */
    protected TradingMarketInterface $tradingMarket;

    /**
     * @var Profiler|null
     */
    protected ?Profiler $profiler;

    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    public function __construct(ObfuscatorInterface $obfuscator, RequestStack $requestStack, TradingMarketInterface $tradingMarket, TranslatorInterface $translator, EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator, ?Profiler $profiler = null)
    {
        $this->requestStack = $requestStack;
        $this->obfuscator = $obfuscator;
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->tradingMarket = $tradingMarket;
        $this->paginator = $paginator;
        $this->autocomplete = new Autocomplete($translator);
        $this->profiler = $profiler;
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/autocomplete/{data}", name="ux_autocomplete")
     */
    public function Main(Request $request, string $data): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $dict = $this->obfuscator->decode($data);
        if ($dict === null) {
            return new JsonResponse("Unexpected request", 500);
        }

        $fields = $dict["fields"] ?? null;
        $filters = $dict["filters"] ?? null;
        $class = $dict["class"] ?? null;
        $html = $dict["html"] ?? true;

        $format = FORMAT_IDENTITY;
        if ($dict["capitalize"] !== null) {
            $format = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;
        }

        $token = $dict["token"] ?? null;
        $tokenName = $dict["token_name"] ?? null;
        if (!$tokenName || !$this->isCsrfTokenValid($tokenName, $token)) {
            return new JsonResponse("Invalid token. Please refresh the page and try again", 500);
        }

        $expectedMethod = $this->getService()->isDebug() ? ["GET", "POST"] : ["POST"];
        if (in_array($request->getMethod(), $expectedMethod)) {

            $term = strtolower(str_strip_accents($request->get("term")) ?? "");
            $meta = explode(".", $request->get("page") ?? "");
            $page = max(1, intval($meta[0] ?? 1));
            $bookmark = max(0, intval($meta[1] ?? 0));

            $results = [];
            $pagination = false;
            if ($this->classMetadataManipulator->isEntity($class)) {
                $repository = $this->entityManager->getRepository($class);

                if (!is_associative($fields)) {
                    $fields = array_fill_keys($fields, $term);
                }
                $fields = array_filter($fields);

                $index0 = -1;
                $entries = $repository->cacheByInstanceOfAndPartialModel($filters, $fields, [], [], null, null, ["id"]); // If no field, then get them all..

                do {
                    $bookIsFull = false;
                    $book = $this->paginator->paginate($entries, $page);
                    if ($page > $book->getTotalPages() + 1) {
                        break;
                    }

                    foreach ($book as $index => $result) {
                        $entry = $result["entity"] ?? null;
                        $entry = $this->autocomplete->resolve($entry, $class, ["format" => $format, "html" => $html]);

                        if ($entry === null) {
                            continue;
                        }
                        if ($index0 < 0) {
                            $index0 = $index;
                        }
                        if ($index - $index0 < $bookmark) {
                            continue;
                        }

                        $search = strtolower(str_strip_accents(strval($entry["search"] ?? $entry["text"])));
                        if (str_contains($search, $term)) {
                            $results[] = $entry;
                        }

                        $bookIsFull = count($results) >= $book->getPageSize();
                        if ($bookIsFull) {
                            break;
                        }

                        $bookmark++;
                    }

                    $bookmark = $bookmark % $book->getPageSize();
                } while ($page++ < $book->getTotalPages() && !$bookIsFull);

                $pagination = [];
                $pagination["more"] = $book->getTotalPages() > $book->getPage() || $bookIsFull;
                if ($pagination["more"]) {
                    $page = $book->getPage();
                    $bookmark = ($book->getBookmark() + 1) % $book->getPageSize();
                    if ($bookmark == 0) {
                        $page++;
                    }

                    $pagination["page"] = $page . "." . $bookmark;
                }
            } elseif ($this->classMetadataManipulator->isEnumType($class) || $this->classMetadataManipulator->isSetType($class)) {
                $values = $class::getPermittedValues();
                foreach ($values as $value) {
                    $results[] = array_values(array_filter($this->autocomplete->resolve($value, $class, ["format" => $format, "html" => $html]), fn($r) => !empty($fields) || str_contains(mb_strtolower(strval($r["text"])), $term)));
                }
            }

            $array = [
                "pagination" => $pagination,
                "results" => $results
            ];

            return new JsonResponse($array);
        }

        return new JsonResponse("Invalid request", 500);
    }


    /**
     * @Route("/autocomplete/currency/{source}/{target}/{data}", name="ux_autocomplete_forex")
     */
    public function Forex(Request $request, string $source, string $target, string $data, ?Profiler $profiler = null): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $dict = $this->obfuscator->decode($data);

        $token = $dict["token"] ?? null;
        $tokenName = $dict["token_name"] ?? null;
        if (!$tokenName || !$this->isCsrfTokenValid($tokenName, $token)) {
            return new JsonResponse("Invalid token. Please refresh the page and try again", 500);
        }

        try {
            $rate = $this->tradingMarket->getLatest($source, $target);
        } catch (ChainException $e) {
            return new JsonResponse("Invalid request", 500);
        }

        $array = [
            "source" => $source,
            "target" => $target,
            "rate" => $rate
        ];

        return new JsonResponse($array);
    }


    /**
     * @Route("/autocomplete/{provider}/{pageSize}/{data}", name="ux_autocomplete_icons")
     */
    public function Icons(Request $request, string $provider, int $pageSize, string $data, ?Profiler $profiler = null): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $dict = $this->obfuscator->decode($data);

        $token = $dict["token"] ?? null;
        $html = $dict["html"] ?? true;
        $pageSize = $dict["page_size"] ?? 200;

        $format = FORMAT_IDENTITY;
        if ($dict["capitalize"] !== null) {
            $format = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;
        }

        $results = [];
        $pagination = false;
        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {
            $term = mb_strtolower($request->get("term")) ?? "";
            $meta = explode(".", $request->get("page") ?? "");
            $page = max(1, intval($meta[0] ?? 1));
            $bookmark = max(0, intval($meta[1] ?? 0));

            $iconProvider = $this->getIconProvider()->getAdapter($provider);
            $entries = $iconProvider->getChoices($term);

            $index0 = -1;
            do {
                $bookIsFull = false;
                $book = $this->paginator->paginate($entries, $page, $pageSize);
                if ($page > $book->getTotalPages() + 1) {
                    break;
                }

                foreach ($book as $index => $result) {
                    $entry = $this->autocomplete->resolveArray($result, ["format" => $format, "html" => $html]);
                    if ($entry === null) {
                        continue;
                    }
                    if ($index0 < 0) {
                        $index0 = $index;
                    }
                    if ($index - $index0 < $bookmark) {
                        continue;
                    }

                    $bookIsFull = count_leaves($results) >= $book->getPageSize();
                    if ($bookIsFull) {
                        break;
                    }

                    $bookmark++;
                    $results[] = $entry;
                }

                $bookmark = $bookmark % $book->getPageSize();
            } while ($page++ < $book->getTotalPages() && !$bookIsFull);

            $pagination = [];
            $pagination["more"] = $book->getTotalPages() > $book->getPage() || $bookIsFull;
            if ($pagination["more"]) {
                $page = $book->getPage();
                $bookmark = ($book->getBookmark() + 1) % $book->getPageSize();
                if ($bookmark == 0) {
                    $page++;
                }

                $pagination["page"] = $page . "." . $bookmark;
            }

            $array = [
                "pagination" => $pagination,
                "results" => $results
            ];

            return new JsonResponse($array);
        }

        return new JsonResponse("Invalid request", 500);
    }
}

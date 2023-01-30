<?php

namespace Base\Controller\UX;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Service\Model\Autocomplete;
use Base\Service\ObfuscatorInterface;
use Base\Service\Paginator;
use Base\Service\PaginatorInterface;
use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AutocompleteController extends AbstractController
{
    use BaseTrait;

    /**
     * @var Paginator
     */
    protected $paginator;

    /**
     * @var ObfuscatorInterface
     */
    protected $obfuscator;
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;
    /**
     * @var Autocomplete
     */
    protected $autocomplete;

    public function __construct(ObfuscatorInterface $obfuscator, TranslatorInterface $translator, EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->obfuscator = $obfuscator;
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->paginator = $paginator;
        $this->autocomplete = new Autocomplete($translator);
    }

    /**
     * @Route("/autocomplete/{data}", name="ux_autocomplete")
     */
    public function Main(Request $request, string $data): Response
    {
        $dict    = $this->obfuscator->decode($data);
        if($dict === null) {

            $array = ["status" => "Unexpected request"];
            return new JsonResponse($array, 500);
        }

        $fields  = $dict["fields"] ?? null;
        $filters = $dict["filters"] ?? null;
        $class   = $dict["class"] ?? null;
        $html    = $dict["html"] ?? true;

        $format = FORMAT_IDENTITY;
        if ($dict["capitalize"] !== null)
            $format = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $token   = $dict["token"] ?? null;
        $tokenName = $dict["token_name"] ?? null;
        if(!$tokenName || !$this->isCsrfTokenValid($tokenName, $token)) {

            $array = ["status" => "Invalid token. Please refresh the page and try again"];
            return new JsonResponse($array, 500);
        }

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($request->getMethod() == $expectedMethod) {

            $term = strtolower(str_strip_accents($request->get("term")) ?? "");
            $meta = explode(".", $request->get("page") ?? "");
            $page     = max(1, intval($meta[0] ?? 1));
            $bookmark = max(0, intval($meta[1] ?? 0));

            $results = [];
            $pagination = false;
            if($this->classMetadataManipulator->isEntity($class)) {

                $repository = $this->entityManager->getRepository($class);

                if(!is_associative($fields)) $fields = array_fill_keys($fields, $term);
                $fields = array_filter($fields);

                $index0 = -1;
                $entries = $repository->cacheByInstanceOfAndPartialModel($filters, $fields, [],[],null,null,["id"]); // If no field, then get them all..

                do {

                    $bookIsFull = false;
                    $book = $this->paginator->paginate($entries, $page);
                    if($page > $book->getTotalPages()+1) break;

                    foreach($book as $index => $result) {

                        $entry = $result["entity"] ?? null;
                        $entry = $this->autocomplete->resolve($entry, $class, ["format" => $format, "html" => $html]);

                        if($entry === null) continue;
                        if($index0 < 0) $index0 = $index;
                        if($index - $index0 < $bookmark) continue;

                        $search = strtolower(str_strip_accents(strval($entry["search"] ?? $entry["text"])));
                        if(str_contains($search, $term))
                            $results[] = $entry;

                        $bookIsFull = count($results) >= $book->getPageSize();
                        if($bookIsFull) break;

                        $bookmark++;
                    }

                    $bookmark = $bookmark % $book->getPageSize();

                } while($page++ < $book->getTotalPages() && !$bookIsFull);

                $pagination = [];
                $pagination["more"] = $book->getTotalPages() > $book->getPage() || $bookIsFull;
                if ($pagination["more"]) {

                    $page = $book->getPage();
                    $bookmark = ($book->getBookmark()+1) % $book->getPageSize();
                    if($bookmark == 0) $page++;

                    $pagination["page"] = $page.".".$bookmark;
                }

            } else if ($this->classMetadataManipulator->isEnumType($class) || $this->classMetadataManipulator->isSetType($class)) {

                $values = $class::getPermittedValues();
                foreach($values as $value)
                    $results[] = array_values(array_filter($this->autocomplete->resolve($value, $class, ["format" => $format, "html" => $html]), fn($r) => !empty($fields) || str_contains(mb_strtolower(strval($r["text"])), $term)));
            }

            $array = [
                "pagination" => $pagination,
                "results" => $results
            ];

            return new JsonResponse($array);
        }

        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }

    /**
     * @Route("/autocomplete/{provider}/{pageSize}/{data}", name="ux_autocomplete_icons")
     */
    public function Icons(Request $request, string $provider, int $pageSize, string $data): Response
    {
        $dict     = $this->obfuscator->decode($data);

        $token    = $dict["token"] ?? null;
        $html     = $dict["html"] ?? true;
        $pageSize = $dict["page_size"] ?? 200;

        $format = FORMAT_IDENTITY;
        if ($dict["capitalize"] !== null)
            $format = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $results = [];
        $pagination = false;
        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod)
        {
            $term = mb_strtolower($request->get("term")) ?? "";
            $meta = explode(".", $request->get("page") ?? "");
            $page     = max(1, intval($meta[0] ?? 1));
            $bookmark = max(0, intval($meta[1] ?? 0));

            $iconProvider = $this->getIconProvider()->getAdapter($provider);
            $entries = $iconProvider->getChoices($term);

            $index0 = -1;
            do {

                $bookIsFull = false;
                $book = $this->paginator->paginate($entries, $page, $pageSize);
                if($page > $book->getTotalPages()+1) break;

                foreach($book as $index => $result) {

                    $entry = $this->autocomplete->resolveArray($result, ["format" => $format, "html" => $html]);
                    if($entry === null) continue;
                    if($index0 < 0) $index0 = $index;
                    if($index - $index0 < $bookmark) continue;

                    $bookIsFull = count_leaves($results) >= $book->getPageSize();
                    if($bookIsFull) break;

                    $bookmark++;
                    $results[] = $entry;
                }

                $bookmark = $bookmark % $book->getPageSize();

            } while($page++ < $book->getTotalPages() && !$bookIsFull);

            $pagination = [];
            $pagination["more"] = $book->getTotalPages() > $book->getPage() || $bookIsFull;
            if ($pagination["more"]) {

                $page = $book->getPage();
                $bookmark = ($book->getBookmark()+1) % $book->getPageSize();
                if($bookmark == 0) $page++;

                $pagination["page"] = $page.".".$bookmark;
            }

            $array = [
                "pagination" => $pagination,
                "results" => $results
            ];

            return new JsonResponse($array);
        }

        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }
}

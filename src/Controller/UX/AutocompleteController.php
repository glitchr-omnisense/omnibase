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

    public function __construct(ObfuscatorInterface $obfuscator, TranslatorInterface $translator, EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->obfuscator = $obfuscator;
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->paginator = $paginator;
        $this->autocomplete = new Autocomplete($translator);
    }

    /**
     * @Route("/autocomplete/{hashid}", name="ux_autocomplete")
     */
    public function Main(Request $request, string $hashid): Response
    {
        $dict    = $this->obfuscator->decode($hashid);
        $token   = $dict["token"] ?? null;
        $fields  = $dict["fields"] ?? null;
        $filters = $dict["filters"] ?? null;
        $class   = $dict["class"] ?? null;
        $html    = $dict["html"] ?? true;
        $format  = ($dict["capitalize"] ?? false) ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {

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

                $entries = $repository->cacheByInstanceOfAndPartialModel($filters, $fields); // If no field, then get them all..
                do {

                    $book = $this->paginator->paginate($entries, $page);
                    if($page > $book->getTotalPages())
                        throw $this->createNotFoundException("Page Not Found");

                    $bookIsFull = false;

                    $index = 0;
                    foreach($book as $entry) {

                        if($index++ < $bookmark) continue;
                        $bookmark = $index;

                        $entry = $this->autocomplete->resolve($entry, $class, ["format" => $format, "html" => $html]);
                        $search = strtolower(str_strip_accents(strval($entry["search"] ?? $entry["text"])));
                        if(str_contains($search, $term))
                            $results[] = $entry;

                        $bookIsFull = count($results) >= $book->getPageSize();
                        if($bookIsFull) break;
                    }

                    $bookmark = $bookmark % $book->getPageSize();
                    $page++;

                } while($page <= $book->getTotalPages() && !$bookIsFull);

                $pagination = [];
                $pagination["more"] = $book->getTotalPages() > $book->getPage();
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
     * @Route("/autocomplete/{provider}/{pageSize}/{hashid}", name="ux_autocomplete_icons")
     */
    public function Icons(Request $request, string $provider, int $pageSize, string $hashid): Response
    {
        $dict    = $this->obfuscator->decode($hashid);

        $token   = $dict["token"] ?? null;
        $format  = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {

            $term = mb_strtolower($request->get("term")) ?? "";
            $page = $request->get("page") ?? 1;

            $iconProvider = $this->getIconProvider()->getAdapter($provider);
            $entries = $iconProvider->getChoices($term);

            $book = $this->paginator->paginate($entries, $page, $pageSize);
            $pagination = $book->getTotalPages() > $book->getPage();
            $array["pagination"] = ["more" => $pagination];
            $results = $book->current();

            $array["results"] = array_transforms(function($k,$v,$callback,$i,$d) use ($format): ?array {

                if(is_array($v)) {

                    $children = array_transforms($callback, $v, ++$d);

                    $group = array_pop_key("_self", $children);
                    $group["text"] = $k;
                    $group["children"] = $children;
                    return [null, $group];
                }

                return [null, ["id" => $v, "icon" => $v, "text" => castcase($k, $format)]];

            }, !empty($results) ? $results : []);

            return new JsonResponse($array);
        }

        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }
}

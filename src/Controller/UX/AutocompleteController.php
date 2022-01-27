<?php

namespace Base\Controller\UX;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Model\Autocomplete;

use Base\Service\Paginator;
use Base\Service\PaginatorInterface;
use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
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

    public function __construct(TranslatorInterface $translator, EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->hashIds = new Hashids($this->getService()->getSalt());
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->paginator = $paginator;
        $this->autocomplete = new Autocomplete($translator);
    }

    public function encode(array $array) : string
    {
        $hex = bin2hex(serialize($array));
        return $this->hashIds->encodeHex($hex);
    }

    public function decode(string $hash): array
    {
        $hex = $this->hashIds->decodeHex($hash);
        return $hex ? unserialize(hex2bin($hex)) : [];
    }

    /**
     * @Route("/autocomplete/{hashid}", name="ux_autocomplete")
     */
    public function Main(Request $request, string $hashid): Response
    {
        $dict    = $this->decode($hashid);
        $token   = $dict["token"] ?? null;
        $fields  = $dict["fields"] ?? null;
        $filters = $dict["filters"] ?? null;
        $class   = $dict["class"] ?? null;
        $format  = ($dict["capitalize"] ?? false) ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {

            $term = mb_strtolower($request->get("term")) ?? "";
            $page = $request->get("page") ?? 1;

            $results = [];
            $pagination = false;
            if($this->classMetadataManipulator->isEntity($class)) {

                $repository = $this->entityManager->getRepository($class);

                if(!is_associative($fields)) $fields = array_fill_keys($fields, $term);
                $fields = array_filter($fields);

                $entries = $repository->findByInstanceOfAndPartialModel($filters, $fields); // If no field, then get them all..
                $book = $this->paginator->paginate($entries, $page);
                $pagination = $book->getTotalPages() > $book->getPage();

                foreach($book as $i => $entry)
                    $results[] = $this->autocomplete->resolve($entry, $class, $format);

            } else if ($this->classMetadataManipulator->isEnumType($class) || $this->classMetadataManipulator->isSetType($class)) {

                $values = $class::getPermittedValues();
                foreach($values as $value)
                    $results[] = $this->autocomplete->resolve($value, $class, $format);
            }

            $array = [];
            $array["pagination"] = ["more" => $pagination];
            $array["results"] = !empty($results) ? $results : [];
            $array["results"] = array_values(array_filter($array["results"], fn($r) => !empty($fields) || str_contains(mb_strtolower(strval($r["text"])), $term)));

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
        $dict    = $this->decode($hashid);

        $token   = $dict["token"] ?? null;
        $format  = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {

            $term = mb_strtolower($request->get("term")) ?? "";
            $page = $request->get("page") ?? 1;

            $iconProvider = $this->getIconService()->getProvider($provider);
            $entries = $iconProvider->getChoices($term);
    
            $book = $this->paginator->paginate($entries, $page, $pageSize);
            $pagination = $book->getTotalPages() > $book->getPage();
            $array["pagination"] = ["more" => $pagination];
            $results = $book->current();

            $array["results"] = array_transforms(function($k,$v,$i,$callback) use ($format): ?array {

                if(is_array($v))
                    return [null, ["text" => $k, "children" => array_transforms($callback, $v)]];

                return [null, ["id" => $v, "icon" => $v, "text" => castcase($k, $format)]];

            }, !empty($results) ? $results : []);

            return new JsonResponse($array);
        }

        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }
}

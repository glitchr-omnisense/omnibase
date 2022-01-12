<?php

namespace Base\Controller;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\Type\SelectType;
use Base\Model\Icon\FontAwesome;
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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SelectController extends AbstractController
{
    use BaseTrait;

    /**
     * @var Paginator
     */
    protected $paginator;

    public function __construct(CacheInterface $cache, TranslatorInterface $translator, EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->hashIds = new Hashids($this->getService()->getSalt());
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->paginator = $paginator;
        $this->translator = $translator;
        $this->cache = $cache;
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
    public function Autocomplete(Request $request, string $hashid): Response
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
                
                if(!is_associative($fields))
                    $fields = array_fill_keys($fields, $term);

                $fields = array_filter($fields);
                $entries = $repository->findByInstanceOfAndPartialModel($filters, $fields); // If no field, then get them all..
                $book = $this->paginator->paginate($entries, $page);
                $pagination = $book->getTotalPages() > $book->getPage();

                foreach($book as $i => $entry)
                    $results[] = SelectType::getFormattedValues($entry, $class, $this->translator, $format);

            } else if ($this->classMetadataManipulator->isEnumType($class) || $this->classMetadataManipulator->isSetType($class)) {

                $values = $class::getPermittedValues();
                foreach($values as $value)
                    $results[] = SelectType::getFormattedValues($value, $class, $this->translator, $format);
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
     * @Route("/autocomplete/fa/{pageSize}/{hashid}", name="ux_autocomplete_fa")
     */
    public function AutocompleteFontAwesomeIcons(Request $request, int $pageSize, string $hashid): Response
    {
        $dict    = $this->decode($hashid);

        $token   = $dict["token"] ?? null;
        $format  = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {

            $term = mb_strtolower($request->get("term")) ?? "";
            $page = $request->get("page") ?? 1;

            $fa = new FontAwesome($this->getService()->getParameterBag("base.vendor.font_awesome.metadata"));
            $entries = $fa->getChoices($term); 

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

    /**
     * @Route("/autocomplete/bi/{pageSize}/{hashid}", name="ux_autocomplete_bi")
     */
    public function AutocompleteBootstrapIcons(Request $request, int $pageSize, string $hashid): Response
    {
        $dict    = $this->decode($hashid);
        $token   = $dict["token"] ?? null;
        $format  = $dict["capitalize"] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE;

        // $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        // if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {

        //     $term = mb_strtolower($request->get("term")) ?? "";
        //     $page = $request->get("page") ?? 1;

        //     $fa = new FontAwesome($this->getService()->getParameterBag("base.vendor.font_awesome.metadata"));
        //     $entries = $fa->getChoices($term);

        //     $book = $this->paginator->paginate($entries, $page, $pageSize);
        //     $pagination = $book->getTotalPages() > $book->getPage();
        //     $array["pagination"] = ["more" => $pagination];
        //     $results = $book->current();

        //     $array["results"] = array_transforms(function($k,$v,$i,$callback) use ($format): ?array {
                
        //         if(is_array($v))
        //             return [null, ["text" => $k, "children" => array_transforms($callback, $v)]];

        //         return [null, ["id" => $v, "icon" => $v, "text" => castcase($k, $format)]];

        //     }, !empty($results) ? $results : []);

        //     return new JsonResponse($array);
        // }

        // $array = ["status" => "Invalid request"];
        // return new JsonResponse($array, 500);
    }
}

<?php

namespace Base\Controller;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\Type\SelectType;
use Base\Service\BaseService;
use Base\Service\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SelectController extends AbstractController
{
    public function __construct(TranslatorInterface $translator, EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator, BaseService $baseService)
    {
        $this->hashIds = new Hashids();
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->paginator = $paginator;
        $this->translator = $translator;
        $this->baseService = $baseService;
    }

    public function encode(array $array) : string
    {
        $hex = bin2hex(serialize($array));
        return $this->hashIds->encodeHex($hex);
    }

    public function decode(string $hash): array
    {
        $hex = $this->hashIds->decodeHex($hash);
        return unserialize(hex2bin($hex));
    }
    
    /**
     * @Route("/autocomplete/{hashid}", name="base_autocomplete")
     */
    public function Autocomplete(Request $request, string $hashid): Response
    {
        $dict    = $this->decode($hashid);
        $token   = $dict["token"] ?? null;
        $fields  = $dict["fields"] ?? null;
        $filters = $dict["filters"] ?? null;
        $class   = $dict["class"] ?? null;

        $expectedMethod = $this->baseService->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("select2", $token) && $request->getMethod() == $expectedMethod) {
        
            $term = $request->get("term") ?? "";
            $page = $request->get("page") ?? 1;

            $results = [];
            $pagination = false;
            if($this->classMetadataManipulator->isEntity($class)) {
                
                $repository = $this->entityManager->getRepository($class);
                if ($fields && !empty($term)) {

                    $fields = array_fill_keys($fields, $term);
                    $entries = $repository->findByInsensitivePartialModel($fields);
                    if($filters) $entries = array_filter($entries, function($entry) use ($filters) {

                        foreach($filters as $filter)
                            if(is_subclass_of($entry, $filter)) return true;

                        return false;
                    });

                    $book = $this->paginator->paginate($entries, $page);
                    $pagination = $book->getTotalPages() == $book->getPage();

                } else {

                    $entries = $repository->findAll(); // If no field, then get them all..
                    if($filters) $entries = array_filter($entries, function($entry) use ($filters) {

                        foreach($filters as $filter)
                            if(is_subclass_of($entry, $filter)) return true;

                        return false;
                    });
                    
                    if($term) $entries = array_filter($entries, fn($e) => str_contains(strval($e), $term));

                    $pagination = false;
                }

                foreach($entries as $i => $entry) 
                    $results[] = SelectType::getFormattedValues($entry, $class, $this->translator);

            } else if ($this->classMetadataManipulator->isEnumType($class) || $this->classMetadataManipulator->isSetType($class)) {

                $values = $class::getPermittedValues();
                foreach($values as $value)
                    $results[] = SelectType::getFormattedValues($value, $class, $this->translator);
            }

            $array = [];
            $array["pagination"] = ["more" => $pagination];
            $array["results"] = !empty($results) ? $results : [];
            $array["results"] = array_filter($array["results"], fn($r) => str_contains(strval($r["text"]), $term));

            return new JsonResponse($array);
        }
        
        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }
}
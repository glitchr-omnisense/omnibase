<?php

namespace Base\Controller;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Types\EnumType;
use Base\Database\Types\SetType;
use Base\Field\Type\SelectType;
use Base\Model\AutocompleteInterface;
use Base\Model\IconizeInterface;
use Base\Repository\Sitemap\Widget\PageRepository;
use Base\Service\BaseService;
use Base\Service\Paginator;
use Base\Service\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SelectController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator, BaseService $baseService)
    {
        $this->hashIds = new Hashids();
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->paginator = $paginator;
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
        $dict  = $this->decode($hashid);
        $token = $dict["token"] ?? null;
        $fields = $dict["fields"] ?? null;
        $class  = $dict["class"] ?? null;
        
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
                    $book = $this->paginator->paginate($entries, $page);
                    $pagination = $book->getTotalPages() == $book->getPage();

                } else {

                    $entries = $repository->findAll(); // If no field, then get them all..
                    if($term) $entries = array_filter($entries, fn($e) => str_contains(strval($e), $term));

                    $pagination = false;
                }

                foreach($entries as $i => $entry)
                    $results[] = SelectType::getFormattedValues($entry, $class);

            } else if ($class instanceof EnumType || $class instanceof SetType) {

                $values = $class::getPermittedValues();
                foreach($values as $value)
                    $results[] = SelectType::getFormattedValues($values, $class);
            }

            $array = [];
            $array["pagination"] = ["more" => $pagination];
            $array["results"] = !empty($results) ? $results : null;
            
            return new JsonResponse($array);
        }
        
        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }
}
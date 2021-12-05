<?php

namespace Base\Controller;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Types\EnumType;
use Base\Database\Types\SetType;
use Base\Repository\Sitemap\Widget\PageRepository;
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
    public function __construct(ClassMetadataManipulator $classMetadataManipulator, EntityManagerInterface $entityManager)
    {
        $this->hashIds = new Hashids();
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
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
     * @Route("/autocomplete/{hashid}", name="widget_page")
     */
    public function Autocomplete(Request $request, string $hash): Response
    {
        $dict  = $this->decode($hash);
        $token = $dict["token"] ?? null;
        $class  = $dict["class"] ?? null;
        
        // 'delete-item' is the same value used in the template to generate the token
        if ($this->isCsrfTokenValid("select2", $token)) {
        
            $term = $request->get("term");
            if($this->classMetadataManipulator->isEntity($class)) {
                $repository = $this->entityManager->getRepository($class);

            } else if ($class instanceof EnumType || $class instanceof SetType) {

            }

            $array = [];
            $array["pagination"] = ["more" => false];
            $array["results"] = [
                
                ["id" => 1, "text" => "OPTION 1"],
                ["id" => 2, "text" => "OPTION 2"],
                ["id" => 3, "text" => "OPTION 3"],
                ["id" => 4, "text" => "OPTION 4"],
                ["id" => 5, "text" => "OPTION 5"],
                ["id" => 6, "text" => "OPTION 6"]
            ];
            
            return new JsonResponse($array);
        }
        
        $array = ["status" => "Invalid request"];
        return new JsonResponse($array, 500);
    }
}
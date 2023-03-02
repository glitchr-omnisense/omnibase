<?php

namespace Base\Controller\UX;

use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(priority = -1)
 * */
class AutovalidateController extends AbstractController
{
    use BaseTrait;

    public function __construct(TranslatorInterface $translator, EntityManagerInterface $entityManager)
    {
        $this->hashIds = new Hashids($this->getService()->getSalt());
        $this->entityManager = $entityManager;
        $this->translator = $translator;
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
     * @Route("/validation/{hashid}", name="ux_validation")
     */
    public function Main(Request $request, string $hashid): Response
    {
        $dict     = $this->decode($hashid);
        $token    = $dict["token"] ?? null;
        $value    = $dict["value"] ?? null;
        $formType = $dict["form_type"] ?? null;

        $expectedMethod = $this->getService()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("validation", $token) && $request->getMethod() == $expectedMethod) {

            $array = ["status" => "Fine"];
            return new JsonResponse($array);
        }

        return new JsonResponse("Invalid request", 500);
    }

}

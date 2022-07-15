<?php

namespace Base\Controller\Client\Layout;

use Base\BaseBundle;
use Base\Entity\Layout\Widget\Attachment;
use Base\Entity\Layout\Widget\Page;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;

class WidgetController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        if(BaseBundle::hasDoctrine()) {
            $this->pageRepository = $entityManager->getRepository(Page::class);
            $this->attachmentRepository = $entityManager->getRepository(Attachment::class);
        }
    }

    /**
     * @Route("/page/{slug}", name="widget_page")
     */
    public function Page($slug): Response
    {
        $page = $this->pageRepository->findOneBySlug($slug);
        if($page === null)
            throw new NotFoundException("Page requested doesn't exist.");

        return $this->render("@Base/widget/page.html.twig", ["page" => $page]);
    }

    /**
     * @Route("/attachment/{slug}", name="widget_attachment")
     */
    public function Attachment($slug): BinaryFileResponse
    {
        $attachment = $this->attachmentRepository->findOneBySlug($slug);
        if($attachment === null)
            throw new NotFoundException("Attachment requested doesn't exist.");

        $mimeTypeGuesser = new FileinfoMimeTypeGuesser();

        $response = new BinaryFileResponse($attachment->getPublic());
        if($mimeTypeGuesser->isGuesserSupported())
            $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($attachment->getPublic()));
        else
            $response->headers->set('Content-Type', 'text/plain');

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getSlug());

        return $response;
    }
}

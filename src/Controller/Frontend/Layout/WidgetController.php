<?php

namespace Base\Controller\Frontend\Layout;

use Base\BaseBundle;
use Base\Entity\Layout\Widget\Attachment;
use Base\Entity\Layout\Widget\Page;
use Base\Repository\Layout\Widget\AttachmentRepository;
use Base\Repository\Layout\Widget\PageRepository;
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
    public function __construct(PageRepository $pageRepository, AttachmentRepository $attachmentRepository)
    {
        $this->pageRepository       = $pageRepository;
        $this->attachmentRepository = $attachmentRepository;
    }

    /**
     * @Route("/page/{slug}", name="widget_page")
     */
    public function Page($slug): Response
    {
        $page = $this->pageRepository->findOneBySlug($slug);
        if($page === null)
            throw new NotFoundException("Page requested doesn't exist.");

        return $this->render("widget/page.html.twig", ["page" => $page]);
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

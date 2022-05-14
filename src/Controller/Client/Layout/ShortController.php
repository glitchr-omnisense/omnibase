<?php

namespace Base\Controller\Client\Layout;

use Base\Repository\Layout\ShortRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Http\Discovery\Exception\NotFoundException;

class ShortController extends AbstractController
{
    /**
     * @var ShortRepository
     */
    protected $shortRepository;

    public function __construct(ShortRepository $shortRepository)
    {
        $this->shortRepository = $shortRepository;
    }

    /**
     * @Route("/{slug}", name="short", host="s.(.*)")
     * @Route("/{slug}/{_locale}", name="short", host="s.(.*)")
     */
    public function Main($slug): Response
    {
        /**
         * @var Short
         */
        $short = $this->shortRepository->findOneBySlug($slug);
        if($short === null || !$short->isReachable())
            throw new NotFoundException("Page requested doesn't exist.");

        return $this->redirect($short->getUrl());
    }
}

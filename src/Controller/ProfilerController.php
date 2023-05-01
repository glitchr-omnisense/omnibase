<?php

namespace Base\Controller;

use App\Entity\Marketplace\Product\Extra\Wallpaper\Variant as WallpaperVariant;
use Base\Annotations\Annotation\Iconize;
use Base\Annotations\Annotation\IsGranted;
use Base\Annotations\Annotation\Sitemap;
use Base\Entity\User\Notification;
use Base\Notifier\NotifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\Marketplace\Product\Extra\WallpaperRepository;
use App\Repository\Marketplace\ReviewRepository;
use App\Repository\Marketplace\Sales\ChannelRepository;
use App\Repository\Marketplace\StoreRepository;
use App\Repository\User\ArtistRepository;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Notifier\Notifier;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpFoundation\Request;

class ProfilerController extends AbstractController
{
    /**
     * @var NotifierInterface
     * */
    protected NotifierInterface $notifier;

    public function __construct(NotifierInterface $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @Route("/_profiler/email", name="_profiler_email", priority=1)
     */
    public function Email(): Response
    {
        return $this->notifier->renderTestEmail($this->getUser());
    }

    /**
     * @Route("/_profiler/email/send", name="_profiler_email_send", priority=1)
     */
    public function SendEmail(): Response
    {
        $this->notifier->sendTestEmail($this->getUser());
        return $this->redirectToRoute("_profiler_email");
    }
}

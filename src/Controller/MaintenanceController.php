<?php

namespace Base\Controller;
use Base\Service\BaseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class MaintenanceController extends AbstractController
{
    private $baseService;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    /**
     * Link to this controller to start the maintenance
     *
     * @Route("/m", name="base_maintenance")
     */
    public function Main(): Response
    {
        $downtime = $uptime = 0;

        $fname = $this->baseService->getParameterBag("base.maintenance.lockpath");
        $f = @fopen($fname, "r");
        if ($f) {

            $downtime = trim(fgets($f, 4096));

            if(!feof($f)) $uptime = trim(fgets($f, 4096));

            fclose($f);

        } else {

            $downtime = $this->baseService->getSettings("base.settings.maintenance_downtime");
            $uptime   = $this->baseService->getSettings("base.settings.maintenance_uptime");
        }

        $downtime = ($downtime) ? strtotime($downtime) : 0;
        $uptime = ($uptime) ? strtotime($uptime) : 0;

        $remainingTime = $uptime - time();
        if ($downtime-time() > 0 || $downtime < 1) $downtime = 0;
        if (  $uptime-time() < 0 || $uptime < 1) $uptime = 0;

        if( !$downtime || ($uptime-$downtime <= 0) || ($uptime-time() <= 0) ) $percentage = -1;
        else $percentage = round(100 * (time()-$downtime)/($uptime-$downtime));

        return $this->render('@Base/maintenance.html.twig', [
            'remainingTime' => $remainingTime,
            'percentage' => $percentage,
            'downtime'   => $downtime,
            'uptime'     => $uptime
        ]);
    }
}

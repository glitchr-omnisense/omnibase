<?php

namespace Base\Service;

use App\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamApi;
use Base\Model\Pagination;
use Base\Model\SpamProtectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Paginator implements PaginatorInterface
{
    protected $router;
    public function __construct(RouterInterface $router, BaseService $baseService)
    {
        $this->router = $router;
        $this->baseService = $baseService;
    }

    public function paginate(Query $query, int $page = 0, int $pageSize = 0, int $pageRange = 0): ?Pagination
    {
        $pageSize      = ($pageSize  < 1 ? $this->baseService->getParameterBag("base.paginator.page_size") : $pageSize);
        $pageRange     = ($pageRange < 1 ? $this->baseService->getParameterBag("base.paginator.page_range") : $pageRange);
        $parameterName = $this->baseService->getParameterBag("base.paginator.page_parameter");
        $pageTemplate  = $this->baseService->getParameterBag("base.paginator.default_template");

        $pagination = new Pagination($query, $this->router, $parameterName);
        $pagination->setPage($page);
        $pagination->setPageSize($pageSize);
        $pagination->setPageRange($pageRange);
        $pagination->setTemplate($pageTemplate);

        return $pagination;
    }
}

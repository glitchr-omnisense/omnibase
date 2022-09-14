<?php

namespace Base\Service;

use Base\Service\Model\Pagination;
use Doctrine\ORM\Query;
use Symfony\Component\Routing\RouterInterface;

class Paginator implements PaginatorInterface
{
    protected $router;
    public function __construct(RouterInterface $router, ParameterBag $parameterBag)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
    }

    public function paginate(array|Query $arrayOrQuery, int $page = 1, int $pageSize = 0, int $pageRange = 0): ?Pagination
    {
        $pageSize      = ($pageSize  < 1 ? $this->parameterBag->get("base.paginator.page_size") : $pageSize);
        $pageRange     = ($pageRange < 1 ? $this->parameterBag->get("base.paginator.page_range") : $pageRange);
        $parameterName = $this->parameterBag->get("base.paginator.page_parameter");
        $pageTemplate  = $this->parameterBag->get("base.paginator.default_template");

        $pagination = new Pagination($arrayOrQuery, $this->router, $parameterName);
        $pagination->setPageSize($pageSize);
        $pagination->setPageRange($pageRange);
        $pagination->setTemplate($pageTemplate);
        $pagination->setPage($page);

        return $pagination;
    }
}

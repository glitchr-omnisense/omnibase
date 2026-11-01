<?php

namespace Base\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Base\Service\Model\Pages;
use Base\Service\Model\Pagination;
use Doctrine\ORM\Query;

class Paginator implements PaginatorInterface
{
    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
    }

    /** @param array|Pages|Query $query the rows: an array, a query's Pages, a Doctrine Query (adapted, step F2e) */
    public function paginate(array|Pages|Query $query, int $page = 1, int $pageSize = 0, int $pageRange = 0): ?Pagination
    {
        $pageSize = ($pageSize < 1 ? $this->parameterBag->get("base.paginator.page_size") : $pageSize);
        $pageRange = ($pageRange < 1 ? $this->parameterBag->get("base.paginator.page_range") : $pageRange);
        $parameterName = $this->parameterBag->get("base.paginator.page_parameter");
        $pageTemplate = $this->parameterBag->get("base.paginator.default_template");

        $pagination = new Pagination($query, $this->router, $parameterName);
        $pagination->setPageSize($pageSize);
        $pagination->setPageRange($pageRange);
        $pagination->setTemplate($pageTemplate);
        $pagination->setPage($page);

        return $pagination;
    }
}

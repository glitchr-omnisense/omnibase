<?php

namespace Base\Service;

use Base\Database\Repository\CriteriaPages;
use Base\Database\Repository\Entities;
use Base\Service\Model\Pages;
use Base\Service\Model\Pagination;
use Omnibridge\Contract\Urls;
use Symfony\Component\Routing\RouterInterface;

/**
 * omnibase's Paginator (PaginatorInterface): a repository with criteria and an order, a finder's Result, or an array,
 * paged by the persistence; the pages' addresses by the host's Urls (Symfony's router where the host is Symfony).
 */
class Paginator implements PaginatorInterface
{
    public function __construct(
        protected Urls|RouterInterface $router,
        protected ParameterBagInterface $parameterBag,
    ) {
    }

    public function paginate(Entities|Pages|array $source, array $criteria = [], ?array $orderBy = null, int $page = 1, int $pageSize = 0, int $pageRange = 0): Pagination
    {
        if ($source instanceof Entities) {
            $source = new CriteriaPages($source, $criteria, $orderBy);
        } elseif ([] !== $criteria || null !== $orderBy) {
            throw new \InvalidArgumentException(sprintf('Criteria and an order page a repository (Entities): a %s is paged as it is - give the repository, or none of them.', get_debug_type($source)));
        }

        $pageSize = $pageSize < 1 ? (int) $this->parameterBag->get('base.paginator.page_size') : $pageSize;
        $pageRange = $pageRange < 1 ? (int) $this->parameterBag->get('base.paginator.page_range') : $pageRange;

        $pagination = new Pagination($source, $this->router, $this->parameterBag->get('base.paginator.page_parameter'));
        $pagination->setPageSize($pageSize);
        $pagination->setPageRange($pageRange);
        $pagination->setTemplate($this->parameterBag->get('base.paginator.default_template'));
        $pagination->setPage(max(1, $page));

        return $pagination;
    }
}

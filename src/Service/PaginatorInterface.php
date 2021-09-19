<?php

namespace Base\Service;

use App\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamApi;
use Base\Model\SpamProtectionInterface;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface PaginatorInterface
{
    public function paginate(Query $query, int $page = 0, int $pageSize = 0, int $pageRange = 0);
}

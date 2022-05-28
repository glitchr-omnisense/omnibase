<?php

namespace Base\Model;

use App\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamApi;
use Base\Model\SpamProtectionInterface;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface PaginationInterface
{
    public function getPage();
    public function getPageSize();
    public function getPageRange();
    public function getTotalPages();

    public function getTemplate();
    
    public function getPath(string $name, int $page = 0, array $parameters = []);
    public function getResult();
    public function getTotalCount();
}

<?php

namespace Base\Model;

use App\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamApi;
use Base\Exception\InvalidPageException;
use Base\Model\SpamProtectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Iterator;
use Symfony\Component\Routing\RouterInterface;

class Pagination implements PaginationInterface, Iterator
{
    protected $router;
    private $build;
    
    protected $doctrinePaginator = null;
    protected $totalCount = 0;

    protected $route = null;
    protected array $routeParameters = [];

    protected $page = 0;
    protected $pageIter = 0;
    protected $pageSize = 0;
    protected $pageRange = 0;
    
    protected $template = "@Base/paginator/sliding.html.twig";

    public function __construct(Query $query, RouterInterface $router, ?string $parameterName = "page")
    {
        $this->doctrinePaginator = new DoctrinePaginator($query);
        $this->totalCount = count($this->doctrinePaginator);
        $this->parameterName = $parameterName;

        $this->build = true;
        $this->router = $router;
    }

    public function rewind()  { $this->pageIter = 0; }
    public function next()    { $this->pageIter++; }
    public function key()     { return ($this->page-1)*$this->pageSize + $this->pageIter+1;    }
    public function current() { return $this->getResult()[$this->pageIter]; } 
    public function valid()   { return $this->pageIter < $this->pageSize && $this->pageIter < count($this->getResult()); }

    public function getDoctrinePaginator() { return $this->doctrinePaginator; }
    public function getQuery() { $this->doctrinePaginator->getQuery(); }
    public function getTotalCount() { return $this->totalCount; }
    public function getTotalPages()
    {
        $pageSize = $this->getPageSize();
        return ceil(($pageSize < 1 ? 0 : $this->getTotalCount()/$pageSize));
    }

    public function getTemplate() { return $this->template; }
    public function setTemplate(string $template)
    {
        $this->template = $template;
        return $this;
    }

    public function setParameterName($parameterName)
    {
        $this->parameterName = $parameterName;
        return $this;
    }

    public function getParameterName() { return $this->parameterName; }
    
    public function getPageRange() { return $this->pageRange; }
    public function setPageRange(int $pageRange) 
    {
        $this->pageRange = $pageRange;
        return $this;
    }

    public function getPageSize() { return $this->pageSize; }
    public function setPageSize($pageSize)
    {      
        $pageSize = ($pageSize < 1 ? $this->getTotalCount() : $pageSize);
        if($this->pageSize != $pageSize) {
            $this->pageSize = $pageSize;
            $this->build = true;
        }

        return $this;
    }

    public function getPage() { return $this->page; }
    public function setPage($page)
    {
        $page = ($page < 0 ? 0 : $page);
        if($this->page != $page) {
            $this->page = $page;
            $this->build = true;
        }
        return $this;
    }

    public function getPath(string $name, int $page = 0, array $parameters = [])
    {
        if ($page < 1) 
            $page = $this->getPage();

        return $this->router->generate($name, array_merge($parameters, [$this->getParameterName() => $page]));
    }

    public function getResult() { return $this->build(); }
    protected function build() 
    {
        if(!$this->build) return $this->lastResult;
        $this->build = false;

        if($this->page > $this->getTotalPages())
            throw new InvalidPageException("Page not found.");

        if($this->page < 1) $this->lastResult = [];
        else $this->lastResult = $this->doctrinePaginator->getQuery()
                                      ->setFirstResult($this->pageSize * ($this->page-1))
                                      ->setMaxResults ($this->pageSize)->getResult();

        return $this->lastResult;
    } 
}

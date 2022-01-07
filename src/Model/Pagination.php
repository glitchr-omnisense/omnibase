<?php

namespace Base\Model;

use App\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamApi;
use Base\Exception\InvalidPageException;
use Base\Model\SpamProtectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator as instance;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Iterator;
use Symfony\Component\Routing\RouterInterface;

class Pagination implements PaginationInterface, Iterator
{
    protected $router;
    private $build;
    
    protected $instance = null;
    protected $totalCount        = 0;

    protected       $route           = null;
    protected array $routeParameters = [];

    protected $page      = 0;
    protected $pageIter  = 0;
    protected $pageSize  = 0;
    protected $pageRange = 0;
    
    protected $template = "@Base/paginator/sliding.html.twig";

    public function __construct(array|Query $arrayOrQuery, RouterInterface $router, ?string $parameterName = "page")
    {
        $this->instance      = ($arrayOrQuery instanceof Query) ? new instance($arrayOrQuery) : $arrayOrQuery;
        $this->totalCount    = ($arrayOrQuery instanceof Query) ? count($this->instance) : count_leaves($this->instance);
        $this->parameterName = $parameterName;
        $this->build = true;
        $this->router = $router;
    }

    public function rewind(): void { $this->pageIter = 0; }
    public function next(): void   { $this->pageIter++; }
    public function key()          { return ($this->page-1)*$this->pageSize + $this->pageIter+1;    }
    public function valid(): bool  { return $this->isQuery() ? $this->getTotalPages() >= $this->getPage() && $this->pageIter < count($this->getResult()) : $this->pageIter == 0; }
    public function current()      { return $this->isQuery() ? $this->getResult()[$this->pageIter] : $this->getResult(); }

    public function get() { return $this->instance; }
    public function getQuery() { $this->isQuery() ? $this->instance->getQuery() : null; }
    public function isQuery()  { return $this->instance instanceof instance; }

    public function getTotalCount() { return $this->totalCount; }
    public function getTotalPages()
    {
        $pageSize = $this->getPageSize();
        
        if($this->getTotalCount() <= $pageSize) return 1;
        return ceil(($pageSize < 1 ? 1 : $this->getTotalCount()/$pageSize));
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

    protected array $lastResult = [];
    public function getResult() { return $this->build(); }
    protected function build() 
    {
        if(!$this->build) return $this->lastResult;
        $this->build = false;

        if($this->page < 1) return ($this->lastResult = []);
        if($this->page > $this->getTotalPages())
            throw new InvalidPageException("Page not found.");

        if($this->isQuery()) {

            return $this->lastResult = $this->instance
                                            ->getQuery()
                                            ->setFirstResult($this->pageSize * ($this->page-1))
                                            ->setMaxResults ($this->pageSize)->getResult();

        }
        
        return $this->lastResult =  array_slice_recursive(
                                        $this->get(), 
                                        $this->pageSize * ($this->page-1), 
                                        $this->pageSize
                                    );
    } 
}

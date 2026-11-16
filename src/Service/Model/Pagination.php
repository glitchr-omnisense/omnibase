<?php

namespace Base\Service\Model;

use Base\Exception\InvalidPageException;
use Omnibridge\Contract\Urls;
use Symfony\Component\Routing\RouterInterface;
use Countable;
use Iterator;
use UnexpectedValueException;

class Pagination implements PaginationInterface, Iterator, Countable
{
    /** The addresses of the pages: omnibridge's Urls (plan "omnibase agnostic", F5a: where the host has no router of Symfony's), or Symfony's router (its advanced_router under Symfony, its links as they were). */
    protected Urls|RouterInterface $router;
    private bool $build;

    protected mixed $paginator = null;
    protected mixed $totalCount = 0;

    protected $route = null;
    protected array $routeParameters = [];

    protected int $page = 0;
    protected int $pageIter = 0;
    protected int $pageSize = 0;
    protected int $pageRange = 0;

    protected string $template = "@Base/paginator/sliding.html.twig";
    protected ?string $parameterName;

    /** @param array|Pages $arrayOrQuery the rows: an array, or Pages (a finder's Result, CriteriaPages...) */
    public function __construct(array|Pages $arrayOrQuery, Urls|RouterInterface $router, ?string $parameterName = "page")
    {
        if (!is_array($arrayOrQuery)) {
            $this->paginator = self::pages($arrayOrQuery);
            $this->totalCount = $this->paginator->count();
        } else {
            $this->paginator = $arrayOrQuery;
            $this->totalCount = count_leaves($this->paginator);
        }

        $this->parameterName = $parameterName;
        $this->build = true;
        $this->router = $router;
    }

    /** The rows of a query as Pages: a finder's Result, CriteriaPages, any Pages. */
    public static function pages(object $query): Pages
    {
        return $query instanceof Pages ? $query : throw new UnexpectedValueException(sprintf('No pages for a %s: give Pages (a finder\'s Result, Base\Database\Repository\CriteriaPages).', get_debug_type($query)));
    }

    public function rewind(): void
    {
        $this->pageIter = 0;
    }

    public function next(): void
    {
        $this->pageIter++;
    }

    public function count(): int
    {
        if($this->getPage() <= 0 && $this->getPage() > $this->getTotalPages()) {
            return 0;
        }

        return $this->pageSize > 0 ? ($this->totalCount % $this->pageSize) + 1 : 0;
    }

    public function key(): mixed
    {
        return ($this->page - 1) * $this->pageSize + $this->pageIter;
    }

    public function valid(): bool
    {
        if($this->getPage() <= 0 && $this->getPage() > $this->getTotalPages()) {
            return false;
        }

        if($this->isQuery()) {
            return $this->getTotalPages() >= $this->getPage() && $this->pageIter < count($this->getResult());
        }

        return $this->pageIter == 0;
    }

    public function current(): mixed
    {
        if($this->isQuery()) {
            return $this->getResult()[$this->pageIter] ?? null;
        }

        return $this->getResult();
    }

    public function getBookmark(): mixed
    {
        return $this->pageIter % $this->getPageSize();
    }

    /**
     * @return array|Pages the rows: the array, or the query's Pages
     */
    public function get()
    {
        return $this->paginator;
    }

    /**
     * Whether the rows are a query's Pages (else an array).
     *
     * @return bool
     */
    public function isQuery()
    {
        return $this->paginator instanceof Pages;
    }

    /**
     * @return float|int|mixed|string
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * @return float|int
     */
    public function getLastPage()
    {
        return $this->getTotalPages();
    }

    /**
     * @return float|int
     */
    public function getTotalPages()
    {
        $pageSize = $this->getPageSize();
        if (!$pageSize) {
            throw new UnexpectedValueException("No page size defined");
        }

        if ($this->getTotalCount() <= $pageSize) {
            return 1;
        }
        return ceil(max(1, $this->getTotalCount() / $pageSize));
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @param $parameterName
     * @return $this
     */
    public function setParameterName($parameterName)
    {
        $this->parameterName = $parameterName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getParameterName()
    {
        return $this->parameterName;
    }

    /**
     * @return int
     */
    public function getPageRange()
    {
        return $this->pageRange;
    }

    /**
     * @param int $pageRange
     * @return $this
     */
    public function setPageRange(int $pageRange)
    {
        $this->pageRange = $pageRange;
        return $this;
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * @param $pageSize
     * @return $this
     */
    public function setPageSize($pageSize)
    {
        $pageSize = ($pageSize < 1 ? $this->getTotalCount() : $pageSize);
        if ($this->pageSize != $pageSize) {
            $this->pageSize = $pageSize;
            $this->build = true;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param $page
     * @return $this
     */
    public function setPage($page)
    {
        if ($this->page != $page) {
            $this->page = $page;
            $this->build = true;
        }

        return $this;
    }

    /**
     * @param string $name
     * @param int $page
     * @param array $parameters
     * @return string
     */
    public function getPath(string $name, int $page = 0, array $parameters = [])
    {
        if ($page < 1) {
            $page = $this->getPage();
        }

        $parameters = array_merge($parameters, [$this->getParameterName() => $page]);

        return $this->router instanceof Urls ? $this->router->path($name, $parameters) : $this->router->generate($name, $parameters);
    }

    protected array $lastResult = [];

    /**
     * @return array|float|int|mixed|string
     * @throws InvalidPageException
     */
    public function getResult()
    {
        return $this->build();
    }

    /**
     * @return array|float|int|mixed|string
     * @throws InvalidPageException
     */
    protected function build()
    {
        if (!$this->build) {
            return $this->lastResult;
        }
        $this->build = false;

        if ($this->page < 1) {
            return ($this->lastResult = []);
        }
        if ($this->page > $this->getTotalPages()) {
            throw new InvalidPageException("Page not found.");
        }

        if ($this->isQuery()) {
            return $this->lastResult = $this->paginator->slice($this->pageSize * ($this->page - 1), $this->pageSize);
        }

        return $this->lastResult = array_slice_recursive($this->get(), $this->pageSize * ($this->page - 1), $this->pageSize, true /*Always preserve keys*/);
    }
}

<?php

namespace Base\Entity\User;

use App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * @ORM\Entity(repositoryClass=LogRepository::class)
 */
class Log
{
    public const INFO     = "INFO";
    public const DEBUG    = "DEBUG";
    public const WARNING  = "WARNING";
    public const CRITICAL = "CRITICAL";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * Type Of Event
     *
     * @ORM\Column(type="string", length=255)
     */
    private $event;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $priority;

    /**
     * @ORM\Column(type="text")
     */
    private $controller;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $ip;

    /**
     * @ORM\Column(type="string", length=5, nullable=true)
     */
    private $locale;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $method;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $requestUri;

    /**
     * @ORM\Column(type="text")
     */
    private $browser;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $extra;

    /**
     * Level
     *
     * @ORM\Column(type="string", length=255)
     */
    private $level;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\OneToOne(targetEntity=User::class)
     */
    private $impersonator;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="logs")
     */
    private $user;

    public function __construct(array $listener, Request $request = null)
    {
        if (!array_key_exists("event", $listener))
            throw new Exception("Array key \"event\" missing in dispatcher entry");
        if (!array_key_exists("priority", $listener))
            throw new Exception("Array key \"priority\" missing in dispatcher entry");
        if (!array_key_exists("pretty", $listener))
            throw new Exception("Array key \"pretty\" missing in dispatcher entry");
        if (!array_key_exists("stub", $listener))
            throw new Exception("Array key \"stub\" missing in dispatcher entry");

        $this->ip = $this->getIp();
        $this->browser = $this->getBrowser();

        $this->createdAt   = new \DateTime("now");
        $this->level      = self::INFO;
        $this->event      = $listener["event"];
        $this->priority   = $listener["priority"];
        $this->controller = $listener["pretty"];

        if ($request)
            $this->setRequest($request);
    }

    function setRequest(Request $request) {

        $this->requestUri = $request->getRequestUri() ?? null;
        $this->locale     = $request->getLocale()     ?? null;
        $this->method     = $request->getMethod()     ?? null;
    }

    function getIp(): string
    {
        $keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP))
                return $_SERVER[$k];
        }
        return "UNKNOWN";
    }

    function getBrowser(): string
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    function setException(?\Throwable $exception)
    {
        if(!$exception) return;
        $this->level      = self::CRITICAL;

        $this->extra = "";
        if($exception instanceof HttpException)
            $this->extra .= "HTTP ".$exception->getStatusCode() . " ";
        $this->extra .= get_class($exception) . PHP_EOL;
        $this->extra .= PHP_EOL;
        $this->extra .= $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
        $this->extra .= $exception->getMessage() . PHP_EOL;
        $this->extra .= PHP_EOL;
        $this->extra .= $exception->getTraceAsString();
    }

    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function setImpersonator(?User $impersonator): self
    {
        $this->impersonator = $impersonator;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        $this->user->addLog($this);

        return $this;
    }
}

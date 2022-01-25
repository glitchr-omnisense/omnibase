<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Enum\LogLevel;
use Base\Model\IconizeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Config\Definition\Exception\Exception;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\LogRepository;

/**
 * @ORM\Entity(repositoryClass=LogRepository::class)
 */
class Log implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-info-circle"]; } 

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId() { return $this->id; }

    /**
     * Type Of Event
     *
     * @ORM\Column(type="string", length=255)
     */
    protected $event;
    public function getEvent() { return $this->event; }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $priority;
    public function getPriority() { return $this->priority; }

    /**
     * @ORM\Column(type="text")
     */
    protected $pretty;
    public function getPretty() { return $this->pretty; }

    /**
     * @ORM\OneToOne(targetEntity=User::class)
     */
    protected $impersonator;
    public function getImpersonator(): ?User { return $this->impersonator; }
    public function setImpersonator(?User $impersonator): self
    {
        $this->impersonator = $impersonator;

        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="logs")
     */
    protected $user;
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        $this->user->addLog($this);

        $this->ip = $this->getUser()->getIp();
        $this->browser = $this->getUser()->getBrowser();

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=20)
     */
    protected $ip;
    public function getIp() { return $this->ip; }

    /**
     * @ORM\Column(type="string", length=5, nullable=true)
     */
    protected $locale;
    public function getLocale() { return $this->locale; }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $method;
    public function getMethod() { return $this->method; }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $requestUri;
    public function getRequestUri() { return $this->requestUri; }

    /**
     * @ORM\Column(type="integer")
     */
    protected $statusCode;
    public function getStatusCode() { return $this->statusCode; }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $browser;
    public function getBrowser() { return $this->browser; }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $extra;
    public function getExtra() { return $this->extra; }

    /**
     * Level
     *
     * @ORM\Column(type="log_level")
     */
    protected $level;
    public function getLevel() { return $this->level; }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;
    public function getCreatedAt() { return $this->createdAt; }

    public function __construct(array $listener, Request $request = null)
    {
        if (!array_key_exists("event", $listener))
            throw new Exception("Array key \"event\" missing in dispatcher entry");
        if (!array_key_exists("priority", $listener))
            throw new Exception("Array key \"priority\" missing in dispatcher entry");
        if (!array_key_exists("pretty", $listener))
            throw new Exception("Array key \"pretty\" missing in dispatcher entry");

        $this->createdAt   = new \DateTime("now");
        $this->level      = LogLevel::INFO;
        $this->event      = $listener["event"];
        $this->priority   = $listener["priority"];
        $this->pretty   = $listener["pretty"];

        if ($request)
            $this->setRequest($request);
    }

    public function __toString()
    {
        return __CLASS__." #".$this->getId().": ".$this->event."/". $this->level ."/".$this->createdAt;
    }

    function setRequest(Request $request) {

        $this->statusCode = "302";
        $this->requestUri = $request->getRequestUri() ?? null;
        $this->locale     = $request->getLocale()     ?? null;
        $this->method     = $request->getMethod()     ?? null;
    }

    function setException(?\Throwable $exception)
    {
        if(!$exception) return;
        $this->level      = LogLevel::CRITICAL;
        $this->statusCode = $exception->getStatusCode();

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
}

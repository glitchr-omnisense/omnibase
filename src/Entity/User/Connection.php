<?php

namespace Base\Entity\User;

use Base\Database\Attribute\Blameable;
use Base\Database\Attribute\Timestamp;
use Base\Entity\User;
use Base\Enum\ConnectionState;
use Base\Repository\User\ConnectionRepository;
use Base\Database\Attribute\Cache;
use Base\Service\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass:ConnectionRepository::class)]
#[Cache(usage:"NONSTRICT_READ_WRITE", associations:"ALL")]
class Connection implements IconizeInterface
{
    public function __iconize(): ?array
    {
        return self::__iconizeStatic()[$this->isValid() ? 1 : 0];
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-plug-circle-bolt"];
    }

    public function __construct(string $uniqid = "", ?User $user = null)
    {
        $this->uniqid = $uniqid;
        $this->user = $user;
        
        $this->ipList = [];
        $this->timezones = [];
        $this->hostnames = [];

        $this->state = ConnectionState::REQUESTED;
        $this->loginAttempts = 0;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    protected $id;
    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(type:"string", length:23)]
    private $uniqid;
    public function getUniqid(): ?string
    {
        return $this->uniqid;
    }

    public function setUniqid(string $uniqid): self
    {
        $this->uniqid = $uniqid;

        return $this;
    }

    #[ORM\ManyToOne(targetEntity:User::class, inversedBy:"connections")]
    #[ORM\JoinColumn(nullable:false)]
    protected $user;
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    #[ORM\ManyToOne(targetEntity:User::class)]
    #[Blameable(on:["create", "update"], impersonator:true)]
    protected $impersonator;
    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function setImpersonator(?User $impersonator): self
    {
        $this->impersonator = $impersonator;

        return $this;
    }

    #[ORM\Column(type:"integer")]
    protected $loginAttempts;
    public function getLoginAttempts(): ?string
    {
        return $this->loginAttempts;
    }

    public function setLoginAttempts(int $loginAttempts): self
    {
        $this->loginAttempts = $loginAttempts;
        return $this;
    }

    #[ORM\Column(type:"connection_state")]
    protected $state;
    public function getState(): ?string
    {
        return $this->state;
    }

    public function isValid(): bool
    {
        return $this->state == ConnectionState::SUCCEEDED;
    }

    public function markAsSucceeded(): self
    {
        $this->state = ConnectionState::SUCCEEDED;
        return $this;
    }

    public function markAsFailed(): self
    {
        $this->state = ConnectionState::FAILED;
        $this->loginAttempts++;
        return $this;
    }

    public function markAsLogout(): self
    {
        $this->state = ConnectionState::CLOSED;
        return $this;
    }

    #[ORM\Column(type:"json")]
    protected $ipList = [];
    public function getIpList(): ?array
    {
        return $this->ipList;
    }

    public function addIp(string $ip): self
    {
        if(!in_array($ip, $this->ipList)){
            $this->ipList[] = $ip;
        }
        
        return $this;
    }

    public function removeIp(string $ip): self
    {
        if (($key = array_search($ip, $this->ipList)) !== false) {
            unset($this->ipList[$key]);
        }

        return $this;
    }

    #[ORM\Column(type:"text", nullable:true)]
    protected $agent;
    public function getAgent(): ?string
    {
        return $this->agent;
    }

    public function setAgent(string $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * A short, readable name for the browser behind this connection.
     *
     * Purely for display: a settings page listing "Mozilla/5.0 (Macintosh;
     * Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko)
     * Version/26.5 Safari/605.1.15" twenty times over tells nobody which
     * session is theirs. The full string stays available - this is a label
     * beside it, not a replacement for it.
     *
     * Order matters in both lists: Edge and Chrome both claim to be Safari,
     * and Chrome claims to be Safari too, so the most specific claim has to
     * be tested first. Anything unrecognised keeps the raw string.
     */
    public function getAgentLabel(): ?string
    {
        if (!$this->agent) {
            return $this->agent;
        }

        $browsers = [
            'Edg/' => 'Edge',
            'OPR/' => 'Opera',
            'Firefox/' => 'Firefox',
            'Chrome/' => 'Chrome',
            'Safari/' => 'Safari',
            'curl/' => 'curl',
            'Wget/' => 'Wget',
        ];

        $systems = [
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
            'Android' => 'Android',
            'Mac OS X' => 'macOS',
            'Windows' => 'Windows',
            'CrOS' => 'ChromeOS',
            'Linux' => 'Linux',
        ];

        $browser = null;
        foreach ($browsers as $needle => $name) {
            if (str_contains($this->agent, $needle)) {
                $browser = $name;
                break;
            }
        }

        $system = null;
        foreach ($systems as $needle => $name) {
            if (str_contains($this->agent, $needle)) {
                $system = $name;
                break;
            }
        }

        if (null === $browser && null === $system) {
            return $this->agent;
        }

        if (null === $system) {
            return $browser;
        }

        return null === $browser ? $system : $browser . ' - ' . $system;
    }

    #[ORM\Column(type:"string", length:16, nullable:true)]
    protected $locale;
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    #[ORM\Column(type:"json")]
    protected $timezones = [];
    public function getTimezones(): ?array
    {
        return $this->timezones;
    }

    public function addTimezone(?string $timezone): self
    {
        if(!$timezone) return $this;
        if(!in_array($timezone, $this->timezones)){
            $this->timezones[] = $timezone;
        }
        
        return $this;
    }

    public function removeTimezone(?string $timezone): self
    {
        if (($key = array_search($timezone, $this->timezones)) !== false) {
            unset($this->timezones[$key]);
        }

        return $this;
    }
    
    #[ORM\Column(type:"json")]
    protected $hostnames = [];
    public function getHostnames(): ?array
    {
        return $this->hostnames;
    }

    public function addHostname(?string $hostname): self
    {
        if(!$hostname) return $this;
        if(!in_array($hostname, $this->hostnames)){
            $this->hostnames[] = $hostname;
        }
        
        return $this;
    }

    public function removeHostname(?string $hostname): self
    {
        if (($key = array_search($hostname, $this->hostnames)) !== false) {
            unset($this->hostnames[$key]);
        }

        return $this;
    }

    #[ORM\Column(type:"datetime")]
    #[Timestamp(on:"create")]
    protected $createdAt;
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\Column(type:"datetime")]
    #[Timestamp(on:["create", "update"])]
    protected $updatedAt;
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

}

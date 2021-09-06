<?php

namespace Base\Entity;

use App\Entity\Thread\Like;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Mention;

use App\Repository\UserRepository;

use App\Entity\User\Log;
use App\Entity\User\Token;
use App\Entity\User\Group;
use App\Entity\User\Penalty;
use App\Entity\User\Permission;
use App\Entity\User\Notification;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\Timestamp;
use Base\Database\Annotation\Uploader;
use Base\Database\Annotation\Hashify;
use Base\Service\BaseService;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Translation\TranslatorInterface;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

use Base\Traits\BaseTrait;
use DateTimeZone;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\DiscriminatorColumn( name = "metadata", type = "integer" )
 *     @DiscriminatorEntry( value = "0" )
 *
 * @AssertBase\UniqueEntity(fields={"email"}, groups={"new", "edit"})
 */
class User implements UserInterface, TwoFactorInterface, PasswordAuthenticatedUserInterface
{
    use BaseTrait;

    // TODO: Remove the two next methods in S6.0
    public function getUsername() { return $this->getUserIdentifier(); }
    public function getSalt() { return null; }
    // TODO-END
    
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(name="secret", type="string", nullable=true)
     */
    protected $secret;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\Email(groups={"new", "edit"})
     */
    protected $email;

    /**
     * @ORM\Column(type="string", length=16)
     * @Assert\Locale(canonicalize = true)
     */
    protected $locale;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $timezone;

    /**
     * @var string Plain password should be empty unless you want to change it
     * @Assert\Length(min=8, groups={"new", "edit"})
     * @Assert\NotBlank(groups={"new"})
     *  */
    protected $plainPassword;

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     * @Hashify(reference="plainPassword", algorithm="auto")
     */
    protected $password;

    /**
     * @ORM\Column(type="json")
     * @Assert\NotBlank(groups={"new", "edit"})
     */
    protected $roles = [];

    /**
     * @ORM\OneToMany(targetEntity=Log::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $logs;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, inversedBy="members", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $groups;

    /**
     * @ORM\ManyToMany(targetEntity=Permission::class, inversedBy="uid", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $permissions;

    /**
     * @ORM\ManyToMany(targetEntity=Penalty::class, inversedBy="uid", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $penalties;

    /**
     * @ORM\OneToMany(targetEntity=Notification::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $notifications;

    /**
     * @ORM\OneToMany(targetEntity=Token::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $tokens;

    /**
     * @ORM\ManyToMany(targetEntity=Thread::class, mappedBy="authors", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $threads;

    /**
     * @ORM\ManyToMany(targetEntity=Thread::class, mappedBy="followers", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $followedThreads;

    /**
     * @ORM\OneToMany(targetEntity=Mention::class, mappedBy="target", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $mentions;

    /**
     * @ORM\OneToMany(targetEntity=Mention::class, mappedBy="author", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $authoredMentions;

    /**
     * @ORM\OneToMany(targetEntity=Like::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $likes;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isApproved;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isVerified;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isEnabled;

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on={"create", "update"})
     */
    protected $updatedAt;

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Uploader(size="1024K", mime={"image/jpg", "image/png"})
     * @AssertBase\FileSize(max="1024K", groups={"new", "edit"})
     * @AssertBase\FileMimeType(type={"image/jpg", "image/png"}, groups={"new", "edit"})
     */
    protected $avatar;

    public function getAvatar(): ?string { return $this->avatar; }
    public function getAvatarFile(): ?File { return Uploader::getFile($this, "avatar"); }
    public function setAvatar($avatar)
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function __construct()
    {
        $this->roles = [self::ROLE_USER];
        $this->isApproved = false;
        $this->isVerified = false;
        $this->isEnabled  = true;

        $this->tokens = new ArrayCollection();
        $this->logs = new ArrayCollection();
        $this->permissions = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->penalties = new ArrayCollection();

        $this->threads = new ArrayCollection();
        $this->followedThreads = new ArrayCollection();
        $this->mentions = new ArrayCollection();
        $this->authoredMentions = new ArrayCollection();
        $this->likes = new ArrayCollection();

        $this->setDefaultTimezone();
        $this->setDefaultLocale();
    }

    public static $property = "email";
    public function __toString()
    {
        $getter = "get" . ucfirst(self::$property);
        if(!method_exists(get_called_class(), $getter))
            throw new Exception("A getter $getter is expected to identify users.");

        $str = $this->$getter();
        if(!is_string($str))
            throw new Exception("Returned value from getter $getter is expected to be a string, currently : \"".gettype($str)."\"");

        return $str;
    }

    public function getId(): ?int { return $this->id; }
    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function sameAs($other): bool { return ($other->getId() == $this->getId()); }

    public static function getCookie(string $key = null)
    {
        $cookie = json_decode($_COOKIE["user"] ?? "", true);

        if(!isset($cookie)) return null;
        if(!isset($key) || empty($key)) return $cookie;

        return $cookie[$key] ?? null;
    }

    public function setDefaultLocale(): self
    {
        $this->locale = User::getCookie("locale") ?? "en";
        return $this;
    }

    public function getLocale(): string { return explode("-", $this->locale)[0]; }
    public function setLocale($locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function setDefaultTimezone(): self
    {
        $timezone = User::getCookie("timezone") ?? "UTC";
        return $this->setTimezone($timezone);
    }

    public function getTimezone(): string { return $this->timezone; }
    public function setTimezone(string $timezone): self
    {
        if( !in_array($timezone, timezone_identifiers_list()) )
            $timezone = "UTC";

        $this->timezone = $timezone;
        return $this;
    }

    public static function getIp(): ?string
    {
        $keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP))
                return $_SERVER[$k];
        }
        return null;
    }

    public function isBanned() {
        return false;
    }

    public function setSecret($secret): self
    {
        $this->secret = $secret;
        return $this;
    }
    public function getSecret() { return $this->secret; }
    public function isTotpAuthenticationEnabled(): bool { return $this->secret ? true : false; }
    public function getTotpAuthenticationUsername(): string { return $this->getUserIdentifier(); }

    const TOTP_LENGTH  = 6;
    const TOTP_TIMEOUT = 30;
    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if($this->secret == null) return null;
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, User::TOTP_TIMEOUT, User::TOTP_LENGTH);
    }

    const ROLE_SOCIAL     = "ROLE_SOCIAL";
    const ROLE_USER       = "ROLE_USER";
    const ROLE_ADMIN      = "ROLE_ADMIN";
    const ROLE_SUPERADMIN = "ROLE_SUPERADMIN";

    /**
     * @see UserInterface
     */
    public function getRoles(): array 
    { 
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        if(empty($roles)) 
            $roles[] = User::ROLE_USER;
            
        $this->roles = array_unique($roles);
        
        return $this;
    }

    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles))
            $this->roles[] = $role;

        return $this;
    }

    public function removeRole(string $role): self
    {
        if ( ($pos = array_search($role, $this->roles)) )
            unset($this->roles[$pos]);

        return $this;
    }

    public function isSocialAccount(): bool { return in_array(User::ROLE_SOCIAL, $this->roles); }

    public function isPersistent(): bool { return (!$this->isSocialAccount() || $this->id > 0); }

    /**
     * @see UserInterface
     */
    public function getPassword(): ?string { return (string) $this->password; }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials() { $this->plainPassword = null; }

    public function getUserIdentifier(): string { return $this->email; }

    public function erasePlainPassword()
    {
        $this->plainPassword = null;

        return $this;
    }

    public function getPlainPassword(): ?string { return $this->plainPassword; }

    public function setPlainPassword(string $password): void
    {
        $this->plainPassword = $password;
        $this->updatedAt = new \DateTime("now"); // Plain password is not an ORM variable..
    }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function isApproved(): bool { return $this->isApproved; }

    public function setIsApproved(bool $isApproved = true): self
    {
        $this->isApproved = $isApproved;
        return $this;
    }

    public function approve(bool $isApproved = true): self { return $this->setIsApproved($isApproved); }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified = true): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function verify(bool $isVerified = true): self { return $this->setIsVerified($isVerified); }
    
    public function isDisabled(): ?bool { return !$this->isEnabled(); }
    public function isEnabled (): ?bool { return  $this->isEnabled; }
    public function disable(bool $isDisabled = true): self { return $this->setIsDisabled($isDisabled); }
    public function enable(bool $isEnabled = true): self { return $this->setIsEnabled($isEnabled); }
    public function setIsDisabled(bool $isDisabled = true): self {   return $this->setIsEnabled(!$isDisabled); }
    public function setIsEnabled(bool $isEnabled = true): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /**
     * @return array|Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        $this->groups->removeElement($group);

        return $this;
    }

    /**
     * @return array|Log[]
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    public function addLog(Log $log): self
    {
        if (!$this->logs->contains($log)) {
            $this->logs[] = $log;
            $log->setUser($this);
        }

        return $this;
    }

    public function removeLog(Log $log): self
    {
        if ($this->logs->removeElement($log)) {
            // set the owning side to null (unless already changed)
            if ($log->getUser() === $this) {
                $log->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return array|Permission[]
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): self
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions[] = $permission;
        }

        return $this;
    }

    public function removePermission(Permission $permission): self
    {
        $this->permissions->removeElement($permission);

        return $this;
    }

    /**
     * @return array|Penalty[]
     */
    public function getPenalties(): array
    {
        return $this->penalties;
    }

    public function addPenalty(Penalty $penalty): self
    {
        if (!$this->penalties->contains($penalty)) {
            $this->penalties[] = $penalty;
        }

        return $this;
    }

    public function removePenalty(Penalty $penalty): self
    {
        $this->penalties->removeElement($penalty);

        return $this;
    }

    /**
     * @return array|Notification[]
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications[] = $notification;
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return array|Token[]
     */
    public const ALL_TOKENS     = "ALL_TOKENS";
    public const VALID_TOKENS   = "VALID_TOKENS";
    public const EXPIRED_TOKENS = "EXPIRED_TOKENS";

    public function getTokens($type = self::ALL_TOKENS): ?array
    {
        $tokens = [];
        foreach($this->tokens as $token)
        {
            switch($type)
            {
                case self::ALL_TOKENS:
                    $tokens[] = $token;
                    break;
                case self::VALID_TOKENS:
                    if($token->isValid()) $tokens[] = $token;
                    break;
                case self::EXPIRED_TOKENS:
                    if (!$token->isValid()) $tokens[] = $token;
                    break;
                }
        }

        return $tokens;
    }

    public function getExpiredTokens(): ?array
    {
        return $this->getTokens(self::EXPIRED_TOKENS);
    }

    public function getValidTokens(): ?array
    {
        return $this->getTokens(self::VALID_TOKENS);
    }

    public function getToken(string $name, $type = self::ALL_TOKENS): ?Token
    {
        $tokens = $this->getTokens($type);
        foreach ($tokens as $token)
            if ($token->getName() == $name) return $token;

        return null;
    }

    public function addToken(Token $token): self
    {
        if (!$this->tokens->contains($token)) {
            $this->tokens[] = $token;
            $token->setUser($this);
        }

        return $this;
    }

    public function removeToken(Token $token): self
    {
        if ($this->tokens->removeElement($token)) {
            // set the owning side to null (unless already changed)
            if ($token->getUser() === $this) {
                $token->setUser(null);
            }
        }

        return $this;
    }

    public function getExpiredToken(string $name): ?Token
    {
        return $this->getToken($name, self::EXPIRED_TOKENS);
    }

    public function getValidToken(string $name): ?Token
    {
        return $this->getToken($name, self::VALID_TOKENS);
    }

    public function removeExpiredTokens(): self
    {
        $expiredTokens = $this->getExpiredTokens();
        foreach ($expiredTokens as $token) {
            $this->removeToken($token);
        }

        return $this;
    }

    /**
     * @return Collection|Thread[]
     */
    public function getAuthoredThreads(): Collection
    {
        return $this->getThreads();
    }
    public function getThreads(): Collection
    {
        return $this->threads;
    }

    public function addThread(Thread $thread): self
    {
        if (!$this->threads->contains($thread)) {
            $this->threads[] = $thread;
            $thread->addAuthor($this);
        }

        return $this;
    }

    public function removeThread(Thread $thread): self
    {
        if ($this->threads->removeElement($thread)) {
            $thread->removeAuthor($this);
        }

        return $this;
    }

    /**
     * @return array|Thread[]
     */
    public function getFollowedThreads(): ArrayCollection
    {
        return $this->followedThreads;
    }

    public function addFollowedThread(Thread $followedThread): self
    {
        if (!$this->followedThreads->contains($followedThread)) {
            $this->followedThreads[] = $followedThread;
            $followedThread->addFollower($this);
        }

        return $this;
    }

    public function removeFollowedThread(Thread $followedThread): self
    {
        if ($this->followedThreads->removeElement($followedThread)) {
            $followedThread->removeFollower($this);
        }

        return $this;
    }

    /**
     * @return array|Mention[]
     */
    public function getMentions(): array
    {
        return $this->mentions;
    }

    public function addMention(Mention $mention): self
    {
        if (!$this->mentions->contains($mention)) {
            $this->mentions[] = $mention;
            $mention->setTarget($this);
        }

        return $this;
    }

    public function removeMention(Mention $mention): self
    {
        if ($this->mentions->removeElement($mention)) {
            // set the owning side to null (unless already changed)
            if ($mention->getTarget() === $this) {
                $mention->setTarget(null);
            }
        }

        return $this;
    }

    /**
     * @return array|Mention[]
     */
    public function getAuthoredMentions(): ArrayCollection
    {
        return $this->authoredMentions;
    }

    public function addAuthoredMention(Mention $authoredMention): self
    {
        if (!$this->authoredMentions->contains($authoredMention)) {
            $this->authoredMentions[] = $authoredMention;
            $authoredMention->setAuthor($this);
        }

        return $this;
    }

    public function removeAuthoredMention(Mention $authoredMention): self
    {
        if ($this->authoredMentions->removeElement($authoredMention)) {
            // set the owning side to null (unless already changed)
            if ($authoredMention->getAuthor() === $this) {
                $authoredMention->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return array|Like[]
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): self
    {
        if (!$this->likes->contains($like)) {
            $this->likes[] = $like;
            $like->setUser($this);
        }

        return $this;
    }

    public function removeLike(Like $like): self
    {
        if ($this->likes->removeElement($like)) {
            // set the owning side to null (unless already changed)
            if ($like->getUser() === $this) {
                $like->setUser(null);
            }
        }

        return $this;
    }

    public function getRecipient(): Recipient
    {
        $email = $this->getEmail();
        if (method_exists(User::class, "getUsername") && !empty($this->getUsername()))
            $email = $this->getUsername() . " <".$email.">";

        if (method_exists(User::class, "getPhone") && !empty($this->getPhone()))
            return new Recipient($email, $this->getPhone());

        return new Recipient($email);
    }
}
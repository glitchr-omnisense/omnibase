<?php

namespace Base\Entity;

use Base\Entity\User\Address;
use Base\Exception\MissingLocaleException;

use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;

use Base\Entity\Extension\Log;

use App\Entity\User\Token;
use App\Entity\User\Group;
use App\Entity\User\Penalty;
use App\Entity\User\Permission;
use App\Entity\User\Notification;
use Base\Database\Annotation\OrderColumn;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\Intl\Timezones;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Timestamp;
use Base\Annotations\Annotation\Uploader;
use Base\Annotations\Annotation\Hashify;

use Base\Service\Localizer;
use Base\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Base\Service\Model\IconizeInterface;

use Base\Traits\BaseTrait;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Exception;
use Base\Database\Annotation\Cache;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use App\Enum\UserState;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 * @DiscriminatorEntry( value = "common" )
 *
 * @AssertBase\UniqueEntity(fields={"email"}, groups={"new", "edit"})
 */
class User implements UserInterface, TwoFactorInterface, PasswordAuthenticatedUserInterface, IconizeInterface
{
    use BaseTrait;

    public function __iconize(): ?array
    {
        return array_map(fn($r) => UserRole::getIcon($r, 0), array_filter($this->getRoles()));
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-user"];
    }

    public const __COOKIE_IDENTIFIER__ = "USER/INFO";
    public const __DEFAULT_IDENTIFIER__ = "email";

    public function isGranted($role): bool
    {
        return $this->getService()->isGranted($role, $this);
    }

    public function killSession()
    {
        $this->logout();
    }

    public static $identifier = self::__DEFAULT_IDENTIFIER__;

    public function getUserIdentifier(): string
    {
        $identifier = null;

        $accessor = PropertyAccess::createPropertyAccessor();
        if ($accessor->isReadable($this, self::$identifier)) {
            $identifier = $accessor->getValue($this, self::$identifier);
        }

        if ($accessor->isReadable($this, self::__DEFAULT_IDENTIFIER__) && !$identifier) {
            $identifier = $accessor->getValue($this, self::$identifier);
        }

        if ($identifier === null) {
            throw new Exception("User identifier is NULL. Is this user initialized or database persistent ?");
        }

        return $identifier;
    }

    public function equals($other): bool
    {
        return ($other->getId() == $this->getId());
    }

    public function __toString()
    {
        return $this->getUserIdentifier();
    }

    public function __construct(?string $email = null)
    {
        $this->email = $email;

        $role = strtoupper(class_basename(get_called_class()));
        $this->roles = [UserRole::hasKey($role) ? UserRole::getValue($role) : UserRole::USER];
        $this->states = [UserState::ENABLED, UserState::NEWCOMER];

        $this->tokens = new ArrayCollection();
        $this->logs = new ArrayCollection();
        $this->permissions = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->penalties = new ArrayCollection();

        $this->threads = new ArrayCollection();
        $this->followedThreads = new ArrayCollection();
        $this->mentions = new ArrayCollection();
        $this->likes = new ArrayCollection();

        $this->addresses = new ArrayCollection();

        $this->setTimezone();
        $this->setLocale();
    }

    public function getRecipient(): Recipient
    {
        $email = $this->getUserIdentifier() . " <" . $this->getEmail() . ">";
        $phone = $this->getPhone() ?? '';
        $locale = $this->getLocale();
        $timezone = $this->getTimezone();

        return new Recipient($email, $phone, $locale, $timezone);
    }

    public function logout(?string $domain)
    {
        $token = $this->getTokenStorage()->getToken();
        if ($token === null || $token->getUser() !== $this) {
            $this->kick();
        } else {
            $this->getTokenStorage()->setToken(null);
            setcookie("REMEMBERME", '', time() - 1);
            setcookie("REMEMBERME", '', time() - 1, "/", $this->getRouter()->getDomain());
        }
    }

    public static function getCookie(string $key = null)
    {
        $cookie = json_decode($_COOKIE[self::__COOKIE_IDENTIFIER__] ?? "", true) ?? [];
        if (array_key_exists("timezone", $cookie)) {
            $timezone = Timezones::getCountryCode($cookie["timezone"]);
            if (!array_key_exists("country", $cookie) || $timezone != $cookie["country"]) {
                $cookie["country"] = Timezones::getCountryCode($cookie["timezone"]);
                User::setCookie("country", $cookie["country"]);
            }
        }

        if (!isset($cookie)) {
            return null;
        }
        if (empty($key)) {
            return $cookie;
        }

        return $cookie[$key] ?? null;
    }

    public static function setCookie(string $key, $value, int $lifetime = 0)
    {
        $cookie = json_decode($_COOKIE[self::__COOKIE_IDENTIFIER__] ?? "", true) ?? [];
        $cookie = array_merge($cookie, [$key => $value]);

        setcookie(self::__COOKIE_IDENTIFIER__, json_encode($cookie), $lifetime > 0 ? time() + $lifetime : 0, "/", parse_url2(get_url())["domain"] ?? "");
    }

    public static function getBrowser(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    public static function getIp(): ?string
    {
        $keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) {
                return $_SERVER[$k];
            }
        }
        return null;
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @ORM\Column(name="secret", type="string", nullable=true)
     */
    protected $secret;

    public const TOTP_LENGTH = 6;
    public const TOTP_TIMEOUT = 30;

    public function getSecret()
    {
        return $this->secret;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return (bool)$this->secret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if ($this->secret == null) {
            return null;
        }
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, User::TOTP_TIMEOUT, User::TOTP_LENGTH);
    }

    public function setSecret($secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\Email(groups={"new", "edit"})
     */
    protected $email;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower($email);

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    protected $phone;

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Uploader(storage="local.storage", max_size="5MB", mime_types={"image/*"}, fetch=true)
     * @AssertBase\File(max_size="5MB", mime_types={"image/*"})
     */
    protected $avatar;

    public function getAvatar()
    {
        return Uploader::getPublic($this, "avatar");
    }

    public function getAvatarFile()
    {
        return Uploader::get($this, "avatar");
    }

    public function setAvatar($avatar)
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=16, nullable=true)
     * @Assert\Locale(canonicalize = true)
     */
    protected $locale;

    public function getLocale(): ?string
    {
        return $this->locale ?? Localizer::__toLocale($this->getTranslator()->getLocale());
    }

    public function setLocale(?string $locale = null): self
    {
        if (empty($locale)) {
            $locale = $this->locale ?? null;
        }
        $this->locale = $locale ?? Localizer::__toLocale($this->getTranslator()->getLocale());

        if (!$this->locale) {
            throw new MissingLocaleException("Missing locale.");
        }

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $timezone;

    public function getCountryCode(): string
    {
        return Timezones::getCountryCode($this->getTimezone());
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? "UTC";
    }

    public function setTimezone(string $timezone = null): self
    {
        if (empty($timezone)) {
            $timezone = $this->timezone ?? null;
        }
        $this->timezone = $timezone ?? User::getCookie("timezone") ?? null;
        if (!in_array($this->timezone, timezone_identifiers_list())) {
            $this->timezone = "UTC";
        }

        return $this;
    }

    /**
     * @var string Plain password should be empty unless you want to change it
     * @Assert\NotCompromisedPassword
     * @AssertBase\Password(min_strength=4, min_length=8)
     */
    protected $plainPassword;

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $password): void
    {
        $this->plainPassword = $password;
        $this->updatedAt = new DateTime("now"); // Plain password is not an ORM variable..
    }

    public function eraseCredentials()
    {
        $this->plainPassword = null;
    }

    public function erasePlainPassword()
    {
        $this->plainPassword = null;
        return $this;
    }

    /**
     * @var string The hashed password
     * @ORM\Column(type="string", nullable=true)
     * @Assert\NotBlank(groups={"new", "edit"}, allowNull=true)
     * @Hashify(reference="plainPassword", algorithm="auto")
     */
    protected $password;

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @ORM\Column(type="user_role")
     * @Assert\NotBlank(groups={"new", "edit"})
     * @OrderColumn
     */
    protected $roles = [];

    public function isSocial(): bool
    {
        return in_array(UserRole::SOCIAL, $this->roles);
    }

    public function isPersistent(): bool
    {
        return (!$this->isSocial() || $this->id > 0);
    }

    public function getRoles(): array
    {
        if (empty($this->roles)) {
            $this->roles[] = UserRole::USER;
        }

        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        if (empty($roles)) {
            $roles[] = UserRole::USER;
        }

        $this->roles = array_filter(array_unique($roles));
        return $this;
    }

    /**
     * @ORM\OneToMany(targetEntity=Log::class, mappedBy="user")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $logs;

    public function getLogs(): Collection
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
     * @ORM\ManyToMany(targetEntity=Group::class, inversedBy="members", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $groups;

    public function getGroups(): Collection
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
     * @ORM\ManyToMany(targetEntity=Permission::class, inversedBy="uid", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $permissions;

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
     * @ORM\ManyToMany(targetEntity=Penalty::class, inversedBy="uid", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $penalties;

    public function getPenalties(): Collection
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
     * @ORM\OneToMany(targetEntity=Notification::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $notifications;

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

    public function isTester()
    {
        if (!$this->getService()->isDebug()) {
            return false;
        }

        foreach ($this->getService()->getParameterBag("base.notifier.test_recipients") as $testRecipient) {
            if (preg_match("/" . $testRecipient . "/", $this->getEmail())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @ORM\OneToMany(targetEntity=Token::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $tokens;

    public function getExpiredTokens(): Collection
    {
        return $this->getTokens(Token::EXPIRED);
    }

    public function getValidTokens(): Collection
    {
        return $this->getTokens(Token::VALID);
    }

    public function getTokens($type = Token::ALL): Collection
    {
        return $this->tokens->filter(function ($token) use ($type) {
            return match ($type) {
                Token::VALID => $token->isValid(),
                Token::EXPIRED => !$token->isValid(),
                default => true,
            };

        });
    }

    public function removeExpiredTokens(): self
    {
        $expiredTokens = $this->getExpiredTokens();
        foreach ($expiredTokens as $token) {
            $this->removeToken($token);
        }

        return $this;
    }

    public function getExpiredToken(string $name): ?Token
    {
        return $this->getToken($name, Token::EXPIRED);
    }

    public function getValidToken(string $name): ?Token
    {
        return $this->getToken($name, Token::VALID);
    }

    public function getToken(string $name, $type = Token::ALL): ?Token
    {
        $tokens = $this->getTokens($type);
        foreach ($tokens as $token) {
            if ($token->getName() == $name) {
                return $token;
            }
        }

        return null;
    }

    public function addToken(Token $token): self
    {
        if (!$this->tokens->contains($token)) {
            $this->removeTokenByName($token->getName());

            $this->tokens[] = $token;
            $token->setUser($this);
        }

        return $this;
    }

    public function removeTokenByName(?string $name): self
    {
        $tokens = $this->getTokens();
        foreach ($tokens as $token) {
            if ($token->getName() == $name) {
                $this->removeToken($token);
            }
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

    /**
     * @ORM\ManyToMany(targetEntity=Thread::class, mappedBy="owners")
     */
    protected $threads;

    public function getThreads(): Collection
    {
        return $this->threads;
    }

    public function addThread(Thread $thread): self
    {
        if (!$this->threads->contains($thread)) {
            $this->threads[] = $thread;
            $thread->addOwner($this);
        }

        return $this;
    }

    public function removeThread(Thread $thread): self
    {
        if ($this->threads->removeElement($thread)) {
            $thread->removeOwner($this);
        }

        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Thread::class, mappedBy="followers", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $followedThreads;

    public function getFollowedThreads(): Collection
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
     * @ORM\OneToMany(targetEntity=Mention::class, mappedBy="target", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $mentions;

    public function getMentions(): Collection
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
     * @ORM\OneToMany(targetEntity=Like::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $likes;

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


    /**
     * @ORM\Column(type="user_state")
     */
    protected $states = [];

    public function getStates(): array
    {
        return $this->states;
    }

    public function setStates(array $states): self
    {
        $this->states = array_unique($states);
        return $this;
    }

    public function newcomer(bool $newState = true): self
    {
        return $this->setIsNewcomer($newState);
    }

    public function elder(bool $newState = true): self
    {
        return $this->setIsNewcomer(!$newState);
    }

    public function isNewcomer(): bool
    {
        return in_array(UserState::NEWCOMER, $this->states);
    }

    public function isElder(): bool
    {
        return !$this->isNewcomer();
    }

    public function setIsNewcomer(bool $newState): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::NEWCOMER) : array_values_remove($this->states, UserState::NEWCOMER);
        return $this;
    }

    public function approve(bool $newState = true): self
    {
        return $this->setIsApproved($newState);
    }

    public function disregarded(bool $newState = true): self
    {
        return $this->setIsApproved(!$newState);
    }

    public function isApproved(): bool
    {
        return in_array(UserState::APPROVED, $this->states);
    }

    public function isDisregarded(): bool
    {
        return !$this->isApproved();
    }

    public function setIsApproved(bool $newState): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::APPROVED) : array_values_remove($this->states, UserState::APPROVED);
        return $this;
    }

    public function verify(bool $newState = true): self
    {
        return $this->setIsVerified($newState);
    }

    public function isVerified(): bool
    {
        return in_array(UserState::VERIFIED, $this->states);
    }

    public function setIsVerified(bool $newState)
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::VERIFIED) : array_values_remove($this->states, UserState::VERIFIED);
        return $this;
    }

    public function enable(bool $newState = true): self
    {
        return $this->setIsEnabled($newState);
    }

    public function disable(bool $newState = true): self
    {
        return $this->setIsEnabled(!$newState);
    }

    public function isEnabled(): bool
    {
        return in_array(UserState::ENABLED, $this->states);
    }

    public function isDisabled(): bool
    {
        return !$this->isEnabled();
    }

    public function setIsEnabled(bool $newState): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::ENABLED) : array_values_remove($this->states, UserState::ENABLED);
        return $this;
    }

    public function lock(bool $newState = true): self
    {
        return $this->setIsLocked($newState);
    }

    public function unlock(bool $newState = true): self
    {
        return $this->setIsLocked(!$newState);
    }

    public function isLocked(): bool
    {
        return in_array(UserState::LOCKED, $this->states);
    }

    public function setIsLocked(bool $newState): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::LOCKED) : array_values_remove($this->states, UserState::LOCKED);
        return $this;
    }

    public function ban(bool $newState = true): self
    {
        return $this->setIsBanned($newState);
    }

    public function unban(bool $newState = true): self
    {
        return $this->setIsBanned(!$newState);
    }

    public function isBanned(): bool
    {
        return in_array(UserState::BANNED, $this->states);
    } // TO IMPLEMENT..

    public function setIsBanned(bool $newState = true): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::BANNED) : array_values_remove($this->states, UserState::BANNED);
        return $this;
    }

    public function kick(bool $newState = true): self
    {
        return $this->setIsKicked($newState);
    }

    public function unkick(bool $newState = true): self
    {
        return $this->setIsKicked(!$newState);
    }

    public function isKicked(): bool
    {
        return in_array(UserState::KICKED, $this->states);
    }

    public function setIsKicked(bool $newState = true): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::KICKED) : array_values_remove($this->states, UserState::KICKED);
        return $this;
    }

    public function ghost(bool $newState = true): self
    {
        return $this->setIsGhost($newState);
    }

    public function isGhost(): bool
    {
        return in_array(UserState::GHOST, $this->states);
    }

    public function setIsGhost(bool $newState): self
    {
        $this->states = $newState ? array_values_insert($this->states, UserState::GHOST) : array_values_remove($this->states, UserState::GHOST);
        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on={"create", "update"})
     */
    protected $updatedAt;

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $activeAt;

    public function getActiveAt(): ?DateTimeInterface
    {
        return $this->activeAt;
    }

    public function poke(?DateTimeInterface $activeAt): self
    {
        $this->activeAt = $activeAt;
        return $this;
    }

    public static function getActiveDelay(): int
    {
        return self::getParameterBag()->get("base.user.active_delay");
    }

    public function isActive(): bool
    {
        return $this->getActiveAt() && $this->getActiveAt() > new DateTime($this->getActiveDelay() . ' seconds ago');
    }

    public static function getOnlineDelay(): int
    {
        return self::getParameterBag()->get("base.user.online_delay");
    }

    public function isOnline(): bool
    {
        return $this->getActiveAt() && $this->getActiveAt() > new DateTime($this->getOnlineDelay() . ' seconds ago');
    }

    /**
     * @ORM\OneToMany(targetEntity=Address::class, mappedBy="user")
     */
    protected $addresses;

    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function getAddress(int $i = 0): ?Address
    {
        return $this->addresses[$i] ?? null;
    }

    public function addAddress(Address $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses[] = $address;
            $address->setUser($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): self
    {
        if ($this->addresses->removeElement($address)) {
            // set the owning side to null (unless already changed)
            if ($address->getUser() === $this) {
                $address->setUser(null);
            }
        }

        return $this;
    }
}

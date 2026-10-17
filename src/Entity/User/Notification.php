<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Bridge\Symfony\NotificationMessages;
use Base\Notifier\Abstract\BaseNotificationInterface;
use Base\Service\Model\IconizeInterface;

use DateTimeInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;

use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

use Base\Traits\BaseTrait;
use Exception;
use Base\Database\Attribute\DiscriminatorEntry;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\NotificationRepository;

/**
 * A notification: its subject, content, importance, channels, recipients,
 * context and templates, and its sending through omnibase's notifier. Cut in
 * two (plan "omnibase agnostique", step C3f): what needs Symfony - an
 * ExceptionEvent or a FlattenException made content, render()'s Response,
 * the e-mail built on Twig's TemplatedEmail and HttpFoundation's uploaded
 * files - is Base\Bridge\Symfony\NotificationMessages'; this class names
 * nothing of the framework (Symfony's Notifier is a standalone component).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\InheritanceType( "JOINED" )]
#[ORM\DiscriminatorColumn( name: "class", type: "string" )]
#[DiscriminatorEntry( value: "common" )]
class Notification extends SymfonyNotification implements BaseNotificationInterface, SmsNotificationInterface, EmailNotificationInterface, ChatNotificationInterface, IconizeInterface
{
    use BaseTrait;

    public function __toPrune(?RecipientInterface $recipient = null, ): SymfonyNotification
    {
        $symfonyNotification = new SymfonyNotification($this->getSubject(), $this->getChannels($recipient));
        if ($this->getException()) {
            $symfonyNotification->exception(new Exception($this->getExceptionAsString()));
        }

        return $symfonyNotification
            ->content($this->getContent())
            ->emoji($this->getEmoji())
            ->importance($this->getImportance());
    }

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-bell"];
    }

    // Default notification
    public const IMPORTANCE_DEFAULT = "default";

    // Browser notification
    public const IMPORTANCE_SUCCESS = "success";
    public const IMPORTANCE_INFO = "info";
    public const IMPORTANCE_NOTICE = "notice";
    public const IMPORTANCE_DANGER = "danger";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * NO cascade remove here. Cascade on the owning side of a ManyToOne means
     * "when this notification is removed, remove its user too" - which is what
     * happened on 2026-09-07 the first time a notification was deleted through
     * the notification center: the member's whole account went with it. The
     * inverse side (User::$notifications, orphanRemoval) already handles the
     * only cascade that makes sense, user gone => notifications gone.
     */
    #[ORM\ManyToOne(targetEntity:User::class, inversedBy:"notifications", cascade:["persist"])]
    #[ORM\JoinColumn(nullable:false)]
    protected $user;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        if ($this->user) {
            $this->user->removeNotification($this);
        }

        if (($this->user = $user)) {
            $this->user->addNotification($this);
        }

        return $this;
    }

    protected $attachments = [];

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * An e-mail's attachment: what the e-mail embeds is an uploaded file (HttpFoundation's
     * UploadedFile, Base\Bridge\Symfony\NotificationMessages::email()), as before; the type is
     * its parent, \SplFileInfo, so that this class names nothing of the framework.
     */
    public function addAttachment(\SplFileInfo $attachment): self
    {
        if (!in_array($attachment, $this->attachments)) {
            $this->attachments[] = $attachment;
        }

        return $this;
    }

    public function removeAttachment(\SplFileInfo $attachment): self
    {
        array_remove($this->attachments, $attachment);
        return $this;
    }

    protected $recipients = [];

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function addRecipient(RecipientInterface $recipient): self
    {
        if (!in_array($recipient, $this->recipients)) {
            $this->recipients[] = $recipient;
        }

        return $this;
    }

    public function removeRecipient(RecipientInterface $recipient): self
    {
        array_remove($this->recipients, $recipient);
        return $this;
    }

    #[ORM\Column(type:"json")]
    protected $channels = [];

    public function getChannels(?RecipientInterface $recipient = null): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): self
    {
        $this->channels = array_unique($channels);
        return $this;
    }

    #[ORM\Column(type:"string", length:255)]
    protected $importance;

    public function getImportance(): string
    {
        return $this->importance;
    }

    public function setImportance(?string $importance): self
    {
        $this->importance = $importance;
        return $this;
    }

    #[ORM\Column(type:"string", length:255)]
    protected $subject;

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    #[ORM\Column(type:"text")]
    protected $content;

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);

        return $this;
    }

    /**
     * Where the notification points (the article it announces, the profile
     * it concerns...). Optional: a plain informational notification has no
     * destination. Stored as a path or absolute URL, whatever the sender
     * had; the in-app list and the push payload use it verbatim.
     */
    #[ORM\Column(type:"string", length:512, nullable:true)]
    protected $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url ?: null;
        return $this;
    }

    /**
     * The entity this notification is about (an article, a comment...), as
     * class + identifier, so each side of the site can build its own link
     * to it: the public page on the front-end, the edit page in the
     * back-office (see NotificationLinkerInterface). `url` stays the
     * front-end link; this is the adaptive half.
     */
    #[ORM\Column(type:"string", length:255, nullable:true)]
    protected $targetClass = null;

    #[ORM\Column(type:"string", length:64, nullable:true)]
    protected $targetId = null;

    public function getTargetClass(): ?string { return $this->targetClass; }
    public function getTargetId(): ?string { return $this->targetId; }

    public function setTarget(?object $entity): self
    {
        $this->targetClass = $entity ? get_class($entity) : null;
        // The id when the entity has one; its uuid when it is still being
        // persisted (a comment's notifications fire before its flush) - the
        // linker resolves either.
        $id = $entity && method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($id === null && $entity && method_exists($entity, 'getUuid')) {
            $id = $entity->getUuid();
        }
        $this->targetId = ($id === null || $id === '') ? null : (string) $id;
        return $this;
    }

    #[ORM\Column(type:"string", length:255)]
    protected $title;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    #[ORM\Column(type:"boolean")]
    protected $isRead = false;

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    /**
     * @param bool $isRead
     * @return $this
     */
    public function markAsRead(bool $isRead)
    {
        return $this->setIsRead($isRead);
    }

    public function markAsReadIfNeeded(array $channels = [])
    {
        $options = [];
        foreach ($this->getNotifier()->getOptions() as $option) {
            $options[$option["channel"]] = $option;
        }

        foreach ($this->channels as $channel) {
            if (array_key_exists($channel, $options) && !$this->isRead()) {
                $this->markAsRead($options[$channel]["markAsRead"]);
            }
        }
    }

    #[ORM\Column(type:"datetime", nullable:"true")]
    protected $sentAt = null;

    public function getSentAt(): ?DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    #[ORM\Column(type:"text")]
    protected $backtrace = ""; // Internal use only (code line might be changing..)

    public function getBacktrace(): string
    {
        return $this->backtrace;
    }

    #[ORM\Column(type:"boolean")]
    protected $markAsAdmin = false;

    /**
     * @return bool|mixed
     */
    public function isMarkAsAdmin()
    {
        return $this->markAsAdmin;
    }

    /**
     * @param bool $markAsAdmin
     * @return $this
     */
    public function markAsAdmin(bool $markAsAdmin = true)
    {
        $this->markAsAdmin = $markAsAdmin;
        return $this;
    }

    /**
     * @var array
     */
    protected $context = [];

    /**
     * @param array $additionalContext
     * @return array
     */
    public function getContext(array $additionalContext = [])
    {
        if ($additionalContext) {
            return array_merge($this->context, $additionalContext);
        }
        return $this->context;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function addContextKey(string $key, $value = null): self
    {
        return $this->addContext([$key => $value]);
    }

    public function addContext(array $context = []): self
    {
        if (empty($context)) {
            return $this;
        }
        return $this->setContext(array_merge($this->context, $context));
    }

    public function setContext(array $context): self
    {
        if (array_key_exists("subject", $context)) {
            $this->setSubject($context["subject"]);
        }
        if (array_key_exists("content", $context)) {
            $this->setContent($context["content"]);
        }

        $this->context = $context;

        return $this;
    }

    public function removeContextKey(string $key): self
    {
        if ($key == "subject") {
            $this->setSubject("");
        }
        if (array_key_exists($key, $this->context)) {
            unset($this->context[$key]);
        }

        return $this;
    }

    /* Handle custom emails */
    protected $htmlTemplate = "";

    /**
     * @return mixed|string
     */
    public function getHtmlTemplate()
    {
        return $this->htmlTemplate;
    }

    /**
     * @param string|null $htmlTemplate
     * @param array $htmlParameters
     * @return $this
     */
    public function setHtmlTemplate(?string $htmlTemplate, array $htmlParameters = [])
    {
        $this->htmlTemplate = $htmlTemplate;
        foreach ($htmlParameters as $key => $htmlParameter) {
            $this->addHtmlParameter($key, $htmlParameter);
        }

        return $this;
    }

    protected $htmlParameters = [];

    public function getHtmlParameters(): array
    {
        return $this->htmlParameters;
    }

    /**
     * @param array $htmlParameters
     * @return $this
     */
    public function setHtmlParameters(array $htmlParameters)
    {
        $this->htmlParameters = $htmlParameters;
        return $this;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function addHtmlParameter(string $key, $value)
    {
        $this->htmlParameters[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function removeHtmlParameter(string $key)
    {
        array_remove($this->htmlParameters, $key);
        return $this;
    }


    /**
     * @return mixed|string
     */
    public function getExcerpt()
    {
        return $this->context["excerpt"] ?? "";
    }

    /**
     * @param string $excerpt
     * @return $this
     */
    public function setExcerpt(string $excerpt)
    {
        $this->context["excerpt"] = $excerpt;
        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getFooter()
    {
        return $this->context["footer_text"] ?? "";
    }

    /**
     * @param string $footer
     * @return $this
     */
    public function setFooter(string $footer)
    {
        $this->context["footer_text"] = $footer;
        return $this;
    }

    /**
     * @param array $parameters
     * @param string $domain
     * @param string|null $locale
     * @throws Exception
     */
    public function __construct($content = null, array $parameters = array(), string $domain = "@notifications", ?string $locale = null)
    {
        $backtrace = debug_backtrace()[0];
        $this->backtrace = $backtrace["file"] . ":" . $backtrace["line"];
        $this->recipients = [];

        // Inject service from base class..
        if ($this->getNotifier() == null) {
            throw new Exception("Notifier not found in User class");
        }

        // Notification variables
        $this->importance = self::IMPORTANCE_DEFAULT;//parent::getImportance();
        $this->setSubject("");
        $this->setTitle("");
        $this->setFooter("");

        // Formatting strings if exception passed as argument (an ExceptionEvent's, a FlattenException, a Throwable)
        if (($exception = NotificationMessages::throwable($content)) !== null) {
            $location = str_replace($this->getProjectDir(), '.', $exception->getFile()) . ":" . $exception->getLine();
            $message = $exception->getMessage();

            $this->setContent("<div class='title'>" . $location . "</div><div class='message'>" . $message . '</div>');
        } elseif ($this->getTwig()->getLoader()->exists($content)) {
            $this->setHtmlTemplate($content);
            $this->setContent("");
        } else {
            $this->setContent($this->getTranslator()->trans($content, $parameters, $domain, $locale) ?? "");
        }
    }

    /**
     * The notification as a page: Symfony's Response of renderHtml()
     * (Base\Bridge\Symfony\NotificationMessages::response()).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render()
    {
        return NotificationMessages::response($this->renderHtml());
    }

    /**
     * The notification's HTML: its template rendered with its context, else its content.
     */
    public function renderHtml(): string
    {
        $htmlTemplate = $this->getHtmlTemplate();
        $context = $this->getContext();

        if ($htmlTemplate) {
            $context = array_merge([

                "raw" => true, // render html         // Make sure notification context for html template got images pre-rendered
                "warmup" => $context["warmup"] ?? true, // e.g. when sending emails..
                "attachments" => array_unique(array_merge($this->getAttachments(), $context["attachments"] ?? []))

            ], $context, $this->getHtmlParameters());

            return $this->getTwig()->render($htmlTemplate, $context);
        }

        return $this->getContent();
    }

    /**
     * @param string|null $importance
     * @param RecipientInterface ...$recipients
     * @return $this
     */
    public function send(?string $importance = null, RecipientInterface ...$recipients)
    {
        $this->setImportance($importance ?? self::IMPORTANCE_DEFAULT);

        // NB: User recipient is only added if no other recipients are found..
        $recipients = array_merge($this->getRecipients(), $recipients);
        if (empty($recipients)) {

            $userRecipient = $this->user?->getRecipient();
            if ($userRecipient !== null) {
                $recipients[] = $userRecipient;
            }
        }

        $recipients = array_filter($recipients, fn($r) => !$r instanceof NoRecipient);    
        $this->getNotifier()->sendUsers($this, ...array_unique($recipients));
        return $this;
    }

    /**
     * @param array $channels
     * @param RecipientInterface ...$recipients
     * @return $this
     */
    public function sendBy(array $channels, RecipientInterface ...$recipients)
    {
        if (!$this->getImportance()) {
            $this->setImportance(Notification::IMPORTANCE_DEFAULT);
        }

        // NB: User recipient is only added if no other recipients are found..
        $recipients = array_merge($this->getRecipients(), $recipients);
        if (empty($recipients)) {
            $userRecipient = $this->user?->getRecipient();
            if ($userRecipient !== null) {
                $recipients[] = $userRecipient;
            }
        }

        $recipients = array_filter($recipients, fn($r) => !$r instanceof NoRecipient);
        $this->getNotifier()->sendUsersBy($channels, $this, ...array_unique($recipients));

        return $this;
    }

    /**
     * @param string $importance
     * @return $this
     */
    public function sendAdmins(string $importance)
    {
        $this->setImportance($importance);
        $this->getNotifier()->sendAdmins($this);

        return $this;
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        //throw new UnexpectedValueException("No SMS support implemented yet.");
        return null;
    }


    /**
     * The e-mail: Base\Bridge\Symfony\NotificationMessages::email(), with the notifier, Twig and
     * the project's directory this notification reaches through BaseTrait.
     */
    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        return NotificationMessages::email($this, $recipient, $this->getNotifier(), $this->getTwig(), $this->getProjectDir());
    }

    public function asChatMessage(RecipientInterface $recipient, ?string $transport = null): ?ChatMessage
    {
        $chatMessage = ChatMessage::fromNotification($this->__toPrune());

        $fwd = "";
        $content = $this->getContent();
        $userIdentifier = $this->user->getIdentifier();

        if ($this->isMarkAsAdmin()) {
            $fwd = "Fwd: ";
            $content = $userIdentifier . " forwarded its notification: \"" . $this->getContent() . "\"";
        } elseif (in_array($recipient, $this->getRecipients()) && $this->getNotifier()->isTest($recipient)) {
            $fwd = "Fwd: [TEST:" . $recipient . "] ";
            $content = $this->getContent();
        }

        switch ($transport) {
            case 'discord':
                $chatMessage->options(new DiscordOptions(["username" => $userIdentifier]));
                break;
            //case '[...]':
        }

        $chatMessage->subject($fwd . "[" . $this->getSubject() . "] " . $content);
        return $chatMessage;
    }
}

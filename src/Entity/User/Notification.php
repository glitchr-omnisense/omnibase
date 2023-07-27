<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Notifier\Abstract\BaseNotificationInterface;
use Base\Service\Model\IconizeInterface;

use DateTimeInterface;
use RuntimeException;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
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
use Throwable;
use Exception;
use Twig\Error\LoaderError;
use UnexpectedValueException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Base\Database\Annotation\DiscriminatorEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\User\NotificationRepository;

/**
 * @ORM\Entity(repositoryClass=NotificationRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 * @DiscriminatorEntry( value = "common" )
 */
class Notification extends SymfonyNotification implements BaseNotificationInterface, SmsNotificationInterface, EmailNotificationInterface, ChatNotificationInterface, IconizeInterface
{
    use BaseTrait;

    public function __toPrune(?RecipientInterface $recipient = null): SymfonyNotification
    {
        $symfonyNotification = new SymfonyNotification($this->getSubject(), $this->getChannels($recipient));
        if ($this->getException()) {
            $symfonyNotification->exception($this->getException());
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

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="notifications", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
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

    public function addAttachment(UploadedFile $attachment): self
    {
        if (!in_array($attachment, $this->attachments)) {
            $this->attachments[] = $attachment;
        }

        return $this;
    }

    public function removeAttachment(UploadedFile $attachment): self
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

    /**
     * @ORM\Column(type="json")
     */
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

    /**
     * @ORM\Column(type="string", length=255)
     */
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

    /**
     * @ORM\Column(type="string", length=255)
     */
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

    /**
     * @ORM\Column(type="text")
     */
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
     * @ORM\Column(type="string", length=255)
     */
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

    /**
     * @ORM\Column(type="boolean")
     */
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

    /**
     * @ORM\Column(type="datetime", nullable="true")
     */
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

    /**
     * @ORM\Column(type="text")
     */
    protected $backtrace = ""; // Internal use only (code line might be changing..)

    public function getBacktrace(): string
    {
        return $this->backtrace;
    }

    /**
     * @ORM\Column(type="boolean")
     */
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
     * @param $value
     * @return $this
     */
    /**
     * @param string $key
     * @param $value
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
     * @param $value
     * @return $this
     */
    /**
     * @param string $key
     * @param $value
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
     * @param $content
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
        $this->importance = parent::getImportance();
        $this->setSubject("");
        $this->setTitle("");
        $this->setFooter("");

        // Formatting strings if exception passed as argument
        if ($content instanceof ExceptionEvent) {
            $exception = $content->getThrowable();
            $location = str_replace($this->getProjectDir(), '.', $exception->getFile()) . ":" . $exception->getLine();
            $message = $exception->getMessage();

            $this->setContent("<div class='title'>" . $location . "</div><div class='message'>" . $message . '</div>');
        } elseif ($content instanceof FlattenException || $content instanceof Throwable) {
            $location = str_replace($this->getProjectDir(), '.', $content->getFile()) . ":" . $content->getLine();
            $message = $content->getMessage();

            $this->setContent("<div class='title'>" . $location . "</div><div class='message'>" . $message . '</div>');
        } elseif ($this->getTwig()->getLoader()->exists($content)) {
            $this->setHtmlTemplate($content);
            $this->setContent("");
        } else {
            $this->setContent($this->getTranslator()->trans($content, $parameters, $domain, $locale) ?? "");
        }
    }

    public function render(): Response
    {
        $htmlTemplate = $this->getHtmlTemplate();
        $context = $this->getContext();

        if ($htmlTemplate) {
            $context = array_merge([

                "raw" => true, // render html         // Make sure notification context for html template got images pre-rendered
                "warmup" => $context["warmup"] ?? true, // e.g. when sending emails..
                "attachments" => array_unique(array_merge($this->getAttachments(), $context["attachments"] ?? []))

            ], $context, $this->getHtmlParameters());

            return new Response($this->getTwig()->render($htmlTemplate, $context));
        }

        return new Response($this->getContent());
    }

    /**
     * @param string|null $importance
     * @param RecipientInterface ...$recipients
     * @return $this
     */
    /**
     * @param string|null $importance
     * @param RecipientInterface ...$recipients
     * @return $this
     */
    public function send(string $importance = null, RecipientInterface ...$recipients)
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

    public function asSmsMessage(SmsRecipientInterface $recipient, string $transport = null): ?SmsMessage
    {
        //throw new UnexpectedValueException("No SMS support implemented yet.");
        return null;
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $notifier = $this->getNotifier();
        $notification = EmailMessage::fromNotification($this, $recipient);

        /**
         * @var EmailRecipientInterface $notifier
         */

        $technicalRecipient = $notifier->getTechnicalRecipient();
        if ($technicalRecipient instanceof NoRecipient) {
            throw new UnexpectedValueException("No support address found.");
        }

        $title = $this->getTitle();
        $content = $this->getContent();
        $footer = $this->getFooter();

        $importance = $this->getImportance();

        $fwd = "";
        $subject = $this->getSubject();
        $from = $technicalRecipient->getEmail();
        $to = $recipient->getEmail();

        if ($this->isMarkAsAdmin()) {
            $user = $this->user ?? "User \"" . User::getIp() . "\"";
            $fwd .= "Admin: ";
            $title = $notifier->getTranslator()->trans("@emails.admin_forwarding.notice", [$user, $this->getTitle()]);
            $content = $this->getContent();
        }

        if ($this->getNotifier()->isTest($recipient)) {
            $fwd .= "Test: ";
            $email = mailparse($recipient->getEmail());
            $email = mailformat(mailparse($technicalRecipient->getEmail()), first($email));
            $to = "[" . $notifier->getTranslator()->trans("@emails.fake_test.author") . "] " . $email;

            $footer = [$footer, $notifier->getTranslator()->trans("@emails.fake_test.notice")];
            $footer = implode(" - ", array_filter($footer));
        }

        /**
         * @var TemplatedEmail $notification
         */
        $email = $notification->getMessage(); // Embed image inside email (cid:/)
        $context = array_merge([

            // render html
            "raw" => true,
            // Make sure notification context for html template got images pre-rendered
            "warmup" => true, // e.g. when sending emails..

        ], $this->getContext([
            "importance" => $importance,
            "title" => $title,
            "content" => $content,
            "footer_text" => $footer,
            "recipient" => $recipient
        ]), $this->getHtmlParameters());

        // Append notification attachments
        $attachments = array_unique(array_merge($this->getAttachments(), $context["attachments"] ?? []));
        $context["attachments"] = $attachments;

        foreach ($attachments as $attachment) {
            if (!$attachment instanceof UploadedFile) {
                continue;
            }
            $email->embed($attachment->getContent(), $attachment->getClientOriginalName());
        }

        // Fallback: Append cid:/ like attachments
        foreach ($context as $key => $value) {
            if (!$value) {
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            if (!str_starts_with($value, "cid:")) {
                continue;
            }

            list($cid, $path) = explode(":", $value);
            $email->embed(fopen($this->getProjectDir() . "/" . $path, 'rb'), $path);
        }


        // Render html template to get back email title..
        // I was hoping to replace content with html(), but this gets overriden by Symfony notification
        try {
            $htmlTemplate = $this->getTwig()->render($this->htmlTemplate, $context);
        } catch (LoaderError $e) {
            $htmlTemplate = $this->getTwig()->render("@Base/notifier/email.html.twig", $context);
        } catch (RuntimeException $e) {
            throw new UnexpectedValueException("Template \"$this->htmlTemplate\" not found.", 500, $e);
        }

        if (preg_match('/<title>(.*)<\/title>/ims', $htmlTemplate, $matches)) {
            $subject = html_entity_decode(trim($matches[1]));
        }

        $subject ??= $this->getSubject();
        $subject = $fwd . $subject;

        $priority = [self::IMPORTANCE_HIGH, self::IMPORTANCE_MEDIUM, self::IMPORTANCE_LOW];
        $email
            ->importance(in_array($importance, $priority) ? $importance : "")
            ->subject($subject)
            ->from($from)
            ->to($to)
            ->replyTo($this->context["replyTo"] ?? $from)
            //->html($html) // DO NOT USE: Overridden by the default Symfony notification template
            ->htmlTemplate($this->htmlTemplate)
            ->context($context);

        return $notification;
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $chatMessage = ChatMessage::fromNotification($this->__toPrune());

        $fwd = "";
        $content = $this->getContent();
        $userIdentifier = $this->user->getIdentifier();

        if ($this->isMarkAsAdmin()) {
            $fwd = "Fwd: ";
            $content = $userIdentifier . " forwarded its notification: \"" . $this->getContent() . "\"";
        } elseif (in_array($recipient, $this->getRecipients()) && $this->getNotifier()->isTest($recipient)) {
            $user = $recipient;
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

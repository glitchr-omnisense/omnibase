<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Service\Model\IconizeInterface;

use Base\Service\BaseService;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
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
use UnexpectedValueException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Base\Database\Annotation\DiscriminatorEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\User\NotificationRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=NotificationRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "common" )
 */
class Notification extends \Symfony\Component\Notifier\Notification\Notification implements SmsNotificationInterface, EmailNotificationInterface, ChatNotificationInterface, IconizeInterface
{
    use BaseTrait;

    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-bell"]; }

    // Default notification
    public const IMPORTANCE_DEFAULT = "default";

    // Browser notification
    public const IMPORTANCE_SUCCESS = "success";
    public const IMPORTANCE_INFO    = "info";
    public const IMPORTANCE_NOTICE  = "notice";
    public const IMPORTANCE_DANGER  = "danger";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="notifications")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    public function getUser() : ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        
        if ($this->user) {
            $this->user->removeNotification($this);
            $this->removeContextKey("user");
        }

        if(($this->user = $user) ) {
            $this->user->addNotification($this);
            $this->setRecipient($this->user->getRecipient());
            $this->addContextKey("user", $this->user);
        }

        return $this;
    }

    protected RecipientInterface $recipient;
    public function getRecipient(): RecipientInterface { return $this->recipient; }
    public function setRecipient(?RecipientInterface $recipient): self
    {
        $this->recipient = $recipient ?? new NoRecipient();
        return $this;
    }

    /**
     * @ORM\Column(type="json")
     */
    protected $channels = [];

    public function getChannels(?RecipientInterface $recipient = null): array { return $this->channels; }
    public function setChannels(array $channels): self
    {
        $this->channels = array_unique($channels);
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $importance;

    public function getImportance(): string { return $this->importance; }
    public function setImportance(?string $importance): self {
        $this->importance = $importance;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $subject;

    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $content;

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self
    {
        $this->content = trim($content);

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $title;

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isRead = false;
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function markAsRead(bool $isRead) { return $this->setIsRead($isRead); }
    public function markAsReadIfNeeded(array $channels = [])
    {
        $options = [];
        foreach(BaseService::getNotifier()->getOptions() as $option)
            $options[$option["channel"]] = $option;

        foreach($this->channels as $channel) {

            if(array_key_exists($channel, $options) && !$this->isRead())
                $this->markAsRead($options[$channel]["markAsRead"]);
        }
    }

    /**
     * @ORM\Column(type="datetime", nullable="true")
     */
    protected $sentAt = null;
    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function setSentAt(?\DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected string $backtrace = ""; // Internal use only (code line might be changing..)
    public function getBacktrace(): string { return $this->backtrace; }

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $markAsAdmin = false;

    public function isMarkAsAdmin() { return $this->markAsAdmin; }
    public function markAsAdmin(bool $markAsAdmin = true)
    {
        $this->markAsAdmin = $markAsAdmin;
        return $this;
    }

    /**
     * @var array
     */
    protected array $context = [];
    public function getContext(array $additionalContext = [])
    {
        if($additionalContext) return array_merge($this->context, $additionalContext);
        return $this->context;
    }

    public function addContextKey(string $key, $value = null): self { return $this->addContext([$key => $value]); }
    public function addContext(array $context = []): self
    {
        if(empty($context)) return $this;
        return $this->setContext(array_merge($this->context, $context));
    }
    public function setContext(array $context): self
    {
        if(array_key_exists("subject", $context)) $this->setSubject($context["subject"]);
        if(array_key_exists("content", $context)) $this->setContent($context["content"]);

        $this->context = $context;

        return $this;
    }

    public function removeContextKey(string $key): self
    {
        if($key == "subject") $this->setSubject("");
        if(array_key_exists($key, $this->context))
            unset($this->context[$key]);

        return $this;
    }

    /* Handle custom emails */
    protected string $htmlTemplate = "";
    public function getHtmlTemplate() { return $this->htmlTemplate; }
    public function setHtmlTemplate(?string $htmlTemplate, array $context = [])
    {
        $this->htmlTemplate = $htmlTemplate;

        if(!empty($context))
            $this->addContext($context);

        return $this;
    }

    public function getExcerpt() { return  $this->context["excerpt"] ?? ""; }
    public function setExcerpt(string $excerpt)
    {
        $this->context["excerpt"] = $excerpt;
        return $this;
    }

    public function getFooter() { return $this->context["footer_text"] ?? ""; }
    public function setFooter(string $footer)
    {
        $this->context["footer_text"] = $footer;
        return $this;
    }

    public function __construct($content = null, array $parameters = array(), string $domain = "@notifications", ?string $locale = null)
    {
        $backtrace = debug_backtrace()[0];
        $this->backtrace = $backtrace["file"].":".$backtrace["line"];
        $this->recipient = new NoRecipient();

        // Inject service from base class..
        if (User::getNotifier() == null)
            throw new Exception("Notifier not found in User class");

        // Notification variables
        $this->importance = parent::getImportance();
        $this->setSubject("");
        $this->setTitle("");
        $this->setFooter("");

        // Formatting strings if exception passed as argument
        if ( $content instanceof ExceptionEvent ) {

            $exception = $content->getThrowable();
            $location = str_replace($this->getProjectDir(),'.', $exception->getFile()) . ":" . $exception->getLine();
            $message = $exception->getMessage();

            $this->setContent("<div class='title'>".$location."</div><div class='message'>".$message.'</div>');

        } else if ($content instanceof FlattenException || $content instanceof Throwable) {

            $location = str_replace($this->getProjectDir(),'.', $content->getFile()) . ":" . $content->getLine();
            $message = $content->getMessage();

            $this->setContent("<div class='title'>".$location."</div><div class='message'>".$message.'</div>');

        } else if($this->getTwig()->getLoader()->exists($content)) {
            
            $this->setHtmlTemplate($content);
            $this->setContent("");
            
        } else {

            $this->setContent($this->getTranslator()->trans($content, $parameters, $domain, $locale) ?? "");
        }
    }

    public function render(): Response
    {
        $htmlTemplate = $this->getHtmlTemplate();
        $context      = $this->getContext();

        return new Response($htmlTemplate ? $this->getTwig()->render($htmlTemplate, $context) : $this->getContent());
    }

    public function send(string $importance = null, RecipientInterface ...$recipients)
    {
        $this->setImportance($importance ?? self::IMPORTANCE_DEFAULT);

        $userRecipient = $this->getRecipient();
        if(empty($recipients) && $userRecipient !== null)
                $recipients[] = $userRecipient;

        $recipients = array_filter($recipients, fn($r) => !$r instanceof NoRecipient);
        User::getNotifier()->sendUsers($this, ...array_unique_object($recipients));

        return $this;
    }

    public function sendBy(array $channels, RecipientInterface ...$recipients)
    {
        if(!$this->getImportance())
            $this->setImportance(Notification::IMPORTANCE_DEFAULT);

        $userRecipient = $this->getRecipient();
        if($userRecipient !== null && !in_array($userRecipient, $recipients))
            $recipients[] = $this->getRecipient();

        $recipients = array_filter($recipients, fn($r) => !$r instanceof NoRecipient);
        User::getNotifier()->sendUsersBy($channels, $this, ...array_unique_object($recipients));

        return $this;
    }

    public function sendAdmins(string $importance)
    {
        $this->setImportance($importance);
        User::getNotifier()->sendAdmins($this);

        return $this;
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, string $transport = null): ?SmsMessage
    {
        //throw new UnexpectedValueException("No SMS support implemented yet.");
        return null;
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $notifier = User::getNotifier();
        $notification = EmailMessage::fromNotification($this, $recipient, $transport);

        /**
         * @var EmailRecipientInterface
         */

        $technicalRecipient = $notifier->getTechnicalRecipient();
        if($technicalRecipient instanceof NoRecipient) 
            throw new UnexpectedValueException("No support address found.");

        $title = $this->getTitle();
        $content = $this->getContent();
        $footer = $this->getFooter();

        $importance = $this->getImportance();

        $fwd = "";
        $subject = $this->getSubject();
        $from = $technicalRecipient->getEmail();
        $to   = $recipient->getEmail();
   
       if($this->isMarkAsAdmin()) {

            $user = $this->user ?? "User \"".User::getIp()."\"";
            $fwd .= "Admin: ";
            $title   = $notifier->getTranslator()->trans("@emails.admin_forwarding.notice", [$user, $this->getTitle()]);
            $content = $this->getContent();
        }

        if(User::getNotifier()->isTest($recipient)) {

            $fwd .= "Test: ";
            $email = mailparse($recipient->getEmail());
            $email = mailformat(mailparse($technicalRecipient->getEmail()), first($email));
            $to   = "[".$notifier->getTranslator()->trans("@emails.fake_test.author")."] ". $email;

            $footer  = [$footer, $notifier->getTranslator()->trans("@emails.fake_test.notice")];
            $footer  = implode(" - ", array_filter($footer));
        }

        /**
         * @var TemplatedEmail
         */
        $email = $notification->getMessage(); // Embed image inside email (cid:/)
        $context = $this->getContext([
            "importance"  => $importance,
            "title"       => $title,
            "content"     => $content,
            "footer_text" => $footer,
            "recipient"   => $recipient
        ]);

        // Attach images using cid:/
        $projectDir = BaseService::getProjectDir();
        foreach($context as $key => $value) {

            if(!$value) continue;
            if(!is_string($value)) continue;
            if(!str_starts_with($value, "cid:")) continue;

            list($cid, $path) = explode(":", $value);
            $email->embed(fopen($projectDir . "/" . $path, 'rb'), $path);
        }

        // Render html template to get back email title..
        // I was hoping to replace content with html(), but this gets overriden by Symfony notification
        $htmlTemplate = $this->getTwig()->render($this->htmlTemplate, $context);
        if(preg_match('/<title>(.*)<\/title>/ims', $htmlTemplate, $matches))
            $subject = trim($matches[1]);

        $subject ??= $this->getSubject();
        $subject = $fwd.$subject;
       
        $priority = [self::IMPORTANCE_HIGH, self::IMPORTANCE_MEDIUM, self::IMPORTANCE_LOW];
        $email
            ->importance(in_array($importance, $priority) ? $importance : "")
            ->subject($subject)
            ->from($from)
            ->to($to)
            //->html($html) // DO NOT USE: Overridden by the default Symfony notification template
            ->htmlTemplate($this->htmlTemplate)
            ->context($context);

        return $notification;
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $chatMessage = ChatMessage::fromNotification($this, $recipient, $transport);

        $user = ($this->user ? $this->user->getUsername() : "User \"".User::getIp()."\"");

        $fwd = "";
        $content = $this->getContent();
        $user = $this->user ?? "User \"" . User::getIp() . "\"";

        if($this->isMarkAsAdmin()) {

            $fwd = "Fwd: ";
            $user = $this->user ?? "User \"" . User::getIp() . "\"";
            $content = $user . " forwarded its notification: \"" . $this->getContent() . "\"";

        } else if($this->getRecipient() != $recipient && User::getNotifier()->isTest($recipient)) {

            $user = $recipient;
            $fwd = "Fwd: [TEST:".$recipient."] ";
            $content = $this->getContent();
        }

        switch ($transport) {
            case 'discord':
                $chatMessage->options(new DiscordOptions(["username" => $user]));
        }

        $chatMessage->subject($fwd . "[" . $this->getSubject(). "] " . $content);
        return $chatMessage;
    }
}

<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Model\IconizeInterface;
use Doctrine\Common\Collections\ArrayCollection;

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
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\User\NotificationRepository;


/**
 * @ORM\Entity(repositoryClass=NotificationRepository::class)
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

    public function getUser() { return $this->user; }
    
    public function setUser(?User $user): self
    {
        if ($this->user) {
            $this->user->removeNotification($this);
            $this->removeContextKey("user");
        }

        if(($this->user = $user) ) {
            $this->user->addNotification($this);
            $this->addContextKey("user", $this->user);
        }

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
     * @ORM\Column(type="string", length=255)
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
        $this->recipient = new ArrayCollection();
        
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

        } else {

            $this->setContent($this->getTranslator()->trans($content, $parameters, $domain, $locale) ?? "");
        }
    }

    public function send(string $importance, ...$recipients) 
    { 
        $this->setImportance($importance);

        $recipients[] = ($this->user ? $this->user->getRecipient() : new NoRecipient());
        User::getNotifier()->sendUsers($this, ...$recipients); 

        return $this;
    }
    
    public function sendBy(array $channels,  ...$recipients) 
    {
        if(!$this->getImportance())
            $this->setImportance(Notification::IMPORTANCE_DEFAULT);

        $recipients[] = ($this->user ? $this->user->getRecipient() : new NoRecipient());
        User::getNotifier()->sendUsersBy($channels, $this, ...$recipients);
        
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
        
        /**
         * @var EmailRecipientInterface
         */
        $adminAddress = $notifier->getAdminRecipients()[0] ?? new NoRecipient();
        if($adminAddress instanceof NoRecipient) throw new UnexpectedValueException("No support address found.");

        $title = $this->getTitle();
        $content = $this->getContent();
        $footer = $this->getFooter();

        $importance = $this->getImportance();
        $this->setImportance(""); // Remove importance from email subject

        if($this->isMarkAsAdmin()) {
            
            $user = $this->user ?? "User \"".User::getIp()."\"";
            $subject = "Fwd: " . $this->getSubject();
            $title   = $notifier->getTranslator()->trans("@emails.admin_forwarding.notice", [$user, $this->getTitle()]);
            $content = $this->getContent();
            $from    = $adminAddress->getEmail();

        } else if($this->user && $this->user->getRecipient() != $recipient) {

            $subject = "Fwd: " . $this->getSubject();
            $from    = "[".$notifier->getTranslator()->trans("@emails.fake_test.author")."] ". $this->user->getRecipient()->getEmail();
            $footer  = [$footer, $notifier->getTranslator()->trans("@emails.fake_test.notice")];
            $footer  = implode(" - ", array_filter($footer)); 
        
        } else {

            $subject = $this->getSubject();
            $from    = $adminAddress->getEmail();
        }

        $notification = EmailMessage::fromNotification($this, $recipient, $transport);
        
        /**
         * @var TemplatedEmail
         */
        $email = $notification->getMessage(); // Embed image inside email (cid:/)
        $context = $this->getContext([
            "importance"  => $importance,
            "title"       => $title,
            "content"     => $content,
            "footer_text" => $footer
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
            $subject = trim($matches[1] ?? $this->getSubject());

        $email
            ->subject($subject)
            ->from($from)
            //->html($html) // DO NOT USE: Overridden by the default Symfony notification template
            ->htmlTemplate($this->htmlTemplate)
            ->context($context);

        $this->setImportance($importance);
        return $notification;
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $chatMessage = ChatMessage::fromNotification($this, $recipient, $transport);

        $user = ($this->user ? $this->user->getUsername() : "User \"".User::getIp()."\"");
        if($this->isMarkAsAdmin()) {
            
            $user = $this->user ?? "User \"" . User::getIp() . "\"";
            $subject = "Fwd: " . $this->getSubject();
            $content = $user . " forwarded its notification: \"" . $this->getContent() . "\"";

        } else if($this->user && $this->user->getRecipient() != $recipient) {

            $user = $recipient;
            $subject = "Fwd: [TEST:".$recipient."] " . $this->getSubject();
            $content = $this->getContent();

        } else {

            $user = $this->user ?? "User \"" . User::getIp() . "\"";
            $subject = $this->getSubject();
            $content = $this->getContent();
        }

        switch ($transport) {
            case 'discord':
                $chatMessage->options(new DiscordOptions(["username" => $user]));
        }

        $chatMessage->subject("[" . $subject. "] " . $content);
        return $chatMessage;
    }
}

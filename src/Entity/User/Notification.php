<?php

namespace Base\Entity\User;

use App\Entity\User;
use App\Entity\User\Group;

use App\Repository\User\NotificationRepository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Base\Service\BaseService;
use Base\Twig\BaseTwigExtension;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\NotifierInterface;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;

use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @ORM\Entity(repositoryClass=NotificationRepository::class)
 */
class Notification extends \Symfony\Component\Notifier\Notification\Notification implements SmsNotificationInterface, EmailNotificationInterface, ChatNotificationInterface
{
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
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="notifications")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     * @ORM\Column(type="json")
     */
    protected $channels;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $importance;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $subject;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $content;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isRead;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $sentAt = [];

    protected ?string $projectDir = null;
    
    protected ?BaseTwigExtension $translator = null;
    public function __construct($subject, ?string $content = null, array $parameters = array())
    {
        $this->recipient = new ArrayCollection();

        // Inject service from base class..
        if (User::getNotifier() == null)
            throw new Exception("Notifier not found in User class");

        // Inject translator from base class..
        if (User::getTranslator() == null)
            throw new Exception("Translator not found in User class");

        $this->projectDir = BaseService::getProjectDir();
        $this->translator = new BaseTwigExtension(User::getTranslator());

        // Notification variables
        $this->importance = parent::getImportance();
        $this->htmlTemplate = "";
        $this->context = [];
        $this->isRead = false;

        
        // Formatting strings if exception passed as argument
        if ( $subject instanceof ExceptionEvent ) {

            $event     = $subject;
            $exception = $event->getThrowable();

            $this->setSubject("Exception");
            $this->setContent(
                "<b>".$exception->getFile() . ":" . $exception->getLine()."</b>".
                "<br/>".$exception->getMessage()
            );

        } else if ($subject instanceof FlattenException) {

            $exception = $subject;

            $this->setSubject("Exception");
            $this->setContent(
                "<b>" . $exception->getFile() . ":" . $exception->getLine() . "</b>" .
                    "<br/>" . $exception->getMessage()
            );

        } else if(!$content){

            $this->setSubject("Unknown");
            $this->setContent($this->translator->trans2($subject, $parameters) ?? "");

        } else {

            $this->setSubject($this->translator->trans2($subject, $parameters) ?? "");
            $this->setContent($this->translator->trans2($content, $parameters) ?? "");
        }
    }

    public function getTranslator(): ?BaseTwigExtension
    {
        return $this->translator;
    }
    
    /**
     * Entity related methods
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getSentAt(?string $channel = null)
    {
        if (!$channel) return $this->sentAt;
        return $this->sentAt[$channel];
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);

        return $this;
    }

    public function getImportance(): string
    {
        return $this->importance;
    }

    public function setImportance(?string $importance)
    {
        $this->importance = $importance;
    }

    /* Inherited methods from Symfony */
    public function getUser()
    {
        return $this->user;
    }

    public function __sleep()
    {
        $this->projectDir = null;
        $this->translator = null;

        return array_keys(get_object_vars($this));
    }

    public function __wakeup()
    {
        $this->projectDir = BaseService::getProjectDir();
        $this->translator = new BaseTwigExtension(User::getTranslator());
    }

    
    public function setUser(?User $user): self
    {
        if($this->user) {
            $this->user->removeNotification($this);
            $this->removeContextKey("user");
        }

        $this->user = $user;
        $this->user->addNotification($this);

        $this->addContextKey("user", $this->user);
        return $this;
    }

    /* Handle custom emails */
    protected string $htmlTemplate;
    public function getHtmlTemplate()
    {
        return $this->htmlTemplate;
    }
    public function setHtmlTemplate(?string $htmlTemplate, ?array $context = null)
    {
        $this->htmlTemplate = $htmlTemplate;
        if($context) $this->setContext($context);

        if(array_key_exists("subject", $context))
            $this->setSubject($context["subject"]);

        return $this;
    }

    /**
     * @var array
     */

    protected array $context;
    public function getContext(array $additionalContext = [])
    {
        if($additionalContext) return array_merge($additionalContext, $this->context);
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function addContext(array $context = []): self
    {
        if($context)
            $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function addContextKey(string $key, $value = null): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function removeContextKey(string $key): self
    {
        if(array_key_exists($key, $this->context))
            unset($this->context[$key]);

        return $this;
    }

    /**
     * @var string
     */
    protected string $footer = "";

    public function getFooter()
    {
        return $this->footer;
    }

    public function setFooter(string $footer)
    {
        $this->footer = $footer;
        return $this;
    }

    /**
     * @var string
     */
    protected string $excerpt = "";
    public function getExcerpt()
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt)
    {
        $this->excerpt = $excerpt;
        return $this;
    }



    public function asSmsMessage(SmsRecipientInterface $recipient, string $transport = null): ?SmsMessage
    {
        return null;
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $supportAddress = User::getNotifier()->getAdminRecipients()[0] ?? [new NoRecipient()];
        if($supportAddress instanceof NoRecipient)
            throw new Exception("Unexpected support address found.. Administrator has been notified");

        if($this->isAdminChannels()) {

            $user = ($this->user ? $this->user->getUsername() : "User \"".User::getIp()."\"");

            $subject = "Fwd: " . $this->getSubject();
            $content = $user . " forwarded notification: \"" . $this->getContent() . "\"";

            $notification = EmailMessage::fromNotification($this, $recipient, $transport);
            $email = $notification->getMessage();
            foreach($context as $key => $value) {
            
                if(!$value) continue;
                if(!is_string($value)) continue;
                if(!str_starts_with($value, "cid:")) continue;
                list($cid, $path) = explode(":", $value);

                $email->embed(fopen($this->projectDir . "/" . $path, 'rb'), $path);
            }

            $email
                ->subject($subject)
                ->from($supportAddress->getEmail())
                ->htmlTemplate("@Base/email/notifier/default.html.twig")
                ->context($this->getContext([
                    "content" => $content, 
                    "excerpt" => $this->excerpt, 
                    "footer" => $this->footer
                ]));

        } else {

            $subject = $this->getSubject();
            $content = $this->getContent();
           
            $importance = $this->getImportance();
            $this->setImportance("");

            $context = $this->getContext([
                "content" => $content, 
                "excerpt" => $this->excerpt, 
                "footer" => $this->footer
            ]);

            $notification = EmailMessage::fromNotification($this, $recipient, $transport);
            $email = $notification->getMessage();
            foreach($context as $key => $value) {
            
                if(!$value) continue;
                if(!is_string($value)) continue;
                if(!str_starts_with($value, "cid:")) continue;
                list($cid, $path) = explode(":", $value);

                $email->embed(fopen($this->projectDir . "/" . $path, 'rb'), $path);
            }

            $email
                ->subject($subject)
                ->from($supportAddress->getEmail())
                ->htmlTemplate($this->htmlTemplate)
                ->context($context);

            $this->setImportance($importance);
        }

        return $notification;
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $chatMessage = ChatMessage::fromNotification($this, $recipient, $transport);

        if (!$this->isAdminChannels())
            $subject = "[" . $this->getSubject() . "] " . $this->getContent();
        else
            $subject = "[Fwd: " . $this->getSubject()."] " . $this->getContent();

        $username = ($this->user ? $this->user->getUsername() : "User \"" . User::getIp() . "\"");
        switch ($transport) {

            case 'discord':
                $chatMessage->options(new DiscordOptions(["username" => $username]));
        }

        $chatMessage->subject($subject);
        return $chatMessage;
    }

    public function getDefaultChannels()
    {
        return User::getNotifierPolicy()->getChannels($this->importance);
    }

    public function getChannels(RecipientInterface $recipient): array
    {
        if( !empty($this->channels) ) return $this->channels;

        if( $this->isAdminChannels() ) return $this->getAdminChannels($recipient);
        else return $this->getUserChannels($recipient);
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    /**
     * @var bool
     */
    protected bool $adminChannels = false;
    public function isAdminChannels()
    {
        return $this->adminChannels;
    }

    public function setAdminChannels(bool $adminChannels)
    {
        $this->adminChannels = $adminChannels;
        return $this;
    }

    public function getUserChannels(RecipientInterface $recipient): array
    {
        $channels = [];
        foreach($this->getDefaultChannels() as $channel) {

            // Replace email by email+ for user..
            // Users should not receive the default admin email sent by Symfony notifier
            if (str_starts_with($channel, "email")) $channel = "email+";

            // If no recipient, only browser notification is allowed to be sent.
            if($recipient instanceof NoRecipient && !str_starts_with($channel, "browser"))
                continue;

            // If recipient implement SMS interface, check if sms is allowed and phone number available
            else if(str_starts_with($channel, "sms")) {

                if($recipient instanceof SmsRecipientInterface && empty($recipient->getPhone()))
                    continue;
            }

            // If recipient implement Email interface, check if email is available
            else if(str_starts_with($channel, "email")) {

                if($recipient instanceof EmailRecipientInterface && empty($recipient->getEmail()))
                    continue;
            }

            // Only admin can receive chat message..
            else if(str_starts_with($channel, "chat/"))
                continue;

            $channels[] = $channel;
        }

        return array_unique($channels);
    }

    public function getAdminChannels(RecipientInterface $recipient): array
    {
        $channels = [];
        foreach ($this->getDefaultChannels() as $channel) {

            // Replace email by email+ for user..
            // Users should not receive the default admin email sent by Symfony notifier
            if (str_starts_with($channel, "email"  )) $channel = "email";

            // I suppose admin should receive notification by email when user is browser notified.
            else if (str_starts_with($channel, "browser")) $channel = "email";

            // If no recipient, only browser notification is allowed to be sent.
            else if ($recipient instanceof NoRecipient) continue;

            // If recipient implement SMS interface, check if sms is allowed and phone number available
            else if (str_starts_with($channel, "sms")) {

                if($recipient instanceof SmsRecipientInterface && empty($recipient->getPhone()) )
                    continue;
            }

            // If recipient implement Email interface, check if email is available
            else if (str_starts_with($channel, "email")) {

                if( $recipient instanceof EmailRecipientInterface && empty($recipient->getEmail()) )
                    continue;
            }

            else if (str_starts_with($channel, "chat/")) {

                $firstAdminRecipient = User::getNotifier()->getAdminRecipients()[0] ?? null;
                if($recipient != $firstAdminRecipient) continue;
            }

            $channels[] = $channel;
        }

        return array_unique($channels);
    }

    public function send(string $importance, array $recipients = [] /* additional recipients */)
    {
        // Set importance of the notification
        $this->setImportance($importance);
        $this->setAdminChannels(false);
        $this->setChannels([]);

        // Determine recipient information
        $recipients[] = ($this->user ? $this->user->getRecipient() : new NoRecipient());
        foreach ($recipients as $recipient) {

            // Set selected channels, if any
            $channels    = $this->getChannels($recipient);
            if (empty($channels)) return $this;
            $this->setChannels($channels);

            // Submit notification
            User::getNotifier()->send($this, $recipient);
        }

        $this->sentAt = new \DateTime("now");
        return $this;
    }

    public function sendBy(array $channels, array $recipients = [] /* additional recipients */)
    {
        // Set importance of the notification
        $this->setImportance(self::IMPORTANCE_DEFAULT);
        $this->setAdminChannels(false);
        $this->setChannels([]);

        // NB: Main recipient is put last, therefore the channel set are matching
        $recipients[] = ($this->user ? $this->user->getRecipient() : new NoRecipient());
        foreach ($recipients as $recipient) {

            // Check if valid recipient instance
            if ( $recipient instanceof NoRecipient) continue;
            if (!$recipient instanceof Recipient  ) continue;

            // Determine channels
            $channels    = array_intersect($channels, $this->getChannels($recipient));
            if (empty($channels)) continue;

            $this->setChannels($channels);

            // Send notification
            User::getNotifier()->send($this, $recipient);
        }

        $this->sentAt = new \DateTime("now");
        return $this;
    }

    public function sendAdmins(string $importance)
    {
        // Set importance of the notification
        $this->setImportance($importance ?? $this->getImportance());
        $this->setAdminChannels(true);

        // Reset channels and keep here them for later use
        $channelsBak = $this->channels;
        $this->setChannels([]);

        // Back up channels and importance variables..
        // to be restored at the end of the method
        $recipients = User::getNotifier()->getAdminRecipients() ?? [new NoRecipient()];
        foreach($recipients as $recipient) {

            // Check if valid recipient instance
            if ($recipient instanceof NoRecipient) continue;
            if (!$recipient instanceof Recipient) continue;

            // Set selected channels, if any
            $channels    = $this->getAdminChannels($recipient);
            if (empty($channels)) return $this;
            $this->setChannels($channels);

            // Send notification
            User::getNotifier()->send($this, $recipient);
        }

        // Put back channels
        $this->channels = $channelsBak;
        return $this;
    }
}

<?php

namespace Base\Twig\Extension;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Service\IconProvider;
use Base\Service\MediaService;
use Base\Service\Model\HtmlizeInterface;
use Base\Service\TranslatorInterface;
use Base\View\Captions;
use Base\View\Dates;
use Base\View\Formats;
use Base\Admin\Config\Crud;
use Base\Routing\AdminUrlGeneratorInterface;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * omnibase's Twig functions and filters. html_attributes, mb_ucfirst, second,
 * join_if_exists, less_than, at and emoji_only are omnibridge/twig's HelperExtension's
 * (registered beside this one, the same results); str_shorten, highlight and
 * datetime stay here, they do more than its copies (str_shorten's
 * SHORTEN_BACK_SHRINK/EXTEND, highlight's excerpt, datetime's patterns lists,
 * intervals and formats), and win over them.
 */
final class FunctionTwigExtension extends AbstractExtension
{
    protected TranslatorInterface $translator;

    protected MediaService $mediaService;

    protected IconProvider $iconProvider;

    protected MimeTypes $mimeTypes;

    protected AssetExtension $assetExtension;

    protected string $projectDir;

    protected AdminUrlGeneratorInterface $adminUrlGenerator;

    protected Environment $twig;

    private readonly Captions $captions;

    private readonly Dates $dates;

    public function __construct(TranslatorInterface $translator, AssetExtension $assetExtension, Environment $twig, AdminUrlGeneratorInterface $adminUrlGenerator, string $projectDir, private readonly ?AdminRouteRegistry $routeRegistry = null)
    {
        $this->translator = $translator;
        $this->assetExtension = $assetExtension;
        $this->projectDir = $projectDir;
        $this->mimeTypes = new MimeTypes();

        $this->twig = $twig;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->captions = new Captions($translator);
        $this->dates = new Dates($translator);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('exit', 'exit'),
            new TwigFunction('count_leaves', 'count_leaves'),

            new TwigFunction('synopsis', 'synopsis'),
            new TwigFunction('title', [$this, 'title'], ['is_safe' => ['all']]),
            new TwigFunction('excerpt', [$this, 'excerpt'], ['is_safe' => ['all']]),
            new TwigFunction('get_class', [$this, 'get_class']),
            new TwigFunction('is_json', 'is_json'),
            new TwigFunction('is_string', 'is_string'),
            new TwigFunction('is_linkable', [$this, 'is_linkable']),
            new TwigFunction('is_countable', [$this, 'is_countable']),
            new TwigFunction('is_callable', [$this, 'is_callable']),
            new TwigFunction('call_user_func_with_defaults', [$this, 'call_user_func_with_defaults']),
            new TwigFunction('call_user_func_if_exists', [$this, 'call_user_func_if_exists'], ['needs_environment' => true]),
            new TwigFunction('method_exists', [$this, 'method_exists']),
            new TwigFunction('static_call', [$this, 'static_call']),
            new TwigFunction('static_property', [$this, 'static_property']),

            new TwigFunction('mailparse', 'mailparse', ['is_safe' => ['all']]),
            new TwigFunction('mailparse_email', [$this, 'mailparseEmail'], ['is_safe' => ['all']]),
            new TwigFunction('mailparse_name', [$this, 'mailparseName'], ['is_safe' => ['all']]),

            new TwigFunction('render_stylesheet', [$this, 'render_stylesheet'], ['is_safe' => ['all']]),
            new TwigFunction('render_javascript', [$this, 'render_javascript'], ['is_safe' => ['all']]),

            new TwigFunction('str_starts_with', fn(string $haystack, string $needle): bool => str_starts_with($haystack, $needle)),
            new TwigFunction('str_ends_with', fn(string $haystack, string $needle): bool => str_starts_with($haystack, $needle)),
            new TwigFunction('empty', fn( mixed $var): bool => empty($var)),
            new TwigFunction('property_accessor', [$this, 'property_accessor']),
            new TwigFunction('cast', 'cast'),

            new TwigFunction('mailto', [$this, 'mailto'], ['is_safe' => ['all']]),

            new TwigFunction('addslashes', 'addslashes'),
            new TwigFunction('email_preview', [$this, 'email'], ['needs_context' => true]),
        ];
    }

    public function getFilters(): array
    {
        return
            [
                new TwigFilter('str_shorten', 'str_shorten'),
                new TwigFilter('intval', 'intval'),
                new TwigFilter('strval', 'strval'),
                new TwigFilter('url_decode', 'urldecode'),
                new TwigFilter('synopsis', 'synopsis'),
                new TwigFilter('closest', 'closest'),
                new TwigFilter('distance', 'distance'),
                new TwigFilter('is_json', 'is_json'),
                new TwigFilter('is_json', 'is_json'),
                new TwigFilter('is_linkable', [$this, 'is_linkable']),
                new TwigFilter('is_countable', [$this, 'is_countable']),
                new TwigFilter('is_callable', [$this, 'is_callable']),
                new TwigFilter('is_uuidv4', 'is_uuidv4'),
                new TwigFilter('basename', 'basename'),
                new TwigFilter('uniq', 'array_unique'),
                new TwigFilter('addslashes', 'addslashes'),
                new TwigFilter('ceil', 'ceil'),
                new TwigFilter('floor', 'floor'),
                new TwigFilter('count_leaves', 'count_leaves'),
                new TwigFilter('color_name', [$this, 'color_name']),

                new TwigFilter('sign', 'sign'),

                new TwigFilter('mailto', [$this, 'mailto'], ['is_safe' => ['all']]),

                new TwigFilter('datetime', [$this, 'datetime'], ['needs_environment' => true]),
                new TwigFilter('countdown', [$this, 'countdown'], ['needs_environment' => true, 'is_safe' => ['all']]),
                new TwigFilter('progress', [$this, 'progress'], ['needs_environment' => true, 'is_safe' => ['all']]),

                new TwigFilter('pickup', [$this, 'pickup']),
                new TwigFilter('preg_split', [$this, 'preg_split']),
                new TwigFilter('nargs', [$this, 'nargs']),
                new TwigFilter('instanceof', [$this, 'instanceof']),
                new TwigFilter('stringify', [$this, 'stringify']),
                new TwigFilter('highlight', [$this, 'highlight']),
                new TwigFilter('array_flatten', [$this, 'array_flatten']),
                new TwigFilter('greater_than', [$this, 'greater_than']),
                new TwigFilter('transforms', [$this, 'transforms'], ['needs_environment' => true]),
                new TwigFilter('pad', [$this, 'pad']),
                new TwigFilter('mb_ucwords', 'mb_ucwords'),
                new TwigFilter('third', 'third'),
                new TwigFilter('fourth', 'fourth'),
                new TwigFilter('fifth', 'fifth'),
                new TwigFilter('empty', 'empty'),

                new TwigFilter('head', 'begin'), // omnibase's head(): null for an empty list, whatever head() Laravel defines
                new TwigFilter('tail', 'tail'),

                new TwigFilter('crudify', [$this, 'crudify'], ['is_safe' => ['all']]),
                new TwigFilter('htmlify', [$this, 'htmlify'], ['is_safe' => ['all']]),

            ];
    }

    /**
     * A link to the entity's edit page in the admin, or nothing when no CRUD
     * manages it. Options: label, icon. The registered controller first (an
     * app's CRUD may sit outside the namespace convention), then the
     * convention.
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function crudify(?object $entity, array $options = []): string
    {
        $controller = $entity ? ($this->routeRegistry?->getControllerForEntity($entity) ?? AbstractCrudController::getCrudControllerFqcn($entity)) : null;
        if (null === $controller) {
            return '';
        }

        return $this->twig->render('@Base/admin-legacy/crudify.html.twig', [
            'label' => $options['label'] ?? null,
            'icon' => $options['icon'] ?? null,
            'path' => $this->adminUrlGenerator->unsetAll()
                ->setController($controller)
                ->setEntityId($entity->getId())
                ->setAction(Crud::PAGE_EDIT)
                ->generateUrl(),
        ]);
    }

    public function mailparseEmail(string|EmailRecipientInterface $recipient): ?string
    {
        return Formats::mailparseEmail($recipient);
    }

    public function mailparseName(string|EmailRecipientInterface $recipient): ?string
    {
        return Formats::mailparseName($recipient);
    }

    public function htmlify(?HtmlizeInterface $object, array $options = [], ...$args): ?string
    {
        if (null === $object) {
            return null;
        }

        $html = $object->__toHtml($options, ...$args);
        if (null !== $html) {
            return $html;
        }

        $className = get_class($object);
        while ($className) {
            $path = str_replace('\\_', '/', camel2snake(str_lstrip($className, ['Proxies\\__CG__\\', 'App\\', 'Base\\'])));
            try {
                return $this->twig->render($path . '.html.twig', ['options' => $options, 'entity' => $object]);
            } catch (\RuntimeException|LoaderError $e) {
            }

            $className = get_parent_class($className);
        }

        return null;
    }

    /**
     * @param array $context
     * @return bool
     */
    public function email(array $context)
    {
        return !array_key_exists('email', $context);
    }

    public function is_linkable(mixed $value): bool
    {
        return Formats::isLinkable($value);
    }

    /**
     * @param mixed $value
     * @param bool $syntax_only
     * @param $callable_name
     * @return bool
     */
    public function is_callable(mixed $value, bool $syntax_only = false, &$callable_name = null): bool
    {
        return is_callable($value, $syntax_only, $callable_name);
    }

    public function nargs(callable $fn): int
    {
        return Formats::nargs($fn);
    }

    /**
     * @param callable $fn
     * @param ...$args
     * @return mixed
     */
    public function call_user_func_with_defaults(callable $fn, ...$args)
    {
        return call_user_func_with_defaults($fn, ...$args);
    }

    /**
     * @param Environment $environment
     * @param string $fn
     * @param array $args
     * @return string
     */
    public function call_user_func_if_exists(Environment $environment, string $fn, array $args)
    {
        if (false === $func = $environment->getFunction($fn)) {
            return '';
        }

        return $func->getCallable()(...array_values($args));
    }

    public function pad(array $array = [], int $length = 0, mixed $value = null): array
    {
        return Formats::pad($array, $length, $value);
    }

    /**
     * @param array $array
     * @param $arrow
     * @return array|mixed
     */
    public function transforms(array $array = [], $arrow = null)
    {
        return Formats::transforms($array, $arrow);
    }

    /**
     * @throws \Exception
     */
    public function static_property($class, $propertyName)
    {
        return Formats::staticProperty($class, $propertyName);
    }

    public function property_accessor(mixed $object, array|string $propertyName, bool $enableMagicCall = false): mixed
    {
        return Formats::propertyAccessor($object, $propertyName, $enableMagicCall);
    }

    /**
     * @param string $hex
     * @return mixed|null
     * @throws \Exception
     */
    public function color_name(string $hex)
    {
        return Formats::colorName($hex); // the nearest CSS colour name
    }

    /**
     * @param $object
     * @return bool
     */
    public function is_countable($object)
    {
        return is_countable($object);
    }

    /**
     * @param $object
     * @param $method
     * @return string|null
     */
    public function get_class($object, $method)
    {
        return class_exists($object) ? get_class($object) : null;
    }

    /**
     * @param $object
     * @param $method
     * @return bool
     */
    public function method_exists($object, $method)
    {
        return Formats::methodExists($object, $method);
    }

    /**
     * @return array|false|string[]
     */
    public function preg_split(string $subject, string $pattern, int $limit = -1, int $flags = 0)
    {
        return Formats::pregSplit($subject, $pattern, $limit, $flags);
    }

    public function instanceof(mixed $object, string $class): bool
    {
        return Formats::instanceOf($object, $class);
    }

    /**
     * @param array|null $array
     * @param string $separator
     * @return string|null
     */
    public function joinIfExists(?array $array, string $separator)
    {
        return \Omnibridge\Twig\HelperExtension::joinIfExists($array, $separator);
    }

    /**
     * @throws \Exception
     */
    public function static_call($class, $method, ...$args)
    {
        return Formats::staticCall($class, $method, ...$args);
    }

    public function render_stylesheet(string $href, array $attributes = [], bool $keepIfNotFound = true): string
    {
        $attributes['rel'] = 'stylesheet';
        $attributes['type'] = 'text/css';

        $href = $this->assetExtension->getAssetUrl($href);
        $isUrl = true === filter_var($this->projectDir . '/public' . $href, FILTER_VALIDATE_URL);
        $isEmpty = !file_exists($this->projectDir . '/public' . $href) || 0 == filesize($this->projectDir . '/public' . $href);
        if (!$isUrl && $isEmpty && !$keepIfNotFound) {
            return '';
        }

        return "<link href='" . $href . "' " . html_attributes($attributes) . '>';
    }

    public function render_javascript(string $src, array $attributes = [], bool $keepIfNotFound = true): string
    {
        $src = $this->assetExtension->getAssetUrl($src);
        $isUrl = true === filter_var($this->projectDir . '/public' . $src, FILTER_VALIDATE_URL);
        $isEmpty = !file_exists($this->projectDir . '/public' . $src) || 0 == filesize($this->projectDir . '/public' . $src);
        if (!$isUrl && $isEmpty && !$keepIfNotFound) {
            return '';
        }

        return "<script src='" . $src . "' " . html_attributes($attributes) . '></script>';
    }

    /**
     * A moment as a pattern says it, lowered (Base\View\Dates, in Twig's time zone).
     *
     * @param Environment $env
     * @param \DateTime|\DateInterval|int|string|null $datetime
     * @param array|string $pattern
     * @return array|string
     */
    public function datetime(Environment $env, \DateTime|\DateInterval|int|string|null $datetime, array|string $pattern = 'YYYY-MM-dd HH:mm:ss', ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', $timezone = null, string $calendar = 'gregorian', ?string $locale = null): array|string
    {
        return $this->dates->datetime($datetime, $pattern, $dateFormat, $timeFormat, $timezone, $calendar, $locale, $env->getExtension(CoreExtension::class)->getTimezone());
    }

    /** Whether a string is a moment in ISO 8601: a date, a time, and a zone or not (Base\View\Dates). */
    public static function isMoment(string $value): bool
    {
        return Dates::isMoment($value);
    }

    /**
     * @param array|null $array
     * @param int $i
     * @return array|null
     */
    public function pickup(?array $array, int $i)
    {
        return Formats::pickup($array, $i);
    }

    public function countdown(Environment $env, \DateTime|\DateInterval|int|string|null $datetime, array $parameters = []): string
    {
        $now = time();
        if ($datetime instanceof \DateTime) {
            $timestamp = $datetime->getTimestamp();
        } elseif ($datetime instanceof \DateInterval) {
            $timestamp = $now + (int)$datetime->format('s');
        } else {
            $timestamp = $datetime;
        }

        return $env->render('@Base/progress/countdown.html.twig', array_merge($parameters, [
            'id' => rand(),
            'datetime' => $datetime,
            'countdown' => $timestamp - $now,
            'timestamp' => $timestamp,
        ]));
    }

    public function progress(Environment $env, \DateTime $start, \DateTime $end, array $parameters = []): string
    {
        return $env->render('@Base/progress/progressbar.html.twig', array_merge($parameters, [
            'id' => rand(),
            'progress-start' => $start->getTimestamp(),
            'progress-end' => $end->getTimestamp(),
        ]));
    }

    public function title(string $name, array $parameters = [], ?string $domain = 'controllers', ?string $locale = null): string
    {
        return $this->captions->title($name, $parameters, $domain, $locale);
    }

    public function excerpt(string $name, array $parameters = [], ?string $domain = 'controllers', ?string $locale = null): string
    {
        return $this->captions->excerpt($name, $parameters, $domain, $locale);
    }

    /**
     * @param $date
     * @param $diff
     * @return bool
     * @throws \Exception
     */
    public function less_than($date, $diff): bool
    {
        return \Omnibridge\Twig\HelperExtension::lessThan($date, $diff);
    }

    /**
     * @throws \Exception
     */
    public function greater_than($date, int $diff): bool
    {
        return Formats::greaterThan($date, $diff);
    }

    public function truncate($string, $maxLength = 30, $replacement = '', $truncAtSpace = false): string
    {
        return Formats::truncate($string, $maxLength, $replacement, (bool) $truncAtSpace);
    }

    /**
     * @param string|null $content
     * @param string|array $pattern
     * @param $gateLength Number of words before and after
     * @return string|null
     */
    public function highlight(?string $content, string|array $pattern, $gateLength = -1)
    {
        return Formats::highlight($content, $pattern, (int) $gateLength);
    }

    public function stringify($value): string
    {
        return $this->captions->stringify($value);
    }

    /**
     * @param string $address
     * @param string $label
     * @return string
     */
    public function mailto(string $address, string $label)
    {
        return Formats::mailto($address, $label);
    }
}

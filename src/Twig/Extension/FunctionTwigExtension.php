<?php

namespace Base\Twig\Extension;

use Base\Database\Type\EnumType;
use Base\Service\Model\Color\Intl\Colors;
use Base\Service\IconProvider;
use Base\Service\MediaService;
use Base\Service\Model\ColorizeInterface;
use Base\Service\Model\HtmlizeInterface;
use Base\Service\Model\LinkableInterface;
use Base\Service\TranslatorInterface;
use Closure;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type;
use Exception;
use ReflectionFunction;
use RuntimeException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Base\Controller\Backend\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFunction;
use function count;
use function is_array;
use function is_bool;
use function is_object;
use function is_string;

final class FunctionTwigExtension extends AbstractExtension
{
    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var MediaService
     */
    protected MediaService $mediaService;

    /**
     * @var IconProvider
     */
    protected IconProvider $iconProvider;

    /**
     * @var IntlExtension
     */
    protected IntlExtension $intlExtension;

    /**
     * @var MimeTypes
     */
    protected MimeTypes $mimeTypes;

    /**
     * @var AssetExtension
     */
    protected AssetExtension $assetExtension;

    /**
     * @var string
     */
    protected string $projectDir;

    /**
     * @var AdminUrlGenerator
     */
    protected AdminUrlGenerator $adminUrlGenerator;

    /**
     * @var Environment
     */
    protected Environment $twig;

    public function __construct(TranslatorInterface $translator, AssetExtension $assetExtension, Environment $twig, AdminUrlGenerator $adminUrlGenerator, string $projectDir)
    {
        $this->translator = $translator;
        $this->assetExtension = $assetExtension;
        $this->projectDir = $projectDir;
        $this->mimeTypes = new MimeTypes();
        $this->intlExtension = new IntlExtension();

        $this->twig = $twig;
        $this->adminUrlGenerator = $adminUrlGenerator;
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
            new TwigFunction('is_linkable', [$this, 'is_linkable']),
            new TwigFunction('is_countable', [$this, 'is_countable']),
            new TwigFunction('is_callable', [$this, 'is_callable']),
            new TwigFunction('call_user_func_with_defaults', [$this, 'call_user_func_with_defaults']),
            new TwigFunction('call_user_func_if_exists', [$this, 'call_user_func_if_exists'], ["needs_environment" => true]),
            new TwigFunction('method_exists', [$this, 'method_exists']),
            new TwigFunction('static_call', [$this, 'static_call']),
            new TwigFunction('static_property', [$this, 'static_property']),

            new TwigFunction('html_attributes', 'html_attributes', ["is_safe" => ['all']]),
            new TwigFunction('render_stylesheet', [$this, 'render_stylesheet'], ["is_safe" => ["all"]]),
            new TwigFunction('render_javascript', [$this, 'render_javascript'], ["is_safe" => ["all"]]),

            new TwigFunction('str_starts_with', "str_starts_with"),
            new TwigFunction('str_ends_with', "str_ends_with"),
            new TwigFunction('empty', "empty"),
            new TwigFunction('property_accessor', [$this, "property_accessor"]),
            new TwigFunction('cast', "cast"),

            new TwigFunction('mailto', [$this, 'mailto'], ["is_safe" => ['all']]),

            new TwigFunction('addslashes', 'addslashes'),
            new TwigFunction('enum', [$this, 'enum']),
            new TwigFunction('email_preview', [$this, 'email'], ['needs_context' => true])
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
                new TwigFilter('color_name', 'color_name'),
                new TwigFilter('is_json', 'is_json'),
                new TwigFilter('is_uuidv4', 'is_uuidv4'),
                new TwigFilter('basename', 'basename'),
                new TwigFilter('uniq', 'array_unique'),
                new TwigFilter('addslashes', 'addslashes'),
                new TwigFilter('at', 'at'),
                new TwigFilter('ceil', 'ceil'),
                new TwigFilter('floor', 'floor'),
                new TwigFilter('count_leaves', 'count_leaves'),

                new TwigFilter('sign', 'sign'),

                new TwigFilter('mailto', [$this, 'mailto'], ["is_safe" => ['all']]),
                new TwigFilter('datetime', [$this, 'datetime'], ['needs_environment' => true]),
                new TwigFilter('countdown', [$this, 'countdown'], ['needs_environment' => true, "is_safe" => ["all"]]),
                new TwigFilter('progress', [$this, 'progress'], ['needs_environment' => true, "is_safe" => ["all"]]),

                new TwigFilter('pickup', [$this, 'pickup']),
                new TwigFilter('preg_split', [$this, 'preg_split']),
                new TwigFilter('nargs', [$this, 'nargs']),
                new TwigFilter('instanceof', [$this, 'instanceof']),
                new TwigFilter('join_if_exists', [$this, 'joinIfExists']),
                new TwigFilter('stringify', [$this, 'stringify']),
                new TwigFilter('highlight', [$this, 'highlight']),
                new TwigFilter('array_flatten', [$this, 'array_flatten']),
                new TwigFilter('less_than', [$this, 'less_than']),
                new TwigFilter('greater_than', [$this, 'greater_than']),
                new TwigFilter('filter', [$this, 'filter'], ['needs_environment' => true]),
                new TwigFilter('transforms', [$this, 'transforms'], ['needs_environment' => true]),
                new TwigFilter('pad', [$this, 'pad']),
                new TwigFilter('mb_ucfirst', 'mb_ucfirst'),
                new TwigFilter('mb_ucwords', 'mb_ucwords'),
                new TwigFilter('second', "second"),
                new TwigFilter('third', "third"),
                new TwigFilter('fourth', "fourth"),
                new TwigFilter('fifth', "fifth"),
                new TwigFilter('empty', "empty"),

                new TwigFilter('colorify', [$this, 'colorify']),
                new TwigFilter('crudify', [$this, 'crudify'], ["is_safe" => ['all']]),
                new TwigFilter('htmlify', [$this, 'htmlify'], ["is_safe" => ['all']])
            ];
    }

    // Used in twig environment
    public function crudify($entity): string
    {
        return $this->twig->render("@Base/easyadmin/crudify.html.twig", [
            "path" => $this->adminUrlGenerator->unsetAll()
                ->setController(AbstractCrudController::getCrudControllerFqcn($entity))
                ->setEntityId($entity->getId())
                ->setAction(Crud::PAGE_EDIT)
                ->includeReferrer()
                ->generateUrl()
        ]);
    }

    public function htmlify(?HtmlizeInterface $object, array $options = [], ...$args): ?string
    {
        if ($object === null) {
            return null;
        }

        $html = $object->__toHtml($options, ...$args);
        if ($html !== null) {
            return $html;
        }

        $className = get_class($object);
        while ($className) {
            $path = str_replace("\\_", "/", camel2snake(str_lstrip($className, ["Proxies\\__CG__\\", "App\\", "Base\\"])));
            try {
                return $this->twig->render($path . ".html.twig", ["options" => $options, "entity" => $object]);
            } catch (RuntimeException|LoaderError $e) {
            }

            $className = get_parent_class($className);
        }

        return null;
    }

    public function email(array $context)
    {
        return !array_key_exists("email", $context);
    }

    public function is_linkable(mixed $value): bool
    {
        return $value instanceof LinkableInterface;
    }

    public function is_callable(mixed $value, bool $syntax_only = false, &$callable_name = null): bool
    {
        return is_callable($value, $syntax_only, $callable_name);
    }

    public function nargs(callable $fn): int
    {
        return (new ReflectionFunction($fn))->getNumberOfParameters();
    }

    public function call_user_func_with_defaults(callable $fn, ...$args)
    {
        return call_user_func_with_defaults($fn, ...$args);
    }

    public function call_user_func_if_exists(Environment $environment, string $fn, array $args)
    {
        if (false === $func = $environment->getFunction($fn)) {
            return '';
        }
        return $func->getCallable()(...array_values($args));
    }

    public function pad(array $array = [], int $length = 0, mixed $value = null): array
    {
        return array_pad($array, $length, $value);
    }

    public function transforms(array $array = [], $arrow = null)
    {
        return $arrow instanceof Closure ? $arrow($array) : $array;
    }

    public function enum(null|string|array $class): null|Type|array
    {
        if (is_array($class)) {
            return array_map(fn($c) => $this->enum($c), $class);
        }

        if (class_exists($class) && is_instanceof($class, EnumType::class)) {
            return new $class();
        }
        return Type::hasType($class) ? Type::getType($class) : null;
    }


    public function colorify(null|string|array|ColorizeInterface $color): null|string|array
    {
        if (!$color) {
            return $color;
        }

        if ($color instanceof ColorizeInterface && $color instanceof EnumType) {
            return $color->__colorize() ?? $color->__colorizeStatic();
        }
        if ($color instanceof ColorizeInterface) {
            $color = $color->__colorize() ?? $color->__colorizeStatic();
        }

        if (is_array($color)) {
            $color = array_filter(array_map(fn($i) => $this->colorify($i), $color));
            if ($color) {
                return array_merge(...$color);
            }
        }

        return null;
    }

    public function filter(Environment $env, $array = [], $arrow = null)
    {
        if ($arrow === null) {
            $arrow = function ($el) {
                return $el !== null && $el !== false && $el !== "";
            };
        }

        return twig_array_filter($env, $array, $arrow);
    }

    public function static_property($class, $propertyName)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        if (!class_exists($class)) {
            throw new Exception("Cannot call static property $propertyName on \"$class\": invalid class");
        }
        if (!property_exists($class, $propertyName)) {
            throw new Exception("Cannot call static property $propertyName on \"$class\": invalid property");
        }

        return $class::$$propertyName;
    }

    public function property_accessor(mixed $object, array|string $propertyName, bool $enableMagicCall = false): mixed
    {
        if ($object == null) {
            return null;
        }

        // Shape property path
        $propertyPath = is_string($propertyName) ? explode(".", $propertyName) : $propertyName;
        if (!$propertyPath) {
            return $object;
        }

        // Special case for array
        if (is_array($object) || $object instanceof Collection) {
            $id = array_unshift($attributes);
            $object = $object[$id] ?? null;
        }

        // Extract head
        $propertyName = first($propertyPath);
        $propertyAccessorBuilder = PropertyAccess::createPropertyAccessorBuilder();
        if ($enableMagicCall) {
            $propertyAccessorBuilder->enableMagicCall();
        }

        $propertyAccessor = $propertyAccessorBuilder->getPropertyAccessor();
        if (!$propertyAccessor->isReadable($object, $propertyName)) {
            return null;
        }

        $object = $propertyAccessor->getValue($object, $propertyName);
        return $this->property_accessor($object, tail($propertyPath)); // Recursive processing
    }

    public function color_name(string $hex)
    {
        $color = hex2rgb($hex);

        $closestNames = array_transforms(
            fn($hex, $name): array => [$name, distance($color, hex2rgb($hex))],
            Colors::getNames()
        );

        asort($closestNames);

        $closestNames = array_keys($closestNames);
        return begin($closestNames);
    }

    public function is_countable($object)
    {
        return is_countable($object);
    }

    public function get_class($object, $method)
    {
        return class_exists($object) ? get_class($object) : null;
    }

    public function method_exists($object, $method)
    {
        return $object && method_exists($object, $method);
    }

    public function preg_split(string $subject, string $pattern, int $limit = -1, int $flags = 0)
    {
        return preg_split($pattern, $subject, $limit, $flags);
    }

    public function instanceof(mixed $object, string $class): bool
    {
        return is_instanceof($object, $class);
    }

    public function joinIfExists(?array $array, string $separator)
    {
        if ($array === null) {
            return null;
        }
        return implode($separator, array_filter($array));
    }

    public function static_call($class, $method, ...$args)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        if (!class_exists($class)) {
            throw new Exception("Cannot call static method $method on \"$class\": invalid class");
        }
        if (!method_exists($class, $method)) {
            throw new Exception("Cannot call static method $method on \"$class\": invalid method");
        }

        return forward_static_call_array([$class, $method], $args);
    }

    public function render_stylesheet(string $href, array $attributes = [], bool $keepIfNotFound = true): string
    {
        $attributes["rel"] = 'stylesheet';
        $attributes["type"] = 'text/css';

        $href = $this->assetExtension->getAssetUrl($href);
        $isUrl = filter_var($this->projectDir . "/public" . $href, FILTER_VALIDATE_URL) === true;
        $isEmpty = !file_exists($this->projectDir . "/public" . $href) || filesize($this->projectDir . "/public" . $href) == 0;
        if (!$isUrl && $isEmpty && !$keepIfNotFound) {
            return "";
        }

        return "<link href='" . $href . "' " . html_attributes($attributes) . ">";
    }

    public function render_javascript(string $src, array $attributes = [], bool $keepIfNotFound = true): string
    {
        $src = $this->assetExtension->getAssetUrl($src);
        $isUrl = filter_var($this->projectDir . "/public" . $src, FILTER_VALIDATE_URL) === true;
        $isEmpty = !file_exists($this->projectDir . "/public" . $src) || filesize($this->projectDir . "/public" . $src) == 0;
        if (!$isUrl && $isEmpty && !$keepIfNotFound) {
            return "";
        }

        return "<script src='" . $src . "' " . html_attributes($attributes) . "></script>";
    }

    public function datetime(Environment $env, DateTime|DateInterval|int|string|null $datetime, array|string $pattern = "YYYY-MM-dd HH:mm:ss", ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', $timezone = null, string $calendar = 'gregorian', string $locale = null): array|string
    {
        if ($locale === null) {
            $locale = $this->translator->getLocale();
        }
        if (is_array($pattern)) {
            $array = [];
            foreach ($pattern as $p) {
                $array[] = $this->datetime($env, $datetime, $p, $dateFormat, $timeFormat, $timezone, $calendar, $locale);
            }

            return $array;
        }

        $now = time();
        if ($datetime == null) {
            return $pattern;
        }
        if ($datetime instanceof DateTime) {
            $datetime = $datetime->getTimestamp();
        }
        if ($datetime instanceof DateInterval) {
            $datetime = $now + (int)$datetime->format("s");
        }
        if (is_string($datetime)) {
            return mb_strtolower($datetime);
        }

        return mb_strtolower($this->intlExtension->formatDateTime($env, $datetime, 'none', $timeFormat, $pattern, $timezone, $calendar, $locale));
    }

    public function pickup(?array $array, int $i)
    {
        if ($array === null) {
            return null;
        }

        $keys = array_rand($array, min(count($array), $i)) ?? [];
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_filter($array, fn($k) => in_array($k, $keys), ARRAY_FILTER_USE_KEY);
    }

    public function countdown(Environment $env, DateTime|DateInterval|int|string|null $datetime, array $parameters = []): string
    {
        $now = time();
        if ($datetime instanceof DateTime) {
            $timestamp = $datetime->getTimestamp();
        } elseif ($datetime instanceof DateInterval) {
            $timestamp = $now + (int)$datetime->format("s");
        } else {
            $timestamp = $datetime;
        }

        return $env->render("@Base/progress/countdown.html.twig", array_merge($parameters, [
            "id" => rand(),
            "datetime" => $datetime,
            "countdown" => $timestamp - $now,
            "timestamp" => $timestamp,
        ]));
    }

    public function progress(Environment $env, DateTime $start, DateTime $end, array $parameters = []): string
    {
        return $env->render("@Base/progress/progressbar.html.twig", array_merge($parameters, [
            "id" => rand(),
            "progress-start" => $start->getTimestamp(),
            "progress-end" => $end->getTimestamp()
        ]));
    }

    public function title(string $name, array $parameters = array(), ?string $domain = "controllers", ?string $locale = null): string
    {
        $ret = $this->translator->trans($name . ".title", $parameters, $domain, $locale);
        return $ret == $name . ".title" ? "@" . $domain . "." . $ret : $ret;
    }

    public function excerpt(string $name, array $parameters = array(), ?string $domain = "controllers", ?string $locale = null): string
    {
        $ret = $this->translator->trans($name . ".excerpt", $parameters, $domain, $locale);
        return $ret == $name . ".excerpt" ? "@" . $domain . "." . $ret : $ret;
    }

    public function less_than($date, $diff): bool
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        if ($date instanceof DateTime) {
            $date = $date->getTimestamp();
        }
        if (is_string($diff)) {
            $diff = new DateTime($diff);
        }
        if ($diff instanceof DateTime) {
            $diff = $diff->getTimestamp() - time();
        }

        $deltaTime = time() - $date;
        return $deltaTime < $diff;
    }

    public function greater_than($date, int $diff): bool
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        if ($date instanceof DateTime) {
            $date = $date->getTimestamp();
        }
        if ($diff instanceof DateTime) {
            $diff = $diff->getTimestamp() - time();
        }

        $deltaTime = time() - $date;
        return $deltaTime > $diff;
    }

    public function truncate($string, $maxLength = 30, $replacement = '', $truncAtSpace = false): string
    {
        $maxLength -= strlen($replacement);
        $length = strlen($string);

        if ($length <= $maxLength) {
            return $string;
        }

        if ($truncAtSpace && ($space_position = strrpos($string, ' ', $maxLength - $length))) {
            $maxLength = $space_position;
        }

        return substr_replace($string, $replacement, $maxLength) . "..";
    }

    public function highlight(?string $content, $pattern, $gate = 5)
    {
        // Empty entry
        if ($content == null) {
            return null;
        }
        if ($pattern == null) {
            return null;
        }

        $highlightContent = "";
        if ($gate < 0) {
            $highlightContent = preg_replace_callback(
                '/([^ ]*)(' . $pattern . ')([^ ]*)/im',
                function ($matches) {
                    if (empty($matches[2])) {
                        return $matches[0];
                    }

                    return '<span class="highlightWord">' .
                        $matches[1] .
                        '<span class="highlightPattern">' . $matches[2] . '</span>' .
                        $matches[3] .
                        '</span>';
                },
                $content
            );
        } elseif (preg_match_all('/((?:[^ ]+ ){0,' . $gate . '})([^ ]*)(' . $pattern . ')([^ ]*)((?: [^ ]+){0,' . $gate . '})/im', $content, $matches)) {
            $priorPatternGate = $matches[1][0] ?? "";
            $priorPattern = $matches[2][0] ?? "";
            $pattern = $matches[3][0] ?? ""; //(Case insensitive)
            $afterPattern = $matches[4][0] ?? "";
            $afterPatternGate = $matches[5][0] ?? "";

            $sentence = $priorPatternGate . $priorPattern . $pattern . $afterPattern . $afterPatternGate;

            if (!str_starts_with($content, $sentence)) {
                $highlightContent .= "[..] ";
            }

            $highlightContent .= "<span class='highlightGate'>";
            $highlightContent .= $priorPatternGate;
            $highlightContent .= "<span class='highlightWord'>";
            $highlightContent .= $priorPattern;
            $highlightContent .= "<span class='highlightPattern'>";
            $highlightContent .= $pattern;
            $highlightContent .= "</span>";
            $highlightContent .= $afterPattern;
            $highlightContent .= "</span>";
            $highlightContent .= $afterPatternGate;
            $highlightContent .= "</span>";

            if (!str_ends_with($content, $sentence)) {
                $highlightContent .= " [..]";
            }
        }

        return (empty($highlightContent) ? null : $highlightContent);
    }

    public function stringify($value): string
    {
        if (null === $value) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return sprintf('Array (%d items)', count($value));
        }

        if (is_object($value)) {
            if (is_stringeable($value)) {
                return (string)$value;
            }

            if (method_exists($value, 'getId')) {
                return sprintf('%s #%s', $this->translator->transEntity(get_class($value)), $value->getId());
            } elseif (method_exists($value, 'getUuid')) {
                return sprintf('%s #%s', $this->translator->transEntity(get_class($value)), $value->getUuid());
            }

            return sprintf('%s #%s', $this->translator->transEntity(get_class($value)), substr(md5(spl_object_hash($value)), 0, 7));
        }

        return '';
    }

    public function mailto(string $address, string $label)
    {
        return "<a href='mailto:" . $address . "'>" . $label . "</a>";
    }
}

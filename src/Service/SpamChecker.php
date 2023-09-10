<?php

namespace Base\Service;

use Base\Enum\SpamApi;
use Base\Enum\SpamScore;
use Base\Service\Model\SpamProtectionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 *
 */
class SpamChecker implements SpamCheckerInterface
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var SettingBag
     */
    protected $settingBag;

    /**
     * @var ParameterBag
     */
    protected $parameterBag;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /** * @var bool */
    protected bool $debug;

    public function __construct(RequestStack $requestStack, SettingBagInterface $settingBag, ParameterBagInterface $parameterBag, TranslatorInterface $translator, HttpClientInterface $client, bool $debug)
    {
        $this->requestStack = $requestStack;
        $this->settingBag = $settingBag;
        $this->parameterBag = $parameterBag;

        $this->translator = $translator;
        $this->client = $client;
        $this->debug = $debug;
    }

    /**
     * @return array|bool|float|int|string|\UnitEnum|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getLang()
    {
        $defaultLocale = $this->parameterBag->get("kernel.default_locale");
        $fallbacks = $this->translator->getFallbackLocales();
        $locale = $this->translator->getLocale();

        return (in_array($locale, $fallbacks) ? $locale : $defaultLocale);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return sprintf(
            "%s://%s%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['SERVER_NAME'],
            $_SERVER['REQUEST_URI']
        );
    }

    /**
     * @param $api
     * @return string|null
     */
    public function getKey($api): ?string
    {
        return match ($api) {
            SpamApi::AKISMET => $this->settingBag->getScalar("api.spam.akismet"),
            default => throw new RuntimeException("Unknown Spam API \"" . $api . "\"."),
        };
    }

    /**
     * @param $api
     * @return string|null
     */
    public function getEndpoint($api): ?string
    {
        $key = $this->getKey($api);
        if (!$key) {
            return null;
        }
        return match ($api) {
            SpamApi::AKISMET => sprintf('https://%s.rest.akismet.com/1.1/comment-check', $key),
            default => throw new RuntimeException("Unknown Spam API \"" . $api . "\"."),
        };
    }

    /**
     * @param SpamProtectionInterface $candidate
     * @param array $context
     * @param $api
     * @return int
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function check(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int
    {
        $score = $this->score($candidate, $context, $api);
        $candidate->getSpamCallback($score);

        return $score;
    }

    /**
     * @param SpamProtectionInterface $candidate
     * @param array $context
     * @param string $api
     * @return int Spam score: 0: not spam, 1: maybe spam, 2: blatant spam
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function score(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int
    {
        $enum = SpamScore::__toInt();
        if (empty($candidate->getSpamText())) {
            return $enum[SpamScore::NO_TEXT];
        }

        $request = $this->requestStack->getCurrentRequest();
        switch ($api) {
            default:
                throw new RuntimeException("Unknown Spam API \"" . $api . "\".");

            case SpamApi::AKISMET :
                $options = [
                    'body' => array_merge($context, [
                        'is_test' => $this->debug,
                        'user_ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('user-agent'),
                        'referrer' => $request->headers->get('referer'),
                        'permalink' => $request->getUri(),

                        'blog' => $this->getUrl(),
                        'blog_charset' => 'UTF-8',
                        'blog_lang' => $this->getLang(),

                        'comment_type' => 'comment',
                        'comment_author' => $candidate->getSpamBlameable(),
                        'comment_author_email' => $candidate->getSpamBlameable()?->getEmail(),
                        'comment_content' => $candidate->getSpamText(),
                        'comment_date_gmt' => $candidate->getSpamDate()
                    ])
                ];

                $endpoint = $this->getEndpoint($api);
                if (!$endpoint) {
                    return $enum[SpamScore::NOT_SPAM];
                }

                $response = null;
                try { 
                    $response = $this->client->request('POST', $endpoint, $options);
                } catch(\Exception $e) {
                    $score = $enum[SpamScore::NOT_SPAM];
                }

                if($response) {

                    $headers = $response->getHeaders();
                    if ('discard' === ($headers['x-akismet-pro-tip'][0] ?? '')) {
                        $score = $enum[SpamScore::BLATANT_SPAM];
                    } else {
                        $content = $response->getContent();
                        if (isset($headers['x-akismet-debug-help'][0])) {
                            throw new RuntimeException(sprintf('Unable to check for spam: %s (%s).', $content, $headers['x-akismet-debug-help'][0]));
                        }

                        $score = ($content === "true" ? $enum[SpamScore::MAYBE_SPAM] : $enum[SpamScore::NOT_SPAM]);
                    }
                }

                return $score;
        }

        return $enum[SpamScore::NOT_SPAM];
    }
}

<?php

namespace Base\Service;

use App\Entity\Thread;
use Base\Entity\User\Notification;
use Base\Enum\SpamApi;
use Base\Enum\SpamScore;
use Base\Model\SpamProtectionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpamChecker
{
    private $client;

    public function __construct(RequestStack $requestStack, HttpClientInterface $client, BaseService $baseService)
    {
        $this->requestStack = $requestStack;
        $this->client       = $client;

        $this->baseService  = $baseService;
        $this->settings = $baseService->getSettings();
    }

    public function getLang()
    {
        $defaultLocale = $this->baseService->getParameterBag("kernel.default_locale");
        $fallbacks = $this->baseService->getTranslator()->getFallbackLocales();
        $locale = $this->baseService->getTranslator()->getLocale();

        return (in_array($locale, $fallbacks) ? $locale : $defaultLocale);
    }

    public function getUrl()
    {
        return sprintf(
            "%s://%s%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['SERVER_NAME'],
            $_SERVER['REQUEST_URI']
        );
    }

    public function getKey($api): string
    {
        switch($api) {

            case SpamApi::AKISMET:
                return $this->settings->getScalar("api.spam.akismet");

            default:
                throw new \RuntimeException("Unknown Spam API \"".$api."\".");
        }
    }

    public function getEndpoint($api): string
    {
        switch($api) {

            case SpamApi::AKISMET:
                return sprintf('https://%s.rest.akismet.com/1.1/comment-check', $this->getKey($api));

            default:
                throw new \RuntimeException("Unknown Spam API \"".$api."\".");
        }
    }

    /**
     * @return int Spam score: 0: not spam, 1: maybe spam, 2: blatant spam
     *
     * @throws \RuntimeException if the call did not work
     */
    public function getScore(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int
    {
        $enum = SpamScore::__toInt();
        if(empty($candidate->getSpamText()))
            return $enum[SpamScore::NO_TEXT];

        $request = $this->requestStack->getCurrentRequest();
        switch($api) {

            default:
                throw new \RuntimeException("Unknown Spam API \"".$api."\".");

            case SpamApi::AKISMET :
                $options = [
                    'body' => array_merge($context, [
                        'is_test' => $this->baseService->isDebug(),
                        'user_ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('user-agent'),
                        'referrer' => $request->headers->get('referer'),
                        'permalink' => $request->getUri(),

                        'blog' => $this->getUrl(),
                        'blog_charset' => 'UTF-8',
                        'blog_lang' => $this->getLang(),

                        'comment_type' => 'comment',
                        'comment_author' => $candidate->getAuthor(),
                        'comment_author_email' => $candidate->getAuthor()->getEmail(),
                        'comment_content' => $candidate->getSpamText(),
                        'comment_date_gmt' => $candidate->getSpamDate()
                    ])
                ];

                $response = $this->client->request('POST', $this->getEndpoint($api), $options);

                $headers = $response->getHeaders();
                if ('discard' === ($headers['x-akismet-pro-tip'][0] ?? '')) $score = $enum[SpamScore::BLATANT_SPAM];
                else {

                    $content = $response->getContent();
                    if (isset($headers['x-akismet-debug-help'][0]))
                        throw new \RuntimeException(sprintf('Unable to check for spam: %s (%s).', $content, $headers['x-akismet-debug-help'][0]));

                    $score = ($content === "true" ? $enum[SpamScore::MAYBE_SPAM] : $enum[SpamScore::NOT_SPAM]);
                }
        }

        return $score;
    }
}

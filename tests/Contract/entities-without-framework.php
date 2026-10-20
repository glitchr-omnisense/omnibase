<?php

/*
 * omnibase's own entities answering through the contracts of the Foundation
 * where there is no framework (plan "omnibase agnostique", C3e): run by
 * docker/bin/bare for tests/BareTest.php, with Composer's autoloader alone -
 * no kernel, no container, no bundle, no BaseService - and bare reports every
 * class and file of the framework loaded on the way (none may be).
 *
 * The getters C3e moved from BaseTrait's services (the router, the
 * authorization checker, the Localizer) onto Base\Contract\Runtime are called
 * on the real classes of the core, with plain PHP implementations of the
 * contracts set by hand, as another framework sets its own at boot. Each
 * would have answered null, or died on a null service, before C3e.
 * Returns what they answered.
 */

use Base\Contract\Access;
use Base\Contract\Link;
use Base\Contract\Locales;
use Base\Contract\Runtime;
use Base\Contract\Urls;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\ShortLink;
use Base\Entity\Thread;
use Base\Entity\ThreadIntl;
use Base\Enum\ThreadState;
use Base\Enum\WorkflowState;
use Base\Exception\MissingContractException;
use Base\Service\Model\LocaleNormalizer;

$answers = [];

// Before anything is set: said aloud, not a silent null.
$semantic = (new ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
$semantic->setRouteName('article');
$semantic->setRouteParameters(['slug' => 'abbey-road']);
try {
    $semantic->__toLink();
} catch (MissingContractException $e) {
    $answers['semantic, no Urls'] = 'MissingContractException';
}

// What a framework sets at boot: each contract by its interface's name.
Runtime::set(Urls::class, new class implements Urls {
    public function path(string $name, array $parameters = []): string
    {
        if ('missing' === $name) {
            throw new InvalidArgumentException('No route "missing".');
        }

        return '/'.$name.($parameters ? '?'.http_build_query($parameters) : '');
    }

    public function url(string $name, array $parameters = []): string
    {
        return 'https://lesringostars.test'.$this->path($name, $parameters);
    }

    public function asset(string $path): string
    {
        return '/'.ltrim($path, '/');
    }
});

// Urls: Semantic's address (__toLink, getUrl) and ShortLink's __toLink.
$answers['semantic, link'] = $semantic->__toLink();
$answers['semantic, url'] = $semantic->__toLink(Link::ABSOLUTE_URL);
$answers['semantic, getUrl'] = $semantic->getUrl();
$answers['semantic, relative path'] = $semantic->__toLink(Link::RELATIVE_PATH);
$semantic->setRouteName('missing');
$answers['semantic, missing route'] = $semantic->__toLink();

$short = (new ReflectionClass(ShortLink::class))->newInstanceWithoutConstructor();
$answers['short link, no slug'] = $short->__toLink();
$short->setSlug('x7Kq2');
$answers['short link, link'] = $short->__toLink();
$answers['short link, url'] = $short->__toLink(['utm' => 'mail'], Link::ABSOLUTE_URL);
try {
    $short->__toLink([], Link::NETWORK_PATH);
} catch (InvalidArgumentException $e) {
    $answers['short link, network path'] = 'InvalidArgumentException';
}
$answers['link'] = Link::to('blog_post', ['slug' => 'help']);

// Access: Thread::isVisible() (User::isGranted() too, but User loads the framework: Security's
// UserInterface, Scheb's - EntityGettersTest compares it under Symfony).
$access = new class implements Access {
    public array $roles = [];

    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return in_array($attribute, $this->roles, true);
    }

    public function getUser(): ?object
    {
        return null;
    }
};
Runtime::set(Access::class, $access);
$threads = [];
foreach (['published' => ThreadState::PUBLISH, 'draft' => ThreadState::DRAFT, 'secret' => ThreadState::SECRET] as $label => $state) {
    $threads[$label] = (new ReflectionClass(Thread::class))->newInstanceWithoutConstructor();
    $threads[$label]->setState($state);
    $threads[$label]->setWorkflow(WorkflowState::APPROVED);
}
foreach ($threads as $label => $thread) {
    $answers['thread '.$label.', nobody'] = $thread->isVisible();
}
$access->roles = ['ROLE_ADMIN'];
foreach ($threads as $label => $thread) {
    $answers['thread '.$label.', an administrator'] = $thread->isVisible();
}

// Locales: a translatable entity's translations (TranslatableTrait::translate(), its __call
// getters and setters, TranslationTrait's locale), the page's language from the contract.
$answers['thread, no Locales'] = (new ReflectionClass(Thread::class))->newInstanceWithoutConstructor()->translate();
$locales = new class implements Locales {
    public string $current = 'fr-FR';

    public function getLocale(): string
    {
        return $this->current;
    }

    public function getDefaultLocale(): string
    {
        return 'fr-FR';
    }

    public function getAvailableLocales(): array
    {
        return ['fr-FR', 'en-GB'];
    }
};
Runtime::set(Locales::class, $locales);
$answers['normalized'] = array_map(LocaleNormalizer::here(...), ['fr' => 'fr', 'en' => 'en', 'en_US' => 'en_US', 'ja' => 'ja', 'xx' => 'xx']);

$article = new Thread(null, null, 'Abbey Road (fr)');
$article->translate('en')->setTitle('Abbey Road (en)');
$locales->current = 'en-GB';
$answers['title en'] = $article->getTitle();
$locales->current = 'fr-FR';
$answers['title fr'] = $article->getTitle();
$locales->current = 'en-US';
$answers['title en-US, the same language elsewhere'] = $article->getTitle();
$locales->current = 'ja-JP';
$answers['title ja, the default'] = $article->getTitle();
$answers['translation en'] = $article->translate('en')->getTitle();
$answers['translations'] = array_keys($article->getTranslations()->toArray());
$answers['default'] = $article->translate(-1)->getLocale();
$intl = new ThreadIntl();
$answers['intl locale'] = $intl->setLocale('en')->getLocale();
$short = new ShortLink('https://lesringostars.test/articles/abbey-road', 'Abbey Road');
$answers['short link, label'] = (string) $short;

Runtime::reset();

return $answers;

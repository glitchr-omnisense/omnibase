<?php

/*
 * omnibase's own entities answering through the contracts of the Fondation
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

use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Contract\Urls;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\ShortLink;
use Base\Exception\MissingContractException;

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

Runtime::reset();

return $answers;

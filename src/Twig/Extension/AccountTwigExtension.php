<?php

namespace Base\Twig\Extension;

use Base\Bridge\Symfony\AccountScreens;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * account_profile(user): a member's profile as its view model
 * (Base\Screen\Account\ProfilePage), as the visitor asking sees it - what a
 * host page gives the core's profile template when it renders it with the
 * user alone (lesringostars' /profile/of/{username}:
 * `@Base/client/user/profile_show.html.twig` with `{user}`), so that the
 * template reads its screen whoever renders it (plan "omnibase agnostique",
 * C5). Answered by the AccountScreens runtime.
 */
final class AccountTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('account_profile', [AccountScreens::class, 'profile'])];
    }
}

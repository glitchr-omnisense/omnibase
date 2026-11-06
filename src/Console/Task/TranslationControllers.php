<?php

namespace Base\Console\Task;

use Base\Console\Report;

/**
 * translation:controllers (plan "omnibase agnostic", step F5e, slice 3): the routes served by an App\ or a Base\
 * controller and, for each language, the key of their page's title, "@controllers.<route>.<suffix>", with its
 * translation or "no translation found". The host gives its routes.
 */
final class TranslationControllers extends TranslationTask
{
    public function run(TranslationControllersInput $input, ?\Closure $sink = null): Report
    {
        $report = self::report($sink);
        $controllers = array_filter($input->routes, static fn (string $controller): bool => str_starts_with($controller, 'App') || str_starts_with($controller, 'Base'));

        $maxLength = 0;
        if (!$input->controller) {
            foreach ($controllers as $controller) {
                $maxLength = max(\strlen($controller), $maxLength);
            }
        }

        [$locale, $availableLocales] = $this->languages($input->locale);

        if ($controllers) {
            $report->line('Controller list:');
        }
        foreach ($controllers as $route => $controller) {
            if (!str_starts_with($controller, $input->controller)) {
                continue;
            }

            $space = '';
            $trans = '';
            foreach ($availableLocales as $currentLocale) {
                if (null !== $locale && $locale != $currentLocale) {
                    continue;
                }
                $prefix = null === $locale ? "\n\t - " : '';
                $space = null === $locale ? '' : str_repeat(' ', max($maxLength - \strlen($controller), 0));

                $trans .= $this->key('@controllers.'.$route.'.'.$input->suffix, $prefix."@controllers[$currentLocale].<ln>".$route.'.'.$input->suffix.'</ln>', $currentLocale);
            }

            $report->line(' * <info>'.trim($controller).'</info> '.$space.": $trans");
        }

        return $report;
    }
}

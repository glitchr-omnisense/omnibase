<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\LocalizerInterface;
use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Locales;

/**
 * translation:settings (plan "omnibase agnostic", step F5e, slice 3): the settings under a path and their value in
 * each language (a setting of a single language marked so), or, raw, each one's label and help per language.
 */
final class TranslationSettings extends TranslationTask
{
    public function __construct(TranslatorInterface $translator, LocalizerInterface $localizer, Locales $locales, private readonly SettingBagInterface $settingBag)
    {
        parent::__construct($translator, $localizer, $locales);
    }

    /** @throws \Exception no setting under the path, or a language the site has not got */
    public function run(TranslationSettingsInput $input, ?\Closure $sink = null): Report
    {
        $report = self::report($sink);
        $path = $input->path;
        [$locale, $availableLocales] = $this->languages($input->locale);

        $rawSettings = array_transforms(
            fn ($k, $v): array => $path ? [$k ? $path.'.'.$k : $path, $v] : [$k, $v],
            $this->settingBag->denormalize($this->settingBag->getRaw($path))
        );
        if (!$rawSettings) {
            throw new \Exception("No settings found for \"$path\"");
        }

        $settings = array_map_recursive(fn ($s) => array_transforms(fn ($i, $k): array => [$k, null], $s->getTranslations()->getKeys()), $rawSettings);
        foreach ($settings as $path => $_) {
            foreach ($_ as $currentLocale => $array) {
                if (null !== $locale && $locale != $currentLocale) {
                    continue;
                }
                $settings[$path][$currentLocale] = $rawSettings[$path]->translate($currentLocale)->getValue();
            }
        }

        $additional = static fn (array $setting): int => \strlen($input->raw ? '.label' : (1 == \count($setting) ? '[single,]' : ''));
        $maxLength = 0;
        foreach ($settings as $path => $setting) {
            $maxLength = max(\strlen($path ?? 0) + $additional($setting) + 1, $maxLength);
        }

        if ($settings) {
            $report->line('Setting list: '.$path);
        }

        foreach ($settings as $path => $setting) {
            $space = str_repeat(' ', max($maxLength - \strlen($path) - $additional($setting), 0));
            $singleLocale = 1 == \count($setting);
            if ($input->raw) {
                $rawSetting = $rawSettings[$path];
                foreach ($availableLocales as $currentLocale) {
                    $value = $rawSetting->translate($currentLocale)->getLabel() ?? '<red>* no entry found *</red>';
                    $report->line(' * <info>'.trim($path).'.label'.$space."<magenta>[$currentLocale]</magenta></info> : $value");
                }
                foreach ($availableLocales as $currentLocale) {
                    $value = $rawSetting->translate($currentLocale)->getHelp() ?? '<red>* no entry found *</red>';
                    $report->line(' * <info>'.trim($path).'.help'.$space." <magenta>[$currentLocale]</magenta></info> : $value");
                }
                $report->line('');
                continue;
            }

            foreach ($availableLocales as $currentLocale) {
                if ($singleLocale && !\array_key_exists($currentLocale, $setting)) {
                    continue;
                }

                $value = $setting[$currentLocale] ?? '<red>* no entry found *</red>';
                if (\is_array($value)) {
                    // a list of lists (an array setting's rows) shown as JSON, where implode() said "Array"
                    $value = "\n   {\n\t".implode(",\n\t", array_map(static fn ($v) => \is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $v, $value))."\n   }";
                }
                if (!is_stringeable($value)) {
                    $value = \get_class($value).'([...])';
                }

                $report->line(' * <info>'.trim($path).$space.'<magenta>['.($singleLocale ? 'single,' : '')."$currentLocale]</magenta></info> : <warning>$value</warning>");
            }
        }

        return $report;
    }
}

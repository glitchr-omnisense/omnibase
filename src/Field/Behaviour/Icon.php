<?php

namespace Base\Field\Behaviour;

use Base\Service\Icons;
use Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter;
use Base\Service\ParameterBagInterface;
use Omnibridge\Runtime;

/**
 * Kind::ICON: a select2 of the icon provider's icons, always searched
 * remotely (ux_autocomplete_icons, 200 a page, the provider's adapter: the
 * parameter base.icon_provider.default_adapter, Font Awesome's by default).
 * An icon chosen is shown as IconType shows it: itself, named by its
 * adapter's list (fa-solid fa-icons is "Icons").
 */
final class Icon extends Select
{
    protected function defaults(): array
    {
        return ['class' => null, 'adapter' => null, 'autocomplete' => true, 'autocomplete_pagesize' => 200, 'autocomplete_endpoint' => null, 'autocomplete_endpoint_parameters' => null] + parent::defaults();
    }

    public function options(array $given): array
    {
        $options = parent::options($given);
        $options['adapter'] ??= self::defaultAdapter();
        $options['autocomplete_endpoint'] ??= 'ux_autocomplete_icons';
        $options['autocomplete_endpoint_parameters'] ??= array_filter([
            'provider' => \is_string($options['adapter']) && method_exists($options['adapter'], 'getName') ? $options['adapter']::getName() : null,
            'pageSize' => $options['autocomplete_pagesize'],
        ], static fn (mixed $value): bool => null !== $value);

        return $options;
    }

    /** IconType::getIcon() and getText(): the icon itself where an adapter knows it, its name in that adapter's list. */
    public function entry(array $entry, array $options): array
    {
        $id = \is_string($entry['id'] ?? null) ? $entry['id'] : null;
        try {
            $icons = Runtime::find(Icons::class);
            $adapter = null !== $id && $icons instanceof Icons ? $icons->adapter($id) : null;
            $choices = null !== $adapter ? (array) $adapter->getChoices() : [];
        } catch (\Throwable) {
            $adapter = null; // a host whose icons' metadata cannot be read: the value as it is
        }
        if (null === $adapter) {
            return $entry;
        }
        $entry['icon'] = $id;
        $path = array_search_recursive($id, $choices);
        if ($path) {
            $format = null === $options['capitalize'] ? FORMAT_IDENTITY : ($options['capitalize'] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE);
            $entry['text'] = castcase((string) $path[\count($path) - 1], $format);
        }

        return $entry;
    }

    /** The adapter IconType defaults to (base.icon_provider.default_adapter). */
    private static function defaultAdapter(): string
    {
        $parameters = Runtime::find(ParameterBagInterface::class);
        try {
            $adapter = $parameters instanceof ParameterBagInterface ? $parameters->get('base.icon_provider.default_adapter') : null;
        } catch (\Throwable) {
            $adapter = null; // a host whose parameters do not know it
        }

        return \is_string($adapter) && '' !== $adapter ? $adapter : FontAwesomeAdapter::class;
    }
}

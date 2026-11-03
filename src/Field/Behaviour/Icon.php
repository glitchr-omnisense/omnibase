<?php

namespace Base\Field\Behaviour;

/**
 * Kind::ICON: a select2 of the icon provider's icons, always searched
 * remotely (ux_autocomplete_icons, 200 a page, the provider's adapter).
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
        $options['autocomplete_endpoint'] ??= 'ux_autocomplete_icons';
        $options['autocomplete_endpoint_parameters'] ??= array_filter([
            'provider' => \is_string($options['adapter']) && method_exists($options['adapter'], 'getName') ? $options['adapter']::getName() : null,
            'pageSize' => $options['autocomplete_pagesize'],
        ], static fn (mixed $value): bool => null !== $value);

        return $options;
    }
}

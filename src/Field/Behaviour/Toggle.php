<?php

namespace Base\Field\Behaviour;

/**
 * Kind::BOOLEAN: a switch, asking for a confirmation when it is ticked or
 * unticked (both by default), possibly saved at once through a toggle route
 * (`toogle_url`, the option's historical spelling).
 */
final class Toggle extends AbstractBehaviour
{
    protected function defaults(): array
    {
        return ['confirmation[onCheck]' => true, 'confirmation[onUncheck]' => true, 'toogle_url' => null, 'switch' => true, 'inline' => false, 'required' => false];
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        return [
            'switch' => $options['switch'],
            'confirmation_check' => $options['confirmation[onCheck]'],
            'confirmation_uncheck' => $options['confirmation[onUncheck]'],
            'toogle_url' => $options['toogle_url'],
            'inline' => $options['inline'],
        ];
    }

    protected function controller(): ?string
    {
        return 'boolean';
    }
}

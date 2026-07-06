<?php

namespace Base\Service;

/**
 * Opaque per-locale value holder used inside a compiled SettingBag snapshot
 * tree, standing in for the live Setting entity that same tree position holds
 * in SettingBag::normalize()'s entity-tree shape. Kept as an object (not a
 * plain array) so array_map_recursive() treats it as a leaf and does not
 * recurse into the per-locale map itself.
 */
final class SettingSnapshotValue
{
    /**
     * @param array<string, mixed> $values locale => RAW translated Setting
     *   value (SettingIntl::getValueRaw() — Uploader public-URL resolution is
     *   deliberately NOT applied here; it happens at read time in
     *   SettingBag::get() so the snapshot stays media-storage-independent)
     */
    public function __construct(public readonly array $values)
    {
    }
}

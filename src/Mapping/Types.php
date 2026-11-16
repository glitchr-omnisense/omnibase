<?php

namespace Base\Mapping;

/**
 * The column types a #[Column(type: ...)] names, as string constants: the portable types by the names and values
 * of doctrine/dbal 4's Doctrine\DBAL\Types\Types (Types::DATE_IMMUTABLE is 'date_immutable'), then omnibase's own
 * types (the UTC dates, the serialized array, the passkeys' values) - the same strings, so an entity names no
 * persistence and both engines read the same column type. The core's tests/Mapping/VocabularyTest compares them
 * with doctrine/dbal's when it is installed.
 */
final class Types
{
    public const ASCII_STRING = 'ascii_string';
    public const BIGINT = 'bigint';
    public const BINARY = 'binary';
    public const BLOB = 'blob';
    public const BOOLEAN = 'boolean';
    public const DATE_IMMUTABLE = 'date_immutable';
    public const DATEINTERVAL = 'dateinterval';
    public const DATETIME_IMMUTABLE = 'datetime_immutable';
    public const DATETIME_UTC_IMMUTABLE = 'datetime_utc_immutable';
    public const DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
    public const DECIMAL = 'decimal';
    public const NUMBER = 'number';
    public const FLOAT = 'float';
    public const ENUM = 'enum';
    public const GUID = 'guid';
    public const INTEGER = 'integer';
    public const JSON = 'json';
    public const JSON_OBJECT = 'json_object';
    public const JSONB = 'jsonb';
    public const JSONB_OBJECT = 'jsonb_object';
    public const SMALLFLOAT = 'smallfloat';
    public const SMALLINT = 'smallint';
    public const STRING = 'string';
    public const TEXT = 'text';
    public const TIME_IMMUTABLE = 'time_immutable';
    public const DATE_MUTABLE = 'date';
    public const DATETIME_MUTABLE = 'datetime';
    public const DATETIME_UTC_MUTABLE = 'datetime_utc';
    public const DATETIMETZ_MUTABLE = 'datetimetz';
    public const SIMPLE_ARRAY = 'simple_array';
    public const TIME_MUTABLE = 'time';

    // omnibase's own column types (registered by omnibridge/doctrine as Base\Database\Type\*, cast by omnibridge/eloquent).
    public const ARRAY = 'array';
    public const UTC_DATETIME = 'utc_datetime';
    public const UTC_DATETIME_IMMUTABLE = 'utc_datetime_immutable';
    public const PASSKEY_BASE64 = 'passkey_base64';
    public const PASSKEY_AAGUID = 'passkey_aaguid';
    public const PASSKEY_TRUST_PATH = 'passkey_trust_path';

    /** The DBAL names: the constants above that doctrine/dbal's Types declares too. */
    public const PORTABLE = ['ASCII_STRING', 'BIGINT', 'BINARY', 'BLOB', 'BOOLEAN', 'DATE_IMMUTABLE', 'DATEINTERVAL', 'DATETIME_IMMUTABLE', 'DATETIME_UTC_IMMUTABLE', 'DATETIMETZ_IMMUTABLE', 'DECIMAL', 'NUMBER', 'FLOAT', 'ENUM', 'GUID', 'INTEGER', 'JSON', 'JSON_OBJECT', 'JSONB', 'JSONB_OBJECT', 'SMALLFLOAT', 'SMALLINT', 'STRING', 'TEXT', 'TIME_IMMUTABLE', 'DATE_MUTABLE', 'DATETIME_MUTABLE', 'DATETIME_UTC_MUTABLE', 'DATETIMETZ_MUTABLE', 'SIMPLE_ARRAY', 'TIME_MUTABLE'];

    private function __construct()
    {
    }
}

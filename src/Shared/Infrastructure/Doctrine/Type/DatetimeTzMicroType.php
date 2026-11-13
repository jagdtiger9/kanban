<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * timestamptz with microsecond precision. The stock DBAL types truncate to whole
 * seconds on PostgreSQL (format strings lack 'u'), which breaks ordering of
 * same-second events. The SQL declaration is inherited (TIMESTAMP(0) WITH TIME
 * ZONE) so the comparator sees no drift; the real DDL comes from columnDefinition.
 */
final class DatetimeTzMicroType extends DateTimeTzImmutableType
{
    public function getName(): string
    {
        return 'datetimetz_micro';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s.uP');
        }

        throw InvalidType::new($value, static::class, ['null', \DateTimeImmutable::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            // PG timestamptz text output carries the offset and optional
            // microseconds; the constructor parses all of its forms natively.
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw InvalidFormat::new($value, static::class, 'ISO-8601 with offset', $e);
        }
    }
}

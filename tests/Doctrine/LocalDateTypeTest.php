<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra\Test\Unit\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Gammadia\DateTimeExtra\Doctrine\LocalDateType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class LocalDateTypeTest extends TestCase
{
    private LocalDateType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = (new ReflectionClass(LocalDateType::class))->newInstanceWithoutConstructor();
        $this->platform = new MySqlPlatform();
    }

    public function testGetSQLDeclaration(): void
    {
        self::assertSame('DATE', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testConvertToPHPValue(): void
    {
        $localDate = $this->type->convertToPHPValue('2019-02-02', $this->platform);

        self::assertNotNull($localDate);
        self::assertSame('2019-02-02', (string) $localDate);
    }

    public function testGetName(): void
    {
        self::assertSame('local_date', $this->type->getName());
    }
}

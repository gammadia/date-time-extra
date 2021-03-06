<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra\Test\Unit\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Gammadia\DateTimeExtra\Doctrine\InstantType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class InstantTypeTest extends TestCase
{
    private InstantType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = (new ReflectionClass(InstantType::class))->newInstanceWithoutConstructor();
        $this->platform = new MySqlPlatform();
    }

    public function testGetSQLDeclaration(): void
    {
        self::assertSame('DATETIME', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testGetName(): void
    {
        self::assertSame('instant', $this->type->getName());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Mapping;

use Backendbase\Shared\Helpers\PathFinder;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

use function array_intersect;
use function array_map;

final class EntityFieldOwnershipTest extends TestCase
{
    public function testEveryEntityDeclaresItsMappedFieldsWithoutAParentClass(): void
    {
        require 'config/doctrine-types.php';
        $configuration = ORMSetup::createAttributeMetadataConfiguration(PathFinder::doctrineEntityPaths(), true);
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $manager    = new EntityManager($connection, $configuration);
        $factory    = $manager->getMetadataFactory();
        $metadata   = $factory->getAllMetadata();
        self::assertNotEmpty($metadata);

        foreach ($metadata as $entityMetadata) {
            $this->assertFieldsBelongToEntity($entityMetadata);
        }
    }

    /** @param ClassMetadata<object> $metadata */
    private function assertFieldsBelongToEntity(ClassMetadata $metadata): void
    {
        $reflection = $metadata->getReflectionClass();
        $className  = $reflection->getName();
        self::assertFalse($reflection->getParentClass(), $className);
        self::assertFalse($metadata->isMappedSuperclass, $className);
        $fieldNames = $metadata->getFieldNames();

        foreach ($fieldNames as $fieldName) {
            $this->assertFieldIsDeclaredByEntity($reflection, $fieldName, $className);
        }

        foreach ($reflection->getTraits() as $trait) {
            $this->assertTraitHasNoMappedFields($trait, $fieldNames);
        }
    }

    /** @param ReflectionClass<covariant object> $reflection */
    private function assertFieldIsDeclaredByEntity(ReflectionClass $reflection, string $fieldName, string $className): void
    {
        $property       = $reflection->getProperty($fieldName);
        $declaringClass = $property->getDeclaringClass();

        self::assertSame($className, $declaringClass->getName(), $className . '::$' . $fieldName);
    }

    /**
     * @param ReflectionClass<covariant object> $trait
     * @param array<int, string>                $fieldNames
     */
    private function assertTraitHasNoMappedFields(ReflectionClass $trait, array $fieldNames): void
    {
        $properties    = $trait->getProperties();
        $propertyNames = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $properties);

        self::assertSame([], array_intersect($fieldNames, $propertyNames), $trait->getName());
    }
}

<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Type;
use Dunglas\DoctrineJsonOdm\Serializer;
use Dunglas\DoctrineJsonOdm\Type\JsonDocumentType;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

if (! Type::hasType('uuid')) {
    Type::addType('uuid', UuidType::class);
}

if (! Type::hasType('uuid_binary')) {
    Type::addType('uuid_binary', UuidBinaryType::class);
}

if (! Type::hasType('json_document')) {
    Type::addType('json_document', JsonDocumentType::class);
    $jsonDocumentType = Type::getType('json_document');
    if (! $jsonDocumentType instanceof JsonDocumentType) {
        throw new UnexpectedValueException('The json_document Doctrine type is invalid.');
    }

    $jsonDocumentType->setSerializer(
        new Serializer([new BackedEnumNormalizer(), new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer()], [new JsonEncoder()]),
    );
}

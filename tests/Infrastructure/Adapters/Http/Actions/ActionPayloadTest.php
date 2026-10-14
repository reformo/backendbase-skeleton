<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Actions;

use Backendbase\Infrastructure\Adapters\Http\Actions\ActionError;
use Backendbase\Infrastructure\Adapters\Http\Actions\ActionPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActionPayloadTest extends TestCase
{
    #[Test]
    public function itSerializesDataAndErrorPayloads(): void
    {
        $dataPayload = new ActionPayload(201, ['resource' => ['id' => 'resource-id']]);
        self::assertSame(201, $dataPayload->getStatusCode());
        self::assertSame(['resource' => ['id' => 'resource-id']], $dataPayload->getData());
        self::assertNull($dataPayload->getError());
        self::assertSame([
            'resource' => ['id' => 'resource-id'],
        ], $dataPayload->jsonSerialize());

        $error        = new ActionError(400, 'Invalid', 'invalid', 'about:blank', 'Bad input');
        $errorPayload = new ActionPayload(400, null, $error);
        self::assertSame($error, $errorPayload->getError());
        self::assertSame($error->jsonSerialize(), $errorPayload->jsonSerialize());
        self::assertSame([], (new ActionPayload(204))->jsonSerialize());
        $problem = $errorPayload->jsonSerialize();
        self::assertSame(400, $problem['status']);
    }

    #[Test]
    public function itPreservesEmptyAndObjectData(): void
    {
        self::assertSame(['users' => []], (new ActionPayload(200, ['users' => []]))->jsonSerialize());
        $data = (object) ['user' => (object) ['accountId' => 'account-id']];
        self::assertSame($data, (new ActionPayload(200, $data))->jsonSerialize());
    }

    #[Test]
    public function itMutatesAndSerializesActionErrors(): void
    {
        $error = new ActionError(
            400,
            'Invalid input',
            'invalid-input',
            'about:blank',
            'Original detail',
            ['status' => 422, 'field' => 'email'],
        );
        self::assertSame(400, $error->status());
        self::assertSame('Invalid input', $error->title());
        self::assertSame('invalid-input', $error->getCode());
        self::assertSame('about:blank', $error->getType());
        self::assertSame('Original detail', $error->getDescription());

        $error->setStatus(409);
        $error->setTitle('Conflict');
        $error->setCode('conflict');
        self::assertSame($error, $error->setType('problem/conflict'));
        self::assertSame($error, $error->setDescription('Changed detail'));
        self::assertSame($error, $error->setAdditionalData(['resource' => 'example']));
        self::assertSame([
            'type' => 'problem/conflict',
            'code' => 'conflict',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'Changed detail',
            'resource' => 'example',
        ], $error->jsonSerialize());

        $error->setAdditionalData(null);
        self::assertArrayNotHasKey('resource', $error->jsonSerialize());
    }
}

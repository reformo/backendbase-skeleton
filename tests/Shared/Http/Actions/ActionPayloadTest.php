<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Actions;

use Backendbase\Shared\Http\Actions\ActionError;
use Backendbase\Shared\Http\Actions\ActionPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActionPayloadTest extends TestCase
{
    #[Test]
    public function itSerializesDataAndErrorPayloads(): void
    {
        $dataPayload = new ActionPayload(201, ['id' => 'resource-id']);
        self::assertSame(201, $dataPayload->getStatusCode());
        self::assertSame(['id' => 'resource-id'], $dataPayload->getData());
        self::assertNull($dataPayload->getError());
        self::assertSame([
            'statusCode' => 201,
            'data' => ['id' => 'resource-id'],
        ], $dataPayload->jsonSerialize());

        $error        = new ActionError(400, 'Invalid', 'invalid', 'about:blank', 'Bad input');
        $errorPayload = new ActionPayload(400, null, $error);
        self::assertSame($error, $errorPayload->getError());
        self::assertSame($error->jsonSerialize(), $errorPayload->jsonSerialize());
        self::assertSame(['statusCode' => 204], (new ActionPayload(204))->jsonSerialize());
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
        self::assertSame(422, $error->status());
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
            'status' => 422,
            'detail' => 'Changed detail',
            'resource' => 'example',
        ], $error->jsonSerialize());

        $error->setAdditionalData(null);
        self::assertArrayNotHasKey('resource', $error->jsonSerialize());
    }
}

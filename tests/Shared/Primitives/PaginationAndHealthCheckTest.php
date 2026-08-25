<?php

declare(strict_types=1);

namespace Tests\Shared\Primitives;

use Backendbase\Shared\Primitives\HealthCheckData;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function getenv;
use function mkdir;
use function putenv;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PaginationAndHealthCheckTest extends TestCase
{
    #[Test]
    public function itCalculatesAndClampsPaginationData(): void
    {
        $pagination = new Pagination(10, -1);
        self::assertSame(1, $pagination->page());
        self::assertSame(10, $pagination->pageSize());
        self::assertSame(0, $pagination->getOffset());

        $pagination->setTotal(25);
        self::assertSame(25, $pagination->total());
        self::assertSame(3, $pagination->totalPages());
        self::assertSame(1, $pagination->getCurrentPage());
        self::assertSame([
            'pageSize' => 10,
            'page' => 1,
            'total' => 25,
        ], $pagination->toArray());
        self::assertSame($pagination->toArray(), $pagination->jsonSerialize());

        $lastPage = new Pagination(10, 9);
        $lastPage->setTotal(25);
        self::assertSame(3, $lastPage->page());
        self::assertSame(20, $lastPage->getOffset());
    }

    #[Test]
    public function itExposesMutableHealthCheckData(): void
    {
        $originalPath = getenv('PATH');
        self::assertIsString($originalPath);
        $binaryDirectory = sys_get_temp_dir() . '/backendbase-health-' . uniqid();
        $gitBinary       = $binaryDirectory . '/git';
        mkdir($binaryDirectory);
        file_put_contents($gitBinary, "#!/bin/sh\nprintf 'test-build\\n'\n");
        chmod($gitBinary, 0755);
        putenv('PATH=' . $binaryDirectory . ':' . $originalPath);

        try {
            $data = new HealthCheckData();
            self::assertSame('test-build', $data->buildId());
            self::assertSame($data, $data->setBuildId('new-build'));
            self::assertSame($data, $data->setStatus(503));
            self::assertSame($data, $data->setStatusString('Unavailable'));
            self::assertSame('new-build', $data->buildId());
            self::assertSame(503, $data->status());
            self::assertSame('Unavailable', $data->statusString());
            self::assertSame([
                'buildId' => 'new-build',
                'status' => 503,
                'statusString' => 'Unavailable',
            ], $data->jsonSerialize());
        } finally {
            putenv('PATH=' . $originalPath);
            unlink($gitBinary);
            rmdir($binaryDirectory);
        }
    }
}

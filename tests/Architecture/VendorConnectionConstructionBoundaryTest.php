<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\PhpConstructionScanner;

use function array_merge;
use function dirname;
use function in_array;
use function sort;
use function str_contains;

final class VendorConnectionConstructionBoundaryTest extends TestCase
{
    private const array APPROVED_LOCATIONS = [
        'new Aws\S3\S3Client' => ['config/dependencies/aws.php'],
        'new Aws\Sns\SnsClient' => ['config/dependencies/aws.php'],
        'new Aws\Sqs\SqsClient' => ['config/dependencies/aws.php'],
        'new Doctrine\ORM\EntityManager' => ['config/dependencies/doctrine.php'],
        'new Redis' => ['config/dependencies/redis.php'],
        'new Redislabs\Module\RedisJson\RedisJson' => ['config/dependencies/redis.php'],
        'new Redislabs\RedisClient\Redis' => ['config/dependencies/redis.php'],
        'Doctrine\DBAL\DriverManager::getConnection' => ['config/dependencies/doctrine.php'],
        'PhpAmqpLib\Connection\AMQPConnectionFactory::create' => [
            'config/dependencies/rabbitmq.php',
            'src/Backendbase/Infrastructure/Adapters/Queue/RabbitMQ/PhpAmqpLibRabbitMQConnectionFactory.php',
        ],
    ];

    #[Test]
    public function vendorConnectionsAreCreatedOnlyInCompositionRootsOrFactories(): void
    {
        $projectRoot   = dirname(__DIR__, 2);
        $constructions = array_merge(
            PhpConstructionScanner::constructionsByFile($projectRoot, 'config'),
            PhpConstructionScanner::constructionsByFile($projectRoot, 'src/Backendbase'),
        );
        $violations    = [];

        foreach ($constructions as $file => $fileConstructions) {
            if (str_contains($file, '/Tests/')) {
                continue;
            }

            foreach ($fileConstructions as $construction) {
                $approvedLocations = self::APPROVED_LOCATIONS[$construction] ?? null;
                if ($approvedLocations === null || in_array($file, $approvedLocations, true)) {
                    continue;
                }

                $violations[] = $file . ' constructs ' . $construction;
            }
        }

        sort($violations);

        self::assertSame([], $violations);
    }
}

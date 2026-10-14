<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\AwsSettings;
use Backendbase\Infrastructure\Configuration\MessageDestination;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Composition\ProductionContainerFixture;

use function array_replace_recursive;

final class MessageDestinationTest extends TestCase
{
    public function testSqsDestinationRequiresAStableConsumerNameEvenWithAnExplicitUrl(): void
    {
        $container = ProductionContainerFixture::build([], 'sqs');
        self::assertSame('events', $container->get(MessageDestination::class)->name());
        $settings = $container->get(SettingsInterface::class);
        $values   = $settings->get();
        self::assertIsArray($values);
        $raw = new Settings(array_replace_recursive($values, ['aws' => ['sqs' => ['queue' => '']]]));
        $this->expectException(InvalidArgumentException::class);
        new MessageDestination(new QueueSettings($raw), new AwsSettings($raw));
    }
}

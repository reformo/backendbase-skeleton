<?php

declare(strict_types=1);

namespace Tests\Shared\Services;

use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Services\Translator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SharedServicesTest extends TestCase
{
    #[Test]
    public function itTranslatesStringsAndStructuredValues(): void
    {
        $translator = new Translator('en', [
            'en' => [
                'welcome' => 'Welcome :name',
                'options' => ['one', 'two'],
            ],
            'tr' => ['welcome' => 'Merhaba :name'],
        ]);

        self::assertSame('Welcome User', $translator->translate('welcome', ['name' => 'User']));
        self::assertSame('Merhaba Kullanıcı', $translator->_('welcome', ['name' => 'Kullanıcı'], 'tr'));
        self::assertSame(['one', 'two'], $translator->translate('options'));
        self::assertSame('en.missing', $translator->translate('missing'));
    }

    #[Test]
    public function itExposesSettingsAndEnvironmentValues(): void
    {
        $settings = new Settings(['env' => 'test', 'feature' => true]);
        self::assertSame(['env' => 'test', 'feature' => true], $settings->get());
        self::assertTrue($settings->get('feature'));
        self::assertSame(Environment::TEST, $settings->env());

        self::assertSame(Environment::STAGE, Environment::fromValue('stage'));
        self::assertSame(Environment::CI, Environment::fromValue('ci'));
        self::assertSame(Environment::TEST, Environment::fromValue('test'));
        self::assertSame(Environment::DEV, Environment::fromValue('development'));
        self::assertSame(Environment::PRODUCTION, Environment::fromValue('unexpected'));
    }
}

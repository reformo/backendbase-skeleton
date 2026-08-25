<?php

declare(strict_types=1);

namespace Tests\Shared\Services;

use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Services\ObjectMapper;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Services\Translator;
use CuyZ\Valinor\Cache\FileSystemCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

final class SharedServicesTest extends TestCase
{
    /** @var FileSystemCache<mixed> */
    private FileSystemCache $cache;

    protected function setUp(): void
    {
        $this->cache = new FileSystemCache(sys_get_temp_dir() . '/backendbase-valinor-test');
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    #[Test]
    public function itMapsSanitizedPayloadsToObjects(): void
    {
        $mapper = new ObjectMapper($this->cache);
        $mapped = $mapper->map(
            MappedInput::class,
            ['name' => 'User', 'age' => 42, 'ignored' => true],
            static function (array $payload): array {
                $payload['name'] = 'Mapped ' . $payload['name'];

                return $payload;
            },
        );

        self::assertInstanceOf(MappedInput::class, $mapped);
        self::assertSame('Mapped User', $mapped->name);
        self::assertSame(42, $mapped->age);
    }

    #[Test]
    public function itReportsObjectMappingErrorsAsInvalidInput(): void
    {
        $mapper = new ObjectMapper($this->cache);

        try {
            $mapper->map(MappedInput::class, ['name' => 'User', 'age' => []]);
            self::fail('Invalid mapped input must fail.');
        } catch (InvalidUserInput $exception) {
            self::assertSame('Invalid input(s) provided', $exception->getDetail());
            self::assertSame('MappedInput', $exception->getAdditionalData()['target']);
            self::assertNotEmpty($exception->getAdditionalData()['errors']);
        }
    }

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

<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\PhpConstructionScanner;
use Tests\Architecture\Support\PhpDependencyScanner;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class PhpDependencyScannerTest extends TestCase
{
    private string $temporaryDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/backendbase-architecture-' . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($this->temporaryDirectory . '/source', 0700, true));
        self::assertIsInt(file_put_contents($this->fixturePath(), <<<'PHP'
<?php

namespace Fixture;

use Laminas\Diactoros\Response\{EmptyResponse as NoContent, JsonResponse};
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Psr\Http\Message\ResponseInterface;

final class Example extends JsonResponse
{
    public const string RESPONSE_CLASS = NoContent::class;

    private string $namespaceText = 'Slim\\App';

    public function response(): ResponseInterface
    {
        throw new \RuntimeException();
    }

    public function connect(mixed $configuration): void
    {
        new JsonResponse([]);
        AMQPConnectionFactory::create($configuration);
    }
}
PHP));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->fixturePath())) {
            unlink($this->fixturePath());
        }

        if (is_dir($this->temporaryDirectory . '/source')) {
            rmdir($this->temporaryDirectory . '/source');
        }

        rmdir($this->temporaryDirectory);
    }

    #[Test]
    public function itResolvesImportedAliasesAndIgnoresNamespaceText(): void
    {
        $dependenciesByFile = PhpDependencyScanner::dependenciesByFile($this->temporaryDirectory, 'source');
        $dependencies       = $dependenciesByFile['source/Fixture.php'];

        self::assertContains('Laminas\Diactoros\Response\EmptyResponse', $dependencies);
        self::assertContains('Laminas\Diactoros\Response\JsonResponse', $dependencies);
        self::assertContains('Psr\Http\Message\ResponseInterface', $dependencies);
        self::assertNotContains('Slim\App', $dependencies);
    }

    #[Test]
    public function itFindsConstructorsAndStaticFactories(): void
    {
        $constructionsByFile = PhpConstructionScanner::constructionsByFile(
            $this->temporaryDirectory,
            'source',
        );
        $constructions       = $constructionsByFile['source/Fixture.php'];

        self::assertContains('new Laminas\Diactoros\Response\JsonResponse', $constructions);
        self::assertContains('PhpAmqpLib\Connection\AMQPConnectionFactory::create', $constructions);
    }

    private function fixturePath(): string
    {
        return $this->temporaryDirectory . '/source/Fixture.php';
    }
}

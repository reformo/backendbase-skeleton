<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function Backendbase\OpenApiTags\validateFile;
use function bin2hex;
use function file_put_contents;
use function glob;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

require_once 'bin/openapi-tags.php';

final class OpenApiTagsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/openapi-tags-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');
        self::assertIsArray($files);
        foreach ($files as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    /**
     * @param array<string, mixed>       $tags
     * @param list<array<string, mixed>> $declarations
     */
    #[DataProvider('tagDefinitions')]
    public function testItRequiresDeclaredNonEmptyOperationTags(array $tags, array $declarations, string|null $error): void
    {
        $document                           = self::document();
        $document['paths']['/items']['get'] = $tags + ['responses' => [200 => ['description' => 'OK']]];
        $document['tags']                   = $declarations;
        $file                               = $this->write('root.yml', $document);
        if ($error !== null) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($error);
        }

        self::assertSame(1, validateFile($file));
    }

    /** @return iterable<string, array{array<string, mixed>, list<array<string, mixed>>, string|null}> */
    public static function tagDefinitions(): iterable
    {
        $declarations = [['name' => 'Items']];

        yield 'declared group' => [['tags' => ['Items']], $declarations, null];
        yield 'multiple groups' => [['tags' => ['Items', 'Search']], [...$declarations, ['name' => 'Search']], null];
        yield 'missing tags' => [[], $declarations, 'GET /items must define a non-empty tags list.'];
        yield 'empty list' => [['tags' => []], $declarations, 'GET /items must define a non-empty tags list.'];
        yield 'associative tags' => [['tags' => ['group' => 'Items']], $declarations, 'must define a non-empty tags list.'];
        yield 'empty name' => [['tags' => ['']], $declarations, 'OpenAPI tag names must be non-empty'];
        yield 'whitespace' => [['tags' => [' Items ']], $declarations, 'no surrounding whitespace'];
        yield 'undeclared group' => [['tags' => ['Other']], $declarations, 'GET /items uses undeclared OpenAPI tag: Other'];
        yield 'missing declarations' => [['tags' => ['Items']], [], 'undeclared OpenAPI tag: Items'];
        yield 'duplicate declarations' => [['tags' => ['Items']], [...$declarations, ...$declarations], 'Duplicate OpenAPI tag declaration.'];
        yield 'blank declaration' => [['tags' => ['Items']], [['name' => ' ']], 'no surrounding whitespace'];
        yield 'wrong tag type' => [['tags' => [123]], $declarations, 'Invalid OpenAPI document'];
    }

    #[DataProvider('referencedTags')]
    public function testItChecksOperationsInReferencedPathFiles(bool $tagged): void
    {
        $document            = self::document();
        $pathItem            = $document['paths']['/items'];
        $pathItem['summary'] = 'Inventory items';
        if (! $tagged) {
            unset($pathItem['get']['tags']);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('GET /items must define a non-empty tags list.');
        }

        $this->write('items.yml', $pathItem);
        $document['paths']['/items'] = ['$ref' => './items.yml'];
        $file                        = $this->write('root.yml', $document);

        self::assertSame(1, validateFile($file));
    }

    /** @return iterable<string, array{bool}> */
    public static function referencedTags(): iterable
    {
        yield 'tagged reference' => [true];
        yield 'untagged reference' => [false];
    }

    public function testItRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAPI file does not exist:');

        validateFile($this->directory . '/missing.yml');
    }

    public function testItRejectsMalformedYaml(): void
    {
        $file = $this->directory . '/invalid.yml';
        file_put_contents($file, 'openapi: [');
        $this->expectException(ParseException::class);

        validateFile($file);
    }

    /** @return array{openapi: string, info: array{title: string, version: string}, tags: list<array{name: string}>, paths: array<string, array<string, mixed>>} */
    private static function document(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Inventory API', 'version' => '1.0.0'],
            'tags' => [['name' => 'Items']],
            'paths' => ['/items' => ['get' => ['tags' => ['Items'], 'responses' => [200 => ['description' => 'OK']]]]],
        ];
    }

    /** @param array<string, mixed> $document */
    private function write(string $filename, array $document): string
    {
        $file = $this->directory . '/' . $filename;
        self::assertNotFalse(file_put_contents($file, Yaml::dump($document, 8)));

        return $file;
    }
}

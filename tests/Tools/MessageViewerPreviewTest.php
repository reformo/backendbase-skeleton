<?php

declare(strict_types=1);

namespace Tests\Tools;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

use function Backendbase\MessageViewer\emailPreview;

require_once 'bin/dev/message-viewer/preview.php';

final class MessageViewerPreviewTest extends TestCase
{
    public function testItPreservesFormattedEmailAndUnicode(): void
    {
        $preview = emailPreview('<html><body style="color:#123456"><table><tr><td><b>Doğrulama</b> &amp; 123456</td></tr></table></body></html>', '');
        self::assertStringContainsString('<div style="color:#123456">', $preview);
        self::assertStringContainsString('<table><tr><td><b>Doğrulama</b> &amp; 123456</td></tr></table>', $preview);
    }

    public function testItEscapesPlainTextInsteadOfExecutingIt(): void
    {
        $preview = emailPreview('', '<script>alert(1)</script>' . "\n" . 'Code 123456');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $preview);
        self::assertStringNotContainsString('<script>', $preview);
        self::assertStringContainsString("\nCode 123456</pre>", $preview);
    }

    public function testItRemovesActiveMarkupAndAllNavigableLinks(): void
    {
        $html     = '<meta http-equiv="refresh" content="0;url=https://example.test"><base href="https://example.test">'
            . '<script>parent.alert(1)</script><iframe src="https://example.test"></iframe>'
            . '<form action="https://example.test"><input name="code"><button>Send</button></form>'
            . '<svg onload="alert(1)"></svg><math><mtext>bad</mtext></math>'
            . '<img src="https://example.test/track" srcset="https://example.test/track 2x" onerror="alert(1)" alt="Remote image">'
            . '<a href="javascript:alert(1)" ping="https://example.test">Visit</a><div onclick="alert(1)">Hello</div>';
        $preview  = emailPreview($html, '');
        $document = new DOMDocument();
        self::assertTrue($document->loadHTML($preview));
        $query = new DOMXPath($document);
        self::assertSame(0.0, $query->evaluate('count(//script | //iframe | //form | //input | //button | //svg | //math | //base | //meta[@http-equiv])'));
        self::assertSame(0.0, $query->evaluate('count(//*[@href or @src or @srcset or @ping or @onclick or @onerror])'));
        self::assertStringNotContainsString('parent.alert', $preview);
        self::assertStringContainsString('Hello', $preview);
    }

    public function testItAllowsEmbeddedRasterImagesButRejectsSvgImages(): void
    {
        $preview = emailPreview('<img src="data:image/png;base64,aGVsbG8=">'
            . '<img src="data:image/svg+xml;base64,PHN2Zz4=">', '');
        self::assertStringContainsString('src="data:image/png;base64,aGVsbG8="', $preview);
        self::assertStringNotContainsString('svg+xml', $preview);
    }
}

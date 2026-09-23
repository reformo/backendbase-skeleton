<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use InvalidArgumentException;
use JsonSerializable;
use Override;
use RuntimeException;

use function base64_encode;
use function basename;
use function file_get_contents;
use function filter_var;
use function is_file;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class Attachment implements JsonSerializable
{
    public const string DISPOSITION_ATTACHMENT = 'attachment';
    public const string DISPOSITION_INLINE     = 'inline';

    private function __construct(
        public string $content,
        public string $filename,
        public string|null $type = null,
        public string $disposition = self::DISPOSITION_ATTACHMENT,
        public string|null $contentId = null,
    ) {
        if (trim($content) === '') {
            throw new InvalidArgumentException('Attachment content cannot be empty.');
        }

        if (trim($filename) === '') {
            throw new InvalidArgumentException('Attachment filename cannot be empty.');
        }

        if ($contentId !== null && filter_var($contentId, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Attachment content ID is invalid.');
        }
    }

    public static function fromBase64(
        string $content,
        string $filename,
        string|null $type = null,
        string $disposition = self::DISPOSITION_ATTACHMENT,
        string|null $contentId = null,
    ): self {
        return new self($content, $filename, $type, $disposition, $contentId);
    }

    public static function fromString(
        string $content,
        string $filename,
        string|null $type = null,
        string $disposition = self::DISPOSITION_ATTACHMENT,
        string|null $contentId = null,
    ): self {
        return new self(base64_encode($content), $filename, $type, $disposition, $contentId);
    }

    public static function fromFile(
        string $path,
        string|null $filename = null,
        string|null $type = null,
        string $disposition = self::DISPOSITION_ATTACHMENT,
        string|null $contentId = null,
    ): self {
        if (! is_file($path)) {
            throw new InvalidArgumentException('Attachment file does not exist.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Attachment file could not be read.');
        }

        return self::fromString($content, $filename ?? basename($path), $type, $disposition, $contentId);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $attachment = [
            'content'     => $this->content,
            'filename'    => $this->filename,
            'disposition' => $this->disposition,
        ];

        if ($this->type !== null) {
            $attachment['type'] = $this->type;
        }

        if ($this->contentId !== null) {
            $attachment['content_id'] = $this->contentId;
        }

        return $attachment;
    }

    /** @return array<string, string> */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

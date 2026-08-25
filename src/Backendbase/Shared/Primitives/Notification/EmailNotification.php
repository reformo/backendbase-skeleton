<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use function get_object_vars;

class EmailNotification implements Notification
{
    /** @var array<int, Address> */
    private array $to = [];

    private Address $from;

    private string $subject;
    private string $htmlBody;
    private string $templateId;
    private string $templateLanguage;
    /** @var array<string, mixed> */
    private array $templateData;

    /** @var array<int, Attachment> */
    private array $attachments = [];

    /** @return array<int, Address> */
    public function toAddresses(): array
    {
        return $this->to;
    }

    public function addToAddress(Address $to): self
    {
        $this->to[] = $to;

        return $this;
    }

    public function from(): Address
    {
        return $this->from;
    }

    public function setFromAddress(Address $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function htmlBody(): string
    {
        return $this->htmlBody;
    }

    public function setHtmlBody(string $htmlBody): self
    {
        $this->htmlBody = $htmlBody;

        return $this;
    }

    public function templateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): self
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function templateLanguage(): string
    {
        return $this->templateLanguage;
    }

    public function setTemplateLanguage(string $templateLanguage): self
    {
        $this->templateLanguage = $templateLanguage;

        return $this;
    }

    /** @return array<string, mixed> */
    public function templateData(): array
    {
        return $this->templateData;
    }

    /** @param array<string, mixed> $templateData */
    public function setTemplateData(array $templateData): self
    {
        $this->templateData = $templateData;

        return $this;
    }

    public function addAttachment(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return $this->attachments;
    }

    private const string TYPE = 'email';

    public function type(): string
    {
        return self::TYPE;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

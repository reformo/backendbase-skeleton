<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

class PushNotification implements Notification
{
    private const string TYPE              = 'push';
    private string|null $title             = null;
    private string|null $body              = null;
    private string|null $topic             = null;
    private string|null $deviceToken       = null;
    private string|null $notificationImage = null;
    /** @var array<string, mixed>|null */
    private array|null $data = null;

    public function title(): string|null
    {
        return $this->title;
    }

    public function setTitle(string|null $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(): string|null
    {
        return $this->body;
    }

    public function setBody(string|null $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function topic(): string|null
    {
        return $this->topic;
    }

    public function setTopic(string|null $topic): self
    {
        $this->topic = $topic;

        return $this;
    }

    public function deviceToken(): string|null
    {
        return $this->deviceToken;
    }

    public function setDeviceToken(string|null $deviceToken): self
    {
        $this->deviceToken = $deviceToken;

        return $this;
    }

    public function notificationImage(): string|null
    {
        return $this->notificationImage;
    }

    public function setNotificationImage(string|null $notificationImage): self
    {
        $this->notificationImage = $notificationImage;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function data(): array|null
    {
        return $this->data;
    }

    /** @param array<string, mixed>|null $data */
    public function setData(array|null $data): self
    {
        $this->data = $data;

        return $this;
    }

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
        return [
            'type' => self::TYPE,
            'title' => $this->title,
            'body' => $this->body,
            'topic' => $this->topic,
            'deviceToken' => $this->deviceToken,
            'notificationImage' => $this->notificationImage,
            'data' => $this->data,
        ];
    }
}

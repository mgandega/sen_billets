<?php

namespace App\Message;

class ScheduledNotificationMessage
{
    public function __construct(
        private array $notificationData
    ) {}

    public function getNotificationData(): array
    {
        return $this->notificationData;
    }

    public function getType(): string
    {
        return $this->notificationData['type'];
    }

    public function getUserId(): ?int
    {
        return $this->notificationData['user_id'] ?? null;
    }

    public function getEventId(): ?int
    {
        return $this->notificationData['event_id'] ?? null;
    }

    public function getChannels(): array
    {
        return $this->notificationData['channels'] ?? ['email'];
    }

    public function getScheduledFor(): ?\DateTime
    {
        return $this->notificationData['scheduled_for'] ?? null;
    }
}
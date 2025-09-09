<?php

namespace App\Message;

class SendSMSMessage
{
    public function __construct(
        private int $userId,
        private string $type,
        private array $data = []
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
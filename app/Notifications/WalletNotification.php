<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WalletNotification extends Notification
{
    public function __construct(
        protected string $title,
        protected string $body,
        protected string $type,
        protected array $meta = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'meta' => $this->meta,
        ];
    }
}

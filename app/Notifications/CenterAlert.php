<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** An in-app reminder shown under «التنبيهات» (doctor stopped sending, visit due, booklet requested). */
class CenterAlert extends Notification
{
    use Queueable;

    public function __construct(public string $key, public string $kind, public string $title, public string $body, public ?string $url = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return ['key' => $this->key, 'kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}

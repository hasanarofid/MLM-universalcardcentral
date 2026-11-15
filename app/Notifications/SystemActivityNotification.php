<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemActivityNotification extends Notification
{
    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $message,
        public readonly string $link = '/admin/dashboard',
        public readonly string $roleTarget = 'member',
        public readonly bool $sendMail = false,
        public readonly array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->sendMail && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title . ' — Universal Card Central')
            ->greeting('Halo, ' . ($notifiable->name ?? 'Member') . '!')
            ->line($this->message)
            ->action('Buka Panel UCC', url($this->link))
            ->salutation('Salam hormat, Tim Universal Card Central');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'role_target' => $this->roleTarget,
            'meta' => $this->meta,
        ];
    }

    public static function sendSafe(?\App\Models\User $user, self $notification): void
    {
        if (!$user) {
            return;
        }
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notification dispatch failed: ' . $e->getMessage());
        }
    }

    public static function notifyAdmins(
        string $category,
        string $title,
        string $message,
        string $link = '/admin/dashboard',
        bool $sendMail = false,
        array $meta = []
    ): void {
        try {
            $admins = \App\Models\User::where('username', 'admin')
                ->orWhere('email', 'admin@universalcardcentral.com')
                ->orWhereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->get();

            foreach ($admins as $admin) {
                self::sendSafe($admin, new self(
                    category: $category,
                    title: $title,
                    message: $message,
                    link: $link,
                    roleTarget: 'admin',
                    sendMail: $sendMail,
                    meta: $meta
                ));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin notification dispatch failed: ' . $e->getMessage());
        }
    }
}

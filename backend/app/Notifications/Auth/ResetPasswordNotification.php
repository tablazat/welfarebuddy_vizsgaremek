<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.reset.subject'))
            ->line(__('mail.reset.line1'))
            ->action(__('mail.reset.action'), $url)
            ->line(__('mail.reset.line2'))
            ->line(__('mail.reset.line3', ['count' => config('auth.passwords.users.expire')]));
    }
}
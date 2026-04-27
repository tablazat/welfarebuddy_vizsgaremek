<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

class VerifyEmailNotification extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        
        $parsed = parse_url($signedUrl);
        parse_str($parsed['query'] ?? '', $query);

        
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://www.welfarebuddy.hu'), '/');

        return $frontendUrl . '/verify-email?' . http_build_query([
            'id'        => $notifiable->getKey(),
            'hash'      => sha1($notifiable->getEmailForVerification()),
            'expires'   => $query['expires'] ?? '',
            'signature' => $query['signature'] ?? '',
        ]);
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify.subject'))
            ->line(__('mail.verify.line1'))
            ->action(__('mail.verify.action'), $url)
            ->line(__('mail.verify.line2'));
    }
}

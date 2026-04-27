<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
//use Mailtrap\MailtrapClient;
//use Mailtrap\Config as MailtrapConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mail::extend('mailtrap', function () {
        //     $client = new MailtrapClient(new MailtrapConfig(config('services.mailtrap.key')));
        //     return $client->emails();
        // });
        VerifyEmail::createUrlUsing(function ($notifiable) {
            // Build a signed Laravel URL
            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );

            // Pass its params to your Vue frontend instead
            $params = parse_url($verifyUrl, PHP_URL_QUERY);
            return "https://www.welfarebuddy.hu/verify-email?{$params}&id={$notifiable->getKey()}&hash=" . sha1($notifiable->getEmailForVerification());
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return "https://www.welfarebuddy.hu/reset-password?token={$token}&email={$user->email}";
        });
    }
}

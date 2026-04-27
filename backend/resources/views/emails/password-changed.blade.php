<x-mail::message>
# {{ __('mail.password_changed.title') }}

{{ __('mail.password_changed.greeting', ['name' => $user->name]) }}

{{ __('mail.password_changed.body') }}

{{ __('mail.password_changed.sign_off') }},
{{ config('app.name') }}
</x-mail::message>
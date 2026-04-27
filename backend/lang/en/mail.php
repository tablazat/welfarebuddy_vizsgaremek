<?php

return [
    'password_changed' => [
        'title'    => 'Your Password Was Changed',
        'greeting' => 'Hi :name,',
        'body'     => 'Your password was successfully changed. If you did not make this change, please contact support immediately.',
        'sign_off' => 'Thanks',
        'subject' => "Password Changed"
    ],
    'verify' => [
        'subject' => 'Verify Your Email Address',
        'line1'   => 'Please click the button below to verify your email address.',
        'action'  => 'Verify Email Address',
        'line2'   => 'If you did not create an account, no further action is required.',
    ],
    'reset' => [
        'subject' => 'Reset Your Password',
        'line1'   => 'You are receiving this email because we received a password reset request for your account.',
        'action'  => 'Reset Password',
        'line2'   => 'If you did not request a password reset, no further action is required.',
        'line3'   => 'This password reset link will expire in :count minutes.',
    ],
    'greeting'         => 'Hi!',
    'salutation'       => 'Best regards,',
    'trouble'          => 'If you are having trouble with clicking ":actionText" button, paste this URL into your browser:',
    'copyright'        => '© :year :name. All rights reserved.',
];
<?php
return [
    'host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
    'port' => getenv('SMTP_PORT') ?: 587,
    'username' => getenv('SMTP_USER') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'from_email' => getenv('SMTP_FROM') ?: 'noreply@example.com',
    'from_name' => getenv('SMTP_NAME') ?: 'Ticket System',
    'admin_email' => getenv('ADMIN_EMAIL') ?: 'mail@example.com',
    'system_email' => getenv('SYSTEM_EMAIL') ?: 'webhoster@hoster.com',
    'envelope_from' =>  getenv('ENVOLPE_FROM') ?: 'webhoster@hoster.com',  //Return-Path für Bounces
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'bounce_handling' => true,
    'use_reply_to' => true,
    'reply_to_email' => 'mail@example.com',       // Explizite Reply-To Adresse
    'reply_to_name' => 'Ticket System',
];

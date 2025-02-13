<?php
return [
    'host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
    'port' => getenv('SMTP_PORT') ?: 587,
    'username' => getenv('SMTP_USER') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'from_email' => getenv('SMTP_FROM') ?: 'noreply@example.com',
    'from_name' => getenv('SMTP_NAME') ?: 'Ticket System',
    'admin_email' => getenv('ADMIN_EMAIL') ?: 'empfang@example.com',
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls'
];

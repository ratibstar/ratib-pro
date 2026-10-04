<?php
return [
    'host' => 'localhost',
    'database' => 'admin_rateb_ai',
    'username' => 'admin_rateb_ai',
    'password' => getenv('RATEB_AI_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];

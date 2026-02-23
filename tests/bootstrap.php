<?php

declare(strict_types=1);

/**
 * Force SQLite in-memory for all tests before Laravel's dotenv loader runs.
 *
 * Laravel uses Dotenv::createImmutable(), which only skips setting a variable
 * when getenv() already returns a value for it.  PHPUnit's <env> tags only
 * populate $_ENV / $_SERVER, not the actual process env.  By calling putenv()
 * here — before vendor/autoload.php is required — we ensure that getenv()
 * returns our testing values, so the .env file cannot override them.
 */
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('BCRYPT_ROUNDS=4');
putenv('MAIL_MAILER=array');
putenv('BROADCAST_CONNECTION=null');

require __DIR__ . '/../vendor/autoload.php';

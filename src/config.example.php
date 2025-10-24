<?php
declare(strict_types=1);

// App (example template for commits)
const APP_NAME   = 'Digitales Serviceheft';
const APP_LOCALE = 'de';
const ADMIN_EMAIL = 'admin@example.com';

// Database (fill on the server in config.php, do NOT commit real values)
const DB_HOST = '<DB_HOST>'; // e.g. localhost
const DB_PORT = 3306;
const DB_NAME = '<DB_DATABASE>';
const DB_USER = '<DB_USERNAME>';
const DB_PASS = '<DB_PASSWORD>';

// Registration
// Allow users to self-register. If false, the Register page/link will be hidden and direct access blocked.
const ALLOW_REGISTRATION = true;

// Auth security (example defaults)
// Maximum number of consecutive failed login attempts before a temporary lockout is enforced
const LOGIN_MAX_FAILED_ATTEMPTS = 5;
// Lockout duration in minutes after reaching the maximum failed attempts
const LOGIN_LOCKOUT_MINUTES = 10;
// If true, reset failed_logins immediately when a lockout is applied; if false, reset only on successful login
const LOGIN_RESET_ON_LOCK = false;


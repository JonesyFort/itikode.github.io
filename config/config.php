<?php
declare(strict_types=1);

/**
 * Local configuration. Change these before deploying anywhere public.
 */

// Shows the "demo accounts" helper on the login page. TURN OFF in production.
const DEMO_MODE = true;

// Shows password-reset links on screen (there is no mail server on localhost).
// TURN OFF once you wire real e-mail delivery.
const SHOW_RESET_LINK = true;

// Absolute lifetime of a login session, whatever the activity (seconds).
const SESSION_ABSOLUTE_SECONDS = 8 * 3600;

// Tie each login session to the exact browser User-Agent string. Stronger, but it signs people out when
// a browser changes its UA mid-session (phone emulation, "request desktop site", some in-app browsers).
const BIND_SESSION_TO_UA = false;

// Bump this when the built-in lessons change. Teachers then see an "Update built-in lessons" button.
const LESSON_SEED_VERSION = 4;

// Admin sessions are always at most this many idle minutes, regardless of settings.
const ADMIN_MAX_IDLE_MINUTES = 15;

// Default values for rows in the `settings` table (admins can change them in the UI).
const DEFAULT_SETTINGS = [
    'site_name'            => 'ITiKODE',
    'module_title'         => 'OOP in Python',
    'allow_registration'   => '1',
    'pass_score'           => '60',
    'session_idle_minutes' => '30',
    'max_failed_logins'    => '5',
    'lockout_minutes'      => '15',
    'require_2fa_admin'    => '0',
    'require_2fa_teacher'  => '0',
];

// Sample accounts created on first run (see README).
const SEED_USERS = [
    ['Admin User',   'admin@itikode.edu',   'Admin@Itikode2025', 'admin'],
    ['Teacher User', 'teacher@itikode.edu', 'Teacher@2025!',     'teacher'],
    ['Student User', 'student@itikode.edu', 'Student@2025',      'student'],
];

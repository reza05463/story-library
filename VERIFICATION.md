# Verification

All seven PHP files passed `php -l` with PHP 8.4.15.

The included integration runner passed against a newly initialized, isolated MySQL 8.4.7 database and PHP's local server. It exercised registration, output escaping, author ownership restrictions, CSRF rejection, ignored GET deletion requests, invalid and oversized cover names, invalid category/blank-title handling, rejected array input, role refresh, admin hide/publish, current-password verification for profile changes, user/story deletion, logout, login, and session-ID rotation.

No existing database or real user data was used. Both temporary servers were stopped. These checks cover selected flows, not production security, load, cross-browser accessibility, or every malformed input.

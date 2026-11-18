<?php
/**
 * Router for PHP's built-in server:   php -S localhost:8000 router.php
 * Keeps the database, config and PHP includes from being downloaded.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

if (preg_match('#(^|/)(data|includes|config|database)(/|$)#i', $path) || preg_match('#\.(db|sqlite|sql|log|md)$#i', $path) || str_contains($path, '..')) {
    http_response_code(404);
    echo 'Not found';
    return true;
}
return false; // serve the requested file / PHP script normally

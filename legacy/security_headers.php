<?php
/**
 * Security Headers for Lume
 * Include this file at the top of public-facing PHP files
 */

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection
header('X-XSS-Protection: 1; mode=block');

// Referrer Policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (adjust as needed)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self' https://openrouter.ai https://*.n8n.cloud");

// Check if already on HTTPS (works with reverse proxies like InfinityFree)
function isHttps() {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') return true;
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') return true;
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) return true;
    return false;
}

// In production, force HTTPS (but don't redirect if already on HTTPS)
if (getenv('APP_ENV') === 'production' && isHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

<?php
/**
 * Security Configuration File
 * 
 * This file contains security-related configuration settings
 * and helper functions for the PHP Gift Registry application.
 */

// Prevent direct access
if (!defined('SECURITY_CONFIG_LOADED')) {
    define('SECURITY_CONFIG_LOADED', true);
}

/**
 * Security Headers Configuration
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (basic)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
    
    // Only send over HTTPS in production
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Check if session is valid and not expired
 * @param array $opt Configuration options array
 */
function validateSession($opt = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['userid'])) {
        return false;
    }
    
    // Get session timeout from config, default to 1 hour
    $sessionTimeout = 3600; // Default 1 hour
    if ($opt && isset($opt['session_timeout'])) {
        $sessionTimeout = (int)$opt['session_timeout'];
    }
    
    // Check session timeout (configurable)
    if ($sessionTimeout > 0 && isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $sessionTimeout)) {
        session_destroy();
        return false;
    }
    
    // Regenerate session ID periodically (every 5 minutes)
    if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration'] > 300)) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    return true;
}

/**
 * Require authentication for protected pages
 * @param array $opt Configuration options array
 */
function requireAuth($opt = null) {
    if (!validateSession($opt)) {
        header("Location: " . getFullPath("login.php"));
        exit;
    }
}

/**
 * Require admin privileges
 * @param array $opt Configuration options array
 */
function requireAdmin($opt = null) {
    requireAuth($opt);
    
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
        header("Location: " . getFullPath("index.php?error=" . urlencode("Access denied. Admin privileges required.")));
        exit;
    }
}

/**
 * Rate limiting for login attempts
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
    $cacheFile = sys_get_temp_dir() . '/login_attempts_' . md5($identifier);
    
    $attempts = 0;
    $firstAttempt = time();
    
    if (file_exists($cacheFile)) {
        $data = unserialize(file_get_contents($cacheFile));
        $attempts = $data['attempts'];
        $firstAttempt = $data['first_attempt'];
    }
    
    // Reset if time window has passed
    if (time() - $firstAttempt > $timeWindow) {
        $attempts = 0;
        $firstAttempt = time();
    }
    
    if ($attempts >= $maxAttempts) {
        return false; // Rate limited
    }
    
    // Record attempt
    $attempts++;
    file_put_contents($cacheFile, serialize([
        'attempts' => $attempts,
        'first_attempt' => $firstAttempt
    ]));
    
    return true; // Not rate limited
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '', $severity = 'INFO') {
    $logFile = dirname(__FILE__) . '/../logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $user = $_SESSION['userid'] ?? 'anonymous';
    
    $logEntry = "[$timestamp] [$severity] $event | User: $user | IP: $ip | Details: $details | UA: $userAgent\n";
    
    error_log($logEntry, 3, $logFile);
}

/**
 * Validate and sanitize uploaded files
 */
function validateUploadedFile($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880) {
    $result = validateFileUpload($file, $allowedTypes, $maxSize);
    
    if (!$result['valid']) {
        logSecurityEvent('FILE_UPLOAD_REJECTED', $result['error'], 'WARNING');
    }
    
    return $result;
}

// Initialize security headers on every request
setSecurityHeaders();
?>
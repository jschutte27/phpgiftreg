<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//
// Purpose: Google OAuth callback handler

require_once(dirname(__FILE__) . "/../../includes/funcLib.php");
require_once(dirname(__FILE__) . "/../../includes/MySmarty.class.php");

// Include Google Client Library
if (file_exists(dirname(__FILE__) . "/../../vendor/autoload.php")) {
    require_once(dirname(__FILE__) . "/../../vendor/autoload.php");
} else {
    die("Google OAuth library not installed. Please run 'composer install' in the project root.");
}

require_once(dirname(__FILE__) . "/../../includes/GoogleOAuth.class.php");

$smarty = new MySmarty();
$opt = $smarty->opt();

// Start secure session
startSecureSession($opt);

// Check if Google OAuth is enabled
if (!$opt['google_oauth_enabled']) {
    header("Location: " . getFullPath("login.php?error=oauth_disabled"));
    exit;
}

try {
    $googleOAuth = new GoogleOAuth($opt);
    
    // Handle OAuth callback
    if (isset($_GET['code'])) {
        $userInfo = $googleOAuth->handleCallback($_GET['code']);
        
        // Find or create user
        $user = $googleOAuth->findOrCreateUser($userInfo, $smarty->dbh(), $opt);
        
        if ($user && $user['approved']) {
            // Login successful - create session
            $_SESSION['userid'] = $user['userid'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['admin'] = $user['admin'];
            $_SESSION['login_time'] = time();
            $_SESSION['oauth_login'] = true;
            
            // Debug: Log session creation
            error_log("OAuth Callback: Session created for user '" . $user['fullname'] . "'");
            error_log("OAuth Callback: Session ID before regeneration: " . session_id());
            error_log("OAuth Callback: Session data before regeneration: " . print_r($_SESSION, true));
            
            // Regenerate session ID for security (prevents session fixation attacks)
            session_regenerate_id(true);
            
            // Debug: Log session after regeneration
            error_log("OAuth Callback: Session ID after regeneration: " . session_id());
            error_log("OAuth Callback: Session data after regeneration: " . print_r($_SESSION, true));
            
            // Build proper redirect URL to root directory
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
            header("Location: " . $baseUrl . "/index.php?message=Welcome+back," . urlencode($user['fullname']) . "!");
            exit;
        } elseif ($user && !$user['approved']) {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
            header("Location: " . $baseUrl . "/login.php?error=approval_required");
            exit;
        } else {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
            header("Location: " . $baseUrl . "/login.php?error=account_creation_disabled");
            exit;
        }
    } elseif (isset($_GET['error'])) {
        $error = $_GET['error'];
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
        header("Location: " . $baseUrl . "/login.php?error=oauth_error&details=" . urlencode($error));
        exit;
    } else {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
        header("Location: " . $baseUrl . "/login.php?error=invalid_request");
        exit;
    }
} catch (Exception $e) {
    error_log("Google OAuth Error: " . $e->getMessage());
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
    header("Location: " . $baseUrl . "/login.php?error=oauth_failed&details=" . urlencode($e->getMessage()));
    exit;
}
?>
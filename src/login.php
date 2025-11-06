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
// Purpose: Handles user login and logout using traditional username/password.

require_once(dirname(__FILE__) . "/includes/funcLib.php");
require_once(dirname(__FILE__) . "/includes/MySmarty.class.php");

// Include Google OAuth if available
$googleOAuthAvailable = false;
$googleAuthUrl = '';
if (file_exists(dirname(__FILE__) . "/vendor/autoload.php") && 
    file_exists(dirname(__FILE__) . "/includes/GoogleOAuth.class.php")) {
    try {
        require_once(dirname(__FILE__) . "/vendor/autoload.php");
        require_once(dirname(__FILE__) . "/includes/GoogleOAuth.class.php");
        
        $smarty = new MySmarty();
        $opt = $smarty->opt();
        
        if ($opt['google_oauth_enabled']) {
            $googleOAuth = new GoogleOAuth($opt);
            $googleOAuthAvailable = true;
            $googleAuthUrl = $googleOAuth->getAuthUrl();
        }
    } catch (Exception $e) {
        error_log("Google OAuth initialization error: " . $e->getMessage());
    }
} else {
    $smarty = new MySmarty();
    $opt = $smarty->opt();
}

// Start secure session with proper configuration
startSecureSession($opt);

if (isset($_GET["action"]) && $_GET["action"] == "logout") {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    header("Location: " . getFullPath("login.php")); //Redirect to login page after logout.
    exit;
}

// --- Handle Login Attempt (POST) ---
if (!empty($_POST["username"])) {
	error_log("Login: Processing login attempt for username: " . $_POST["username"]);
	$username = $_POST["username"];
	// Note: Password is read directly from $_POST, which is okay before hashing, but handle with care.
	$password = $_POST["password"];
	
	$loginError = "";
	
	try {
		// Query to find user by username and password hash, and check if approved
		$stmt = $smarty->dbh()->prepare("SELECT userid, fullname, admin, password FROM {$opt["table_prefix"]}users WHERE username = ? AND approved = 1");
		$stmt->bindParam(1, $username, PDO::PARAM_STR); // Bind username

		$stmt->execute();
		if ($row = $stmt->fetch()) {
			// Debug: Log successful user lookup
			error_log("Login: Found user '$username' with ID: " . $row["userid"]);
			
			if (password_verify($password,$row["password"])) {
				// Debug: Log successful password verification
				error_log("Login: Password verified for user '$username'");
				
				// Session is already started by startSecureSession() call at top of file
				// Regenerate session ID to prevent session fixation attacks
				session_regenerate_id(true);
				$_SESSION["userid"] = $row["userid"];
				$_SESSION["fullname"] = $row["fullname"];
				$_SESSION["admin"] = $row["admin"];
				$_SESSION["login_time"] = time();
			
				// Debug: Log session creation
				error_log("Login: Session created for user '$username', redirecting to index.php");
				error_log("Login: Session ID: " . session_id());
				error_log("Login: Session data: " . print_r($_SESSION, true));
				
				$redirectUrl = getFullPath("index.php");
				error_log("Login: Redirect URL: " . $redirectUrl);
				
				header("Location: " . $redirectUrl);
				exit;
			} else {
				// Debug: Log password verification failure
				error_log("Login: Password verification failed for user '$username'");
				$loginError = "Invalid username or password.";
			}
		} else {
			// Debug: Log user not found
			error_log("Login: User '$username' not found or not approved");
			$loginError = "Invalid username or password.";
		}
	}
	catch (PDOException $e) {
		error_log("Login database error: " . $e->getMessage());
		$loginError = "Login system temporarily unavailable. Please try again later.";
	}

	// If login failed, re-display the login form with the entered username
	error_log("Login: Login failed for username: " . $username . " - Error: " . $loginError);
	$smarty->assign('username', sanitizeOutput($username));
	$smarty->assign('login_error', $loginError);
	$smarty->assign('google_oauth_available', $googleOAuthAvailable);
	$smarty->assign('google_auth_url', $googleAuthUrl);
	$smarty->display('login.tpl');
}
else {
	// Handle OAuth error messages
	$oauthError = '';
	if (isset($_GET['error'])) {
		switch ($_GET['error']) {
			case 'oauth_disabled':
				$oauthError = 'Google login is currently disabled.';
				break;
			case 'approval_required':
				$oauthError = 'Your account requires administrator approval.';
				break;
			case 'account_creation_disabled':
				$oauthError = 'Account creation is disabled. Please contact an administrator.';
				break;
			case 'oauth_error':
			case 'oauth_failed':
				$details = isset($_GET['details']) ? $_GET['details'] : '';
				$oauthError = 'Google login failed. ' . sanitizeOutput($details);
				break;
			case 'invalid_request':
				$oauthError = 'Invalid login request.';
				break;
		}
	}
	
	$smarty->assign('google_oauth_available', $googleOAuthAvailable);
	$smarty->assign('google_auth_url', $googleAuthUrl);
	$smarty->assign('oauth_error', $oauthError);
	$smarty->display('login.tpl'); // Display the empty login form initially
}
?>

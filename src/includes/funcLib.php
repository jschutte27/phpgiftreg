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

function getFullPath($url) {
	$fp = $_SERVER["SERVER_PORT"] == "443" ? "https://" : "http://";
	$fp .= $_SERVER["HTTP_HOST"];
	$dir = dirname($_SERVER["PHP_SELF"]);
	if ($dir != "/")
		$fp .= $dir;
	$fp .= "/" . $url;
	return $fp;
}

/**
 * Start a secure session with proper configuration
 * This ensures all pages use the same session settings
 */
function startSecureSession($opt = null) {
	// Only configure and start if session hasn't been started yet
	if (session_status() === PHP_SESSION_NONE) {
		configureSecureSession($opt);
		session_start();
	}
}

function jsEscape($s) {
	return str_replace("\"","\\u0022",str_replace("'","\\'",str_replace("\r\n","\\r\\n",$s)));
}

function adjustAllocQuantity($itemid, $userid, $bought, $adjust, $dbh, $opt) {
	$howmany = getExistingQuantity($itemid, $userid, $bought, $dbh, $opt);
	if ($howmany == 0) {
		if ($adjust < 0) {
			// can't subtract anything from 0.
			return 0;
		}
		else {
			$stmt = $dbh->prepare("INSERT INTO {$opt["table_prefix"]}allocs(itemid,userid,bought,quantity) VALUES(?, ?, ?, ?)");
			$stmt->bindParam(1, $itemid, PDO::PARAM_INT);
			$stmt->bindParam(2, $userid, PDO::PARAM_INT);
			$stmt->bindParam(3, $bought, PDO::PARAM_BOOL);
			$stmt->bindParam(4, $adjust, PDO::PARAM_INT);
			$stmt->execute();
			return $howmany;
		}
	}
	else {
		/* figure out the real amount to adjust by, in case someone claims to have
			received 3 of something from a buyer when they only bought 2. */
		if ($adjust < 0) {
			if (abs($adjust) > $howmany)
				$actual = -$howmany;
			else
				$actual = $adjust;
		}
		else {
			$actual = $adjust;
		}
		
		if ($howmany + $actual == 0) {
			$stmt = $dbh->prepare("DELETE FROM {$opt["table_prefix"]}allocs WHERE itemid = ? AND userid = ? AND bought = ?");
			$stmt->bindParam(1, $itemid, PDO::PARAM_INT);
			$stmt->bindParam(2, $userid, PDO::PARAM_INT);
			$stmt->bindParam(3, $bought, PDO::PARAM_BOOL);
			$stmt->execute();
		}
		else {
			$stmt = $dbh->prepare("UPDATE {$opt["table_prefix"]}allocs " .
					"SET quantity = quantity + ? " .	// because "quantity + -5" is okay.
					"WHERE itemid = ? AND userid = ? AND bought = ?");
			$stmt->bindParam(1, $actual, PDO::PARAM_INT);
			$stmt->bindParam(2, $itemid, PDO::PARAM_INT);
			$stmt->bindParam(3, $userid, PDO::PARAM_INT);
			$stmt->bindParam(4, $bought, PDO::PARAM_BOOL);
			$stmt->execute();
		}
		return $actual;
	}
}

function getExistingQuantity($itemid, $userid, $bought, $dbh, $opt) {
	$stmt = $dbh->prepare("SELECT quantity FROM {$opt["table_prefix"]}allocs WHERE bought = ? AND userid = ? AND itemid = ?");
	$stmt->bindParam(1, $bought, PDO::PARAM_BOOL);
	$stmt->bindParam(2, $userid, PDO::PARAM_INT);
	$stmt->bindParam(3, $itemid, PDO::PARAM_INT);
	$stmt->execute();
	if ($row = $stmt->fetch()) {
		return $row["quantity"];
	}
	else {
		return 0;
	}
}

function processSubscriptions($publisher, $action, $itemdesc, $dbh, $opt) {
	// join the users table as a cheap way to get the guy's name without having to pass it in.
	$stmt = $dbh->prepare("SELECT subscriber, fullname FROM subscriptions sub INNER JOIN users u ON u.userid = sub.publisher WHERE publisher = ? AND (last_notified IS NULL OR DATE_ADD(last_notified, INTERVAL {$opt["notify_threshold_minutes"]} MINUTE) < NOW())");
	$stmt->bindParam(1, $publisher, PDO::PARAM_INT);
	$stmt->execute();

	$msg = "";
	while ($row = $stmt->fetch()) {
		if ($msg == "") {
			// same message for each user but we need the fullname from the first row before we can assemble it.
			if ($action == "insert") {
				$msg = $row["fullname"] . " has added the item \"$itemdesc\" to their list.";
			}
			else if ($action == "update") {
				$msg = $row["fullname"] . " has updated the item \"$itemdesc\" on their list.";
			}
			else if ($action == "delete") {
				$msg = $row["fullname"] . " has deleted the item \"$itemdesc\" from their list.";
			}
			$msg .= "\r\n\r\nYou are receiving this message because you are subscribed to their updates.  You will not receive another message for their updates for the next " . $opt["notify_threshold_minutes"] . " minutes.";
		}
		sendMessage($publisher, $row["subscriber"], $msg, $dbh, $opt);

		// stamp the subscription.
		$stmt2 = $dbh->prepare("UPDATE subscriptions SET last_notified = NOW() WHERE publisher = ? AND subscriber = ?");
		$stmt2->bindParam(1, $publisher, PDO::PARAM_INT);
		$stmt2->bindParam(2, $row["subscriber"], PDO::PARAM_INT);
		$stmt2->execute();
	}
}

function sendMessage($sender, $recipient, $message, $dbh, $opt) {
	$stmt = $dbh->prepare("INSERT INTO {$opt["table_prefix"]}messages(sender,recipient,message,created) VALUES(?, ?, ?, ?)");
	$stmt->bindParam(1, $sender, PDO::PARAM_INT);
	$stmt->bindParam(2, $recipient, PDO::PARAM_INT);
	$stmt->bindParam(3, $message, PDO::PARAM_STR);
	$stmt->bindValue(4, strftime("%Y-%m-%d"), PDO::PARAM_STR);
	$stmt->execute();
	
	// determine if e-mail must be sent.
	$stmt = $dbh->prepare("SELECT ur.email_msgs, ur.email AS remail, us.fullname, us.email AS semail FROM {$opt["table_prefix"]}users ur " .
			"INNER JOIN {$opt["table_prefix"]}users us ON us.userid = ? " .
			"WHERE ur.userid = ?");
	$stmt->bindParam(1, $sender, PDO::PARAM_INT);
	$stmt->bindParam(2, $recipient, PDO::PARAM_INT);
	$stmt->execute();
	if ($row = $stmt->fetch()) {
		if ($row["email_msgs"] == 1) {
			mail(
				$row["remail"],
				"Gift Registry message from " . $row["fullname"],
				$row["fullname"] . " <" . $row["semail"] . "> sends:\r\n" . $message,
				"From: {$opt["email_from"]}\r\nReply-To: " . $row["semail"] . "\r\nX-Mailer: {$opt["email_xmailer"]}\r\n"
			) or die("Mail not accepted for " . $row["remail"]);
		}
	}
	else {
		die("recipient doesn't exist");
	}
}

function generatePassword($opt) {
	//* borrowed from hitech-password.php - a PHP Message board script
	//* (c) Hitech Scripts 2003
	//* For more information, visit http://www.hitech-scripts.com
	//* modified for phpgiftreg by Chris Clonch
	if ($opt["password_length"] > 10) {
		$length = $opt["password_length"];
	} else {
		$length = 10;
	}
	$bytes = random_bytes($length);
	$newstring = bin2hex($bytes); // Or base64_encode($bytes);
	$hash = password_hash($newstring, PASSWORD_BCRYPT);

	return [$newstring, $hash];
}

function formatPrice($price, $opt) {
	if ($price == 0.0 && $opt["hide_zero_price"])
		return "&nbsp;";
	else
		return $opt["currency_symbol"] . number_format($price,2,".",",");
}

function stampUser($userid, $dbh, $opt) {
	$stmt = $dbh->prepare("UPDATE {$opt["table_prefix"]}users SET list_stamp = NOW() WHERE userid = ?");
	$stmt->bindParam(1, $userid, PDO::PARAM_INT);
	$stmt->execute();
}

function deleteImageForItem($itemid, $dbh, $opt) {
	$stmt = $dbh->prepare("SELECT image_filename FROM {$opt["table_prefix"]}items WHERE itemid = ?");
	$stmt->bindParam(1, $itemid, PDO::PARAM_INT);
	$stmt->execute();
	if ($row = $stmt->fetch()) {
		if ($row["image_filename"] != "") {
			unlink($opt["image_subdir"] . "/" . $row["image_filename"]);
		}

		$stmt = $dbh->prepare("UPDATE {$opt["table_prefix"]}items SET image_filename = NULL WHERE itemid = ?");
		$stmt->bindParam(1, $itemid, PDO::PARAM_INT);
		$stmt->execute();
	}
}

function fixForJavaScript($s) {
	$s = htmlentities($s);
	$s = str_replace("'","\\'",$s);
	$s = str_replace("\r\n","<br />",$s);
	$s = str_replace("\n","<br />",$s);
	return $s;
}

/**
 * Generate a CSRF token and store it in the session
 * @return string The generated CSRF token
 */
function generateCSRFToken() {
	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	
	$token = bin2hex(random_bytes(32));
	$_SESSION['csrf_token'] = $token;
	$_SESSION['csrf_token_time'] = time();
	
	return $token;
}

/**
 * Validate a CSRF token against the one stored in session
 * @param string $token The token to validate
 * @param int $maxAge Maximum age of token in seconds (default: 3600)
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token, $maxAge = 3600) {
	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	
	// Check if token exists in session
	if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
		return false;
	}
	
	// Check token age
	if (time() - $_SESSION['csrf_token_time'] > $maxAge) {
		unset($_SESSION['csrf_token']);
		unset($_SESSION['csrf_token_time']);
		return false;
	}
	
	// Use hash_equals to prevent timing attacks
	$isValid = hash_equals($_SESSION['csrf_token'], $token);
	
	// Regenerate token after successful validation (one-time use)
	if ($isValid) {
		unset($_SESSION['csrf_token']);
		unset($_SESSION['csrf_token_time']);
	}
	
	return $isValid;
}

/**
 * Get the current CSRF token, generating one if it doesn't exist
 * @return string The CSRF token
 */
function getCSRFToken() {
	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	
	if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
		return generateCSRFToken();
	}
	
	// Check if token is expired
	if (time() - $_SESSION['csrf_token_time'] > 3600) {
		return generateCSRFToken();
	}
	
	return $_SESSION['csrf_token'];
}

/**
 * Sanitize output to prevent XSS attacks
 * @param string $string The string to sanitize
 * @param bool $allowHTML Whether to allow basic HTML tags
 * @return string The sanitized string
 */
function sanitizeOutput($string, $allowHTML = false) {
	if ($allowHTML) {
		// Allow only safe HTML tags
		$allowedTags = '<p><br><strong><em><u><a><ul><ol><li>';
		$string = strip_tags($string, $allowedTags);
		// Additional filtering for allowed tags
		$string = preg_replace('/<a[^>]*href=["\']javascript:[^"\']*["\'][^>]*>/i', '<a>', $string);
	} else {
		$string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	
	return $string;
}

/**
 * Secure session configuration
 * @param array $opt Configuration options array
 */
function configureSecureSession($opt = null) {
	// Get session timeout from config, default to 1 hour
	$sessionTimeout = 3600; // Default 1 hour
	if ($opt && isset($opt['session_timeout'])) {
		$sessionTimeout = (int)$opt['session_timeout'];
	}
	
	// Configure secure session settings
	ini_set('session.cookie_httponly', 1);
	ini_set('session.use_only_cookies', 1);
	ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
	ini_set('session.cookie_samesite', 'Lax');
	
	// Set session cookie parameters
	$cookieParams = [
		'lifetime' => $sessionTimeout,
		'path' => '/',
		'domain' => '', // Let PHP handle domain automatically
		'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'httponly' => true,
		'samesite' => 'Lax'
	];
	
	// Debug: Log session configuration
	error_log("Session config: HTTP_HOST=" . $_SERVER['HTTP_HOST'] . ", secure=" . ($cookieParams['secure'] ? 'true' : 'false') . ", lifetime=" . $cookieParams['lifetime']);
	
	session_set_cookie_params(lifetime_or_options: $cookieParams);
}

/**
 * Validate file upload security
 * @param array $file The $_FILES array element
 * @param array $allowedTypes Array of allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return array Array with 'valid' boolean and 'error' message
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880) {
	$result = ['valid' => false, 'error' => ''];
	
	// Check if file was uploaded
	if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
		$result['error'] = 'File upload error occurred.';
		return $result;
	}
	
	// Check file size
	if ($file['size'] > $maxSize) {
		$result['error'] = 'File size exceeds maximum allowed size.';
		return $result;
	}
	
	// Verify MIME type
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($finfo, $file['tmp_name']);
	finfo_close($finfo);
	
	if (!in_array($mimeType, $allowedTypes)) {
		$result['error'] = 'File type not allowed.';
		return $result;
	}
	
	// Additional checks for images
	if (strpos($mimeType, 'image/') === 0) {
		$imageInfo = getimagesize($file['tmp_name']);
		if ($imageInfo === false) {
			$result['error'] = 'Invalid image file.';
			return $result;
		}
	}
	
	$result['valid'] = true;
	return $result;
}

/**
 * Generate safe filename for uploads
 * @param string $originalName The original filename
 * @return string Safe filename
 */
function generateSafeFilename($originalName) {
	$pathInfo = pathinfo($originalName);
	$extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : '';
	
	// Generate random filename
	$filename = bin2hex(random_bytes(16));
	
	// Add extension if valid
	$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
	if (in_array($extension, $allowedExtensions)) {
		$filename .= '.' . $extension;
	}
	
	return $filename;
}

/**
 * Validate password meets minimum requirements
 * @param string $password The password to validate
 * @param array $opt Configuration options array
 * @return array Array with 'valid' boolean and 'error' message
 */
function validatePassword($password, $opt) {
	$result = ['valid' => false, 'error' => ''];
	
	$minLength = isset($opt['min_password_length']) ? (int)$opt['min_password_length'] : 8;
	
	if (strlen($password) < $minLength) {
		$result['error'] = "Password must be at least {$minLength} characters long.";
		return $result;
	}
	
	$result['valid'] = true;
	return $result;
}
?>

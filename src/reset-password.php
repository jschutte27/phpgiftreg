<?php
/// This program is free software; you can redistribute it and/or modify
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
// Purpose: Handles password reset via token sent via email

require_once(dirname(__FILE__) . "/includes/funcLib.php");
require_once(dirname(__FILE__) . "/includes/MySmarty.class.php");
$smarty = new MySmarty();
$opt = $smarty->opt(); // Get application options from Smarty instance

$token = isset($_GET["token"]) ? $_GET["token"] : "";
$tokenValid = false;
$userid = null;
$username = "";
$error = "";
$message = "";

try {
	// Validate token
	if (!empty($token)) {
		$stmt = $smarty->dbh()->prepare(
			"SELECT t.userid, u.username FROM {$opt["table_prefix"]}password_reset_tokens t " .
			"JOIN {$opt["table_prefix"]}users u ON t.userid = u.userid " .
			"WHERE t.token = ? AND t.used = 0 AND t.expires_at > NOW()"
		);
		$stmt->bindParam(1, $token, PDO::PARAM_STR);
		$stmt->execute();
		
		if ($row = $stmt->fetch()) {
			$tokenValid = true;
			$userid = $row["userid"];
			$username = $row["username"];
		} else {
			$error = "The password reset link is invalid or has expired.";
		}
	} else {
		$error = "No reset token provided.";
	}
	
	// Handle password reset form submission
	if (isset($_POST["action"]) && $_POST["action"] == "reset" && $tokenValid) {
		$password = isset($_POST["password"]) ? $_POST["password"] : "";
		$passwordConfirm = isset($_POST["password_confirm"]) ? $_POST["password_confirm"] : "";
		
		// Validate password
		if (empty($password)) {
			$error = "Password is required.";
		} else if (!validatePassword($password, $opt)) {
			$error = "Password must be at least " . $opt["min_password_length"] . " characters long.";
		} else if ($password !== $passwordConfirm) {
			$error = "Passwords do not match.";
		}
		
		// If no errors, update password and mark token as used
		if (empty($error)) {
			// Hash the password
			$hash = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
			
			// Update user password
			$stmt = $smarty->dbh()->prepare("UPDATE {$opt["table_prefix"]}users SET password = ? WHERE userid = ?");
			$stmt->bindParam(1, $hash, PDO::PARAM_STR);
			$stmt->bindParam(2, $userid, PDO::PARAM_INT);
			$stmt->execute();
			
			// Mark token as used
			$stmt = $smarty->dbh()->prepare("UPDATE {$opt["table_prefix"]}password_reset_tokens SET used = 1 WHERE token = ?");
			$stmt->bindParam(1, $token, PDO::PARAM_STR);
			$stmt->execute();
			
			$message = "Your password has been successfully reset. You can now log in with your new password.";
			$tokenValid = false; // Prevent form from showing again
		}
	}
	
	$smarty->assign('tokenValid', $tokenValid);
	$smarty->assign('token', $token);
	$smarty->assign('username', $username);
	$smarty->assign('error', $error);
	$smarty->assign('message', $message);
	$smarty->assign('MIN_PASSWORD_LENGTH', $opt["min_password_length"]);
	$smarty->display('reset-password.tpl');
}
catch (PDOException $e) {
	die("sql exception: " . $e->getMessage());
}
?>

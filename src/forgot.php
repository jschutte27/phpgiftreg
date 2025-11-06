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
// Purpose: Handles the "forgot password" functionality, allowing users to
//          reset their password via email.

require_once(dirname(__FILE__) . "/includes/funcLib.php");
require_once(dirname(__FILE__) . "/includes/MySmarty.class.php");
$smarty = new MySmarty();
$opt = $smarty->opt(); // Get application options from Smarty instance

if (isset($_POST["action"]) && $_POST["action"] == "forgot") {
	$username = $_POST["username"];

	try {
		// make sure that username is valid 
		$stmt = $smarty->dbh()->prepare("SELECT userid, email FROM {$opt["table_prefix"]}users WHERE username = ?");
		$stmt->bindParam(1, $username, PDO::PARAM_STR); // Bind the submitted username
			
		$stmt->execute();
		if ($row = $stmt->fetch()) {
			$userid = $row["userid"];
			$email = $row["email"];
		
			if ($email == "")
				// User exists but has no email address configured
				$error = "The username '" . $username . "' does not have an e-mail address, so the password reset request could not be sent.";
			else {
				// Generate a unique token (64 character hex string)
				$token = bin2hex(random_bytes(32));
				
				// Token expires in 24 hours
				$expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
				
				// Store token in database
				$stmt = $smarty->dbh()->prepare("INSERT INTO {$opt["table_prefix"]}password_reset_tokens (userid, token, expires_at) VALUES (?, ?, ?)");
				$stmt->bindParam(1, $userid, PDO::PARAM_INT);
				$stmt->bindParam(2, $token, PDO::PARAM_STR);
				$stmt->bindParam(3, $expiry, PDO::PARAM_STR);
				$stmt->execute();
				
				// Send email with reset link
				$resetLink = getFullPath("reset-password.php?token=" . urlencode($token));
				$mailsent = mail(
					$email,
					"Gift Registry password reset",
					"You requested a password reset for your Gift Registry account.\r\n\r\n" . 
						"Click the link below to set a new password:\r\n" .
						$resetLink . "\r\n\r\n" .
						"This link will expire in 24 hours.\r\n\r\n" .
						"If you did not request this, you can safely ignore this email.",
					"From: {$opt["email_from"]}\r\nReply-To: {$opt["email_reply_to"]}\r\nX-Mailer: {$opt["email_xmailer"]}\r\n"
				);
				
				if (!$mailsent) {
					$error = "The password reset email could not be sent. Please try again later.";
				}
			}
			// Note: The code proceeds to display the template even on successful email send.
		}
		else {
			$error = "The username '" . $username . "' could not be found.";
		}

		if (!empty($error)) {
			$smarty->assign('error', $error);
		}
		$smarty->assign('action', $_POST["action"]);
		$smarty->assign('username', $username);
		$smarty->display('forgot.tpl');
		// Note: Execution continues after display, should ideally exit here.
	}
	catch (PDOException $e) {
		die("sql exception: " . $e->getMessage());
	}
	// Note: Execution continues after catch block, should ideally exit here.
}
else {
	$smarty->display('forgot.tpl');
}
?>

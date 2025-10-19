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
// Purpose: Handles new user registration/signup.
//          Includes checks for username uniqueness and handles admin approval flow.

require_once(dirname(__FILE__) . "/includes/funcLib.php");
require_once(dirname(__FILE__) . "/includes/MySmarty.class.php");

$smarty = new MySmarty();
$opt = $smarty->opt(); // Get application options from Smarty instance

// Configure secure session
configureSecureSession($opt);

if (isset($_POST["action"]) && $_POST["action"] == "signup") {
	// Validate CSRF token
	if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
		$error = "Invalid request. Please try again.";
	} else {
		$username = trim($_POST["username"]);
		$fullname = trim($_POST["fullname"]);
		$email = trim($_POST["email"]);
		$familyid = !empty($_POST["familyid"]) ? (int)$_POST["familyid"] : null;
		
		// Basic input validation
		if (empty($username) || empty($fullname) || empty($email)) {
			$error = "All fields are required.";
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$error = "Please enter a valid email address.";
		} elseif (strlen($username) < 3 || strlen($username) > 20) {
			$error = "Username must be between 3 and 20 characters.";
		} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
			$error = "Username can only contain letters, numbers, and underscores.";
		} else {
			try {
				// make sure that username isn't taken.
				$stmt = $smarty->dbh()->prepare("SELECT userid FROM {$opt["table_prefix"]}users WHERE username = ?");
				$stmt->bindParam(1, $username, PDO::PARAM_STR);
				$stmt->execute();
				if ($stmt->fetch()) {
					$error = "The username '" . sanitizeOutput($username) . "' is already taken. Please choose another.";
				} else {
					// generate a password and insert the row.
					[$pwd, $hash] = generatePassword($opt);

					$stmt = $smarty->dbh()->prepare("INSERT INTO {$opt["table_prefix"]}users(username,fullname,password,email,approved,initialfamilyid) VALUES(?, ?, ?, ?, ?, ?)");
					$stmt->bindParam(1, $username, PDO::PARAM_STR);
					$stmt->bindParam(2, $fullname, PDO::PARAM_STR);
					$stmt->bindParam(3, $hash, PDO::PARAM_STR);
					$stmt->bindParam(4, $email, PDO::PARAM_STR);
					$stmt->bindValue(5, !$opt["newuser_requires_approval"], PDO::PARAM_BOOL);
					$stmt->bindParam(6, $familyid, PDO::PARAM_INT);
					$stmt->execute();
						
					// --- Handle Approval Flow ---
					if ($opt["newuser_requires_approval"]) {
						// send the e-mails to the administrators.
						$stmt = $smarty->dbh()->prepare("SELECT fullname, email FROM {$opt["table_prefix"]}users WHERE admin = 1 AND email IS NOT NULL");
						$stmt->execute();
						while ($row = $stmt->fetch()) {
							$emailBody = sanitizeOutput($fullname) . " <" . sanitizeOutput($email) . "> would like you to approve him/her for access to the Gift Registry.";
							if (!mail(
								$row["email"],
								"Gift Registry approval request for " . sanitizeOutput($fullname),
								$emailBody,
								"From: {$opt["email_from"]}\r\nReply-To: {$opt["email_reply_to"]}\r\nX-Mailer: {$opt["email_xmailer"]}\r\n"
							)) {
								error_log("Failed to send approval request email to admin: " . $row["email"]);
							}
						}
						$success_message = "Your account has been created and is pending administrator approval. You will receive an email when your account is approved.";
					} else {
						// we don't require approval, so immediately send them their initial password.
						// also, join them up to their initial family (if requested).
						if ($familyid != null) {
							$stmt = $smarty->dbh()->prepare("SELECT userid FROM {$opt["table_prefix"]}users WHERE username = ?");
							$stmt->bindParam(1, $username, PDO::PARAM_STR);
							$stmt->execute();
							if ($row = $stmt->fetch()) {
								$userid = $row["userid"];
						
								$stmt = $smarty->dbh()->prepare("INSERT INTO {$opt["table_prefix"]}memberships(userid,familyid) VALUES(?, ?)");
								$stmt->bindParam(1, $userid, PDO::PARAM_INT);
								$stmt->bindParam(2, $familyid, PDO::PARAM_INT);
								$stmt->execute();
							}
						}

						$emailBody = "Your Gift Registry account was created.\r\n" . 
							"Your username is " . sanitizeOutput($username) . " and your password is " . sanitizeOutput($pwd) . ".";
						
						if (!mail(
							$email,
							"Gift Registry account created",
							$emailBody,
							"From: {$opt["email_from"]}\r\nReply-To: {$opt["email_reply_to"]}\r\nX-Mailer: {$opt["email_xmailer"]}\r\n"
						)) {
							error_log("Failed to send welcome email to: " . $email);
						}
						
						$success_message = "Your account has been created successfully! Check your email for login credentials.";
					}
				}
			} catch (PDOException $e) {
				error_log("Signup database error: " . $e->getMessage());
				$error = "Database error occurred. Please try again later.";
			}
		}
	}
}

// --- Fetch Families for Signup Form ---
try {
	$stmt = $smarty->dbh()->prepare("SELECT familyid, familyname FROM {$opt["table_prefix"]}families ORDER BY familyname");
	$stmt->execute();
	$families = array();
	while ($row = $stmt->fetch()) {
		$families[] = $row;
	}

	if (count($families) == 1) {
		// If only one family exists, pre-select it
		$familyid = $families[0]["familyid"];
	}
} catch (PDOException $e) {
	error_log("Failed to fetch families: " . $e->getMessage());
	$families = array();
}

$smarty->assign('families', $families);
$smarty->assign('username', isset($username) ? sanitizeOutput($username) : '');
$smarty->assign('fullname', isset($fullname) ? sanitizeOutput($fullname) : '');
$smarty->assign('email', isset($email) ? sanitizeOutput($email) : '');
$smarty->assign('familyid', isset($familyid) ? $familyid : '');
$smarty->assign('familycount', count($families));
$smarty->assign('action', $_POST["action"] ?? '');
$smarty->assign('csrf_token', getCSRFToken());

// Assign errors and success messages to Smarty template
if (isset($error)) {
	$smarty->assign('error', $error);
}
if (isset($success_message)) {
	$smarty->assign('success_message', $success_message);
}

$smarty->display('signup.tpl');
?>
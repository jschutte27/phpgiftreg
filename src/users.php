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

require_once(dirname(__FILE__) . "/includes/funcLib.php");
require_once(dirname(__FILE__) . "/includes/MySmarty.class.php");
require_once(dirname(__FILE__) . "/includes/MailService.class.php");
$smarty = new MySmarty();
$opt = $smarty->opt();
$mailService = new MailService($opt);

// Start secure session with proper configuration
startSecureSession($opt);
if (!isset($_SESSION["userid"])) {
	header("Location: " . getFullPath("login.php"));
	exit;
}
else {
	$userid = $_SESSION["userid"];
}

if ($_SESSION["admin"] != 1) {
	echo "You don't have admin privileges.";
	exit;
}

if (!empty($_GET["message"])) {
    $message = $_GET["message"];
}

if (isset($_GET["action"]))
	$action = $_GET["action"];
else
	$action = "";

if ($action == "insert" || $action == "update") {
	/* validate the data. */
	$username = isset($_GET["username"]) ? trim($_GET["username"]) : "";
	$fullname = isset($_GET["fullname"]) ? trim($_GET["fullname"]) : "";
	$email = isset($_GET["email"]) ? trim($_GET["email"]) : "";
	$email_msgs = (isset($_GET["email_msgs"]) && strtoupper($_GET["email_msgs"]) == "ON" ? 1 : 0);
	$approved = (isset($_GET["approved"]) && strtoupper($_GET["approved"]) == "ON" ? 1 : 0);
	$userisadmin = (isset($_GET["admin"]) && strtoupper($_GET["admin"]) == "ON" ? 1 : 0);
		
	$haserror = false;
	if ($username == "") {
		$haserror = true;
		$username_error = "A username is required.";
	}
	if ($fullname == "") {
		$haserror = true;
		$fullname_error = "A full name is required.";
	}
	if ($email == "") {
		$haserror = true;
		$email_error = "An e-mail address is required.";
	}

}

if ($action == "delete") {
	// MySQL is too l4m3 to have cascade deletes, so we'll have to do the
	// work ourselves.
	$deluserid = isset($_GET["userid"]) ? (int) $_GET["userid"] : 0;
	
	$stmt = $smarty->dbh()->prepare("DELETE FROM {$opt["table_prefix"]}shoppers WHERE shopper = ? OR mayshopfor = ?");
	$stmt->bindParam(1, $deluserid, PDO::PARAM_INT);
	$stmt->bindParam(2, $deluserid, PDO::PARAM_INT);
	$stmt->execute();
	
	// we can't leave messages with dangling senders, so delete those too.
	$stmt = $smarty->dbh()->prepare("DELETE FROM {$opt["table_prefix"]}messages WHERE sender = ? OR recipient = ?");
	$stmt->bindParam(1, $deluserid, PDO::PARAM_INT);
	$stmt->bindParam(2, $deluserid, PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $smarty->dbh()->prepare("DELETE FROM {$opt["table_prefix"]}events WHERE userid = ?");
	$stmt->bindParam(1, $deluserid, PDO::PARAM_INT);
	$stmt->execute();
	
	$stmt = $smarty->dbh()->prepare("DELETE FROM {$opt["table_prefix"]}items WHERE userid = ?");
	$stmt->bindParam(1, $deluserid, PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $smarty->dbh()->prepare("DELETE FROM {$opt["table_prefix"]}users WHERE userid = ?");
	$stmt->bindParam(1, $deluserid, PDO::PARAM_INT);
	$stmt->execute();
	
	header("Location: " . getFullPath("users.php?message=User+deleted."));
	exit;
}
else if ($action == "edit") {
	$stmt = $smarty->dbh()->prepare("SELECT username, fullname, email, email_msgs, approved, admin FROM {$opt["table_prefix"]}users WHERE userid = ?");
	$stmt->bindValue(1, isset($_GET["userid"]) ? (int) $_GET["userid"] : 0, PDO::PARAM_INT);
	$stmt->execute();
	if ($row = $stmt->fetch()) {
		$username = $row["username"];
		$fullname = $row["fullname"];
		$email = $row["email"];
		$email_msgs = $row["email_msgs"];
		$approved = $row["approved"];
		$userisadmin = $row["admin"];
	}
}
else if ($action == "") {
	$username = "";
	$fullname = "";
	$email = "";
	$email_msgs = 1;
	$approved = 1;
	$userisadmin = 0;
}
else if ($action == "insert") {
	if (!$haserror) {
		// generate a password and insert the row.
		[$pwd, $hash] = generatePassword($opt);
		$stmt = $smarty->dbh()->prepare("INSERT INTO {$opt["table_prefix"]}users(username,password,fullname,email,email_msgs,approved,admin) VALUES(?, ?, ?, ?, ?, ?, ?)");
		$stmt->bindParam(1, $username, PDO::PARAM_STR);
		$stmt->bindParam(2, $hash, PDO::PARAM_STR);
		$stmt->bindParam(3, $fullname, PDO::PARAM_STR);
		$stmt->bindParam(4, $email, PDO::PARAM_STR);
		$stmt->bindParam(5, $email_msgs, PDO::PARAM_BOOL);
		$stmt->bindParam(6, $approved, PDO::PARAM_BOOL);
		$stmt->bindParam(7, $userisadmin, PDO::PARAM_BOOL);
		$stmt->execute();

		$message = "Your Gift Registry account was created.\r\n\r\n" . 
			"Your username is $username.\r\n\r\n" .
			"Visit " . getFullPath("login.php") . " to log in.\r\n\r\n" .
			($opt["google_oauth_enabled"] ? "You can log in using Google or you can set a password on the forgot password page.\r\n" : "You can set a password on the forgot password page.\r\n");
		
		$mailsent = $mailService->send(
			$email,
			"Gift Registry account created",
			$message
		);
		
		if ($mailsent) {
			header("Location: " . getFullPath("users.php?message=User+added+and+e-mail+sent."));
		} else {
			header("Location: " . getFullPath("users.php?message=User+added+but+e-mail+failed+to+send."));
		}
		exit;
	}
}
else if ($action == "update") {
	if (!$haserror) {
		$stmt = $smarty->dbh()->prepare("UPDATE {$opt["table_prefix"]}users SET " .
				"username = ?, " .
				"fullname = ?, " .
				"email = ?, " .
				"email_msgs = ?, " .
				"approved = ?, " . 
				"admin = ? " . 
				"WHERE userid = ?");
		$stmt->bindParam(1, $username, PDO::PARAM_STR);
		$stmt->bindParam(2, $fullname, PDO::PARAM_STR);
		$stmt->bindParam(3, $email, PDO::PARAM_STR);
		$stmt->bindParam(4, $email_msgs, PDO::PARAM_BOOL);
		$stmt->bindParam(5, $approved, PDO::PARAM_BOOL);
		$stmt->bindParam(6, $userisadmin, PDO::PARAM_BOOL);
		$stmt->bindValue(7, isset($_GET["userid"]) ? (int) $_GET["userid"] : 0, PDO::PARAM_INT);
		$stmt->execute();
		header("Location: " . getFullPath("users.php?message=User+updated."));
		exit;		
	}
}
else if ($action == "reset") {
	$resetuserid = isset($_GET["userid"]) ? (int) $_GET["userid"] : 0;
	$resetemail = isset($_GET["email"]) ? $_GET["email"] : "";
	
	if ($resetuserid > 0 && !empty($resetemail)) {
		// Generate a secure token for password reset
		$token = bin2hex(random_bytes(32));
		$token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
		
		try {
			// Store the token in the password_reset_tokens table
			$stmt = $smarty->dbh()->prepare("INSERT INTO {$opt["table_prefix"]}password_reset_tokens(userid, token, expires_at, used) VALUES(?, ?, ?, ?)");
			$stmt->bindParam(1, $resetuserid, PDO::PARAM_INT);
			$stmt->bindParam(2, $token, PDO::PARAM_STR);
			$stmt->bindParam(3, $token_expiry, PDO::PARAM_STR);
			$used = 0;
			$stmt->bindParam(4, $used, PDO::PARAM_INT);
			$stmt->execute();
			
			// Build the password reset link
			$resetLink = getFullPath("reset-password.php?token=" . urlencode($token));
			
			$message = "Your Gift Registry password reset request has been received.\r\n\r\n" .
				"Click the link below to set a new password:\r\n" .
				$resetLink . "\r\n\r\n" .
				"This link will expire in 24 hours.\r\n\r\n" .
				"If you did not request a password reset, please ignore this email.";
			
			$mailsent = $mailService->send(
				$resetemail,
				"Gift Registry password reset",
				$message
			);
			
			if ($mailsent) {
				header("Location: " . getFullPath("users.php?message=Password+reset+email+sent."));
			} else {
				header("Location: " . getFullPath("users.php?message=Password+reset+email+failed+to+send."));
			}
		}
		catch (PDOException $e) {
			error_log("Password reset error: " . $e->getMessage());
			header("Location: " . getFullPath("users.php?message=Error+processing+password+reset."));
		}
	} else {
		header("Location: " . getFullPath("users.php?message=Invalid+user+or+email."));
	}
	exit;
}
else {
	echo "Unknown verb.";
	exit;
}

$stmt = $smarty->dbh()->prepare("SELECT userid, username, fullname, email, email_msgs, approved, admin FROM {$opt["table_prefix"]}users ORDER BY username");
$stmt->execute();
$users = array();
while ($row = $stmt->fetch()) {
	$users[] = $row;
}

$smarty->assign('action', $action);
$smarty->assign('edituserid', isset($_GET["userid"]) ? (int) $_GET["userid"] : -1);
$smarty->assign('username', $username);
if (isset($username_error)) {
	$smarty->assign('username_error', $username_error);
}
$smarty->assign('fullname', $fullname);
if (isset($fullname_error)) {
	$smarty->assign('fullname_error', $fullname_error);
}
$smarty->assign('email', $email);
if (isset($email_error)) {
	$smarty->assign('email_error', $email_error);
}
$smarty->assign('email_msgs', $email_msgs);
$smarty->assign('approved', $approved);
$smarty->assign('userisadmin', $userisadmin);
if (isset($haserror)) {
	$smarty->assign('haserror', $haserror);
}
$smarty->assign('users', $users);
if (isset($message)) {
	$smarty->assign('message', $message);
}
$smarty->assign('userid', $userid);
$smarty->display('users.tpl');
?>

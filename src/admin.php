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
// Purpose: Handles administrative actions related to user approval/rejection.
//          Requires admin privileges.

require_once(dirname(__FILE__) . "/includes/funcLib.php");
require_once(dirname(__FILE__) . "/includes/MySmarty.class.php");

$smarty = new MySmarty();
$opt = $smarty->opt(); // Get application options from Smarty instance

// Configure secure session
configureSecureSession($opt);

session_start();
if (!isset($_SESSION["userid"])) {
	header("Location: " . getFullPath("login.php"));
	exit;
}
else if ($_SESSION["admin"] != 1) {
	// Check if the logged-in user is an administrator
	echo "You don't have admin privileges.";
	exit;
}
else {
	$userid = $_SESSION["userid"]; // Get the logged-in admin's ID
}

// Handle POST requests for admin actions (secure)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Validate CSRF token
	if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
		die("Invalid request. CSRF token validation failed.");
	}
	
	$action = $_POST["action"];
	
	try {
		// --- Handle Approve User Action ---
		if ($action == "approve") {
			[$pwd, $hash] = generatePassword($opt); // Generate a temporary password for the user
			if (!empty($_POST["familyid"])) {
				$stmt = $smarty->dbh()->prepare("INSERT INTO {$opt["table_prefix"]}memberships(userid,familyid) VALUES(?, ?)"); // Add user to the initial family
				$stmt->bindValue(1, (int) $_POST["userid"], PDO::PARAM_INT);
				$stmt->bindValue(2, (int) $_POST["familyid"], PDO::PARAM_INT);
				$stmt->execute();
			}
			$stmt = $smarty->dbh()->prepare("UPDATE {$opt["table_prefix"]}users SET approved = 1, password = ? WHERE userid = ?");
			$stmt->bindParam(1, $hash, PDO::PARAM_STR);
			$stmt->bindValue(2, (int) $_POST["userid"], PDO::PARAM_INT);
			$stmt->execute();
			
			// send the e-mails
			$stmt = $smarty->dbh()->prepare("SELECT username, email FROM {$opt["table_prefix"]}users WHERE userid = ?"); // Fetch user details for email
			$stmt->bindValue(1, (int) $_POST["userid"], PDO::PARAM_INT);
			$stmt->execute();
			if ($row = $stmt->fetch()) {
				$emailBody = "Your Gift Registry application was approved by " . sanitizeOutput($_SESSION["fullname"]) . ".\r\n" . 
					"Your username is " . sanitizeOutput($row["username"]) . " and your password is " . sanitizeOutput($pwd) . ".";
				
				if (!mail(
					$row["email"],
					"Gift Registry application approved",
					$emailBody,
					"From: {$opt["email_from"]}\r\nReply-To: {$opt["email_reply_to"]}\r\nX-Mailer: {$opt["email_xmailer"]}\r\n"
				)) {
					error_log("Failed to send approval email to: " . $row["email"]);
				}
			}
			header("Location: " . getFullPath("index.php?message=" . urlencode("User approved successfully.")));
			exit;
		}
		// --- Handle Reject User Action ---
		else if ($action == "reject") {
			// send the e-mails to the rejected user
			$stmt = $smarty->dbh()->prepare("SELECT email FROM {$opt["table_prefix"]}users WHERE userid = ?");
			$stmt->bindValue(1, (int) $_POST["userid"], PDO::PARAM_INT);
			$stmt->execute();
			if ($row = $stmt->fetch()) {
				$emailBody = "Your Gift Registry application was denied by " . sanitizeOutput($_SESSION["fullname"]) . ".";
				
				if (!mail(
					$row["email"],
					"Gift Registry application denied",
					$emailBody,
					"From: {$opt["email_from"]}\r\nReply-To: {$opt["email_reply_to"]}\r\nX-Mailer: {$opt["email_xmailer"]}\r\n"
				)) {
					error_log("Failed to send rejection email to: " . $row["email"]);
				}
			}

			$stmt = $smarty->dbh()->prepare("DELETE FROM {$opt["table_prefix"]}users WHERE userid = ?"); // Delete the user record
			$stmt->bindValue(1, (int) $_POST["userid"], PDO::PARAM_INT);
			$stmt->execute();
			
			header("Location: " . getFullPath("index.php?message=" . urlencode("User rejected and deleted.")));
			exit;
		}
	}
	catch (PDOException $e) {
		error_log("Admin action database error: " . $e->getMessage());
		header("Location: " . getFullPath("index.php?error=" . urlencode("Database error occurred. Please try again.")));
		exit;
	}
}

// Handle legacy GET requests (redirect to secure POST forms)
if (isset($_GET["action"])) {
	$action = $_GET["action"];
	if ($action == "approve" || $action == "reject") {
		// For security, don't process GET requests for these actions
		// Instead, show a form that will POST
		echo "<h2>Confirm Action</h2>";
		echo "<p>For security reasons, admin actions require confirmation.</p>";
		echo "<form method='POST'>";
		echo "<input type='hidden' name='action' value='" . sanitizeOutput($action) . "'>";
		echo "<input type='hidden' name='userid' value='" . sanitizeOutput($_GET["userid"]) . "'>";
		if (isset($_GET["familyid"])) {
			echo "<input type='hidden' name='familyid' value='" . sanitizeOutput($_GET["familyid"]) . "'>";
		}
		echo "<input type='hidden' name='csrf_token' value='" . getCSRFToken() . "'>";
		echo "<p>Are you sure you want to " . sanitizeOutput($action) . " this user?</p>";
		echo "<input type='submit' value='Confirm " . ucfirst(sanitizeOutput($action)) . "'>";
		echo "</form>";
		echo "<a href='" . getFullPath("index.php") . "'>Cancel</a>";
		exit;
	}
}
?>
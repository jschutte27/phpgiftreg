{*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*}

<!DOCTYPE html>
<html lang="en">
<head>
	<title>Gift Registry - Reset Password</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link href="bootstrap/css/bootstrap-responsive.css" rel="stylesheet">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="bootstrap/js/bootstrap.min.js"></script>
	<script src="js/jquery.validate.min.js"></script>
	<script src="js/giftreg.js"></script>

	<script language="JavaScript" type="text/javascript">
		$(document).ready(function() {
			$("#resetform").validate({
				highlight: validate_highlight,
				success: validate_success,
				rules: {
					password: {
						required: true,
						minlength: {if isset($MIN_PASSWORD_LENGTH)}{$MIN_PASSWORD_LENGTH}{else}8{/if}
					},
					password_confirm: {
						required: true,
						minlength: {if isset($MIN_PASSWORD_LENGTH)}{$MIN_PASSWORD_LENGTH}{else}8{/if},
						equalTo: "#password"
					}
				},
				messages: {
					password: {
						required: "Password is required.",
						minlength: "Password must be at least {if isset($MIN_PASSWORD_LENGTH)}{$MIN_PASSWORD_LENGTH}{else}8{/if} characters."
					},
					password_confirm: {
						required: "Please confirm your password.",
						minlength: "Password must be at least {if isset($MIN_PASSWORD_LENGTH)}{$MIN_PASSWORD_LENGTH}{else}8{/if} characters.",
						equalTo: "Passwords do not match."
					}
				}
			});
		});
	</script>
</head>
<body>
	<div class="container" style="padding-top: 30px;">

	{if isset($message) && $message != ""}
		<div class="row">
			<div class="span12">
				<div class="alert alert-success">
					<p>{$message|escape:'htmlall'}</p>
					<p>Click <a href="login.php">here</a> to login.</p>
				</div>
			</div>
		</div>
	{elseif isset($error) && $error != ""}
		<div class="row">
			<div class="span12">
				<div class="alert alert-error">
					<p>{$error|escape:'htmlall'}</p>
					<p>Click <a href="forgot.php">here</a> to request a new password reset link.</p>
				</div>
			</div>
		</div>
	{elseif $tokenValid}
		<div class="row">
			<div class="span12">
				<form name="resetform" id="resetform" method="post" action="reset-password.php?token={$token|escape:'htmlall'}" class="well form-horizontal">	
					<input type="hidden" name="action" value="reset">
					<fieldset>
						<legend>Set New Password</legend>
						<p>Username: <strong>{$username|escape:'htmlall'}</strong></p>
						
						<div class="control-group">
							<label class="control-label" for="password">New Password</label>
							<div class="controls">
								<input id="password" name="password" type="password" class="input-xlarge">
								<p class="help-block">
									Minimum {$MIN_PASSWORD_LENGTH} characters
								</p>
							</div>
						</div>
						
						<div class="control-group">
							<label class="control-label" for="password_confirm">Confirm Password</label>
							<div class="controls">
								<input id="password_confirm" name="password_confirm" type="password" class="input-xlarge">
								<p class="help-block">
									Re-enter your password to confirm
								</p>
							</div>
						</div>
						
						<div class="form-actions">
							<button type="submit" class="btn btn-primary">Set Password</button>
							<button type="button" class="btn" onClick="document.location.href='login.php';">Cancel</button>
						</div>
					</fieldset>
				</form>
			</div>
		</div>
	{/if}
	</div>
</body>
</html>

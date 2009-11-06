<?php
require('includes/header.php');
update_activity('restore_id');
$page_title = 'Restore ID';
$onload_javascript = 'focusId(\'memorable_name\')';

// If an ID card was uploaded...
if(isset($_POST['do_upload']))
{
	list($uid, $password) = file($_FILES['id_card']['tmp_name'], FILE_IGNORE_NEW_LINES);
}
// ... or an ID and password was inputted...
else if(!empty($_POST['UID']) && !empty($_POST['password']))
{
	$uid = $_POST['UID'];
	$password = $_POST['password'];
}
// ...or a link from a recovery e-mail is being used...
else if(!empty($_GET['UID']) && !empty($_GET['password']))
{
	$uid = $_GET['UID'];
	$password = $_GET['password'];
}
// ... or a memorable name was inputted ...
else if(!empty($_POST['memorable_name']))
{
	$stmt = $link->prepare('SELECT user_settings.uid, users.password FROM user_settings INNER JOIN users ON user_settings.uid = users.uid WHERE LOWER(user_settings.memorable_name) = LOWER(?) AND user_settings.memorable_password = ?');
	$stmt->bind_param('ss', $_POST['memorable_name'], $_POST['memorable_password']);
	$stmt->execute();
	$stmt->bind_result($uid, $password);
	$stmt->fetch();
	$stmt->close();
	
	if(empty($uid))
	{
		add_error('Your memorable information was incorrect.');
	}
}

if(!empty($uid))
{
	$stmt = $link->prepare('SELECT password FROM users WHERE uid = ?');
	$stmt->bind_param('s', $uid);
	$stmt->execute();
	$stmt->bind_result($db_password);
	$stmt->fetch();
	$stmt->close();
	
	if(empty($db_password)) 
	{
		add_error('There is no such UID.');
	}
	else if($password != $db_password)
	{
		add_error('Incorrect password.');
	}
	else {
		$_SESSION['UID'] = $uid;
		$_SESSION['ID_activated'] = true;
		setcookie('UID', $uid, $_SERVER['REQUEST_TIME'] + 315569260, '/');
		setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/');
		
		$stmt = $link->prepare('SELECT spoiler_mode, topics_mode, ostrich_mode, snippet_length FROM user_settings WHERE uid = ?');
		$stmt->bind_param('s', $uid);
		$stmt->execute();
		$stmt->bind_result($user_config['spoiler_mode'], $user_config['topics_mode'], $user_config['ostrich_mode'], $user_config['snippet_length']);
		$stmt->fetch();
		$stmt->close();
		
		foreach($user_config as $key => $value)
		{
			if($value != 0)
			{
				setcookie($key, $value, $_SERVER['REQUEST_TIME'] + 315569260, '/');
			}
		}
		
		$_SESSION['notice'] = 'Welcome back.';
		header('Location: ' . DOMAIN);
		exit;
		// Get settings, etc.
	}
}

print_errors();

?>

<p>Your internal ID can be restored in a number of ways. If none of these work, you may be able to <a href="/recover_ID_by_email">recover your ID by e-mail</a>.

<p>Here are your options:</p>

<fieldset>
	<legend>Input memorable name and password</legend>
	
	<p>Memorable information can be set from the <a href="/dashboard">dashboard</a></p>

	<form action="" method="post">
		<div class="row">
			<label for="memorable_name">Memorable name</label>
			<input type="text" id="memorable_name" name="memorable_name" maxlength="100" />
		</div>
		<div class="row">
			<label for="memorable_password">Memorable password</label>
			<input type="password" id="memorable_password" name="memorable_password" />
		</div>
		<div class="row">
			<input type="submit" value="Restore" />
		</div>
	</form>
	
</fieldset>

<fieldset>
	<legend>Input UID and password</legend>

	<p>Your internal ID and password are automatically set upon creation of your ID. They are available from the <a href="/back_up_ID">back up</a> page.</p>

	<form action="" method="post">
		<div class="row">
			<label for="UID">Internal ID</label>
			<input type="text" id="UID" name="UID" size="23" maxlength="23" />
		</div>
		<div class="row">
			<label for="password">Internal password</label>
			<input type="password" id="password" name="password" size="32" maxlength="32" />
		</div>
		<div class="row">
			<input type="submit" value="Restore" />
		</div>
	</form>
	
</fieldset>

<fieldset>
	<legend>Upload ID card</legend>
	
	<p>If you have an <a href="/generate_ID_card">ID card</a>, upload it here.</p>
		
	<form enctype="multipart/form-data" action="" method="post">
		<div class="row">
			<input name="id_card" type="file" />
		</div>
		<div class="row">
			<input name="do_upload" type="submit" value="Upload and restore" />
		</div>
	</form>
	
</fieldset>

<?php
require('includes/footer.php');
?>
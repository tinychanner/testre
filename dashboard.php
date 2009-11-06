<?php

require('includes/header.php');
force_id();
update_activity('dashboard');
$page_title = 'Dashboard';

// Set defaults.
$defaults = array	
(
	'memorable_name' => '',
	'memorable_password' => '',
	'email' => '',
	'topics_mode' => 0,
	'spoiler_mode' => 0,
	'ostrich_mode' => 0,
	'snippet_length' => 80
);
$user_config = $defaults;

// These inputs have no simple valid settings.
$text_inputs = array
(
	'memorable_name',
	'memorable_password',
	'email'
);

// ...but these do.
$valid_settings = array
(
	'topics_mode' => array('0', '1'),
	'spoiler_mode' => array('0', '1'),
	'ostrich_mode' => array('0', '1'),
	'snippet_length' => array('80', '100', '120', '140', '160')
);

// Get our user's settings from the database.
$stmt = $link->prepare('SELECT memorable_name, memorable_password, email, spoiler_mode, topics_mode, ostrich_mode, snippet_length FROM user_settings WHERE uid = ?');
$stmt->bind_param('s', $_SESSION['UID']);
$stmt->execute();
$stmt->bind_result($user_config_db['memorable_name'], $user_config_db['memorable_password'], $user_config_db['email'], $user_config_db['spoiler_mode'], $user_config_db['topics_mode'], $user_config_db['ostrich_mode'], $user_config_db['snippet_length']);
$stmt->fetch();
$stmt->close();

// If the values were set in the database, overwrite the defaults.
foreach($user_config_db as $key => $value)
{
	if( ! empty($key))
	{
		$user_config[$key] = $value;
	}
}

if($_POST['form_sent'])
{
	// Unticked checkboxes are not sent by the client, so we need to set them ourselves.
	foreach($defaults as $option => $setting)
	{
		if( ! array_key_exists($option, $_POST['form']))
		{
			$_POST['form'][$option] = $setting;
		}
	}
	
	// Make some specific validations ...
	if( ! empty($_POST['form']['memorable_name']) && $_POST['form']['memorable_name'] != $user_config['memorable_name'])
	{
		// Check if the name is already being used.
		$check_name = $link->prepare('SELECT 1 FROM user_settings WHERE LOWER(memorable_name) = LOWER(?)');
		$check_name->bind_param('s', $_POST['form']['memorable_name']);
		$check_name->execute();

		$check_name->store_result();
		if($check_name->num_rows > 0)
		{
			add_error('The memorable name "' . htmlspecialchars($_POST['form']['memorable_name']) . '" is already being used.');
		}

		$check_name->close();
	}

	if( ! $erred)
	{
		//Iterate over every sent form[] value.
		foreach($_POST['form'] as $key => $value)
		{
			// Check if the settings are valid ...
			if( ! in_array($key, $text_inputs) && ( ! array_key_exists($key, $defaults) || ! in_array($value, $valid_settings[$key]) ) )
			{
				continue;
			}
			if(strlen($value) > 100)
			{
				continue;
			}
			
			// If the submitted setting differs from the current setting, update it.
			if($user_config[$key] != $value)
			{
				// Insert or update!
				$link->query('INSERT INTO user_settings (uid, ' . $link->real_escape_string($key). ') VALUES (\'' . $link->real_escape_string($_SESSION['UID']). '\',  \'' . $link->real_escape_string($value) . '\') ON DUPLICATE KEY UPDATE ' . $link->real_escape_string($key). ' = \'' . $link->real_escape_string($value) . '\'');
				
				// Reset the value so it displays correctly on this page load.
				$user_config[$key] = $value;
				
				// Text inputs never need to be set as cookies.
				if(!in_array($key, $text_inputs))
				{
					setcookie($key, $value, $_SERVER['REQUEST_TIME'] + 315569260);
				}
			}
		}
	}
	
	$_SESSION['notice'] = 'Settings updated.';
}

print_errors();

?>

<form action="" method="post">
	<div>
		<label class="common" for="memorable_name">Memorable name</label>
		<input type="text" id="memorable_name" name="form[memorable_name]" class="inline" value="<?php echo htmlspecialchars($user_config['memorable_name']) ?>" maxlength="100" />
	</div>
	<div>
		<label class="common" for="memorable_password">Memorable password</label>
		<input type="text" class="inline" id="memorable_password" name="form[memorable_password]" value="<?php echo htmlspecialchars($user_config['memorable_password']) ?>" maxlength="100" />
		
		<p class="caption">This information can be used to more easily <a href="/restore_ID">restore your ID</a>. Password is optional, but recommended.</p>
	</div>
	
	<div class="row">
		<label class="common" for="e-mail">E-mail address</label>
		<input type="text" id="e-mail" name="form[email]" class="inline" value="<?php echo htmlspecialchars($user_config['email']) ?>"  size="35" maxlength="100" />
		
		<p class="caption">Used to recover your internal ID <a href="/recover_ID_by_email">via e-mail</a>.</p>
	</div>

	<div class="row">
		<label class="common" for="topics_mode" class="inline">Sort topics by:</label>
		<select id="topics_mode" name="form[topics_mode]" class="inline">
			<option value="0"<?php if($user_config['topics_mode'] == 0) echo ' selected' ?>>Last post (default)</option>
			<option value="1"<?php if($user_config['topics_mode'] == 1) echo ' selected' ?>>Date created</option>
		</select>
	</div>
	
	<div class="row">
		<label class="common" for="snippet_length" class="inline">Snippet length in characters</label>
		<select id="snippet_length" name="form[snippet_length]" class="inline">
			<option value="80"<?php if($user_config['snippet_length'] == 0) echo ' selected' ?>>80 (default)</option>
			<option value="100"<?php if($user_config['snippet_length'] == 100) echo ' selected' ?>>100</option>
			<option value="120"<?php if($user_config['snippet_length'] == 120) echo ' selected' ?>>120</option>
			<option value="140"<?php if($user_config['snippet_length'] == 140) echo ' selected' ?>>140</option>
			<option value="160"<?php if($user_config['snippet_length'] == 160) echo ' selected' ?>>160</option>
		</select>
		
		<p class="caption"></p>
	</div>
	
	<div class="row">
		<label class="common" for="spoiler_mode">Spoiler mode</label>
		<input type="checkbox" id="spoiler_mode" name="form[spoiler_mode]" value="1" class="inline"<?php if($user_config['spoiler_mode'] == 1) echo ' checked="checked"' ?> />
		
		<p class="caption">When enabled, snippets of the bodies will show in the topic list. Not recommended unless you have a very high-resolution screen.</p>
	</div>
	
	<div class="row">
		<label class="common" for="ostrich_mode">Ostrich mode</label>
		<input type="checkbox" id="ostrich_mode" name="form[ostrich_mode]" value="1" class="inline"<?php if($user_config['ostrich_mode'] == 1) echo ' checked="checked"' ?> />
		
		<p class="caption">When enabled, any topic or reply that contains a phrase from your <a href="/edit_ignore_list">ignore list</a> will be hidden.</p>
	</div>
	
	<div class="row">
		<input type="submit" name="form_sent" value="Save settings" />
	</div>
	
</form>

<?php

require('includes/footer.php');

?>
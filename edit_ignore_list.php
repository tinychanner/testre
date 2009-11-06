<?php

require('includes/header.php');
force_id();
$page_title = 'Edit ignored phrases';
$onload_javascript = 'focusId(\'ignore_list\'); init();';

if($_POST['form_sent'])
{
	check_length($_POST['ignore_list'], 'ignore list', 0, 4000);
	
	if( ! $erred)
	{
		$update_ignore_list = $link->prepare('INSERT INTO ignore_lists (uid, ignored_phrases) VALUES (?, ?) ON DUPLICATE KEY UPDATE ignored_phrases = ?;');
		$update_ignore_list->bind_param('sss', $_SESSION['UID'], $_POST['ignore_list'], $_POST['ignore_list']);
		$update_ignore_list->execute();
		$update_ignore_list->close();
					
		$_SESSION['notice'] = 'Ignore list updated.';
		if($_COOKIE['ostrich_mode'] != 1)
		{
			$_SESSION['notice'] .= ' You must <a href="/dashboard">enable ostrich mode</a> for this to have any effect.';
		}
	}
	else
	{
		$ignored_phrases = $_POST['ignore_list'];
	}
}

$fetch_ignore_list = $link->prepare('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?');
$fetch_ignore_list->bind_param('s', $_COOKIE['UID']);
$fetch_ignore_list->execute();
$fetch_ignore_list->bind_result($ignored_phrases);
$fetch_ignore_list->fetch();
$fetch_ignore_list->close();

print_errors();

?>

<p>When ostrich mode is <a href="/dashboard">enabled</a>, any topic or reply that contains a phrase on your ignore list will be hidden. Citations to hidden replies will be replaced with "@hidden". Enter one (case insensitive) phrase per line.</p>

<form action="" method="post">
	<div>
		<textarea id="ignore_list" name="ignore_list" cols="80" rows="10"><?php echo sanitize_for_textarea($ignored_phrases) ?></textarea>
	</div>
	
	<div class="row">
		<input type="submit" name="form_sent" value="Update" />
	</div>

</form>

<?php

require('includes/footer.php');

?>
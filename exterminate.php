<?php

require('includes/header.php');

if( ! $administrator && ! $moderator || $moderator && ! ALLOW_MODS_EXTERMINATE)
{
	add_error('You are not wise enough.', true);
}

$page_title = 'Exterminate trolls by phrase';

if($_POST['exterminate'])
{
	$_POST['phrase'] = str_replace("\r", '', $_POST['phrase']);
	
	// Prevent CSRF
	if(empty($_POST['start_time']) || $_POST['start_time'] != $_SESSION['exterminate_start_time'])
	{
		add_error('Session error.', true);
	}

	if(strlen($_POST['phrase']) < 4)
	{
		add_error('That phrase is too short.', true);
	}

	$phrase = '%' . $_POST['phrase'] . '%';

	if(ctype_digit($_POST['range']))
	{
		$affect_posts_after = $_SERVER['REQUEST_TIME'] - $_POST['range'];
	
		// Delete replies.
		$fetch_parents = $link->prepare('SELECT id, parent_id FROM replies WHERE body LIKE ? AND time > ?');
		$fetch_parents->bind_param('si', $phrase, $affect_posts_after);
		$fetch_parents->execute();
		$fetch_parents->bind_result($reply_id, $parent_id);
		
		$victim_parents = array();
		while($fetch_parents->fetch())
		{
			$victim_parents[] = $parent_id;
		}
		$fetch_parents->close();
		
		$delete_replies = $link->prepare('DELETE FROM replies WHERE body LIKE ? AND time > ?');
		$delete_replies->bind_param('si', $phrase, $affect_posts_after);
		$delete_replies->execute();
		$delete_replies->close();
		
		$decrement = $link->prepare('UPDATE topics SET replies = replies - 1 WHERE id = ?');
		foreach($victim_parents as $parent_id)
		{
			$decrement->bind_param('i', $parent_id);
			$decrement->execute();
		}
		$decrement->close();
		
		// Delete topics.
		$delete_topics = $link->prepare('DELETE FROM topics WHERE body LIKE ? OR headline LIKE ? AND time > ?');
		$delete_topics->bind_param('ssi', $phrase, $phrase, $affect_posts_after);
		$delete_topics->execute();
		$delete_topics->close();
		
		$_SESSION['notice'] = 'Finished.';
	}
}

$start_time = $_SERVER['REQUEST_TIME'];
$_SESSION['exterminate_start_time'] = $start_time;

?>

<p>This features removes all posts that contain anywhere in the body or headline the exact phrase that you specify.</p>

<form action="" method="post">
	<div class="noscreen">
		<input type="hidden" name="start_time" value="<?php echo $start_time ?>" />
	</div>

	<div class="row">
		<label for="phrase">Phrase</label>
		<textarea id="phrase" name="phrase"></textarea>
	</div>
	
	<div class="row">
		<label for="range" class="inline">Affect posts made within:</label>
		<select id="range" name="range" class="inline">
			<option value="28800">Last 8 hours</option>
			<option value="86400">Last 24 hours</option>
			<option value="259200">Last 72 hours</option>
			<option value="604800">Last week</option>
			<option value="2629743">Last month</option>
		</select>
	</div>
	
	<div class="row">
		<input type="submit" name="exterminate" value="Clean up this fucking mess" onclick="confirm('Really?')" />
	</div>
</form>

<?php

require('includes/footer.php');

?>
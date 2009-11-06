<?php

require('includes/header.php');
force_id();
update_activity('watchlist');
$page_title = 'Your watchlist';

if( is_array($_POST['rejects']) )
{
	$remove_topic = $link->prepare('DELETE FROM watchlists WHERE uid = ? AND topic_id = ?');
	foreach($_POST['rejects'] as $reject_id)
	{
		$remove_topic->bind_param('si', $_SESSION['UID'], $reject_id);
		$remove_topic->execute();
	}
	$remove_topic->close();
	
	$_SESSION['notice'] = 'Selected topics unwatched.';
}

echo '<form name="fuck_off" action="" method="post">';

$stmt = $link->prepare('SELECT watchlists.topic_id, topics.headline, topics.replies, topics.visits, topics.time FROM watchlists INNER JOIN topics ON watchlists.topic_id = topics.id WHERE watchlists.uid = ? ORDER BY last_post DESC');
$stmt->bind_param('s', $_SESSION['UID']);
$stmt->execute();
$stmt->bind_result($topic_id, $topic_headline, $topic_replies, $topic_visits, $topic_time);

$topics = new table();
$topic_column = '<script type="text/javascript"> document.write(\'<input type="checkbox" name="master_checkbox" class="inline" onclick="checkOrUncheckAllCheckboxes()" title="Check/uncheck all" /> \');</script>Topic';
$columns = array
(
	$topic_column,
	'Replies',
	'Visits',
	'Age ▼'
);
$topics->define_columns($columns, $topic_column);
$topics->add_td_class($topic_column, 'topic_headline');

while($stmt->fetch()) 
{
	$values = array 
	(
		'<input type="checkbox" name="rejects[]" value="' . $topic_id . '" class="inline" /> <a href="/topic/' . $topic_id . '">' . htmlspecialchars($topic_headline) . '</a>',
		replies($topic_id, $topic_replies),
		format_number($topic_visits),
		'<span class="help" title="' . format_date($topic_time) . '">' . calculate_age($topic_time) . '</span>'
	);
								
	$topics->row($values);
}
$stmt->close();
$num_topics_fetched = $topics->num_rows_fetched;
echo $topics->output();

if($num_topics_fetched !== 0)
{
	echo '<div class="row"><input type="submit" value="Unwatch selected" onclick="return confirm(\'Really remove selected topic(s) from your watchlist?\');" class="inline" /></div>';
}
echo '</form>';

require('includes/footer.php');

?>
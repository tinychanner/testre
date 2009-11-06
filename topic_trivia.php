<?php

require('includes/header.php');

if( ! ctype_digit($_GET['id']))
{
	add_error('Invalid ID.', true);
}

$stmt = $link->prepare('SELECT headline, visits, replies, author FROM topics WHERE id = ?');
$stmt->bind_param('i', $_GET['id']);

$stmt->execute();

$stmt->store_result();
if($stmt->num_rows < 1)
{
	$page_title = 'Non-existent topic';
	add_error('There is no such topic. It may have been deleted.', true);
}

$stmt->bind_result($topic_headline, $topic_visits, $topic_replies, $topic_author);
$stmt->fetch();
$stmt->close();

update_activity('topic_trivia', $_GET['id']);

$page_title = 'Trivia for topic: <a href="/topic/' . $_GET['id'] . '">' . htmlspecialchars($topic_headline) . '</a>';

$statistics = array();

$query = "SELECT count(*) FROM watchlists WHERE topic_id = '" . $link->real_escape_string($_GET['id']) . "';";
$query .= "SELECT count(*) FROM activity WHERE action_name = 'topic' AND action_id = '" . $link->real_escape_string($_GET['id']) . "';";
$query .= "SELECT count(*) FROM activity WHERE action_name = 'replying' AND action_id = '" . $link->real_escape_string($_GET['id']) . "';";
$query .= "SELECT count(DISTINCT author) FROM replies WHERE parent_id = '" . $link->real_escape_string($_GET['id']) . "' AND author != '" . $link->real_escape_string($topic_author) . "';"; // Alternatively, we could select the most recent poster_number. I'm not sure which method would be fastest.

$link->multi_query($query);
do 
{
	$result = $link->store_result();
	while ($row = $result->fetch_row()) 
	{
		$statistics[] = $row[0];
	}
	$result->free();
} while ($link->next_result());

$topic_watchers = $statistics[0];
$topic_readers = $statistics[1];
$topic_writers = $statistics[2];
$topic_participants = $statistics[3] + 1; // include topic author

?>

<table>
	<tr>
		<th class="minimal">Total visits</th>
		<td><?php echo format_number($topic_visits) ?></td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Watchers</th>
		<td><?php echo format_number($topic_watchers) ?></td>
	</tr>
	
	<tr>
		<th class="minimal">Participants</th>
		<td><?php echo ($topic_participants === 1) ? '(Just the creator.)' : format_number($topic_participants) ?></td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Replies</th>
		<td><?php echo format_number($topic_replies) ?></td>
	</tr>
	
	<tr>
		<th class="minimal">Current readers</th>
		<td><?php echo format_number($topic_readers) ?></td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Current reply writers</th>
		<td><?php echo format_number($topic_writers) ?></td>
	</tr>
	
</table>

<?php

require('includes/footer.php');

?>
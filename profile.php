<?php

require('includes/header.php');

// If you're not a mod, fuck off.
if( ! $moderator && ! $administrator)
{
	add_error('You are not wise enough.', true);
}

// Demand UID.
if( ! isset($_GET['uid']))
{
	add_error('No UID specified.', true);
}

// Demand a _valid_ UID, fetch first_seen, IP address, and hostname.
$uid_exists = $link->prepare('SELECT first_seen, ip_address FROM users WHERE uid = ?');
$uid_exists->bind_param('s', $_GET['uid']);
$uid_exists->execute();
$uid_exists->store_result();
if($uid_exists->num_rows < 1)
{
	add_error('There is no such user.', true);
}
$uid_exists->bind_result($id_first_seen, $id_ip_address);
$uid_exists->fetch();
$uid_exists->close();

$id_hostname = @gethostbyaddr($id_ip_address);
if($id_hostname === $id_ip_address)
{
	$id_hostname = false;
}


// Check if banned.
$check_uid_ban = $link->prepare('SELECT filed FROM uid_bans WHERE uid = ?');
$check_uid_ban->bind_param('s', $_GET['uid']);
$check_uid_ban->execute();
$check_uid_ban->store_result();
if($check_uid_ban->num_rows > 0)
{
	$banned = true;
}
$check_uid_ban->close();

// Fetch number of topics and replies.
$query = "SELECT count(*) FROM topics WHERE author = '" . $link->real_escape_string($_GET['uid']) . "';";
$query .= "SELECT count(*) FROM replies WHERE author = '" . $link->real_escape_string($_GET['uid']) . "';";

$link->multi_query($query);
do {
	$result = $link->store_result();
	while ($row = $result->fetch_row()) 
	{
		$statistics[] = $row[0];
	}
	$result->free();
} while ($link->next_result());

$id_num_topics = $statistics[0];
$id_num_replies = $statistics[1];

// Now print everything.
$page_title = 'Profile of poster ' . $_GET['uid'];
dummy_form();

echo '<p>First seen <strong class="help" title="' . format_date($id_first_seen) . '">' . calculate_age($id_first_seen) . ' ago</strong> using the IP address <strong><a href="/IP_address/' . $id_ip_address . '">' . $id_ip_address . '</a></strong> (';
//If there's a valid host name ...
if($id_hostname)
{
	echo 'host name <strong>' . $id_hostname . '</strong>';
}
else
{
	echo 'no valid host name';
}
echo '), has started <strong>' . $id_num_topics . '</strong> existing topic' . ($id_num_topics == 1 ? '' : 's') . ' and posted <strong>' . $id_num_replies . '</strong> existing repl' . ($id_num_replies == 1 ? 'y' : 'ies') . '.</p>';
if($banned)
{
	echo '<p>This poster is currently <strong>banned</strong>.';
}
echo '<ul class="menu">';
if( ! $banned)
{
	echo '<li><a href="/ban_poster/' . $_GET['uid'] . '" onclick="return submitDummyForm(\'/ban_poster/' . $_GET['uid'] . '\', \'id\', \'' . $_GET['uid'] . '\', \'Really ban this poster?\');">Ban ID</a></li>';
}
else
{
	echo '<li><a href="/unban_poster/' . $_GET['uid'] . '" onclick="return submitDummyForm(\'/unban_poster/' . $_GET['uid'] . '\', \'id\', \'' . $_GET['uid'] . '\', \'Really unban this poster?\');">Unban ID</a></li>';
}
echo '<li><a href="/nuke_ID/' . $_GET['uid'] . '" onclick="return submitDummyForm(\'/nuke_ID/' . $_GET['uid'] . '\', \'id\', \'' . $_GET['uid'] . '\', \'Really delete all topics and replies by this poster?\');">Delete all posts</a></li>';
echo '</ul>';

if($id_num_topics > 0)
{
	echo '<h4 class="section">Topics</h4>';

	$stmt = $link->prepare('SELECT id, time, replies, visits, headline, author_ip FROM topics WHERE author = ? ORDER BY id DESC');
	$stmt->bind_param('s', $_GET['uid']);
	$stmt->execute();
	$stmt->bind_result($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline, $topic_ip_address);

	$topics = new table();
	$columns = array
	(
		'Headline',
		'IP address',
		'Replies',
		'Visits',
		'Age ▼'
	);
	$topics->define_columns($columns, 'Headline');
	$topics->add_td_class('Headline', 'topic_headline');
	
	while($stmt->fetch()) 
	{
		$values = array 
		(
			'<a href="/topic/' . $topic_id . '">' . htmlspecialchars($topic_headline) . '</a>',
			'<a href="/IP_address/' . $topic_ip_address . '">' . $topic_ip_address . '</a>',
			replies($topic_id, $topic_replies),
			format_number($topic_visits),
			'<span class="help" title="' . format_date($topic_time) . '">' . calculate_age($topic_time) . '</span>'
		);
								
		$topics->row($values);
	}
	$stmt->close();
	echo $topics->output();
}

if($id_num_replies > 0)
{
	echo '<h4 class="section">Replies</h4>';

	$stmt = $link->prepare('SELECT replies.id, replies.parent_id, replies.time, replies.body, replies.author_ip, topics.headline, topics.time FROM replies INNER JOIN topics ON replies.parent_id = topics.id WHERE replies.author = ? ORDER BY id DESC');
	$stmt->bind_param('s', $_GET['uid']);
	$stmt->execute();
	$stmt->bind_result($reply_id, $parent_id, $reply_time, $reply_body, $reply_ip_address, $topic_headline, $topic_time);
	
	$replies = new table();
	$columns = array
	(
		'Reply snippet',
		'Topic',
		'IP address',
		'Age ▼'
	);
	$replies->define_columns($columns, 'Topic');
	$replies->add_td_class('Topic', 'topic_headline');
	$replies->add_td_class('Reply snippet', 'reply_body_snippet');

	while($stmt->fetch()) 
	{
		$values = array 
		(
			'<a href="/topic/' . $parent_id . '#reply_' . $reply_id . '">' . snippet($reply_body) . '</a>',
			'<a href="/topic/' . $parent_id . '">' . htmlspecialchars($topic_headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . calculate_age($topic_time) . ' old)</span>',
			'<a href="/IP_address/' . $reply_ip_address . '">' . $reply_ip_address . '</a>',
			'<span class="help" title="' . format_date($reply_time) . '">' . calculate_age($reply_time) . '</span>'
		);
									
		$replies->row($values);
	}
	$stmt->close();
	echo $replies->output();
}

if($trash = show_trash($_GET['uid']))
{
	echo '<h4 class="section">Trash</h4>' . $trash;
}

require('includes/footer.php');

?>
<?php

require('includes/header.php');
update_activity('history');
force_id();

if ( ! ctype_digit($_GET['p']) || $_GET['p'] < 2) 
{
	$current_page = 1;
	$page_title = 'Your latest post history';
}
else
{
	$current_page = $_GET['p'];
	$page_title = 'Your post history, page #' . number_format($current_page);
}

$items_per_page = ITEMS_PER_PAGE;
$start_listing_at = $items_per_page * ($current_page - 1);  

/* TOPICS */

$stmt = $link->prepare('SELECT id, time, replies, visits, headline FROM topics WHERE author = ? ORDER BY id DESC LIMIT ?, ?');
$stmt->bind_param('sii', $_SESSION['UID'], $start_listing_at, $items_per_page);
$stmt->execute();
$stmt->bind_result($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline);

$topics = new table();
$columns = array
(
	'Headline',
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
		replies($topic_id, $topic_replies),
		format_number($topic_visits),
		'<span class="help" title="' . format_date($topic_time) . '">' . calculate_age($topic_time) . '</span>'
	);
								
	$topics->row($values);
}
$stmt->close();
$num_topics_fetched = $topics->num_rows_fetched;
echo $topics->output('topics');

/* REPLIES */

$stmt = $link->prepare('SELECT replies.id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time FROM replies INNER JOIN topics ON replies.parent_id = topics.id WHERE replies.author = ? ORDER BY id DESC LIMIT ?, ?');
$stmt->bind_param('sii', $_SESSION['UID'], $start_listing_at, $items_per_page);
$stmt->execute();
$stmt->bind_result($reply_id, $parent_id, $reply_time, $reply_body, $topic_headline, $topic_time);

$replies = new table();
$columns = array
(
	'Reply snippet',
	'Topic',
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
		'<span class="help" title="' . format_date($reply_time) . '">' . calculate_age($reply_time) . '</span>'
	);
								
	$replies->row($values);
}
$stmt->close();
$num_replies_fetched = $replies->num_rows_fetched;
echo $replies->output('replies');

page_navigation('history', $current_page, $num_replies_fetched);

require('includes/footer.php');

?>
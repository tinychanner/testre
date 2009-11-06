<?php

require('includes/header.php');

// Validate / fetch topic info.
if( ! ctype_digit($_GET['id']))
{
	add_error('Invalid ID.', true);
}

if(ALLOW_IMAGES)
{
	$stmt = $link->prepare('SELECT topics.time, topics.author, topics.author_name, topics.visits, topics.replies, topics.headline, topics.body, topics.edit_time, topics.edit_mod, images.file_name FROM topics LEFT OUTER JOIN images ON topics.id = images.topic_id WHERE topics.id = ?');
}
else
{
	$stmt = $link->prepare('SELECT time, author, author_name, visits, replies, headline, body, edit_time, edit_mod FROM topics WHERE id = ?');
}
$stmt->bind_param('i', $_GET['id']);

$stmt->execute();

$stmt->store_result();
if($stmt->num_rows < 1)
{
	$page_title = 'Non-existent topic';
	update_activity('nonexistent_topic');
	add_error('There is no such topic. It may have been deleted.', true);
}

if(ALLOW_IMAGES)
{
	$stmt->bind_result($topic_time, $topic_author, $topic_author_name, $topic_visits, $topic_replies, $topic_headline, $topic_body, $topic_edit_time, $topic_edit_mod, $topic_image_name);
}
else
{
	$stmt->bind_result($topic_time, $topic_author, $topic_author_name, $topic_visits, $topic_replies, $topic_headline, $topic_body, $topic_edit_time, $topic_edit_mod);
}
$stmt->fetch();
$stmt->close();

update_activity('topic', $_GET['id']);

$page_title = 'Topic: ' . htmlspecialchars($topic_headline);

// Increment visit count.
if( ! isset($_SESSION['visited_topics'][$_GET['id']]) && isset($_COOKIE['SID']))
{
	$_SESSION['visited_topics'][$_GET['id']] = 1;
	
	$increment_visits = $link->prepare('UPDATE topics SET visits = visits + 1 WHERE id = ?');
	$increment_visits->bind_param('i', $_GET['id']);
	$increment_visits->execute();
	$increment_visits->close();
}

// Set visited cookie...
$last_read_post = $visited_topics[$_GET['id']];
if($last_read_post !== $topic_replies)
{
	// Build cookie.
	// Add the current topic:
	$visited_topics = array( $_GET['id'] => $topic_replies) + $visited_topics;
	// Readd old topics.
	foreach($visited_topics as $cur_topic_id => $num_replies)
	{
		// If the cookie is getting too long (4kb), stop.
		if(strlen($cookie_string) > 3900)
		{
			break;
		}
		
		$cookie_string .= 't' . $cur_topic_id . 'n' . $num_replies;
	}

	setcookie('topic_visits', $cookie_string, $_SERVER['REQUEST_TIME'] + 604800, '/');
}

// If ostrich mode is enabled, fetch a list of blacklisted phrases.
$ignored_phrases = fetch_ignore_list();

// Output dummy form. (This is for JavaScript submissions to action.php.)
dummy_form();

// Output OP.
echo '<h3>';
if($topic_author == 'admin')
{
	echo '<strong class="admin">' . ADMIN_NAME . '</strong> ';
}
else
{
	if($moderator || $administrator)
	{
		echo '<a href="/profile/' . $topic_author . '">';
	}
	if(empty($topic_author_name))
	{
		echo 'Anonymous <strong>A</strong>';
	}
	else
	{
		echo trip($topic_author_name);
	}
	if($moderator || $administrator)
	{
		echo '</a>';
	}
	echo ' ';
}
if($topic_author == $_SESSION['UID'])
{
	echo '(you) ';
}
echo 'started this discussion <strong><span class="help" title="' . format_date($topic_time) . '">' . calculate_age($topic_time) . ' ago</span> <span class="reply_id unimportant"><a href="/topic/' . $_GET['id'] . '">#' . number_format($_GET['id']) . '</a></span></strong></h3> <div class="body">';

if($topic_image_name)
{
	echo '<a href="/img/' . htmlspecialchars($topic_image_name) . '"><img src="/thumbs/' . htmlspecialchars($topic_image_name) . '" alt="" /></a>';
}

echo parse($topic_body);

edited_message($topic_time, $topic_edit_time, $topic_edit_mod);

echo '<ul class="menu">';

if
(
	$topic_author == $_SESSION['UID'] && TIME_TO_EDIT == 0 || 
	$topic_author == $_SESSION['UID'] && ( $_SERVER['REQUEST_TIME'] - $topic_time < TIME_TO_EDIT ) || 
	$moderator || $administrator 
)
{
	echo '<li><a href="/edit_topic/' . $_GET['id'] . '">Edit</a></li>';
}

if($moderator || $administrator)
{
	echo '<li><a href="/delete_topic/' . $_GET['id'] . '" onclick="return submitDummyForm(\'/delete_topic/' . $_GET['id'] . '\', \'id\', ' . $_GET['id'] . ', \'Really delete this topic?\');">Delete</a></li>';
}
echo '<li><a href="/watch_topic/' . $_GET['id'] . '" onclick="return submitDummyForm(\'/watch_topic/' . $_GET['id'] . '\', \'id\', ' . $_GET['id'] . ', \'Really watch this topic?\');">Watch</a></li> <li><a href="/new_reply/' . $_GET['id'] . '/quote_topic">Quote</a></li><li><a href="/trivia_for_topic/' . $_GET['id'] . '" class="help" title="' . $topic_replies . ' repl' . ($topic_replies == 1 ? 'y' : 'ies') . '">' . $topic_visits . ' visit' . ($topic_visits == 1 ? '' : 's') . '</a></li></ul></div>';

// Output replies.
if(ALLOW_IMAGES)
{
	$stmt = $link->prepare('SELECT replies.id, replies.time, replies.author, replies.author_name, replies.poster_number, replies.body, replies.edit_time, replies.edit_mod, images.file_name FROM replies LEFT OUTER JOIN images ON replies.id = images.reply_id WHERE replies.parent_id = ? ORDER BY id');
}
else
{
	$stmt = $link->prepare('SELECT id, time, author, author_name, poster_number, body, edit_time, edit_mod FROM replies WHERE parent_id = ? ORDER BY id');
}
$stmt->bind_param('i', $_GET['id']);
$stmt->execute();
if(ALLOW_IMAGES)
{
	$stmt->bind_result($reply_id, $reply_time, $reply_author, $reply_author_name, $reply_poster_number, $reply_body, $reply_edit_time, $reply_edit_mod, $reply_image_name);
}
else
{
	$stmt->bind_result($reply_id, $reply_time, $reply_author, $reply_author_name, $reply_poster_number, $reply_body, $reply_edit_time, $reply_edit_mod);
}

$reply_ids = array();
$posters = array();
$hidden_replies = array(); // ostrich mode
$previous_poster = $topic_author;
$previous_post_time = $topic_time;
$posts_in_row = 0; // number of posts in a row by one UID
$tuple = array
(
	1 => 'double',
	2 => 'triple',
	3 => 'quadruple',
	4 => 'quintuple'
);
				
while($stmt->fetch()) 
{
	// Should we even bother?
	if($_COOKIE['ostrich_mode'] == 1)
	{
		foreach($ignored_phrases as $ignored_phrase)
		{
			if(stripos($reply_body, $ignored_phrase) !== false)
			{
				$hidden_replies[] = $reply_id;
				$reply_ids[$reply_id] = array
				(
					'body' => $reply_body,
					'author' => $reply_author
				);
				// We've encountered an ignored phrase, so skip the rest of this while() iteration.
				continue 2;
			}
		}
	}
	
	// We should!
	$out = array(); // output variables
	
	if($reply_author == 'admin')
	{
		$out['author'] = '<strong class="admin">' . ADMIN_NAME . '</strong>';
	}
	else
	{
		if($moderator || $administrator)
		{
			$out['author'] = '<a href="/profile/' . $reply_author . '">';
		}
		
		if(empty($reply_author_name))
		{
			$out['author'] .= 'Anonymous <strong>';
			if($reply_author == $topic_author)
			{
				$out['author'] .= 'A';
			}
			else
			{
				$out['author'] .= number_to_letter($reply_poster_number);
			}
			$out['author'] .= '</strong>';
		}
		else
		{
			$out['author'] .= trip($reply_author_name);
		}
		
		if($moderator || $administrator)
		{
			$out['author'] .= '</a>';
		}
	}
	
	if($reply_author == $topic_author)
	{
		$out['author_desc'] = '(OP';
		if($reply_author == $_SESSION['UID'])
		{
			$out['author_desc'] .= ', you';
		}
		$out['author_desc'] .=  ')';
	}
	else
	{
		if($reply_author == $_SESSION['UID'])
		{
			$out['author_desc'] .=  '(you)';
		}
		if( ! in_array($reply_author, $posters))
		{
			$out['action'] = 'joined in and ';
		}
	}
	
	if($reply_author == $previous_poster && $posts_in_row < 4)
	{
		$posts_in_row++;
		$out['action'] .= $tuple[$posts_in_row] . '-posted';
	}
	else 
	{
		$posts_in_row = 0;
		$out['action'] .= 'replied with';
	}
	
	// Now, output the reply.
	echo '<h3 id="reply_' . $reply_id . '">';
	
	// If this is the newest unread post, let the #new anchor highlight it.
	if(count($reply_ids) == $last_read_post)
	{
		echo '<span id="new"></span><input type="hidden" id="new_id" class="noscreen" value="' . $reply_id . '" />';
	}
	
	// The content of the header:
	echo $out['author'] . ' ' . $out['author_desc'] . ' ' . $out['action'] . ' this <strong><span class="help" title="' . format_date($reply_time) . '">' . calculate_age($reply_time) . ' ago</span></strong>, ' . calculate_age($reply_time, $previous_post_time) . ' later';
	if( ! empty($posters)) //if not first reply
	{
		echo ', ' . calculate_age($reply_time, $topic_time) . ' after the original post';
	}
	
	// Finish the header and begin outputting the body.
	echo '<span class="reply_id unimportant"><a href="#reply_' . $reply_id . '" onclick="highlightReply(\'' . $reply_id . '\'); removeSnapbackLink">#' . number_format($reply_id) . '</a></span></h3> <div class="body" id="reply_box_' . $reply_id . '">';
	if($reply_image_name)
	{
		echo '<a href="/img/' . htmlspecialchars($reply_image_name) . '"><img src="/thumbs/' . htmlspecialchars($reply_image_name) . '" alt="" /></a>';
	}
	
	$reply_body_parsed = parse($reply_body);
	
	// Linkify citations. (This might be updated to use preg_replace_callback in the future.)
	preg_match_all('/^@([0-9,]+)/m', $reply_body_parsed, $matches);
	foreach($matches[0] as $formatted_id)
	{
		$you = '';
	
		$pure_id = str_replace(array('@', ','), '', $formatted_id);
		if(!array_key_exists($pure_id, $reply_ids))
		{
			$reply_body_parsed = str_replace($formatted_id, '<span class="unimportant">(Citing a deleted or non-existent reply.)</span>', $reply_body_parsed);
		}
		else if(in_array($pure_id, $hidden_replies))
		{
			$reply_body_parsed = str_replace($formatted_id, '<span class="unimportant help" title="' . snippet($reply_ids[$pure_id]['body']) . '">@hidden</span>', $reply_body_parsed);
		}
		else
		{
			if($pure_id == $previous_id)
			{
				$link_text = '@previous';
			}
			else
			{
				$link_text = $formatted_id;
			}
			
			if($reply_ids[$pure_id]['author'] == $_SESSION['UID'])
			{
				$you = '<span class="unimportant"> (you)</span>';
			}
			
			$reply_body_parsed = str_replace($formatted_id, '<a href="#reply_' . $pure_id . '" onclick="highlightReply(\'' . $pure_id . '\'); createSnapbackLink(\'' . $reply_id . '\')" class="unimportant help" title="' . snippet($reply_ids[$pure_id]['body']) . '">' . $link_text . '</a>' . $you, $reply_body_parsed);
		}
	}
	$reply_body_parsed = preg_replace('/^@OP/', '<span class="unimportant">@OP</span>', $reply_body_parsed);
	
	echo $reply_body_parsed;

	edited_message($reply_time, $reply_edit_time, $reply_edit_mod);
	
	echo '<ul class="menu">';
	
	if
	(
		$reply_author == $_SESSION['UID'] && TIME_TO_EDIT == 0 || 
		$reply_author == $_SESSION['UID'] && ( $_SERVER['REQUEST_TIME'] - $reply_time < TIME_TO_EDIT ) || 
		$moderator || $administrator 
	)
	{
			echo '<li><a href="/edit_reply/' . $_GET['id'] . '/' . $reply_id . '">Edit</a></li>';
	}
	
	if($moderator || $administrator)
	{
		echo '<li><a href="/delete_reply/' . $reply_id . '" onclick="return submitDummyForm(\'/delete_reply/' . $reply_id . '\', \'id\', ' . $reply_id . ', \'Really delete this reply?\');">Delete</a></li>';
	}
	echo '<li><a href="/new_reply/' . $_GET['id'] . '/quote_reply/' . $reply_id . '">Quote</a></li><li><a href="/new_reply/' . $_GET['id'] . '/cite_reply/' . $reply_id . '">Cite</a></li></ul></div>';
	
	// Store information for the next round.
	$reply_ids[$reply_id] = array
	(
		'body' => $reply_body,
		'author' => $reply_author
	);
	$posters[] = $reply_author;
	$previous_poster = $reply_author;
	$previous_id = $reply_id;
	$previous_post_time = $reply_time;
}
$stmt->close();

echo '<ul class="menu"><li><a href="/new_reply/' . $_GET['id'] . '">New reply</a></li></ul>';

require('includes/footer.php');

?>
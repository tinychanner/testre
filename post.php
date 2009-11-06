<?php

require('includes/header.php');
force_id();

if($_GET['reply'])
{
	$reply = true;
	$onload_javascript = 'focusId(\'body\'); init();';
	
	if( ! ctype_digit($_GET['reply'])) 
	{
		add_error('Invalid topic ID.', true);
	}
	
	$stmt = $link->prepare('SELECT headline, author, author_name, replies FROM topics WHERE id = ?');
	$stmt->bind_param('i', $_GET['reply']);
	$stmt->execute();
	$stmt->store_result();
	if($stmt->num_rows < 1)
	{
		$page_title = 'Non-existent topic';
		add_error('There is no such topic. It may have been deleted.', true);
	}
	$stmt->bind_result($replying_to, $topic_author, $topic_author_name, $topic_replies);
	$stmt->fetch();
	$stmt->close();
	
	update_activity('replying', $_GET['reply']);
	$page_title = 'New reply in topic: <a href="/topic/' . $_GET['reply'] . '">' . htmlspecialchars($replying_to) . '</a>';
	
	$check_watchlist = $link->prepare('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?');
	$check_watchlist->bind_param('si', $_SESSION['UID'], $_GET['reply']);
	$check_watchlist->execute();
	$check_watchlist->store_result();
	if($check_watchlist->num_rows > 0)
	{
		$watching_topic = true;
	}
	$check_watchlist->close();
	
	// Check for previous post
	$already_replied = false;
	if($_SESSION['UID'] === $topic_author)
	{
		$already_replied = true;
		$previous_trip = $topic_author_name;
	}
	else if( ! $_GET['edit'])
	{
		$get_trip = $link->prepare('SELECT author_name FROM replies WHERE author = ? AND parent_id = ? LIMIT 1');
		$get_trip->bind_param('si', $_SESSION['UID'], $_GET['reply']);
		$get_trip->execute();
		$get_trip->store_result();
		if($get_trip->num_rows > 0)
		{
			$already_replied = true;
			$get_trip->bind_result($previous_trip);
			$get_trip->fetch();
		}
		$get_trip->close();
	}
}
else // this is a topic
{
	$reply = false;
	$onload_javascript = 'focusId(\'headline\'); init();';
	update_activity('new_topic');
	
	$page_title = 'New topic';
	
	if( ! empty($_POST['headline']))
	{
		$page_title .= ': ' . htmlspecialchars($_POST['headline']);
	}
}

// If we're trying to edit and it's not disabled in the configuration ...
if(ALLOW_EDIT && ctype_digit($_GET['edit']))
{
	$editing = true;
	
	if($reply)
	{
		$fetch_edit = $link->prepare('SELECT author, time, body, edit_mod FROM replies WHERE id = ?');
	}
	else
	{
		$fetch_edit = $link->prepare('SELECT author, time, body, edit_mod, headline FROM topics WHERE id = ?');
	}
		
	$fetch_edit->bind_param('i', $_GET['edit']);
	$fetch_edit->execute();
	$fetch_edit->store_result();
	if($fetch_edit->num_rows < 1)
	{
		add_error('There is no such post. It may have been deleted.', true);
	}
		
	if($reply)
	{
		$fetch_edit->bind_result($edit_data['author'], $edit_data['time'], $edit_data['body'], $edit_data['mod']);
		$page_title = 'Editing <a href="/topic/' . $_GET['reply'] . '#reply_' . $_GET['edit'] . '">reply</a> to topic: <a href="/topic/' . $_GET['reply'] . '">' . htmlspecialchars($replying_to) . '</a>';
	}
	else
	{
		$fetch_edit->bind_result($edit_data['author'], $edit_data['time'], $edit_data['body'], $edit_data['mod'], $edit_data['headline']);
		$page_title = 'Editing topic';
	}
		
	$fetch_edit->fetch();
	$fetch_edit->close();
	
	if($edit_data['author'] === $_SESSION['UID'])
	{
		$edit_mod = 0;
		
		if( ! $administrator && ! $moderator)
		{
			if(TIME_TO_EDIT != 0 && ( $_SERVER['REQUEST_TIME'] - $edit_data['time'] > TIME_TO_EDIT ))
			{
				add_error('You can no longer edit your post.', true);
			}
			if($edit_data['mod'])
			{
				add_error('You cannot edit a post that has been edited by a moderator.');
			}
		}
	}
	else if($administrator || $moderator)
	{
		$edit_mod = 1;
	}
	else
	{
		add_error('You are not allowed to edit that post.', true);
	}
	
	if( ! $_POST['form_sent'])
	{
		$body = $edit_data['body'];
		
		if( ! $reply)
		{
			$page_title .= ': <a href="/topic/' . $_GET['edit'] . '">' . htmlspecialchars($edit_data['headline']) . '</a>';
			$headline = $edit_data['headline'];
		}
	}
	else if( ! empty($_POST['headline']))
	{
		$page_title .= ':  <a href="/topic/' . $_GET['edit'] . '">' . htmlspecialchars($_POST['headline']) . '</a>';
	}
}

if($_POST['form_sent'])
{
	// Trimming.
	$headline = super_trim($_POST['headline']);
	$body = super_trim($_POST['body']);

	// Parse for mass quote tag ([quote]). I'm not sure about create_function, it seems kind of slow.
	$body = preg_replace_callback(
									'/\[quote\](.+?)\[\/quote\]/s',
									create_function(
														'$matches', 
														'return preg_replace(\'/.*[^\s]$/m\', \'> $0\', $matches[1]);'
													),
									$body
								 );

	if($_POST['post']) 
	{
		// Check for poorly made bots.
		if( ! $editing && $_SERVER['REQUEST_TIME'] - $_POST['start_time'] < 3 )
		{
			add_error('Wait a few seconds between starting to compose a post and actually submitting it.');
		}
		if( ! empty($_POST['e-mail']))
		{
			add_error('Bot detected.');
		}
		if( ! is_array($_SESSION['random_posting_hashes']) ) 
		{
			add_error('Session error (no hash values stored). Try again.');
		}
		else foreach($_SESSION['random_posting_hashes'] as $name => $value) 
		{
			if( ! isset($_POST[$name]) || $_POST[$name] != $value) 
			{
				add_error('Session error (wrong hash value sent). Try again.');
				break;
			}
		}
		
		check_length($body, 'body', MIN_LENGTH_BODY, MAX_LENGTH_BODY);
		
		if(count( explode("\n", $body) ) > MAX_LINES)
		{
			add_error('Your post has too many lines.');
		}
		
		// Check for UID ban.
		$check_uid_ban = $link->prepare('SELECT filed FROM uid_bans WHERE uid = ?');
		$check_uid_ban->bind_param('s', $_SESSION['UID']);
		$check_uid_ban->execute();
		$check_uid_ban->store_result();
		if($check_uid_ban->num_rows > 0)
		{
			$check_uid_ban->bind_result($ban_filed);
			$check_uid_ban->fetch();
			
			$time_since_ban = $_SERVER['REQUEST_TIME'] - $ban_filed;
			if($time_since_ban < BAN_PERIOD)
			{
				add_error('You are banned. Your ban will expire in ' . calculate_age( $_SERVER['REQUEST_TIME'], $ban_filed + BAN_PERIOD ) . '.');
			}
			else
			{
				remove_id_ban($_SESSION['UID']);
			}
		}
		$check_uid_ban->close();
		
		
		// Check for IP address ban.
		$check_ip_ban = $link->prepare('SELECT expiry FROM ip_bans WHERE ip_address = ?');
		$check_ip_ban->bind_param('s', $_SERVER['REMOTE_ADDR']);
		$check_ip_ban->execute();
		$check_ip_ban->store_result();
		if($check_ip_ban->num_rows > 0)
		{
			$check_ip_ban->bind_result($ban_expiry);
			$check_ip_ban->fetch();
			
			if($ban_expiry == 0 | $ban_expiry > $_SERVER['REQUEST_TIME'])
			{
				$error_message = 'Your IP address is banned. ';
				if($ban_expiry > 0)
				{
					$error_message .= 'This ban will expire in ' . calculate_age($ban_expiry) . '.';
				}
				else
				{
					$error_message .= 'This ban is not set to expire.';
				}
				add_error($error_message);
			}
			else
			{
				remove_ip_ban($_SERVER['REMOTE_ADDR']);
			}
		}
		$check_ip_ban->close();
		
		if(ALLOW_IMAGES && ! empty($_FILES['image']['name']) && ! $editing)
		{
			$image_data = array();
			
			 switch($_FILES['image']['error']) 
			 {
				case UPLOAD_ERR_OK:
					$uploading = true;
				break;
				
				case UPLOAD_ERR_PARTIAL:
					add_error('The image was only partially uploaded.');
				break;
				
				case UPLOAD_ERR_INI_SIZE:
					add_error('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
				break;
				
				case UPLOAD_ERR_NO_FILE:
					add_error('No file was uploaded.');
				break;
				
				case UPLOAD_ERR_NO_TMP_DIR:
					add_error('Missing a temporary directory.');
				break;
				
				case UPLOAD_ERR_CANT_WRITE:
					add_error('Failed to write image to disk.');
				break;
				
				default:
					add_error('Unable to upload image.');
			}
			
			if($uploading)
			{
				$uploading = false; // until we make our next checks
				$valid_types = array
				(
					'jpg',
					'gif',
					'png'
				);
					
				$valid_name = preg_match('/(.+)\.([a-z0-9]+)$/i', $_FILES['image']['name'], $match);
				$image_data['type']		= strtolower($match[2]);
				$image_data['md5'] 		= md5_file($_FILES['image']['tmp_name']);
				$image_data['name'] 	= str_replace( array('.', '/', '<', '>', '"', "'", '%') , '', $match[1]);
				$image_data['name'] 	= substr( trim($image_data['name']) , 0, 35);
				
				if($image_data['type'] == 'jpeg')
				{
					$image_data['type'] = 'jpg';
				}
				
				if(file_exists('img/' . $image_data['name'] . '.' . $image_data['type']))
				{
					$image_data['name'] = $_SERVER['REQUEST_TIME'] . mt_rand(0, 99);
				}

				if($valid_name === 0 || empty($image_data['name']))
				{
					add_error('The image has an invalid file name.');
				}
				else if( ! in_array($image_data['type'], $valid_types))
				{
					add_error('Only <strong>GIF</strong>, <strong>JPEG</strong> and <strong>PNG</strong> files are allowed.');
				}
				else if($_FILES['image']['size'] > MAX_IMAGE_SIZE)
				{
					add_error('Uploaded images can be no greater than ' . round(MAX_IMAGE_SIZE / 1048576, 2) . ' MB. ');
				}
				else
				{
					$uploading = true;
					$image_data['name'] = $image_data['name'] . '.' . $image_data['type'];
				}
			}
		}
		
		// Set the author (internal use only)
		$author = $_SESSION['UID'];
		if(isset($_POST['admin']) && $administrator)
		{
			$author = 'admin';
		}
		
		// Take care of trips/namefaggotry (public)
		if($already_replied)
		{
			$author_name = $previous_trip;
		}
		else if( ! empty($_POST['trip']))
		{
			list($author_name, $author_trip) = explode('#', $_POST['trip'], 2);
			$author_name = super_trim($author_name);
			
			check_length($author_name, 'name', 0, 25);
			
			if(strtolower($author_name) == strtolower(ADMIN_NAME))
			{
				add_error('That name is reserved for the administrator.');
			}
			else if(preg_match('/^Anonymous\s[A-Z]$/', $author_name))
			{
				add_error('That name is reserved.');
			}
			
			if( ! empty($author_trip))
			{
				setcookie('trip', $author_name . '#' . $author_trip, $_SERVER['REQUEST_TIME'] + 315569260, '/');
			
				$author_trip = mb_convert_encoding($author_trip, 'SJIS', 'UTF-8');
				$trip_salt = preg_replace('[^.-z]', '.', substr($author_trip . "H.", 1, 2));
				$trip_salt = strtr($trip_salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
				$author_trip = substr( crypt($author_trip, $trip_salt), -10);
				
				$author_name = $author_name . '#' . $author_trip;
			}
			
			if($reply)
			{
				$unique_trip = $link->prepare('SELECT 1 FROM replies WHERE LOWER(author_name) = ? AND parent_id = ? LIMIT 1');
				$unique_trip->bind_param('si', strtolower($author_name), $_GET['reply']);
				$unique_trip->execute();
				$unique_trip->store_result();
				if($unique_trip->num_rows > 0 || ($_SESSION['UID'] !== $topic_author && $author_name == $topic_author_name))
				{
					add_error('Someone else is already using that name in this thread.');
				}
				$unique_trip->close();
			}
		}
		if(empty($author_name))
		{
			$author_name = null;
		}
		
		// If this is a reply...
		if($reply) 
		{	
			if( ! $editing)
			{
				//Lurk more?
				if($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_REPLY)
				{
					add_error('Lurk for at least ' . REQUIRED_LURK_TIME_REPLY . ' seconds before posting your first reply.');
				}
				
				// Flood control.
				$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_REPLY;
				$stmt = $link->prepare('SELECT 1 FROM replies WHERE author_ip = ? AND time > ?');
				$stmt->bind_param('si', $_SERVER['REMOTE_ADDR'], $too_early);
				$stmt->execute();

				$stmt->store_result();
				if($stmt->num_rows > 0)
				{
					add_error('Wait at least ' . FLOOD_CONTROL_REPLY . ' seconds between each reply. ');
				}
				$stmt->close();
			
				// Get letter, if applicable.
				if($_SESSION['UID'] == $topic_author)
				{
					$poster_number = 0;
				}
				else // we are not the topic author
				{
					$stmt = $link->prepare('SELECT poster_number FROM replies WHERE parent_id = ? AND author = ? LIMIT 1');
					$stmt->bind_param('is', $_GET['reply'], $author);
					$stmt->execute();
					$stmt->bind_result($poster_number);
					$stmt->fetch();
					$stmt->close();
					
					// If the user has not already replied to this thread, get a new letter.
					if(empty($poster_number))
					{
						// We need to lock the table to prevent others from selecting the same letter.
						$unlock_table = true;
						$link->real_query('LOCK TABLE replies WRITE');
						
						$stmt = $link->prepare('SELECT poster_number FROM replies WHERE parent_id = ? ORDER BY poster_number DESC LIMIT 1');
						$stmt->bind_param('i', $_GET['reply']);
						$stmt->execute();
						$stmt->bind_result($last_number);
						$stmt->fetch();
						$stmt->close();
						
						if(empty($last_number))
						{
							$poster_number = 1;
						}
						else
						{
							$poster_number = $last_number + 1;
						}
					}
				}
		
				$stmt = $link->prepare('INSERT INTO replies (author, author_name, author_ip, poster_number, parent_id, body, time) VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())');
				$stmt->bind_param('sssiis', $author, $author_name, $_SERVER['REMOTE_ADDR'], $poster_number, $_GET['reply'], $body);
				$congratulation = 'Reply posted.';
			}
			else // editing
			{
				$stmt = $link->prepare('UPDATE replies SET body = ?, edit_mod = ?, edit_time = UNIX_TIMESTAMP() WHERE id = ?');
				$stmt->bind_param('sii', $body, $edit_mod, $_GET['edit']);
				$congratulation = 'Reply edited.';
			}
		}
		else { // or a topic...
			check_length($headline, 'headline', MIN_LENGTH_HEADLINE, MAX_LENGTH_HEADLINE);
			
			if( ! $editing)
			{
				//Lurk more?
				if($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_TOPIC)
				{
					add_error('Lurk for at least ' . REQUIRED_LURK_TIME_TOPIC . ' seconds before posting your first topic.');
				}
				
				// Flood control.
				$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_TOPIC;
				$stmt = $link->prepare('SELECT 1 FROM topics WHERE author_ip = ? AND time > ?');
				$stmt->bind_param('si', $_SERVER['REMOTE_ADDR'], $too_early);
				$stmt->execute();

				$stmt->store_result();
				if($stmt->num_rows > 0)
				{
					add_error('Wait at least ' . FLOOD_CONTROL_TOPIC . ' seconds before creating another topic. ');
				}
				$stmt->close();
				
				// Prepare our query...
				$stmt = $link->prepare('INSERT INTO topics (author, author_name, author_ip, headline, body, last_post, time) VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())');
				$stmt->bind_param('sssss', $author, $author_name, $_SERVER['REMOTE_ADDR'], $headline, $body);
				$congratulation = 'Topic created.';
			}
			else // editing
			{
				$stmt = $link->prepare('UPDATE topics SET headline = ?, body = ?, edit_mod = ?, edit_time = UNIX_TIMESTAMP() WHERE id = ?');
				$stmt->bind_param('ssii', $headline, $body, $edit_mod, $_GET['edit']);
				$congratulation = 'Topic edited.';
			}
		}
		
		// If all is well, execute!
		if( ! $erred) {
			$stmt->execute();
			
			if($unlock_table)
			{
				$link->real_query('UNLOCK TABLE');
			}
			
			if($stmt->affected_rows > 0)
			{
				// We did it!
				if( ! $editing)
				{
					setcookie('last_bump', time(), $_SERVER['REQUEST_TIME'] + 315569260, '/');
					if($reply)
					{
						// Update last bump.
						$link->real_query("UPDATE last_actions SET time = UNIX_TIMESTAMP() WHERE feature = 'last_bump'");
					
						$increment_replies = $link->prepare('UPDATE topics SET replies = replies + 1, last_post = UNIX_TIMESTAMP() WHERE id = ?');
						$increment_replies->bind_param('i', $_GET['reply']);
						$increment_replies->execute();
						$increment_replies->close();
					}
					else // if topic
					{
						// Do not change the time() below to REQUEST_TIME. The script execution may have taken a second.
						setcookie('last_topic', time(), $_SERVER['REQUEST_TIME'] + 315569260, '/');
						//Update last topic and last bump, for people using the "date created" order option in the dashboard.
						$link->real_query("UPDATE last_actions SET time = UNIX_TIMESTAMP() WHERE feature = 'last_topic' OR feature = 'last_bump'");
					}
				}
				
				// Sort out what topic we're affecting and where to go next. Way too fucking long.
				if( ! $editing)
				{
					$inserted_id = $stmt->insert_id;
					
					if($reply)
					{
						$target_topic = $_GET['edit'];
						$redir_loc = $_GET['reply'] . '#reply_' . $inserted_id;
					}
					else // if topic
					{
						$target_topic = $inserted_id;
						$redir_loc = $inserted_id;
					}
				}
				else // if editing
				{
					if($reply)
					{
						$target_topic = $_GET['reply'];
						$redir_loc = $_GET['reply'] . '#reply_' . $_GET['edit'];
					}
					else // if topic
					{
						$target_topic = $_GET['edit'];
						$redir_loc = $_GET['edit'];
					}
				}
				
				// Take care of the upload.
				if($uploading)
				{
					// Check if this image is already on the server.
					$duplicate_check = $link->prepare('SELECT file_name FROM images WHERE md5 = ?');
					$duplicate_check->bind_param('s', $image_data['md5']);
					$duplicate_check->execute();
					$duplicate_check->bind_result($previous_image);
					$duplicate_check->fetch();
					$duplicate_check->close();
					
					// If the file has been uploaded before this, just link the old version.
					if($previous_image)
					{
						$image_data['name'] = $previous_image;
					}
					// Otherwise, keep the new image and make a thumbnail.
					else
					{
						thumbnail($_FILES['image']['tmp_name'], $image_data['name'], $image_data['type']);
						move_uploaded_file($_FILES['image']['tmp_name'], 'img/' . $image_data['name']);
					}
					
					if($reply)
					{
						$insert_image = $link->prepare('INSERT INTO images (file_name, md5, reply_id) VALUES (?, ?, ?)');
					}
					else
					{
						$insert_image = $link->prepare('INSERT INTO images (file_name, md5, topic_id) VALUES (?, ?, ?)');
					}
					$insert_image->bind_param('ssi', $image_data['name'], $image_data['md5'], $inserted_id);
					$insert_image->execute();
					$insert_image->close();
				}
				
				// Add topic to watchlist if desired.
				if($_POST['watch_topic'] && ! $watching_topic)
				{
					$add_watchlist = $link->prepare('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)');
					$add_watchlist->bind_param('si', $_SESSION['UID'], $target_topic);
					$add_watchlist->execute();
					$add_watchlist->close();
				}
				
				// The random shit is only good for one post to prevent spambots from reusing the same form data again and again.
				unset($_SESSION['random_posting_hashes']);
				// Set the congratulation notice and redirect to affected topic or reply.
				redirect($congratulation, 'topic/' . $redir_loc);
			}
			else // Our query failed ;_;
			{
				add_error('Database error.');
			}
			
			$stmt->close();
		}
		// If we erred, insert this into failed postings.
		else
		{
			if($unlock_table)
			{
				$link->real_query('UNLOCK TABLE');
			}
			
			if($reply)
			{
				$add_fail = $link->prepare('INSERT INTO failed_postings (time, uid, reason, body) VALUES (UNIX_TIMESTAMP(), ?, ?, ?)');
				$add_fail->bind_param('sss', $_SESSION['UID'], serialize($errors), substr($body, 0, MAX_LENGTH_BODY));
			}
			else
			{
				$add_fail = $link->prepare('INSERT INTO failed_postings (time, uid, reason, body, headline) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)');
				$add_fail->bind_param('ssss', $_SESSION['UID'], serialize($errors), substr($body, 0, MAX_LENGTH_BODY), substr($headline, 0, MAX_LENGTH_HEADLINE));
			}
			$add_fail->execute();
			$add_fail->close();
		}
	}
}

print_errors();

// For the bot check.
$start_time = $_SERVER['REQUEST_TIME'];
if( ctype_digit($_POST['start_time']) )
{
	$start_time = $_POST['start_time'];
}

echo '<div>';

// Check if OP.
if($reply && ! $editing) 
{
		echo '<p>You <strong>are';
		if($_SESSION['UID'] !== $topic_author)
		{
			echo ' not';
		}
		echo '</strong> recognized as the original poster of this topic.</p>';
}

// Print deadline for edit submission.
if($editing && TIME_TO_EDIT != 0 && ! $moderator && ! $administrator)
{
	echo '<p>You have <strong>' . calculate_age( $_SERVER['REQUEST_TIME'], $edit_data['time'] + TIME_TO_EDIT ) . '</strong> left to finish editing this post.</p>';
}

// Print preview.
if($_POST['preview'] && ! empty($body))
{
	$preview_body = parse($body);
	$preview_body = preg_replace('/^@([0-9,]+|OP)/m', '<span class="unimportant"><a href="#">$0</a></span>', $preview_body);
	echo '<h3 id="preview">Preview</h3><div class="body standalone">' . $preview_body . '</div>';
}

// Check if any new replies have been posted since we last viewed the topic.
if($reply && isset($visited_topics[ $_GET['reply'] ]) && $visited_topics[ $_GET['reply'] ] < $topic_replies)
{
	$new_replies = $topic_replies - $visited_topics[$_GET['reply']];
	echo '<p><a href="/topic/' . $_GET['reply'] . '#new"><strong>' . $new_replies . '</strong> new repl' . ($new_replies == 1 ? 'y</a> has' : 'ies</a> have') . ' been posted in this topic since you last checked!</p>';
}

// Print the main form.
	
?>
	
	<form action="" method="post"<?php if(ALLOW_IMAGES) echo ' enctype="multipart/form-data"' ?>>
		<div class="noscreen">
			<input name="form_sent" type="hidden" value="1" />
			<input name="e-mail" type="hidden" />
			<input name="start_time" type="hidden" value="<?php echo $start_time ?>" />
			<?php
			// For the bot check.
			if( ! is_array($_SESSION['random_posting_hashes']) )
			{
				for($i = 0, $max = mt_rand(3, 12); $i < $max; ++$i) 
				{
					$_SESSION['random_posting_hashes'][ dechex(mt_rand()) ] =  dechex(mt_rand());
				}
			}
			
			foreach($_SESSION['random_posting_hashes'] as $name => $value)
			{
				$attributes = array
				(
					'name="' . $name . '"',
					'value="' . $value . '"',
					'type="hidden"'
				);
				// To make life harder for bots, print the elements in a random order.
				shuffle($attributes);
				echo '<input ' . implode(' ', $attributes) . ' />' . "\n\t\t\t";
			}
			?>
			
		</div>
		
		<?php if( ! $reply): ?>
		<div class="row">
			<label for="headline">Headline</label> <script type="text/javascript"> printCharactersRemaining('headline_remaining_characters', 100); </script>
			<input id="headline" name="headline" tabindex="1" type="text" size="124" maxlength="100" onkeydown="updateCharactersRemaining('headline', 'headline_remaining_characters', 100);" onkeyup="updateCharactersRemaining('headline', 'headline_remaining_characters', 100);" value="<?php if($_POST['form_sent'] || $editing) echo htmlspecialchars($headline) ?>" />
		</div>
		<?php endif; ?>
		
		<?php if( ! $already_replied && ! $editing): ?>
		<div class="row">
			<label for="trip">Name</label> (optional)
			<input id="trip" name="trip" tabindex="2" type="text" size="25" maxlength="50" value="<?php
			if( ! empty($_POST['trip'])) 
			{
				echo htmlspecialchars($_POST['trip']) ;
			}
			else if( ! empty($_COOKIE['trip']))
			{
				echo htmlspecialchars($_COOKIE['trip']);
			}
			?>" />
		</div>
		<?php endif; ?>
		
		<div class="row">
			<label for="body" class="noscreen">Post body</label> 
			<textarea name="body" cols="120" rows="18" tabindex="3" id="body"><?php
			// If we've had an error or are previewing, print the submitted text.
			if($_POST['form_sent'] || $editing)
			{
				echo sanitize_for_textarea($body);
			}
			
			// Otherwise, fetch any text we may be quoting.
			else if(isset($_GET['quote_topic']) || ctype_digit($_GET['quote_reply']))
			{
				// Fetch the topic...
				if(isset($_GET['quote_topic']))
				{
					$stmt = $link->prepare('SELECT body FROM topics WHERE id = ?');
					$stmt->bind_param('i', $_GET['reply']);
				}
				// ... or a reply.
				else
				{
					echo '@' . number_format($_GET['quote_reply']) . "\n\n";
					
					$stmt = $link->prepare('SELECT body FROM replies WHERE id = ?');
					$stmt->bind_param('i', $_GET['quote_reply']);
				}
				
				// Execute it.
				$stmt->execute();
				$stmt->bind_result($quoted_text);
				$stmt->fetch();
				$stmt->close();
				
				// Snip citations from quote.
				$quoted_text = trim( preg_replace('/^@([0-9,]+|OP)/m', '', $quoted_text) );
				
				//Prefix newlines with >
				$quoted_text = preg_replace('/^/m', '> ', $quoted_text);
				
				echo sanitize_for_textarea($quoted_text) . "\n\n";
			}
			
			// If we're just citing, print the citation.
			else if(ctype_digit($_GET['cite']))
			{
				echo '@' . number_format($_GET['cite']) . "\n\n";
			}
			
			echo '</textarea>';
			
			if(ALLOW_IMAGES && ! $editing)
			{
				echo '<label for="image" class="noscreen">Image</label> <input type="file" name="image" id="image" />';
			}
			?>
			
			<p><a href="/markup_syntax">Markup syntax</a>: <kbd>''</kbd> on each side of a word or part of text = <em>emphasis</em>. <kbd>'''</kbd> = <strong>strong emphasis</strong>. <kbd>></kbd> on the beginning of a line = quote. To mass quote a long section of text, surround it with <kbd>[quote]</kbd> tags. <abbr>URL</abbr>s are automatically linkified.</p>
		</div>
		
		<?php 
		if( ! $watching_topic) 
		{ 	
			echo '<div class="row"><label for="watch_topic" class="inline">Watch topic</label> <input type="checkbox" name="watch_topic" id="watch_topic" class="inline"';
			if($_POST['watch_topic'])
			{
				echo ' checked="checked"';
			}
			echo ' /></div>';
		}
		if($administrator && ! $editing)
		{
			echo '<div class="row"><label for="admin" class="inline">Post as admin</label> <input type="checkbox" name="admin" id="admin" class="inline"></div>';
		}
		?>
			
		
		<div class="row">
			<input type="submit" name="preview" tabindex="4" value="Preview" class="inline"<?php if(ALLOW_IMAGES) echo ' onclick="document.getElementById(\'image\').value=\'\'"' ?> /> 
			<input type="submit" name="post" tabindex="5" value="<?php echo ($editing) ? 'Update' : 'Post' ?>" class="inline">
		</div>
	</form>
</div>

<?php

// If citing, fetch and display the reply in question.
if(ctype_digit($_GET['cite']))
{
	$stmt = $link->prepare('SELECT body, poster_number FROM replies WHERE id = ?');
	$stmt->bind_param('i', $_GET['cite']);
	$stmt->execute();
	$stmt->bind_result($cited_text, $poster_number);
	$stmt->fetch();
	$stmt->close();
	
	if( ! empty($cited_text))
	{
		$cited_text = parse($cited_text);
	
		// Linkify citations within the text.
		preg_match_all('/^@([0-9,]+)/m', $cited_text, $matches);
		foreach($matches[0] as $formatted_id)
		{
			$pure_id = str_replace( array('@', ',') , '', $formatted_id);

			$cited_text = str_replace($formatted_id, '<a href="/topic/' . $_GET['reply'] . '#reply_' . $pure_id . '" class="unimportant">' . $formatted_id . '</a>', $cited_text);
		}
		
		// And output it!
		echo '<h3 id="replying_to">Replying to Anonymous ' . number_to_letter($poster_number) . '&hellip;</h3> <div class="body standalone">' . $cited_text . '</div>';
	}
}
// If we're not citing or quoting, display the original post.
else if($reply && ! isset($_GET['quote_topic']) && ! isset($_GET['quote_reply']) && ! $editing)
{
	$stmt = $link->prepare('SELECT body FROM topics WHERE id = ?');
	$stmt->bind_param('i', $_GET['reply']);
	$stmt->execute();
	$stmt->bind_result($cited_text);
	$stmt->fetch();
	$stmt->close();
		
	echo '<h3 id="replying_to">Original post</h3> <div class="body standalone">' . parse($cited_text) . '</div>';
}

require('includes/footer.php');

?>
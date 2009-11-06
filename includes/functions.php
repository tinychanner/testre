<?php
$errors = array();
$erred = false;

/* ==============================================
                                  USER FUNCTIONS
  ===============================================*/ 

function create_id()
{
	global $link;
	
	$user_id = uniqid('', true);
	$password = generate_password();
	
	$stmt = $link->prepare('INSERT INTO users (uid, password, ip_address, first_seen) VALUES (?, ?, ?, UNIX_TIMESTAMP())');
	$stmt->bind_param('sss', $user_id, $password, $_SERVER['REMOTE_ADDR']);
	$stmt->execute();
	
	$_SESSION['first_seen'] = $_SERVER['REQUEST_TIME'];
	$_SESSION['notice'] = 'Welcome to <strong>' . SITE_TITLE . '</strong>. An account has automatically been created and assigned to you. You don\'t have to register or log in to use the board. Please don\'t clear your cookies unless you have <a href="/dashboard">set a memorable name and password</a>.';
	
	setcookie('UID', $user_id, $_SERVER['REQUEST_TIME'] + 315569260, '/');
	setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/');
	$_SESSION['UID'] = $user_id;
}

function generate_password()
{
	$characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
	$password = '';

	for($i = 0; $i < 32; ++$i) 
	{
		$password .= $characters[array_rand($characters)];
	}
	return $password;
}

function activate_id()
{
	global $link;
	
	$stmt = $link->prepare('SELECT password, first_seen FROM users WHERE uid = ?');
	$stmt->bind_param('s', $_COOKIE['UID']);
	$stmt->execute();
	$stmt->bind_result($db_password, $first_seen);
	$stmt->fetch();
	$stmt->close();
	
	if( ! empty($db_password) && $_COOKIE['password'] === $db_password)
	{
		// The password is correct!
		$_SESSION['UID'] = $_COOKIE['UID'];
		// Our ID wasn't just created.
		$_SESSION['ID_activated'] = true;
		// For post.php
		$_SESSION['first_seen'] = $first_seen;
		
		return true;
	}
	
	// If the password was wrong, create a new ID.
	create_id();
}

function force_id()
{
	if( ! isset($_SESSION['ID_activated']))
	{
		add_error('The page that you tried to access requires that you have a valid internal ID. This is supposed to be automatically created the first time you load a page here. Maybe you were linked directly to this page? Upon loading this page, assuming that you have cookies supported and enabled in your Web browser, you have been assigned a new ID. If you keep seeing this page, something is wrong with your setup; stop refusing/modifying/deleting cookies!', true);
	}
}

function update_activity($action_name, $action_id = '')
{
	global $link;
	
	if( ! isset($_SESSION['UID']))
	{
		return false;
	}
	
	$update_activity = $link->prepare('INSERT INTO activity (time, uid, action_name, action_id) VALUES (UNIX_TIMESTAMP(), ?, ?, ?) ON DUPLICATE KEY UPDATE time = UNIX_TIMESTAMP(), action_name = ?, action_id = ?;');
	$update_activity->bind_param('ssisi', $_SESSION['UID'], $action_name, $action_id, $action_name, $action_id);
	$update_activity->execute();
	$update_activity->close();
}

function id_exists($id)
{
	global $link;

	$uid_exists = $link->prepare('SELECT 1 FROM users WHERE uid = ?');
	$uid_exists->bind_param('s', $_GET['id']);
	$uid_exists->execute();
	$uid_exists->store_result();
	
	if($uid_exists->num_rows < 1)
	{
		$uid_exists->close();
		return false;
	}
	
	$uid_exists->close();
	return true;
}

function remove_id_ban($id)
{
	global $link;

	$remove_ban = $link->prepare('DELETE FROM uid_bans WHERE uid = ?');
	$remove_ban->bind_param('s', $id);
	$remove_ban->execute();
	$remove_ban->close();
}

function remove_ip_ban($ip)
{
	global $link;

	$remove_ban = $link->prepare('DELETE FROM ip_bans WHERE ip_address = ?');
	$remove_ban->bind_param('s', $ip);
	$remove_ban->execute();
	$remove_ban->close();
}

function fetch_ignore_list() // For ostrich mode. 
{
	global $link;

	if($_COOKIE['ostrich_mode'] == 1)
	{
		$fetch_ignore_list = $link->prepare('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?');
		$fetch_ignore_list->bind_param('s', $_COOKIE['UID']);
		$fetch_ignore_list->execute();
		$fetch_ignore_list->bind_result($ignored_phrases);
		$fetch_ignore_list->fetch();
		$fetch_ignore_list->close();
		
		// To make this work with Windows input, we need to strip out the return carriage.
		$ignored_phrases = explode("\n", str_replace("\r", '', $ignored_phrases));
		
		return $ignored_phrases;
	}
}

function show_trash($uid, $silence = false) // For profile and trash can.
{
	global $link;

	$output = '<table><thead><tr> <th class="minimal">Headline</th> <th>Body</th> <th class="minimal">Time since deletion ▼</th> </tr></thead> <tbody>';
	
	$fetch_trash = $link->prepare('SELECT headline, body, time FROM trash WHERE uid = ? ORDER BY time DESC');
	$fetch_trash->bind_param('s', $uid);
	$fetch_trash->execute();
	$fetch_trash->bind_result($trash_headline, $trash_body, $trash_time);
	
	$table = new table();
	$columns = array
	(
		'Headline',
		'Body',
		'Time since deletion ▼'
	);
	$table->define_columns($columns, 'Body');

	while($fetch_trash->fetch())
	{
		if(empty($trash_headline))
		{
			$trash_headline = '<span class="unimportant">(Reply.)</span>';
		}
		else
		{
			$trash_headline = htmlspecialchars($trash_headline);
		}
	
		$values = array 
		(
			$trash_headline,
			nl2br(htmlspecialchars($trash_body)),
			'<span class="help" title="' . format_date($trash_time) . '">' . calculate_age($trash_time) . '</span>'
		);
								
		$table->row($values);
	}
	$fetch_trash->close();
	
	if($table->num_rows_fetched === 0)
	{
		return false;
	}
	return $table->output();
}

/* ==============================================
                                         OUTPUT
  ===============================================*/ 

// Prettify dynamic mark-up
function indent($num_tabs = 1)
{
	return "\n" . str_repeat("\t", $num_tabs);
}  

// Print a <table>. 100 rows takes ~0.0035 seconds on my computer.
class table
{
	public $num_rows_fetched = 0;
	
	private $output = '';
	
	private $primary_key;
	private $columns = array();
	private $td_classes = array();
	
	private $marker_printed = false;
	private $last_seen = false;
	private $order_time = false;
	
	public function define_columns($all_columns, $primary_column)
	{
		$this->columns = $all_columns;
	
		$this->output .= '<table>' . indent() . '<thead>' . indent(2) . '<tr>';
		
		foreach($all_columns as $key => $column)
		{
			$this->output .=   indent(3) . ' <th';
			if($column != $primary_column)
			{
				$this->output .= ' class="minimal"';
			}
			else
			{
				$this->primary_key = $key;
			}
			$this->output .=  '>' . $column . '</th>';
		}
		
		$this->output .=  indent(2) . '</tr>' . indent() . '</thead>' . indent() . '<tbody>';
	}
	
	public function add_td_class($column_name, $class)
	{
		$this->td_classes[$column_name] = $class;
	}
	
	public function last_seen_marker($last_seen, $order_time)
	{
		$this->last_seen = $last_seen;
		$this->order_time = $order_time;
	}
	
	public function row($values)
	{
		// Print <tr>
		$this->output .=  indent(2) . '<tr';
		if($this->num_rows_fetched & 1) 
		{
			$this->output .=  ' class="odd"';
		}
		// Print the last seen marker.
		if($this->last_seen && ! $this->marker_printed && $this->order_time <= $this->last_seen)
		{
			$this->marker_printed = true;
			if($this->num_rows_fetched != 0)
			{
				$this->output .=  ' id="last_seen_marker"';
			}
		}
		$this->output .=  '>';
		
		// Print each <td>
		foreach($values as $key => $value)
		{
			$classes = array();
		
			$this->output .=  indent(3) . '<td';
			
			// If this isn't the primary column (as set in define_columns()), its length should be minimal.
			if($key !== $this->primary_key)
			{
				$classes[] = 'minimal';
			}
			// Check if a class has been added via add_td_class.
			if( isset( $this->td_classes[ $this->columns[$key] ] ) )
			{
				$classes[] = $this->td_classes[$this->columns[$key]];
			}
			// Print any classes added by the above two conditionals.
			if( ! empty($classes))
			{
				$this->output .=  ' class="' . implode(' ', $classes) . '"';
			}
			
			$this->output .=  '>' . $value . '</td>';
		}
		
		$this->output .=  indent(2) . '</tr>';
		
		$this->num_rows_fetched++;
	}
	
	public function output($items = 'items', $silence = false)
	{
		$this->output .=  indent() . '</tbody>' . "\n" . '</table>' . "\n";
		
		if($this->num_rows_fetched > 0)
		{
			return $this->output;
		}
		else if( ! $silence)
		{
			return '<p>(No ' . $items . ' to show.)</p>';
		}
		
		// Silence.
		return '';
	}
}

  
function add_error($message, $critical = false)  
{
	global $errors, $erred;
	
	$errors[] = $message;
	$erred = true;
	
	if($critical)
	{
		print_errors(true);
	}
}

function print_errors($critical = false) 
{
	global $errors;
	
	$number_errors = count($errors);
	
	if($number_errors > 0) 
	{
		echo '<h3 id="error">';
			if($number_errors > 1)
			{
				echo $number_errors . ' errors';
			}
			else 
			{
				echo 'Error';
			}
		echo '</h3><ul class="body standalone">';
		
		foreach($errors as $error_message) 
		{
			echo '<li>' . $error_message . '</li>';
		}
		
		echo '</ul>';
		
		if($critical) 
		{
			if( ! isset($page_title))
			{
				$page_title = 'Fatal error';
			}
			require('footer.php');
			exit;
		}
	}
}

function page_navigation($section_name, $current_page, $num_items_fetched)
{
	$output = '';
	if($current_page != 1)
	{
		$output .= indent() . '<li><a href="/' . $section_name . '">Latest</a></li>';
	}
	if($current_page != 1 && $current_page != 2)
	{
		$newer = $current_page - 1;
		$output .= indent() . '<li><a href="/' . $section_name . '/' . $newer . '">Newer</a></li>';
	}
	if($num_items_fetched == ITEMS_PER_PAGE)
	{
		$older = $current_page + 1;
		$output .= indent() . '<li><a href="/' . $section_name . '/' . $older . '">Older</a></li>';
	}
	
	if( ! empty($output))
	{
		echo "\n" . '<ul class="menu">' . $output . "\n" . '</ul>' . "\n";
	}
}

function edited_message($original_time, $edit_time, $edit_mod)
{
	if($edit_time)
	{
		echo '<p class="unimportant">(Edited ' . calculate_age($original_time, $edit_time) . ' later';
		if($edit_mod)
		{
			echo ' by a moderator';
		}
		echo '.)</p>';
	}
}

function dummy_form()
{
	echo "\n" . '<form id="dummy_form" class="noscreen" action="" method="post">' . indent() . '<div> <input type="hidden" name="some_var" value="" /> </div>' . "\n" . '</form>' . "\n";
}

// To redirect to index, use redirect($notice, ''). To redirect back to referrer, 
// use redirect($notice). To redirect to /topic/1,  use redirect($notice, 'topic/1')
function redirect($notice = '', $location = NULL)
{
	if( ! empty($notice))
	{
		$_SESSION['notice'] = $notice;
	}
	
	if( ! is_null($location) || empty($_SERVER['HTTP_REFERER']))
	{
		$location = DOMAIN . $location;
	}
	else
	{
		$location = $_SERVER['HTTP_REFERER'];
	}
	
	header('Location: ' . $location);
	exit;
}

// Unused
function regenerate_config()
{
	global $link;

	$output = '<?php' . "\n\n" . '#### DO NOT EDIT THIS FILE. ####' . "\n\n";
	
	$result = $link->query('SELECT `option`, `value` FROM configuration');
	while( $row = $result->fetch_assoc() ) 
	{
		if( ! ctype_digit($row['value']))
		{
			$row['value'] = "'" . $row['value'] . "'";
		}
		$output .= "define('" . strtoupper($row['option']) . "', " . $row['value'] . ");\n";
	}
	$result->close();
	
	$output .= "\n" . '?>';
	
	file_put_contents('cache/config.php', $output, LOCK_EX);
}

function trip($name_with_trip)
{
	list($name, $trip) = explode('#', $name_with_trip, 2);
		
	$out = '<strong>' . htmlspecialchars($name) . '</strong>';
	if($trip)
	{
		$out .= ' #' . $trip;
	}
	
	return $out;
}

/* ==============================================
                                  CHECKING
  ===============================================*/ 

function check_length($text, $name, $min_length, $max_length)
{
	$text_length = strlen($text);

	if($min_length > 0 && empty($text))
	{
		add_error('The ' . $name . ' cannot be blank.');
	}
	else if($text_length > $max_length)
	{
		add_error('The ' . $name . ' was ' . number_format($text_length - $max_length) . ' characters over the limit (' . number_format($max_length) . ').');
	}
	else if($text_length < $min_length) 
	{
		add_error('The ' . $name . ' was too short.');
	}
}

function check_tor($ip_address) //query TorDNSEL
{
	// Reverse the octets of our IP address.
	$ip_address = implode('.', array_reverse( explode('.', $ip_address) ));
	
	 // Returns true if Tor, false if not. 80.208.77.188.166 is of no significance.
	return checkdnsrr($ip_address . '.80.208.77.188.166.ip-port.exitlist.torproject.org', 'A');
}

// Prevent cross-site redirection forgeries.
function csrf_token()
{
	if( ! isset($_SESSION['token']))
	{
		$_SESSION['token'] = md5(SALT . mt_rand());
	}
	echo '<div class="noscreen"> <input type="hidden" name="CSRF_token" value="' . $_SESSION['token'] . '" /> </div>' . "\n";
}

function check_token()
{
	if($_POST['CSRF_token'] !== $_SESSION['token'])
	{
		add_error('Session error. Try again.');
		return false;
	}
	return true;
}

/* ==============================================
                                  FORMATTING
  ===============================================*/ 
  
function parse($text)
{
	$text = htmlspecialchars($text);
	$text = str_replace("\r", '', $text);
	
	$markup = array 
	( 
		// Strong emphasis.
		"/'''(.+?)'''/",
		// Emphasis.
		"/''(.+?)''/",
		// Linkify URLs.
		'@\b(?<!\[)(https?|ftp)://(www\.)?([A-Z0-9.-]+)(/)?([A-Z0-9/&#+%~=_|?.,!:;-]*[A-Z0-9/&#+%=~_|])?@i',
		// Linkify text in the form of [http://example.org text]
		'@\[(https?|ftp)://([A-Z0-9/&#+%~=_|?.,!:;-]+) (.+?)\]@i',
		// Quotes.
		'/^&gt;(.+)/m',
		// Headers.
		'/^==(.+?)==\s+/m'
	);
	
	$html   = array 
	(
		'<strong>$1</strong>',
		'<em>$1</em>',
		'<a href="$0">$1://$2<strong>$3</strong>$4$5</a>',
		'<a href="$1://$2">$3</a>',
		'<span class="quote"><strong>&gt;</strong> $1</span>',
		'<h4 class="user">$1</h4>'
	);
	
	$text = preg_replace($markup, $html, $text);
	return nl2br($text);
}

function snippet($text, $snippet_length = 80)
{
	$patterns     = array
	(
		"/'''?(.*?)'''?/", // strip formatting
		'/^(@|>)(.*)/m' //replace quotes and citations
	);
	
	$replacements = array
	(
		'$1',
		' ~ '
	);
	
	$text = preg_replace($patterns, $replacements, $text); 
	$text = str_replace( array("\r", "\n"), ' ', $text ); // strip line breaks
	$text = htmlspecialchars($text);
	
	if(ctype_digit($_COOKIE['snippet_length']))
	{
		$snippet_length = $_COOKIE['snippet_length'];
	}
	if(strlen($text) > $snippet_length)
	{
		$text = substr($text, 0, $snippet_length) . '&hellip;';
	}
	return $text;
}

function super_trim($text)
{
	// Strip return carriage and non-printing characters.
	$nonprinting_characters = array
	(
		"\r",
		'­', //soft hyphen ( U+00AD)
		'﻿', // zero width no-break space ( U+FEFF)
		'​', // zero width space (U+200B)
		'‍', // zero width joiner (U+200D)
		'‌' // zero width non-joiner (U+200C)
	);
	$text = str_replace($nonprinting_characters, '', $text);
	 //Trim and kill excessive newlines (maximum of 3)
	return preg_replace( '/(\r?\n[ \t]*){3,}/', "\n\n\n", trim($text) );
}

function sanitize_for_textarea($text)
{
	$text = str_ireplace('/textarea', '&#47;textarea', $text);
	$text = str_replace('<!--', '&lt;!--', $text);
	return $text;
}

function calculate_age($timestamp, $comparison = '')
{
	$units = array(
					'second' => 60,
					'minute' => 60,
					'hour' => 24,
					'day' => 7,
					'week' => 4.25, // FUCK YOU GREGORIAN CALENDAR
					'month' => 12
					);
	
	if(empty($comparison))
	{
		$comparison = $_SERVER['REQUEST_TIME'];
	}
	$age_current_unit = abs($comparison - $timestamp);
	foreach($units as $unit => $max_current_unit) 
	{
		$age_next_unit = $age_current_unit / $max_current_unit;
		if($age_next_unit < 1) // are there enough of the current unit to make one of the next unit?
		{
			$age_current_unit = floor($age_current_unit);
			$formatted_age = $age_current_unit . ' ' . $unit;
			return $formatted_age . ($age_current_unit == 1 ? '' : 's');
		}
		$age_current_unit = $age_next_unit;
	}

	$age_current_unit = round($age_current_unit, 1);
	$formatted_age = $age_current_unit . ' year';
	return $formatted_age . (floor($age_current_unit) == 1 ? '' : 's');
	
}

function format_date($timestamp)
{
	return date('Y-m-d H:i:s \U\T\C — l \t\h\e jS \o\f F Y, g:i A', $timestamp);
}

function format_number($number)
{
	if($number == 0)
	{
		return '-';
	}
	return number_format($number);
}

function number_to_letter($number)
{
	$alphabet = range('A', 'Y');
	if($number < 24)
	{
		return $alphabet[$number];
	}
	$number = $number - 23;
	return 'Z-' . $number;
}

function replies($topic_id, $topic_replies)
{
	global $visited_topics;
		
	$output = '';
	if( ! isset($visited_topics[$topic_id]))
	{
		$output = '<strong>';
	}
	$output .= format_number($topic_replies);
	
	if( ! isset($visited_topics[$topic_id]))
	{
		$output .= '</strong>';
	}
	else if($visited_topics[$topic_id] < $topic_replies)
	{
		$output .= ' (<a href="/topic/' . $topic_id . '#new">';
		$new_replies = $topic_replies - $visited_topics[$topic_id];
		if($new_replies != $topic_replies)
		{
			$output .= '<strong>' . $new_replies . '</strong> ';
		}
		else
		{
			$output .= 'all-';
		}
		$output .= 'new</a>)';
	}
		
	return $output;
}

function thumbnail($source, $dest_name, $type)
{
	switch($type)
	{
		case 'jpg':
			$image = imagecreatefromjpeg($source);
		break;
									
		case 'gif':
			$image = imagecreatefromgif($source);
		break;
									
		case 'png':
			$image = imagecreatefrompng($source);
	}
		
	$width = imagesx($image);
	$height = imagesy($image);
	
	if($width > MAX_IMAGE_DIMENSIONS || $height > MAX_IMAGE_DIMENSIONS)
	{
		$percent = MAX_IMAGE_DIMENSIONS / ( ($width > $height) ? $width : $height );
								
		$new_width = $width * $percent;
		$new_height = $height * $percent;
								
		$thumbnail = imagecreatetruecolor($new_width, $new_height) ; 
		imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
	}
	else
	{
		$thumbnail = $image;
	}
							
	switch($type)
	{
		case 'jpg':
			imagejpeg($thumbnail, 'thumbs/' . $dest_name, 70);
		break;
								
		case 'gif':
			imagegif($thumbnail, 'thumbs/' . $dest_name);
		break;
								
		case 'png':
			imagepng($thumbnail, 'thumbs/' . $dest_name);
	}
							
	imagedestroy($thumbnail);
	imagedestroy($image);
}

?>
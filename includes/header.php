<?php

require('config.php');
require('functions.php');

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=UTF-8');
session_cache_limiter('nocache');
session_name('SID');
session_start();

// Connect to the database.
$link = new mysqli($db_info['server'], $db_info['username'], $db_info['password'], $db_info['database']);
if (mysqli_connect_errno()) 
{
	exit('<p>Unable to establish a connection to the database. ;_;</p>');
}

// Assume that we have no privileges.
$moderator = false;
$administrator = false;

// If necessary, assign the client a new ID.
if(empty($_COOKIE['UID']))
{
	create_id();
}
else if( ! empty($_COOKIE['password']))
{
	// Log in those who have just began their session.
	if( ! isset($_SESSION['ID_activated']))
	{
		activate_id();
	}
	
	// ...and check for mod/admin privileges from the cache.
	if(in_array($_SESSION['UID'], $moderators))
	{
		$moderator = true;
	}
	else if(in_array($_SESSION['UID'], $administrators))
	{
		$administrator = true;
	}
}

// Start buffering shit for the template.
ob_start(); 

// Get visited topics from cookie.
$visited_cookie = explode('t', $_COOKIE['topic_visits']);
$visited_topics = array();
foreach($visited_cookie as $topic_info)
{
	if(empty($topic_info))
	{
		continue;
	}
	list($cur_topic_id, $num_replies) = explode('n', $topic_info);
	$visited_topics[$cur_topic_id] = $num_replies;
}

// Get most recent actions to see if there's anything new
$result = $link->query('SELECT feature, time FROM last_actions');
$last_actions = array();
while($row = $result->fetch_assoc()) 
{
	$last_actions[$row['feature']] = $row['time'];
}

?>
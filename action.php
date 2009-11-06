<?php

// This file is for non-content actions.
require('includes/header.php');
force_id();

// Take the action ...
switch($_GET['action'])
{
	// Normal actions ...
	case 'watch_topic':
	
		if( ! ctype_digit($_GET['id']))
		{
			add_error('Invalid ID.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Watch topic';
		
		if(isset($_POST['id']))
		{
			$check_watchlist = $link->prepare('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?');
			$check_watchlist->bind_param('si', $_SESSION['UID'], $id);
			$check_watchlist->execute();
			$check_watchlist->store_result();
			if($check_watchlist->num_rows == 0)
			{
				$add_watchlist = $link->prepare('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)');
				$add_watchlist->bind_param('si', $_SESSION['UID'], $_POST['id']);
				$add_watchlist->execute();
				$add_watchlist->close();
			}
			$check_watchlist->close();
			
			redirect('Topic added to your watchlist.');
		}
		
	break;
	
	//Priveleged actions.
	
	case 'delete_page':
	
		if( ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		
		if( ! ctype_digit($_GET['id']))
		{
			add_error('Invalid ID.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Delete page';
		
		if(isset($_POST['id']))
		{
			$file_uid_ban = $link->prepare('DELETE FROM pages WHERE id = ?');
			$file_uid_ban->bind_param('i', $id);
			$file_uid_ban->execute();
			$file_uid_ban->close();
				
			redirect('Page deleted.');
		}
		
	break;
	
	case 'ban_uid':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
	
		if( ! id_exists($_GET['id']))
		{
			add_error('There is no such user.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Ban poster ' . $id;
		
		if(isset($_POST['id']))
		{
			$file_uid_ban = $link->prepare('INSERT INTO uid_bans (uid, filed) VALUES (?, ?) ON DUPLICATE KEY UPDATE filed = ?');
			$file_uid_ban->bind_param('sii', $id, $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME']);
			$file_uid_ban->execute();
			$file_uid_ban->close();
				
			redirect('User ID banned.');
		}
		
	break;
		
	case 'unban_uid':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		
		if( ! id_exists($_GET['id']))
		{
			add_error('There is no such user.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Unban poster ' . $id;
		
		if(isset($_POST['id']))
		{
			remove_id_ban($id);
			
			redirect('User ID unbanned.');
		}
		
	break;
		
	case 'unban_ip':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP))
		{
			add_error('That is not a valid IP address.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Unban IP address ' . $id;
		
		if(isset($_POST['id']))
		{
			remove_ip_ban($id);
			
			redirect('IP address unbanned.');
		}
		
	break;
	
	case 'delete_topic':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		if( ! ctype_digit($_GET['id']))
		{
			add_error('Invalid topic ID.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Delete topic';
	
		if(isset($_POST['id']))
		{
			// Move record to user's trash.
			$archive_topic = $link->prepare('INSERT INTO trash (uid, headline, body, time) SELECT topics.author, topics.headline, topics.body, UNIX_TIMESTAMP() FROM topics WHERE topics.id = ?;');
			$archive_topic->bind_param('i', $id);
			$archive_topic->execute();
			$archive_topic->close();
		
			// And delete it from the main table.
			$delete_topic = $link->prepare('DELETE FROM topics WHERE id = ?');
			$delete_topic->bind_param('i', $id);
			$delete_topic->execute();
			$delete_topic->close();
			
			redirect('Topic archived and deleted.', '');
		}
		
	break;
		
	case 'delete_reply':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		if( ! ctype_digit($_GET['id']))
		{
			add_error('Invalid reply ID.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Delete reply';
	
		if(isset($_POST['id']))
		{
			$fetch_parent = $link->prepare('SELECT parent_id FROM replies WHERE id = ?');
			$fetch_parent->bind_param('i', $id);
			$fetch_parent->execute();
			$fetch_parent->bind_result($parent_id);
			$fetch_parent->fetch();
			$fetch_parent->close();
			
			if( ! $parent_id)
			{
				add_error('No such reply.', true);
			}
		
			// Move record to user's trash.
			$archive_reply = $link->prepare('INSERT INTO trash (uid, body, time) SELECT replies.author, replies.body, UNIX_TIMESTAMP() FROM replies WHERE replies.id = ?;');
			$archive_reply->bind_param('i', $id);
			$archive_reply->execute();
			$archive_reply->close();
		
			// And delete it from the main table.
			$delete_reply = $link->prepare('DELETE FROM replies WHERE id = ?');
			$delete_reply->bind_param('i', $id);
			$delete_reply->execute();
			$delete_reply->close();
			
			// Reduce the parent's reply count.
			$decrement = $link->prepare('UPDATE topics SET replies = replies - 1 WHERE id = ?');
			$decrement->bind_param('i', $parent_id);
			$decrement->execute();
			$decrement->close();
			
			redirect('Reply archived and deleted.');
		}
		
	break;
	
	case 'delete_ip_ids':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP))
		{
			add_error('That is not a valid IP address.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Delete IDs assigned to <a href="/IP_address/' . $id . '">' . $id . '</a>';
		
		if(isset($_POST['id']))
		{
			$delete_ids = $link->prepare('DELETE FROM users WHERE ip_address = ?');
			$delete_ids->bind_param('s', $id);
			$delete_ids->execute();
			$delete_ids->close();
			
			redirect('IDs deleted.');
		}
		
	break;
	
	case 'nuke_id':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		
		if( ! id_exists($_GET['id']))
		{
			add_error('There is no such user.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Nuke all posts by <a href="/profile/' . $id . '">' . $id . '</a>';
		
		if(isset($_POST['id']))
		{
			// Delete replies.
			$fetch_parents = $link->prepare('SELECT parent_id FROM replies WHERE author = ?');
			$fetch_parents->bind_param('s', $id);
			$fetch_parents->execute();
			$fetch_parents->bind_result($parent_id);
			
			$victim_parents = array();
			while($fetch_parents->fetch())
			{
				$victim_parents[] = $parent_id;
			}
			$fetch_parents->close();
			
			$delete_replies = $link->prepare('DELETE FROM replies WHERE author = ?');
			$delete_replies->bind_param('s', $id);
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
			$delete_topics = $link->prepare('DELETE FROM topics WHERE author = ?');
			$delete_topics->bind_param('s', $id);
			$delete_topics->execute();
			$delete_topics->close();
			
			redirect('All topics and replies by ' . $id . ' have been deleted.');
		}
		
	break;
	
	case 'nuke_ip':
	
		if( ! $moderator && ! $administrator)
		{
			add_error('You are not wise enough.', true);
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP))
		{
			add_error('That is not a valid IP address.', true);
		}
		
		$id = $_GET['id'];
		$page_title = 'Nuke all posts by <a href="/IP_address/' . $id . '">' . $id . '</a>';
		
		if(isset($_POST['id']))
		{
			// Delete replies.
			$fetch_parents = $link->prepare('SELECT parent_id FROM replies WHERE author_ip = ?');
			$fetch_parents->bind_param('s', $id);
			$fetch_parents->execute();
			$fetch_parents->bind_result($parent_id);
			
			$victim_parents = array();
			while($fetch_parents->fetch())
			{
				$victim_parents[] = $parent_id;
			}
			$fetch_parents->close();
			
			$delete_replies = $link->prepare('DELETE FROM replies WHERE author_ip = ?');
			$delete_replies->bind_param('s', $id);
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
			$delete_topics = $link->prepare('DELETE FROM topics WHERE author_ip = ?');
			$delete_topics->bind_param('s', $id);
			$delete_topics->execute();
			$delete_topics->close();
			
			redirect('All topics and replies by ' . $id . ' have been deleted.');
		}
		
	break;
	
	default:
		add_error('No valid action specified.', true);	
}

echo '<p>Really?</p> <form action="" method="post"> <div> <input type="hidden" name="id" value="' . $id . '" /> <input type="submit" value="Do it" /> </div>';

require('includes/footer.php');

?>
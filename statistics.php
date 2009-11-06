<?php

require('includes/header.php');
force_id();
update_activity('statistics');
$page_title = 'Statistics';

$statistics = array();

$query = 'SELECT count(*) FROM topics;';
$query .= 'SELECT count(*) FROM replies;';
$query .= 'SELECT count(*) FROM uid_bans;';
$query .= "SELECT count(*) FROM topics WHERE author = '" . $link->real_escape_string($_SESSION['UID']) . "';";
$query .= "SELECT count(*) FROM replies WHERE author = '" . $link->real_escape_string($_SESSION['UID']) . "';";
$query .= 'SELECT count(*) FROM ip_bans;';

$link->multi_query($query);
do {
	$result = $link->store_result();
	while ($row = $result->fetch_row()) 
	{
		$statistics[] = $row[0];
	}
	$result->free();
} while ($link->next_result());

$num_topics = $statistics[0];
$num_replies = $statistics[1];
$replies_per_topic = round($num_replies / $num_topics);
$num_bans = $statistics[2];
$your_topics = $statistics[3];
$your_replies = $statistics[4];
$your_posts = $your_topics + $your_replies;
$num_ip_bans = $statistics[5];

$total_posts = $num_topics + $num_replies; 
$days_since_start = floor(( $_SERVER['REQUEST_TIME'] - SITE_FOUNDED ) / 86400);
$posts_per_day = round($total_posts / $days_since_start);
$topics_per_day = round($num_topics / $days_since_start);
$replies_per_day = round($num_replies / $days_since_start);

?>

<table>
	<tr>
		<th></th>
		<th class="minimal">Amount</th>
		<th>Comment</th>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Total existing posts</th>
		<td class="minimal"><?php echo format_number($total_posts) ?></td>
		<td>-</td>
	</tr>
	
	<tr>
		<th class="minimal">Existing topics</th>
		<td class="minimal"><?php echo format_number($num_topics) ?></td>
		<td>-</td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Existing replies</th>
		<td class="minimal"><?php echo format_number($num_replies) ?></td>
		<td>That's ~<?php echo $replies_per_topic ?> replies/topic.</td>
	</tr>
	
	<tr>
		<th class="minimal">Posts/day</th>
		<td class="minimal">~<?php echo format_number($posts_per_day) ?></td>
		<td>-</td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Topics/day</th>
		<td class="minimal">~<?php echo format_number($topics_per_day) ?></td>
		<td>-</td>
	</tr>
	
	<tr>
		<th class="minimal">Replies/day</th>
		<td class="minimal">~<?php echo format_number($replies_per_day) ?></td>
		<td>-</td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Temporarily banned IDs</th>
		<td class="minimal"><?php echo format_number($num_bans) ?></td>
		<td>-</td>
	</tr>
	
	<tr>
		<th class="minimal">Banned IP addresses</th>
		<td class="minimal"><?php echo format_number($num_ip_bans) ?></td>
		<td>-</td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Days since launch</th>
		<td class="minimal"><?php echo number_format($days_since_start) ?></td>
		<td>Went live on <?php echo date('Y-m-d', SITE_FOUNDED) . ', ' . calculate_age(SITE_FOUNDED) ?> ago.</td>
	</tr>
</table>

<table>
	<tr>
		<th></th>
		<th>Amount</th>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Total posts by you</th>
		<td><?php echo format_number($your_posts) ?></td>
	</tr>
	
	<tr>
		<th class="minimal">Topics started by you</th>
		<td><?php echo format_number($your_topics) ?></td>
	</tr>
	
	<tr class="odd">
		<th class="minimal">Replies by you</th>
		<td><?php echo format_number($your_replies) ?></td>
	</tr>
</table>

<?php

require('includes/footer.php');

?>
<?php

require('includes/header.php');
update_activity('failed_postings');
$page_title = 'Failed postings';

$items_per_page = ITEMS_PER_PAGE;
$stmt = $link->prepare('SELECT time, uid, reason, headline, body FROM failed_postings ORDER BY time DESC LIMIT ?');
$stmt->bind_param('i', $items_per_page);
$stmt->execute();
$stmt->bind_result($fail_time, $fail_uid, $fail_reason, $fail_headline, $fail_body);

$table = new table();

$columns = array
(
	'Error message',
	'Poster',
	'Age ▼'
);
if( ! $moderator && ! $administrator)
{
	array_splice($columns, 1, 1);
}

$table->define_columns($columns, 'Error message');

while($stmt->fetch()) 
{
	if(strlen($fail_body) > 600)
	{
		$fail_body = substr($fail_body, 0, 600) . ' …';
	}

	$tooltip = '';
	if(empty($fail_headline))
	{
		$tooltip = $fail_body;
	}
	else if( ! empty($fail_body))
	{
		$tooltip = 'Headline: ' . $fail_headline . ' Body: ' . $fail_body;
	}

	$fail_reasons = unserialize($fail_reason);
	$error_message = '<ul class="error_message';
	if( ! empty($tooltip))
	{
		$error_message .= ' help';
	}
	$error_message .= '" title="' . htmlspecialchars($tooltip) . '">';
	foreach($fail_reasons as $reason)
	{
		$error_message .= '<li>' . $reason . '</li>';
	}
	$error_message .= '</ul>';
	
	$values = array 
	(
		$error_message,
		'<a href="/profile/' . $fail_uid . '">' . $fail_uid . '</a>',
		'<span class="help" title="' . format_date($fail_time) . '">' . calculate_age($fail_time) . '</span>'
	);
	if( ! $moderator && ! $administrator)
	{
		array_splice($values, 1, 1);
	}
								
	$table->row($values);
}
$stmt->close();
echo $table->output('failed postings');

require('includes/footer.php');

?>

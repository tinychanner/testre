<?php

require('includes/header.php');

if( ! $administrator)
{
	add_error('You are not wise enough.', true);
}

$page_title = 'Content management';
?>

<p>This feature can be used to edit and create non-dynamic pages.</p>

<?php
$table = new table();
$columns = array
(
	'Path',
	'Title',
	'Content snippet',
	'Edit',
	'Delete'
);
$table->define_columns($columns, 'Content snippet');
$table->add_td_class('Content snippet', 'snippet');

$result = $link->query('SELECT id, url, page_title, content FROM pages');

while( $row = $result->fetch_assoc() ) 
{
	$values = array 
	(
		'<a href="/' . $row['url'] . '">' . $row['url'] . '</a>',
		$row['page_title'],
		snippet($row['content']),
		'<a href="/edit_page/' . $row['id'] . '">&#9998;</a>',
		'<a href="/delete_page/' . $row['id'] . '">&#10008;</a>'
	);
									
	$table->row($values);
}
$result->close();
echo $table->output('pages');

?>

<ul class="menu">
	<li><a href="/new_page">New page</a></li>
</ul>

<?php

require('includes/footer.php');

?>
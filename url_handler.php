<?php

require('includes/header.php');

$requested_page = ltrim($_SERVER['REQUEST_URI'], '/');

$stmt = $link->prepare('SELECT page_title, content FROM pages WHERE url = ?');
$stmt->bind_param('s', $requested_page);
$stmt->execute();

$stmt->store_result();
if($stmt->num_rows < 1)
{
	redirect('The page you requested was not found.', '');
}

$stmt->bind_result($page_title, $content);
$stmt->fetch();
$stmt->close();

echo $content;

require('includes/footer.php');

?>
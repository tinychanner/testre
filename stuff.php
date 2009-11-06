<?php

require('includes/header.php');
update_activity('stuff');
$page_title = 'Stuff';

?>

<ul class="stuff">
	<li><strong><a href="/dashboard">Dashboard</a></strong> — <span class="unimportant">Your personal settings, including username and password.</span></li>
	<li><a href="/edit_ignore_list">Edit ignore list</a> — <span class="unimportant">Self-censorship.</span></li>
	<li><a href="/trash_can">Trash can</a> — <span class="unimportant">Your deleted posts.</span></li>
</ul>

<ul class="stuff">
	<li><strong><a href="/restore_ID">Restore ID</a></strong> — <span class="unimportant">Log in.</span></li>
	<li><a href="/back_up_ID">Back up ID</a></li>
	<li><a href="/recover_ID_by_email">Recover ID by e-mail</a></li>
	<li><a href="/drop_ID">Drop ID</a> — <span class="unimportant">Log out.</span></li>
</ul>

<ul class="stuff">
	<li><a href="/statistics">Statistics</a></li>
	<li><a href="/failed_postings">Failed postings</a></li>
	<li><a href="/date_and_time">Date and time</a></li>
</ul>

<?php

if($administrator || $moderator)
{
	echo '<h4 class="section">Moderation</h4> <ul class="stuff">';
}
if($administrator)
{
	echo '<li><a href="/CMS">Content management</a>  — <span class="unimportant">Edit the FAQ and other pages.</span></li>';
}
if($administrator || $moderator && ALLOW_MODS_EXTERMINATE)
{
	echo '<li><a href="/exterminate">Exterminate trolls by phrase</a>  — <span class="unimportant">A last measure.</span></li>';
}
if($administrator || $moderator)
{
	echo '</ul>';
}

require('includes/footer.php');

?>
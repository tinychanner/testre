<?php

require('includes/header.php');

if( ! $administrator)
{
	add_error('You are not wise enough.', true);
}

$page_data = array();

if($_POST['form_sent'])
{
	$page_data['url'] = ltrim($_POST['url'], '/');
	$page_data['title'] = $_POST['title'];
	$page_data['content'] = $_POST['content'];
}

if($_GET['edit'])
{
	if( ! ctype_digit($_GET['edit']))
	{
		add_error('Invalid page ID.', true);
	}
	
	$stmt = $link->prepare('SELECT url, page_title, content FROM pages WHERE id = ?');
	$stmt->bind_param('i', $_GET['edit']);
	$stmt->execute();
	$stmt->store_result();
	if($stmt->num_rows < 1)
	{
		$page_title = 'Non-existent page';
		add_error('There is no page with that ID.', true);
	}
	if( ! $_POST['form_sent'])
	{
		$stmt->bind_result($page_data['url'], $page_data['title'], $page_data['content']);
		$stmt->fetch();
	}
	$stmt->close();
	
	$editing = true;
	$page_title = 'Editing page: <a href="/' . $page_data['url'] . '">' . htmlspecialchars($page_data['title']) . '</a>';
	
	$page_data['id'] = $_GET['edit'];
}
else // new page
{
	$page_title = 'New page';
	if( ! empty($page_data['title']))
	{
		$page_title .= ': ' . htmlspecialchars($page_data['title']);
	}
}

if($_POST['post'])
{
	check_token();
	
	if(empty($page_data['url']))
	{
		add_error('A path is required.');
	}
	
	if( ! $erred)
	{
		// Undo the effects of sanitize_for_textarea:
		$page_data['content'] = str_replace('&#47;textarea', '/textarea', $page_data['content']);
		
		if($editing)
		{
			$edit_page = $link->prepare('UPDATE pages SET url = ?, page_title = ?, content = ? WHERE id = ?');
			$edit_page->bind_param('sssi', $page_data['url'], $page_data['title'], $page_data['content'], $page_data['id']);
			$edit_page->execute();
			$edit_page->close();
			
			$notice = 'Page successfully edited.';
		}
		else // new page
		{
			$add_page = $link->prepare('INSERT INTO pages (url, page_title, content) VALUES (?, ?, ?)');
			$add_page->bind_param('sss', $page_data['url'], $page_data['title'], $page_data['content']);
			$add_page->execute();
			$add_page->close();
			
			$notice = 'Page successfully created.';
		}
		
		redirect($notice, $page_data['url']);
	}
}

print_errors();

if( $_POST['preview'] && ! empty($page_data['content']) && check_token() )
{
		echo '<h3 id="preview">Preview</h3><div class="body standalone"> <h2>' . $page_data['title'] . '</h2>' . $page_data['content'] . '</div>';
}

?>

<form action="" method="post">
	<?php csrf_token() ?>
	<div class="noscreen">
		<input type="hidden" name="form_sent" value="1" />
	</div>
	
	<div class="row">	
		<label for="url">Path</label>
		<input id="url" name="url" value="<?php echo htmlspecialchars($page_data['url']) ?>" />
	</div>
	
	<div class="row">	
		<label for="title">Page title</label>
		<input id="title" name="title" value="<?php echo htmlspecialchars($page_data['title']) ?>" />
	</div>
	
	<div class="row">	
		 <textarea id="content" name="content" cols="120" rows="18"><?php echo sanitize_for_textarea($page_data['content']) ?></textarea>
		 <p>Use pure HTML.</p>
	</div>
	
	<div class="row">
			<input type="submit" name="preview" value="Preview" class="inline" /> 
			<input type="submit" name="post" value="Submit" class="inline">
	</div>
</form>

<?php

require('includes/footer.php');

?>
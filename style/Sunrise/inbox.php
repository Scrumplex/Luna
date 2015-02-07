<?php

// Make sure no one attempts to run this view directly.
if (!defined('FORUM'))
    exit;

?>
<p><span class="pages-label"><?php echo $lang['Pages'].' '.paginate($num_pages, $page, 'inbox.php?') ?></span></p>
<form method="post" action="inbox.php">
	<fieldset>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Inbox messages</h3>
			</div>
			<input type="hidden" name="box" value="0" />
			<table class="table">
				<thead>
					<tr>
						<th><?php echo $lang['Messages'] ?></th>
						<th><?php echo $lang['Sender'] ?></th>
						<th><?php echo $lang['Receiver'] ?></th>
						<th><?php echo $lang['Last post'] ?></th>
						<th><label style="display: inline; white-space: nowrap;"><?php echo $lang['Select'] ?> <input type="checkbox" id="checkAllButon" value="1" onclick="checkAll('selected_messages[]','checkAllButon');" /></label></th>
					</tr>
				</thead>
				<tbody>
<?php
// Fetch messages
$result = $db->query("SELECT * FROM ".$db->prefix."messages WHERE show_message=1 AND owner='".$luna_user['id']."' ORDER BY last_post DESC LIMIT ".$limit) or error("Unable to find the list of the pms.", __FILE__, __LINE__, $db->error()); 

// If there are messages in this folder.
if ($db->num_rows($result)) {
	while ($cur_mess = $db->fetch_assoc($result)) {
		$item_status = 'roweven';
		if ($cur_mess['showed'] == '0') {
			$item_status .= ' inew';
			$icon_type = 'icon icon-new';
			$subject = '<a href="viewinbox.php?tid='.$cur_mess['shared_id'].'&amp;mid='.$cur_mess['id'].'">'.
					   '<strong>'.luna_htmlspecialchars($cur_mess['subject']).'</strong>'.
					   '</a>';
		} else {
			$icon_type = 'icon';
			$subject = '<a href="viewinbox.php?tid='.$cur_mess['shared_id'].'&amp;mid='.$cur_mess['id'].'">'.
					   luna_htmlspecialchars($cur_mess['subject']).
					   '</a>';
		}
		
		$last_post = '<a href="viewinbox.php?tid='.$cur_mess['shared_id'].'&amp;mid='.$cur_mess['id'].'&amp;pid='.$cur_mess['last_post_id'].'#p'.$cur_mess['last_post_id'].'">'.format_time($cur_mess['last_post']).'</a> <span class="byuser">'.$lang['by'].' '.luna_htmlspecialchars($cur_mess['last_poster']).'</span>';
?>
					<tr class="<?php echo $item_status ?>">
						<td>
							<div class="<?php echo $icon_type ?>"></div>
							<div><?php echo $subject ?></div>
						</td>
						<td>
		<?php
		if ($luna_user['g_view_users'] == '1')
			echo '<a href="me.php?id='.$cur_mess['sender_id'].'">'.luna_htmlspecialchars($cur_mess['sender']).'</a>';
		else
			echo luna_htmlspecialchars($cur_mess['sender']);
		?>
						</td>
						<td>
		<?php
			if ($luna_user['g_view_users'] == '1') {
				$ids_list = explode(', ', $cur_mess['receiver_id']);
				$sender_list = explode(', ', $cur_mess['receiver']);
				$sender_list = str_replace('Deleted', $lang['Deleted'], $sender_list);
				
				for($i = '0'; $i < count($ids_list); $i++){
				echo '<a href="me.php?id='.$ids_list[$i].'">'.luna_htmlspecialchars($sender_list[$i]).'</a>';
				
				if($ids_list[$i][count($ids_list[$i])-'1'])
					echo'<br />';
				} 
			} else
				echo luna_htmlspecialchars($cur_mess['receiver']);
		?>
						</td>
						<td><?php echo $last_post ?></td>
						<td><input type="checkbox" name="selected_messages[]" value="<?php echo $cur_mess['shared_id'] ?>" /></td>
					</tr>
<?php
	}
} else
	echo "\t".'<tr><td colspan="4">'.$lang['No messages'].'</td></tr>'."\n";
?>
				</tbody>
			</table>
		</div>
		<p><?php echo $lang['Pages'].' '.paginate($num_pages, $page, 'inbox.php?') ?></p>
		<label>With selection</label>
		<div class="input-group">
			<select class="form-control" name="action">
				<option value="markread"><?php echo $lang['Mark as read select'] ?></option>
				<option value="markunread"><?php echo $lang['Mark as unread select'] ?></option>
				<option value="delete_multiple"><?php echo $lang['Delete'] ?></option>
			</select>
			<div class="input-group-btn">
				<input class="btn btn-primary" type="submit" value="<?php echo $lang['OK'] ?>" />
			</div>
		</div>
	</fieldset>
</form>
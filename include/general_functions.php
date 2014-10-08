<?php

/*
 * Copyright (C) 2013-2014 Luna
 * License: http://opensource.org/licenses/MIT MIT
 */

// Show errors that occured when there are errors
function draw_error_panel($errors) {
	global $lang, $cur_error;

	if (!empty($errors)) {
?>
    <div class="panel panel-danger">
        <div class="panel-heading">
            <h3 class="panel-title"><?php echo $lang['Post errors'] ?></h3>
        </div>
        <div class="panel-body">
<?php
    foreach ($errors as $cur_error)
        echo $cur_error;
?>
        </div>
    </div>
<?php
	}

}

// Show the preview panel
function draw_preview_panel($message) {
	global $lang, $hide_smilies, $message;

	if (!empty($message)) {
		require_once FORUM_ROOT.'include/parser.php';
		$preview_message = parse_message($message, $hide_smilies);
	
?>
<div class="panel panel-default panel-border">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo $lang['Post preview'] ?></h3>
	</div>
	<div class="panel-body">
		<?php echo $preview_message ?>
	</div>
</div>
<?php
	}
}

// Show the preview panel
function draw_editor($height) {
	global $lang, $orig_message, $quote, $fid, $is_admmod, $can_edit_subject, $cur_post, $message;
	
	$pin_btn = $silence_btn = '';

	if (isset($_POST['stick_topic']) || $cur_post['sticky'] == '1') {
		$pin_status = ' checked="checked"';
		$pin_active = ' active';
	}

	if ($fid && $is_admmod || $can_edit_subject && $is_admmod)
		$pin_btn = '<div class="btn-group" data-toggle="buttons"><label class="btn btn-success'.$pin_active.'"><input type="checkbox" name="stick_topic" value="1"'.$pin_status.' /><span class="fa fa-thumb-tack"></span></label></div>';

	if (FORUM_ACTIVE_PAGE == 'edit') {
		if ((isset($_POST['form_sent']) && isset($_POST['silent'])) || !isset($_POST['form_sent'])) {
			$silence_status = ' checked="checked"';
			$silence_active = ' active';
		}
	
		if ($is_admmod)
			$silence_btn = '<div class="btn-group" data-toggle="buttons"><label class="btn btn-success'.$silence_active.'"><input type="checkbox" name="silent" value="1"'.$silence_status.' /><span class="fa fa-microphone-slash"></span></label></div>';
	}

?>
<div class="panel panel-default panel-editor">
	<fieldset class="postfield">
		<input type="hidden" name="form_sent" value="1" />
		<div class="btn-toolbar textarea-toolbar">
			<?php echo $pin_btn ?>
			<?php echo $silence_btn ?>
			<div class="btn-group">
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('b');" title="<?php echo $lang['Bold']; ?>"><span class="fa fa-bold fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('u');" title="<?php echo $lang['Underline']; ?>"><span class="fa fa-underline fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('i');" title="<?php echo $lang['Italic']; ?>"><span class="fa fa-italic fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('s');" title="<?php echo $lang['Strike']; ?>"><span class="fa fa-strikethrough fa-fw"></span></a>
			</div>
			<div class="btn-group">
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('h');" title="<?php echo $lang['Heading']; ?>"><span class="fa fa-header fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('sub');" title="<?php echo $lang['Subscript']; ?>"><span class="fa fa-subscript fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('sup');" title="<?php echo $lang['Superscript']; ?>"><span class="fa fa-superscript fa-fw"></span></a>
			</div>
			<div class="btn-group hidden-xs">
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('quote');" title="<?php echo $lang['Quote']; ?>"><span class="fa fa-quote-left fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('code');" title="<?php echo $lang['Code']; ?>"><span class="fa fa-code fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('c');" title="<?php echo $lang['Inline code']; ?>"><span class="fa fa-file-code-o fa-fw"></span></a>
			</div>
			<div class="btn-group hidden-xs">
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('url');" title="<?php echo $lang['URL']; ?>"><span class="fa fa-link fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('img');" title="<?php echo $lang['Image']; ?>"><span class="fa fa-image fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('video');" title="<?php echo $lang['Video']; ?>"><span class="fa fa-play-circle fa-fw"></span></a>
			</div>
			<div class="btn-group hidden-xs">
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('list');" title="<?php echo $lang['List']; ?>"><span class="fa fa-list-ul fa-fw"></span></a>
				<a class="btn btn-default" href="javascript:void(0);" onclick="AddTag('*');" title="<?php echo $lang['List item']; ?>"><span class="fa fa-asterisk fa-fw"></span></a>
			</div>
			<div class="btn-group pull-right">
				<button class="btn btn-default<?php if ($luna_config['o_post_responsive'] == 0) echo ' hidden-sm hidden-xs'; ?>" type="submit" name="preview" accesskey="p"><span class="fa fa-eye"></span><span class="hidden-xs"> <?php echo $lang['Preview'] ?></span></button>
				<button class="btn btn-primary" type="submit" name="submit" accesskey="s"><span class="fa fa-plus"></span><span class="hidden-xs hidden-sm"> <?php echo $lang['Submit'] ?></span></button>
			</div>
		</div>
		<textarea class="form-control textarea"  placeholder="<?php echo $lang['Start typing'] ?>" name="req_message" id="post_field" rows="<?php echo $height ?>">
<?php
			if (FORUM_ACTIVE_PAGE == 'post')
				isset($_POST['req_message']) ? luna_htmlspecialchars($orig_message) : (isset($quote) ? $quote : '');
			else if (FORUM_ACTIVE_PAGE == 'edit')
				echo luna_htmlspecialchars(isset($_POST['req_message']) ? $message : $cur_post['message']);
?>
		</textarea>
	</fieldset>
</div>
<script>
function AddTag(tag) {
   var Field = document.getElementById('post_field');
   var val = Field.value;
   var selected_txt = val.substring(Field.selectionStart, Field.selectionEnd);
   var before_txt = val.substring(0, Field.selectionStart);
   var after_txt = val.substring(Field.selectionEnd, val.length);
   Field.value = before_txt + '[' + tag + ']' + selected_txt + '[/' + tag + ']' + after_txt;
}
</script>
<?php
}

function draw_topics_list() {
	global $luna_user, $luna_config, $db, $sort_by, $start_from, $id, $lang;
	
// Retrieve a list of topic IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$result = $db->query('SELECT id FROM '.$db->prefix.'topics WHERE forum_id='.$id.' ORDER BY sticky DESC, '.$sort_by.', id DESC LIMIT '.$start_from.', '.$luna_user['disp_topics']) or error('Unable to fetch topic IDs', __FILE__, __LINE__, $db->error());

// If there are topics in this forum
if ($db->num_rows($result)) {
	$topic_ids = array();
	for ($i = 0; $cur_topic_id = $db->result($result, $i); $i++)
		$topic_ids[] = $cur_topic_id;

	// Fetch list of topics to display on this page
	if ($luna_user['is_guest'] || $luna_config['o_has_posted'] == '0') {
		// When not showing a posted label
		$sql = 'SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, last_poster_id, num_views, num_replies, closed, sticky, moved_to FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $topic_ids).') ORDER BY sticky DESC, '.$sort_by.', id DESC';
	} else {
		// When showing a posted label
		$sql = 'SELECT p.poster_id AS has_posted, t.id, t.subject, t.poster, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'posts AS p ON t.id=p.topic_id AND p.poster_id='.$luna_user['id'].' WHERE t.id IN('.implode(',', $topic_ids).') GROUP BY t.id'.($db_type == 'pgsql' ? ', t.subject, t.poster, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, p.poster_id' : '').' ORDER BY t.sticky DESC, t.'.$sort_by.', t.id DESC';
	}

	$result = $db->query($sql) or error('Unable to fetch topic list', __FILE__, __LINE__, $db->error());

	$topic_count = 0;
	while ($cur_topic = $db->fetch_assoc($result)) {

		++$topic_count;
		$status_text = array();
		$item_status = ($topic_count % 2 == 0) ? 'roweven' : 'rowodd';
		$icon_type = 'icon';

		if (is_null($cur_topic['moved_to']))
			if ($luna_user['g_view_users'] == '1' && $cur_topic['last_poster_id'] > '1')
				$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> <span class="byuser">'.$lang['by'].' <a href="profile.php?id='.$cur_topic['last_poster_id'].'">'.luna_htmlspecialchars($cur_topic['last_poster']).'</a></span>';
        	else
				$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> <span class="byuser">'.$lang['by'].' '.luna_htmlspecialchars($cur_topic['last_poster']).'</span>';
		else
			$last_post = '';

		if ($luna_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['sticky'] == '1') {
			$item_status .= ' isticky';
			$status_text[] = '<span class="label label-success">'.$lang['Sticky'].'</span>';
		}

		if ($cur_topic['moved_to'] != 0) {
			$subject = '<a href="viewtopic.php?id='.$cur_topic['moved_to'].'">'.luna_htmlspecialchars($cur_topic['subject']).'</a> <br /><span class="byuser">'.$lang['by'].' '.luna_htmlspecialchars($cur_topic['poster']).'</span>';
			$status_text[] = '<span class="label label-info">'.$lang['Moved'].'</span>';
			$item_status .= ' imoved';
		} else if ($cur_topic['closed'] == '0')
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.luna_htmlspecialchars($cur_topic['subject']).'</a> <br /><span class="byuser">'.$lang['by'].' '.luna_htmlspecialchars($cur_topic['poster']).'</span>';
		else {
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.luna_htmlspecialchars($cur_topic['subject']).'</a> <br /><span class="byuser">'.$lang['by'].' '.luna_htmlspecialchars($cur_topic['poster']).'</span>';
			$status_text[] = '<span class="label label-danger">'.$lang['Closed'].'</span>';
			$item_status .= ' iclosed';
		}

		if (!$luna_user['is_guest'] && $luna_config['o_has_posted'] == '1') {
			if ($cur_topic['has_posted'] == $luna_user['id']) {
				$status_text[] = '<span class="fa fa-asterisk"></span>';
				$item_status .= ' iposted';
			}
		}

		if (!$luna_user['is_guest'] && $cur_topic['last_post'] > $luna_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$id]) || $tracked_topics['forums'][$id] < $cur_topic['last_post']) && is_null($cur_topic['moved_to'])) {
			$item_status .= ' inew';
			$icon_type = 'icon icon-new';
			$subject = '<strong>'.$subject.'</strong>';
			$subject_new_posts = '<span class="newtext">[ <a href="viewtopic.php?id='.$cur_topic['id'].'&amp;action=new" title="'.$lang['New posts info'].'">'.$lang['New posts'].'</a> ]</span>';
		} else
			$subject_new_posts = null;

		// Insert the status text before the subject
		$subject = implode(' ', $status_text).' '.$subject;

		$num_pages_topic = ceil(($cur_topic['num_replies'] + 1) / $luna_user['disp_posts']);

		if ($num_pages_topic > 1)
			$subject_multipage = '<span class="inline-pagination"> '.simple_paginate($num_pages_topic, -1, 'viewtopic.php?id='.$cur_topic['id']).'</span>';
		else
			$subject_multipage = null;

		// Should we show the "New posts" and/or the multipage links?
		if (!empty($subject_new_posts) || !empty($subject_multipage)) {
			$subject .= !empty($subject_new_posts) ? ' '.$subject_new_posts : '';
			$subject .= !empty($subject_multipage) ? ' '.$subject_multipage : '';
		}

		if (forum_number_format($cur_topic['num_replies']) == '1') {
			$replies_label = $lang['reply'];
		} else {
			$replies_label = $lang['replies'];
		}

		if (forum_number_format($cur_topic['num_views']) == '1') {
			$views_label = $lang['view'];
		} else {
			$views_label = $lang['views'];
		}

		require get_view_path('topic.php');

	}

}
	
}
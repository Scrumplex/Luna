<?php

/**
 * Copyright (C) 2013-2014 Luna
 * Based on code by FluxBB copyright (C) 2008-2012 FluxBB
 * Based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * Licensed under GPLv3 (http://modernbb.be/license.php)
 */

define('FORUM_SEARCH_MIN_WORD', 3);
define('FORUM_SEARCH_MAX_WORD', 20);

define('FORUM_ROOT', dirname(__FILE__).'/');

// Load the version class
require FORUM_ROOT.'include/version.php';

// The number of items to process per page view
define('PER_PAGE', 300);

// Don't set to UTF-8 until after we've found out what the default character set is
define('FORUM_NO_SET_NAMES', 1);

// Send the Content-type header in case the web server is setup to send something else
header('Content-type: text/html; charset=utf-8');

// Make sure we are running at least Version::MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, Version::MIN_PHP_VERSION, '<'))
	exit('You are running PHP version '.PHP_VERSION.'. Luna '.Version::FORUM_VERSION.' requires at least PHP '.Version::MIN_PHP_VERSION.' to run properly. You must upgrade your PHP installation before you can continue.');

// Attempt to load the configuration file config.php
if (file_exists(FORUM_ROOT.'config.php'))
	include FORUM_ROOT.'config.php';

// This fixes incorrect defined PUN, from FluxBB 1.5 and Luna 1.6
if (defined('PUN'))
	define('FORUM', PUN);

// If FORUM isn't defined, config.php is missing or corrupt
if (!defined('FORUM'))
{
	header('Location: install.php');
	exit;
}

// Enable debug mode
if (!defined('FORUM_DEBUG'))
	define('FORUM_DEBUG', 1);

// Load the functions script
require FORUM_ROOT.'include/functions.php';

// Load UTF-8 functions
require FORUM_ROOT.'include/utf8/utf8.php';

// Strip out "bad" UTF-8 characters
forum_remove_bad_characters();

// Reverse the effect of register_globals
forum_unregister_globals();

// Turn on full PHP error reporting
error_reporting(E_ALL);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime())
	set_magic_quotes_runtime(0);

// Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if (get_magic_quotes_gpc())
{
	function stripslashes_array($array)
	{
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}

	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
	$_REQUEST = stripslashes_array($_REQUEST);
}

// If a cookie name is not specified in config.php, we use the default (forum_cookie)
if (empty($cookie_name))
	$cookie_name = 'luna_cookie';

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', FORUM_ROOT.'cache/');

// Turn off PHP time limit
@set_time_limit(0);

// Define a few commonly used constants
define('FORUM_UNVERIFIED', 0);
define('FORUM_ADMIN', 1);
define('FORUM_MOD', 2);
define('FORUM_GUEST', 3);
define('FORUM_MEMBER', 4);

// Load DB abstraction layer and try to connect
require FORUM_ROOT.'include/dblayer/common_db.php';

// Check what the default character set is - since 1.2 didn't specify any we will use whatever the default was (usually latin1)
$old_connection_charset = defined('FORUM_DEFAULT_CHARSET') ? FORUM_DEFAULT_CHARSET : $db->get_names();

// Set the connection to UTF-8 now
$db->set_names('utf8');

// Get the forum config
$result = $db->query('SELECT * FROM '.$db->prefix.'config') or error('Unable to fetch config.', __FILE__, __LINE__, $db->error());
while ($cur_config_item = $db->fetch_row($result))
	$luna_config[$cur_config_item[0]] = $cur_config_item[1];

// Load language file
$default_lang = $luna_config['o_default_lang'];

if (!file_exists(FORUM_ROOT.'lang/'.$default_lang.'/language.php'))
	$default_lang = 'English';

require FORUM_ROOT.'lang/'.$default_lang.'/language.php';

// Do some DB type specific checks
$mysql = false;
switch ($db_type)
{
	case 'mysql':
	case 'mysqli':
	case 'mysql_innodb':
	case 'mysqli_innodb':
		$mysql_info = $db->get_version();
		if (version_compare($mysql_info['version'], Version::MIN_MYSQL_VERSION, '<'))
			error(sprintf($lang['You are running error'], 'MySQL', $mysql_info['version'], Version::FORUM_VERSION, Version::MIN_MYSQL_VERSION));

		$mysql = true;
		break;

	case 'pgsql':
		$pgsql_info = $db->get_version();
		if (version_compare($pgsql_info['version'], Version::MIN_PGSQL_VERSION, '<'))
			error(sprintf($lang_update['You are running error'], 'PostgreSQL', $pgsql_info['version'], Version::FORUM_VERSION, Version::MIN_PGSQL_VERSION));

		break;
}

// Check the database, search index and parser revision and the current version
if (isset($luna_config['o_database_revision']) && $luna_config['o_database_revision'] >= Version::FORUM_DB_VERSION &&
		isset($luna_config['o_searchindex_revision']) && $luna_config['o_searchindex_revision'] >= Version::FORUM_SI_VERSION &&
		isset($luna_config['o_parser_revision']) && $luna_config['o_parser_revision'] >= Version::FORUM_PARSER_VERSION &&
		array_key_exists('o_core_version', $luna_config) && version_compare($luna_config['o_core_version'], Version::FORUM_CORE_VERSION, '>=')) {
	error($lang['No update error']);
}

$default_style = $luna_config['o_default_style'];
if (!file_exists(FORUM_ROOT.'style/'.$default_style.'.css'))
	$default_style = 'Random';

// Empty all output buffers and stop buffering
while (@ob_end_clean());

$stage = isset($_REQUEST['stage']) ? $_REQUEST['stage'] : '';
$old_charset = isset($_REQUEST['req_old_charset']) ? str_replace('ISO8859', 'ISO-8859', strtoupper($_REQUEST['req_old_charset'])) : 'ISO-8859-1';
$start_at = isset($_REQUEST['start_at']) ? intval($_REQUEST['start_at']) : 0;
$query_str = '';

// Show form
if (empty($stage))
{
	if (file_exists(FORUM_CACHE_DIR.'db_update.lock'))
	{
		// Deal with newlines, tabs and multiple spaces
		$pattern = array("\t", '  ', '  ');
		$replace = array('&#160; &#160; ', '&#160; ', ' &#160;');
		$message = str_replace($pattern, $replace, $lang['Down']);

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title><?php echo $lang['Maintenance'] ?></title>
        <link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" />
		<link href="backstage/css/style.css" type="text/css" rel="stylesheet">
	</head>
	<body>
		<div class="alert alert-info">
			<h3><?php echo $lang['Maintenance'] ?></h3>
		</div>
	</body>
</html>
<?php

	}
	else
	{

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>Luna &middot; <?php echo $lang['Update'] ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" />
        <link href="style/<?php echo $default_style ?>/style.css" type="text/css" rel="stylesheet">
	</head>
	<body onload="document.getElementById('install').start.disabled=false;">
        <form class="form" id="install" method="post" action="db_update.php">
            <h1 class="form-heading"><?php echo $lang['Update Luna'] ?></h1>
            <fieldset>
                <input type="hidden" name="stage" value="start" />
				<input class="btn btn-default btn-block btn-update" type="submit" name="start" value="<?php echo $lang['Start update'] ?>" />
            </fieldset>
		</form>
	</body>
</html>
<?php

	}
	$db->end_transaction();
	$db->close();
	exit;

}

switch ($stage)
{
	// Start by updating the database structure
	case 'start':
		$query_str = '?stage=preparse_posts';

		// If we don't need to update the database, skip this stage
		if (isset($luna_config['o_database_revision']) && $luna_config['o_database_revision'] >= Version::FORUM_DB_VERSION)
			break;

		// Since 3.4-rc: Insert new config option o_cookie_bar
		if (!array_key_exists('o_cookie_bar', $luna_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_cookie_bar\', \'0\')') or error('Unable to insert config value \'o_cookie_bar\'', __FILE__, __LINE__, $db->error());

		// Since 3.4-rc: Insert new config option o_moderated_by
		if (!array_key_exists('o_moderated_by', $luna_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_moderated_by\', \'1\')') or error('Unable to insert config value \'o_moderated_by\'', __FILE__, __LINE__, $db->error());

		// Since 3.4-rc: Make password field VARCHAR(256)
		$db->alter_field('users', 'password', 'VARCHAR(256)', true) or error('Unable to alter password field', __FILE__, __LINE__, $db->error());

		// Since 3.4-rc: Insert new config option video_width
		if (!array_key_exists('o_video_width', $luna_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_video_width\', \'640\')') or error('Unable to insert config value \'o_video_width\'', __FILE__, __LINE__, $db->error());

		// Since 3.4-rc: Insert new config option video_height
		if (!array_key_exists('o_video_height', $luna_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_video_height\', \'360\')') or error('Unable to insert config value \'o_video_height\'', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Drop the jabber column from the forums table
		$db->drop_field('users', 'jabber') or error('Unable to drop jabber field', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Drop the icq column from the forums table
		$db->drop_field('users', 'icq') or error('Unable to drop icq field from user table', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Drop the yahoo column from the forums table
		$db->drop_field('users', 'yahoo') or error('Unable to drop yahoo field from user table', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Drop the aim column from the forums table
		$db->drop_field('users', 'aim') or error('Unable to drop aim field from user table', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Add the facebook column to the users table
		$db->add_field('users', 'facebook', 'VARCHAR(30)', true, null) or error('Unable to add facebook field to user table', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Add the twitter column to the users table
		$db->add_field('users', 'twitter', 'VARCHAR(30)', true, null) or error('Unable to add twitter field to user table', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Add the google column to the users table
		$db->add_field('users', 'google', 'VARCHAR(30)', true, null) or error('Unable to add google field to user table', __FILE__, __LINE__, $db->error());

		// Since 3.4.1: Insert new config option o_post_responsive
		if (!array_key_exists('o_post_responsive', $luna_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_post_responsive\', \'0\')') or error('Unable to insert config value \'o_post_responsive\'', __FILE__, __LINE__, $db->error());

		// Since 3.5-beta: Remove obsolete o_antispam_api permission from config table
		if (array_key_exists('o_antispam_api', $luna_config))
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'o_antispam_api\'') or error('Unable to remove config value \'o_antispam_api\'', __FILE__, __LINE__, $db->error());

		// Since 3.5-beta: Add o_core_version
		if (!array_key_exists('o_core_version', $luna_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_core_version\', \''.Version::FORUM_CORE_VERSION.'\')') or error('Unable to insert config value \'o_core_version\'', __FILE__, __LINE__, $db->error());

		// Since 0.1-alpha.1: Remove obsolete o_index_update_check permission from config table
		if (array_key_exists('o_index_update_check', $luna_config))
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'o_index_update_check\'') or error('Unable to remove config value \'o_index_update_check\'', __FILE__, __LINE__, $db->error());

		// Since 0.1-alpha.1: Drop the redirect_url column to the forums table
		$db->drop_field($db->prefix.'forums', 'redirect_url', 'VARCHAR(100)', true, 0) or error('Unable to drop redirect_url field', __FILE__, __LINE__, $db->error());

		// Since 0.1-alpha.1: Drop the backstage_color column to the forums table
		$db->drop_field($db->prefix.'users', 'backstage_color', 'VARCHAR(25)', false, 0) or error('Unable to drop backstage_color field', __FILE__, __LINE__, $db->error());

		break;

	// Preparse posts
	case 'preparse_posts':
		$query_str = '?stage=preparse_sigs';

		// If we don't need to parse the posts, skip this stage
		if (isset($luna_config['o_parser_revision']) && $luna_config['o_parser_revision'] >= Version::FORUM_PARSER_VERSION)
			break;

		require FORUM_ROOT.'include/parser.php';

		// Fetch posts to process this cycle
		$result = $db->query('SELECT id, message FROM '.$db->prefix.'posts WHERE id > '.$start_at.' ORDER BY id ASC LIMIT '.PER_PAGE) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		$temp = array();
		$end_at = 0;
		while ($cur_item = $db->fetch_assoc($result))
		{
			echo sprintf($lang['Preparsing item'], $lang['post'], $cur_item['id']).'<br />'."\n";
			$db->query('UPDATE '.$db->prefix.'posts SET message = \''.$db->escape(preparse_bbcode($cur_item['message'], $temp)).'\' WHERE id = '.$cur_item['id']) or error('Unable to update post', __FILE__, __LINE__, $db->error());

			$end_at = $cur_item['id'];
		}

		// Check if there is more work to do
		if ($end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'posts WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result) > 0)
				$query_str = '?stage=preparse_posts&start_at='.$end_at;
		}

		break;


	// Preparse signatures
	case 'preparse_sigs':
		$query_str = '?stage=rebuild_idx';

		// If we don't need to parse the sigs, skip this stage
		if (isset($luna_config['o_parser_revision']) && $luna_config['o_parser_revision'] >= Version::FORUM_PARSER_VERSION)
			break;

		require FORUM_ROOT.'include/parser.php';

		// Fetch users to process this cycle
		$result = $db->query('SELECT id, signature FROM '.$db->prefix.'users WHERE id > '.$start_at.' ORDER BY id ASC LIMIT '.PER_PAGE) or error('Unable to fetch users', __FILE__, __LINE__, $db->error());

		$temp = array();
		$end_at = 0;
		while ($cur_item = $db->fetch_assoc($result))
		{
			echo sprintf($lang['Preparsing item'], $lang['signature'], $cur_item['id']).'<br />'."\n";
			$db->query('UPDATE '.$db->prefix.'users SET signature = \''.$db->escape(preparse_bbcode($cur_item['signature'], $temp, true)).'\' WHERE id = '.$cur_item['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

			$end_at = $cur_item['id'];
		}

		// Check if there is more work to do
		if ($end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'users WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result) > 0)
				$query_str = '?stage=preparse_sigs&start_at='.$end_at;
		}

		break;


	// Rebuild the search index
	case 'rebuild_idx':
		$query_str = '?stage=finish';

		// If we don't need to update the search index, skip this stage
		if (isset($luna_config['o_searchindex_revision']) && $luna_config['o_searchindex_revision'] >= Version::FORUM_SI_VERSION)
			break;

		if ($start_at == 0)
		{
			// Truncate the tables just in-case we didn't already (if we are coming directly here without converting the tables)
			$db->truncate_table('search_cache') or error('Unable to empty search cache table', __FILE__, __LINE__, $db->error());
			$db->truncate_table('search_matches') or error('Unable to empty search index match table', __FILE__, __LINE__, $db->error());
			$db->truncate_table('search_words') or error('Unable to empty search index words table', __FILE__, __LINE__, $db->error());

			// Reset the sequence for the search words (not needed for SQLite)
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
				case 'mysql_innodb':
				case 'mysqli_innodb':
					$db->query('ALTER TABLE '.$db->prefix.'search_words auto_increment=1') or error('Unable to update table auto_increment', __FILE__, __LINE__, $db->error());
					break;

				case 'pgsql';
					$db->query('SELECT setval(\''.$db->prefix.'search_words_id_seq\', 1, false)') or error('Unable to update sequence', __FILE__, __LINE__, $db->error());
					break;
			}
		}

		require FORUM_ROOT.'include/search_idx.php';

		// Fetch posts to process this cycle
		$result = $db->query('SELECT p.id, p.message, t.subject, t.first_post_id FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id WHERE p.id > '.$start_at.' ORDER BY p.id ASC LIMIT '.PER_PAGE) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		$end_at = 0;
		while ($cur_item = $db->fetch_assoc($result))
		{
			echo sprintf($lang['Rebuilding index item'], $lang['post'], $cur_item['id']).'<br />'."\n";

			if ($cur_item['id'] == $cur_item['first_post_id'])
				update_search_index('post', $cur_item['id'], $cur_item['message'], $cur_item['subject']);
			else
				update_search_index('post', $cur_item['id'], $cur_item['message']);

			$end_at = $cur_item['id'];
		}

		// Check if there is more work to do
		if ($end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'posts WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result) > 0)
				$query_str = '?stage=rebuild_idx&start_at='.$end_at;
		}

		break;


	// Show results page
	case 'finish':
		// We update the version numbers
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.Version::FORUM_VERSION.'\' WHERE conf_name = \'o_cur_version\'') or error('Unable to update version', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.Version::FORUM_CORE_VERSION.'\' WHERE conf_name = \'o_core_version\'') or error('Unable to update core version', __FILE__, __LINE__, $db->error());

		// And the database revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.Version::FORUM_DB_VERSION.'\' WHERE conf_name = \'o_database_revision\'') or error('Unable to update database revision number', __FILE__, __LINE__, $db->error());

		// And the search index revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.Version::FORUM_SI_VERSION.'\' WHERE conf_name = \'o_searchindex_revision\'') or error('Unable to update search index revision number', __FILE__, __LINE__, $db->error());

		// And the parser revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.Version::FORUM_PARSER_VERSION.'\' WHERE conf_name = \'o_parser_revision\'') or error('Unable to update parser revision number', __FILE__, __LINE__, $db->error());

		// Check the default language still exists!
		if (!file_exists(FORUM_ROOT.'lang/'.$luna_config['o_default_lang'].'/common.php'))
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \'English\' WHERE conf_name = \'o_default_lang\'') or error('Unable to update default language', __FILE__, __LINE__, $db->error());

		// Check the default style still exists!
		if (!file_exists(FORUM_ROOT.'style/'.$luna_config['o_default_style'].'/style.css'))
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \'Random\' WHERE conf_name = \'o_default_style\'') or error('Unable to update default style', __FILE__, __LINE__, $db->error());

		// This feels like a good time to synchronize the forums
		$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum IDs', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			update_forum($row[0]);

		// Empty the PHP cache
		forum_clear_cache();

		// Delete the update lock file
		@unlink(FORUM_CACHE_DIR.'db_update.lock');

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title><?php echo $lang['Update Luna'] ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" />
        <link href="style/<?php echo $default_style ?>/style.css" type="text/css" rel="stylesheet">
	</head>
	<body>
        <div class="form">
            <h1 class="form-heading"><?php echo $lang['Update Luna'] ?></h1>
            <p class="form-text"><?php printf($lang['Successfully updated'], sprintf('<a href="index.php">%s</a>', $lang['go to index'])) ?></p>
		</div>
	</body>
</html>
<?php

		break;
}

$db->end_transaction();
$db->close();

if ($query_str != '')
	exit('<script type="text/javascript">window.location="db_update.php'.$query_str.'"</script><noscript><meta http-equiv="refresh" content="0;url=db_update.php'.$query_str.'" /></noscript>');


<?php

/*
 * Copyright (C) 2013-2016 Luna
 * Based on code by FluxBB copyright (C) 2008-2012 FluxBB
 * Based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://opensource.org/licenses/MIT MIT
 */

// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
    exit;

// Define line breaks in mail headers; possible values can be PHP_EOL, "\r\n", "\n" or "\r"
if (!defined('LUNA_EOL'))
    define('LUNA_EOL', PHP_EOL);

require LUNA_ROOT . 'include/utf8/utils/ascii.php';

//
// Validate an email address
//
function is_valid_email($email)
{
    $is_valid = true;
    $at_index = strrpos($email, "@");

    if (is_bool($at_index) && !$at_index)
        $is_valid = false;
    else {
        $domain = substr($email, $at_index + 1);
        $local = substr($email, 0, $at_index);
        $local_length = strlen($local);
        $domain_length = strlen($domain);
        if ($local_length < 1 || $local_length > 64) // Local part lenght is to long
            $is_valid = false;
        else if ($domain_length < 1 || $domain_length > 255) // Domain lenght is to long
            $is_valid = false;
        else if ($local[0] == '.' || $local[$local_length - 1] == '.') // If the local part starts or ends with a dot
            $is_valid = false;
        else if (preg_match('/\\.\\./', $local)) // No 2 dots after each other in the local part
            $is_valid = false;
        else if (preg_match('/\\.\\./', $domain)) // And not in the domain either
            $is_valid = false;
        else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) // No invalid characters
            $is_valid = false;
        else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) {
            // Invalid characters, unless they are quoted
            if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local)))
                $is_valid = false;
        }
    }

    return $is_valid;
}


//
// Check if $email is banned
//
function is_banned_email($email)
{
    global $luna_bans;

    foreach ($luna_bans as $cur_ban) {
        if ($cur_ban['email'] != '' &&
            ($email == $cur_ban['email'] ||
                (strpos($cur_ban['email'], '@') === false && stristr($email, '@' . $cur_ban['email'])))
        )
            return true;
    }

    return false;
}


//
// Only encode with base64, if there is at least one unicode character in the string
//
function encode_mail_text($str)
{
    if (utf8_is_ascii($str))
        return $str;

    return '=?UTF-8?B?' . base64_encode($str) . '?=';
}


//
// Make a comment email safe
//
function bbcode2email($text, $wrap_length = 72)
{
    static $base_url;

    if (!isset($base_url))
        $base_url = get_base_url();

    $text = luna_trim($text, "\t\n ");

    $shortcut_urls = array(
        'thread' => '/thread.php?id=$1',
        'comment' => '/thread.php?pid=$1#p$1',
        'forum' => '/viewforum.php?id=$1',
        'user' => '/profile.php?id=$1',
    );

    // Split code blocks and text so BBcode in codeblocks won't be touched
    list($code, $text) = extract_blocks($text, '[code]', '[/code]');

    // Strip all bbcodes, except the quote, url, img, email, code and list items bbcodes
    $text = preg_replace(array(
        '%\[/?(?!(?:quote|url|thread|comment|user|forum|img|email|code|list|\*))[a-z]+(?:=[^\]]+)?\]%i',
        '%\n\[/?list(?:=[^\]]+)?\]%i' // A separate regex for the list tags to get rid of some whitespace
    ), '', $text);

    // Match the deepest nested bbcode
    // An adapted example from Mastering Regular Expressions
    $match_quote_regex = '%
		\[(quote|\*|url|img|email|thread|comment|user|forum)(?:=([^\]]+))?\]
		(
			(?>[^\[]*)
			(?>
				(?!\[/?\1(?:=[^\]]+)?\])
				\[
				[^\[]*
			)*
		)
		\[/\1\]
	%ix';

    $url_index = 1;
    $url_stack = array();
    while (preg_match($match_quote_regex, $text, $matches)) {
        // Quotes
        if ($matches[1] == 'quote') {
            // Put '>' or '> ' at the start of a line
            $replacement = preg_replace(
                array('%^(?=\>)%m', '%^(?!\>)%m'),
                array('>', '> '),
                $matches[2] . " said:\n" . $matches[3]);
        } // List items
        elseif ($matches[1] == '*') {
            $replacement = ' * ' . $matches[3];
        } // URLs and emails
        elseif (in_array($matches[1], array('url', 'email'))) {
            if (!empty($matches[2])) {
                $replacement = '[' . $matches[3] . '][' . $url_index . ']';
                $url_stack[$url_index] = $matches[2];
                $url_index++;
            } else
                $replacement = '[' . $matches[3] . ']';
        } // Images
        elseif ($matches[1] == 'img') {
            if (!empty($matches[2]))
                $replacement = '[' . $matches[2] . '][' . $url_index . ']';
            else
                $replacement = '[' . basename($matches[3]) . '][' . $url_index . ']';

            $url_stack[$url_index] = $matches[3];
            $url_index++;
        } // Thread, comment, forum and user URLs
        elseif (in_array($matches[1], array('thread', 'comment', 'forum', 'user'))) {
            $url = isset($shortcut_urls[$matches[1]]) ? $base_url . $shortcut_urls[$matches[1]] : '';

            if (!empty($matches[2])) {
                $replacement = '[' . $matches[3] . '][' . $url_index . ']';
                $url_stack[$url_index] = str_replace('$1', $matches[2], $url);
                $url_index++;
            } else
                $replacement = '[' . str_replace('$1', $matches[3], $url) . ']';
        }

        // Update the main text if there is a replacement
        if (!is_null($replacement)) {
            $text = str_replace($matches[0], $replacement, $text);
            $replacement = null;
        }
    }

    // Put code blocks and text together
    if (isset($code)) {
        $parts = explode("\1", $text);
        $text = '';
        foreach ($parts as $i => $part) {
            $text .= $part;
            if (isset($code[$i]))
                $text .= trim($code[$i], "\n\r");
        }
    }

    // Put URLs at the bottom
    if ($url_stack) {
        $text .= "\n\n";
        foreach ($url_stack as $i => $url)
            $text .= "\n" . ' [' . $i . ']: ' . $url;
    }

    // Wrap lines if $wrap_length is higher than -1
    if ($wrap_length > -1) {
        // Split all lines and wrap them individually
        $parts = explode("\n", $text);
        foreach ($parts as $k => $part) {
            preg_match('%^(>+ )?(.*)%', $part, $matches);
            $parts[$k] = wordwrap($matches[1] . $matches[2], $wrap_length -
                strlen($matches[1]), "\n" . $matches[1]);
        }

        return implode("\n", $parts);
    } else
        return $text;
}


//
// Wrapper for PHP's mail()
//
function luna_mail($to, $subject, $message, $reply_to_email = '', $reply_to_name = '')
{
    global $luna_config;

    // Default sender/return address

    // Do a little spring cleaning
    $to = luna_trim(preg_replace('%[\n\r]+%s', '', $to));
    $subject = luna_trim(preg_replace('%[\n\r]+%s', '', $subject));
    $reply_to_email = luna_trim(preg_replace('%[\n\r:]+%s', '', $reply_to_email));
    $reply_to_name = luna_trim(preg_replace('%[\n\r:]+%s', '', str_replace('"', '', $reply_to_name)));

    // Set up some headers to take advantage of UTF-8
    $subject = encode_mail_text($subject);

    $headers = 'Date: ' . date('r') . LUNA_EOL . 'MIME-Version: 1.0' . LUNA_EOL . 'Content-transfer-encoding: 8bit' . LUNA_EOL . 'Content-type: text/plain; charset=utf-8' . LUNA_EOL . 'X-Mailer: Luna Mailer';

    // If we specified a reply-to email, we deal with it here
    if (!empty($reply_to_email)) {
        $reply_to = '"' . encode_mail_text($reply_to_name) . '" <' . $reply_to_email . '>';

        $headers .= LUNA_EOL . 'Reply-To: ' . $reply_to;
    }

    // Make sure all linebreaks are LF in message (and strip out any NULL bytes)
    $message = str_replace("\0", '', luna_linebreaks($message));

    if ($luna_config['o_smtp_host'] != '') {
        // Headers should be \r\n
        // Message should be ??
        $message = str_replace("\n", "\r\n", $message);
        smtp_mail($to, $subject, $message, $headers);
    } else {
        // Headers should be \r\n
        // Message should be \n
        mail($to, $subject, $message, $headers);
    }
}


//
// This function was originally a part of the phpBB Group forum software phpBB2 (http://www.phpbb.com)
// They deserve all the credit for writing it. I made small modifications for it to suit PunBB and its coding standards
//
function server_parse($socket, $expected_response)
{
    $server_response = '';
    while (substr($server_response, 3, 1) != ' ') {
        if (!($server_response = fgets($socket, 256)))
            error('Couldn\'t get mail server response codes. Please contact the forum administrator.', __FILE__, __LINE__);
    }

    if (!(substr($server_response, 0, 3) == $expected_response))
        error('Unable to send email. Please contact the forum administrator with the following error message reported by the SMTP server: "' . $server_response . '"', __FILE__, __LINE__);
}


function smtp_mail($to, $subject, $message, $headers = '')
{
    global $luna_config;
    require_once __DIR__ . '/phpmailer.php';
    require_once __DIR__ . '/phpmailer.smtp.php';

    $recipients = explode(',', $to);

    // Sanitize the message
    $message = str_replace("\r\n.", "\r\n..", $message);
    $message = (substr($message, 0, 1) == '.' ? '.' . $message : $message);

    // Are we using port 25 or a custom port?
    if (strpos($luna_config['o_smtp_host'], ':') !== false)
        list($smtp_host, $smtp_port) = explode(':', $luna_config['o_smtp_host']);
    else {
        $smtp_host = $luna_config['o_smtp_host'];
        $smtp_port = 25;
    }

    //Build 'From' header
    $from_email = $luna_config['o_webmaster_email'];
    $from_email = luna_trim(preg_replace('%[\n\r:]+%s', '', $from_email));
    $from_name = sprintf(__('%s Mailer', 'luna'), $luna_config['o_board_title']);
    $from_name = luna_trim(preg_replace('%[\n\r:]+%s', '', str_replace('"', '', $from_name)));

    try {
        //Initialize PHPMailer
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->CharSet = 'UTF-8';
        $mail->Host = $smtp_host;
        $mail->Port = $smtp_port;
        $mail->SMTPAuth = true;
        $mail->Username = $luna_config['o_smtp_user'];
        $mail->Password = $luna_config['o_smtp_pass'];

        //Ignore certificates if defined
        if (endsWith($luna_config['o_smtp_secure'], "ic"))
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

        //Use tls instead of ssl TODO: Implement ssl AND tls
        if ($luna_config['o_smtp_secure'] == 'ssl')
            $mail->SMTPSecure = 'ssl';
        else if ($luna_config['o_smtp_secure'] == 'tls')
            $mail->SMTPSecure = 'ssl';


        $mail->setFrom($from_email, $from_name);


        foreach ($recipients as $email) {
            $mail->addAddress($email);
        }

        $mail->Subject = $subject;
        $mail->Body = $message;

        //Add headers
        foreach (explode(LUNA_EOL, $headers) as $header) {
            $mail->addCustomHeader($header);
        }
        //Send message
        if (!$mail->send()) {
            error('An error occurred while sending an email: ' . $mail->ErrorInfo, __FILE__, __LINE__);
        }

        $mail->smtpClose();
    } catch (phpmailerException $e) {
        error('An error occurred while sending an email: ' . $e->errorMessage(), __FILE__, __LINE__);
    }

    return true;
}

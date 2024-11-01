<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: upgrade.php 5353 2011-02-15 14:24:00Z Tomm $
 */

if(function_exists("unicode_decode"))
{
	// Unicode extension introduced in 6.0
	error_reporting(E_ALL ^ E_DEPRECATED ^ E_NOTICE ^ E_STRICT);
}
elseif(defined("E_DEPRECATED"))
{
	// E_DEPRECATED introduced in 5.3
	error_reporting(E_ALL ^ E_DEPRECATED ^ E_NOTICE);
}
else
{
	error_reporting(E_ALL & ~E_NOTICE);
}

define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
define("INSTALL_ROOT", dirname(__FILE__)."/");
define("TIME_NOW", time());
define('IN_MYBB', 1);
define("IN_UPGRADE", 1);

if(function_exists('date_default_timezone_set') && !ini_get('date.timezone'))
{
	date_default_timezone_set('GMT');
}

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

require_once MYBB_ROOT."inc/config.php";

$orig_config = $config;

if(!is_array($config['database']))
{
	$config['database'] = array(
		"type" => $config['dbtype'],
		"database" => $config['database'],
		"table_prefix" => $config['table_prefix'],
		"hostname" => $config['hostname'],
		"username" => $config['username'],
		"password" => $config['password'],
		"encoding" => $config['db_encoding'],
	);
}
$mybb->config = &$config;

// Include the files necessary for installation
require_once MYBB_ROOT."inc/class_timers.php";
require_once MYBB_ROOT."inc/functions.php";
require_once MYBB_ROOT."inc/class_xml.php";
require_once MYBB_ROOT.'inc/class_language.php';

$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'install/resources/');
$lang->load('language');

// If we're upgrading from an SQLite installation, make sure we still work.
if($config['database']['type'] == 'sqlite3' || $config['database']['type'] == 'sqlite2')
{
	$config['database']['type'] = 'sqlite';
}

require_once MYBB_ROOT."inc/db_{$config['database']['type']}.php";
switch($config['database']['type'])
{
	case "sqlite":
		$db = new DB_SQLite;
		break;
	case "pgsql":
		$db = new DB_PgSQL;
		break;
	case "mysqli":
		$db = new DB_MySQLi;
		break;
	default:
		$db = new DB_MySQL;
}

// Connect to Database
define('TABLE_PREFIX', $config['database']['table_prefix']);
$db->connect($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['database']['type'];

// Load Settings
if(file_exists(MYBB_ROOT."inc/settings.php"))
{
	require_once MYBB_ROOT."inc/settings.php";
}

if(!file_exists(MYBB_ROOT."inc/settings.php") || !$settings)
{
	if(function_exists('rebuild_settings'))
	{
		rebuild_settings();
	}
	else
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
			);

			$query = $db->simple_select("settings", "value, name", "", $options);
			while($setting = $db->fetch_array($query))
			{
				$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
				$settings[$setting['name']] = $setting['value'];
			}
	}
}

$settings['wolcutoff'] = $settings['wolcutoffmins']*60;
$settings['bbname_orig'] = $settings['bbname'];
$settings['bbname'] = strip_tags($settings['bbname']);

// Fix for people who for some specify a trailing slash on the board URL
if(substr($settings['bburl'], -1) == "/")
{
	$settings['bburl'] = my_substr($settings['bburl'], 0, -1);
}

$mybb->settings = &$settings;
$mybb->parse_cookies();

require_once MYBB_ROOT."inc/class_datacache.php";
$cache = new datacache;

// Load cache
$cache->cache();

$mybb->cache = &$cache;

//print_r($mybb);
//check admin session
$login=0;
if(!isset($mybb->cookies['adminsid']))
{
	$login=1;
}
// Otherwise, check admin session
else
{
	$query = $db->simple_select("adminsessions", "*", "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
	$admin_session = $db->fetch_array($query);

	// No matching admin session found - show message on login screen
	if(!$admin_session['sid'])
	{
		$login=2;
	}
	else
	{
		$admin_session['data'] = @unserialize($admin_session['data']);

		// Fetch the user from the admin session
		$query = $db->simple_select("users", "*", "uid='{$admin_session['uid']}'");
		$mybb->user = $db->fetch_array($query);

		// Login key has changed - force logout
		if(!$mybb->user['uid'] || $mybb->user['loginkey'] != $admin_session['loginkey'])
		{
			$login=3;
		}
		else
		{
			// Admin CP sessions 2 hours old are expired
			if($admin_session['lastactive'] < TIME_NOW-7200)
			{
				$login=4;
			}
			// If IP matching is set - check IP address against the session IP
			else if(ADMIN_IP_SEGMENTS > 0)
			{
				$exploded_ip = explode(".", $ip_address);
				$exploded_admin_ip = explode(".", $admin_session['ip']);
				$matches = 0;
				$valid_ip = false;
				for($i = 0; $i < ADMIN_IP_SEGMENTS; ++$i)
				{
					if($exploded_ip[$i] == $exploded_admin_ip[$i])
					{
						++$matches;
					}
					if($matches == ADMIN_IP_SEGMENTS)
					{
						$valid_ip = true;
						break;
					}
				}
					
				// IP doesn't match properly - show message on logon screen
				if(!$valid_ip)
				{
					$login=5;
				}
			}
		}
	}
}
//end check admin session

$data=$cache->read("version");
$data['login']=$login;
$data['error']=0;
$data['adminsid']=$mybb->cookies['adminsid'];
$data['mybbuser']=$mybb->cookies['mybbuser'];
$data['newversion']=$mybb->version;

echo json_encode($data);



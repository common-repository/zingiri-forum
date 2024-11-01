<?php
/*
 Plugin Name: Forums
 Plugin URI: http://www.choppedcode.com
 Description: Forums is a plugin that integrates the powerfull myBB bulletin board software with Wordpress. It brings one of the most powerfull free forum softwares in reach of Wordpress users.
 Author: choppedcode
 Version: 1.4.6
 Author URI: http://www.choppedcode.com
 */
define("ZING_FORUM_VERSION","1.4.6");
define("ZING_MYBB","mybb");

// Pre-2.6 compatibility for wp-content folder location
if (!defined("WP_CONTENT_URL")) {
	define("WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
}
if (!defined("WP_CONTENT_DIR")) {
	define("WP_CONTENT_DIR", ABSPATH . "wp-content");
}

if (!defined("ZING_FORUM_PLUGIN")) {
	$zing_forum_plugin=str_replace(realpath(dirname(__FILE__).'/..'),"",dirname(__FILE__));
	$zing_forum_plugin=substr($zing_forum_plugin,1);
	define("ZING_FORUM_PLUGIN", $zing_forum_plugin);
}

if (!defined("BLOGUPLOADDIR")) {
	$upload=wp_upload_dir();
	if (isset($upload['basedir'])) define("BLOGUPLOADDIR",$upload['basedir'].'/');
}

define("ZING_FORUM_URL", WP_CONTENT_URL . "/plugins/".ZING_FORUM_PLUGIN."/");
define("ZING_FORUM_DIR", WP_CONTENT_DIR . "/plugins/".ZING_FORUM_PLUGIN."/");

define("ZING_MYBB_URL",ZING_FORUM_URL.ZING_MYBB);
define("ZING_MYBB_DIR",ZING_FORUM_DIR.ZING_MYBB);


$zing_forum_version=get_option("zing_forum_version");
if ($zing_forum_version == ZING_FORUM_VERSION) {
	add_action("init","zing_forum_init");
	add_filter('the_content', 'zing_forum_content', 10, 3);
	add_action('wp_head','zing_forum_header');
	if (get_option("zing_forum_login")=="WP") {
		add_action('wp_login','zing_forum_login');
		add_action('wp_logout','zing_forum_logout');
		add_filter('check_password','zing_forum_check_password',10,4);
		add_action('profile_update','zing_forum_profile_update'); //post wp update
		add_action('user_register','zing_forum_user_register'); //post wp update
		add_action('delete_user','zing_forum_user_delete');
	}
}
if ($zing_forum_version) {
	add_filter('upgrader_pre_install', 'zing_forum_pre_upgrade', 9, 2);
	add_filter('upgrader_post_install', 'zing_forum_post_upgrade', 9, 3);
}
add_action('admin_notices','zing_forum_notices');
add_action('admin_head','zing_forum_admin_header');
register_activation_hook(__FILE__,'zing_forum_activate');
register_deactivation_hook(__FILE__,'zing_forum_deactivate');

require_once(dirname(__FILE__) . '/includes/errorlog.class.php');
require_once(dirname(__FILE__) . '/includes/shared.inc.php');
require_once(dirname(__FILE__) . '/includes/http.class.php');
require_once(dirname(__FILE__) . '/includes/footer.inc.php');
require_once(dirname(__FILE__) . '/includes/integrator.inc.php');
require_once(dirname(__FILE__) . '/forum_cp.php');

$zingForumErrorLog=new zingForumErrorLog();

function zing_forum_pre_upgrade($success, $hook_extra) {
	if ($success && isset($hook_extra['plugin']) && ($hook_extra['plugin'] == 'zingiri-forum/forum.php')) {
		echo '<p>Backing up MyBB folder</p>';
		zing_forum_recurse_copy(ZING_FORUM_DIR.'mybb',BLOGUPLOADDIR.'mybb.tmp');
	}
}

function zing_forum_post_upgrade($success, $hook_extra, $result) {
	if ($success && isset($hook_extra['plugin']) && ($hook_extra['plugin'] == 'zingiri-forum/forum.php')) {
		echo '<p>Restoring MyBB folder</p>';
		zing_forum_recurse_copy(BLOGUPLOADDIR.'mybb.tmp',ZING_FORUM_DIR.'mybb');
		zing_forum_rrmdir(BLOGUPLOADDIR.'mybb.tmp');
	}

}

function zing_forum_recurse_copy($src,$dst) {
	$dir = opendir($src);
	if (!file_exists($dst)) mkdir($dst);
	while(false !== ( $file = readdir($dir)) ) {
		if (!in_array($file,array('.','..','.svn'))) {
			if ( is_dir($src . '/' . $file) ) {
				zing_forum_recurse_copy($src . '/' . $file,$dst . '/' . $file);
			}
			else {
				copy($src . '/' . $file,$dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}

function zing_forum_rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir") zing_forum_rrmdir($dir."/".$object);
				else unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function zing_forum_notices() {
	$zing_ew=zing_forum_check();
	$zing_errors=$zing_ew['errors'];
	$zing_warnings=$zing_ew['warnings'];

	if ($zing_errors) {
		foreach ($zing_errors as $zing_error) {
			echo '<div style="background-color:pink" id="message" class="updated fade"><p>';
			echo 'Forums error: '.$zing_error;
			echo '</p></div>';
		}
	}
	if ($zing_warnings) {
		foreach ($zing_warnings as $zing_warning) {
			echo '<div style="background-color:peachpuff" id="message" class="updated fade"><p>';
			echo 'Forums warning: '.$zing_warning;
			echo '</p></div>';
		}
	}
}

function zing_forum_check() {
	global $wpdb;
	$errors=array();
	$warnings=array();

	$zing_forum_version=get_option('zing_forum_version');

	$upload=wp_upload_dir();
	if ($upload['error']) $errors[]=$upload['error'];
	if (session_save_path() && !is_writable(session_save_path())) $warnings[]='PHP sessions are not properly configured on your server, the sessions save path '.session_save_path().' is not writable.';
	if (phpversion() < '5.2') $errors[]="You are running PHP version ".phpversion().". This plugin requires at least PHP 5.2, we recommend to upgrade to PHP 5.3 or higher.";
	if (ini_get("zend.ze1_compatibility_mode")) $warnings[]="You are running PHP in PHP 4 compatibility mode. We recommend you turn this option off.";
	if (!class_exists('ZipArchive')) $warnings[]="Installation requires PHP ZipArchive functionality, please check with your hosting company to activate this.";
	if (!function_exists('curl_init')) $errors[]="You need to have cURL installed. Contact your hosting provider to do so.";
	if (empty($zing_forum_version)) $warnings[]='Please proceed with the installation from the <a href="admin.php?page=cc-forum-cp">plugin control panel</a>';
	elseif (!isset($_REQUEST['zforuminstall']) && $zing_forum_version != ZING_FORUM_VERSION) $warnings[]='You downloaded version '.ZING_FORUM_VERSION.' and need to upgrade your database (currently at version '.$zing_forum_version.') by clicking Upgrade on the <a href="admin.php?page=cc-forum-cp">plugin control panel</a>.';
	return array('errors'=> $errors, 'warnings' => $warnings);
}


/**
 * Activation: creation of database tables & set up of pages
 * @return unknown_type
 */
function zing_forum_activate() {
	//nothing much to do
}

function zing_forum_install() {
	global $wpdb,$zingForumErrorLog,$current_user;

	$eaw=zing_forum_check();
	if (count($eaw['errors']) > 0) return false;

	ob_start();
	$zingForumErrorLog->clear();
	set_error_handler(array($zingForumErrorLog,'log'));
	error_reporting(E_ALL & ~E_NOTICE);

	if (get_option('zing_forum_mybb_dbname')) {
		$prefix = get_option('zing_forum_mybb_dbprefix');
	} else {
		$prefix = $wpdb->prefix."zing_mybb_";
	}

	$zing_forum_version=get_option("zing_forum_version");

	if ($zing_forum_version != ZING_FORUM_VERSION) {
		//$url='http://www.mybb.com/download/latest';
		//$dir=BLOGUPLOADDIR.'mybb/mybb.zip';
		//file_put_contents($dir, file_get_contents($url));
		//$file=download_url('http://www.mybb.com/download/latest',60);
		$result=download_url(ZING_FORUM_URL.'mybb_1608.zip',60);
		if (is_wp_error($result)) {
			$error_string = $result->get_error_message();
			echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
			die();
		} else $file=$result;
		
		$to=BLOGUPLOADDIR.'mybb';

		$zip = new ZipArchive;
		$res = $zip->open($file);
		if ($res === TRUE) {
			$zip->extractTo($to);
			$zip->close();
			zing_forum_recurse_copy(BLOGUPLOADDIR.'mybb/Upload',ZING_FORUM_DIR.'mybb');
			zing_forum_recurse_copy(ZING_FORUM_DIR.'src',ZING_FORUM_DIR.'mybb');
			unlink($file);
			zing_forum_rrmdir($to);
		} else {
			echo 'Failed to downnload and install latest copy of MyBB (' . $res . ')';
			unlink($file);
			die();
		}
		unlink(ZING_FORUM_DIR.'mybb/install/lock');
	}

	//first installation of MyBB
	if (!$zing_forum_version) {
		if (get_option('zing_forum_mybb_dbname')=='') {
			$zingForumErrorLog->msg('Install forum');
			zing_forum_mybb_install();
		} else {
			$zingForumErrorLog->msg('Connect forum');
			$wpdb->select(get_option('zing_forum_mybb_dbname'));
			$query="update ".get_option('zing_forum_mybb_dbprefix')."settings set value='".ZING_MYBB_URL."' where name='bburl'";
			$wpdb->query($query);
			$query="update ".get_option('zing_forum_mybb_dbprefix')."settings set value='' where name='cookiedomain'";
			$wpdb->query($query);
			$user_login=get_option('zing_forum_admin_login');//$current_user->data->user_login;
			$user_pass=zing_forum_admin_password();
			$salt=create_sessionid(8);
			$loginkey=create_sessionid(50);
			$password=md5(md5($salt).md5($user_pass));
			$query2=sprintf("UPDATE `".get_option('zing_forum_mybb_dbprefix')."users` SET `salt`='%s',`loginkey`='%s',`password`='%s' WHERE `username`='%s'",$salt,$loginkey,$password,$user_login);
			$zingForumErrorLog->msg($query2);
			$wpdb->query($query2);
			$wpdb->select(DB_NAME);
		}
	}

	//create pages
	$zingForumErrorLog->msg('Creating pages');
	if (!$zing_forum_version) {
		$pages=array();
		$pages[]=array("Forum","forum","*",0);

		$ids="";
		foreach ($pages as $i =>$p) {
			$my_post = array();
			$my_post['post_title'] = $p['0'];
			$my_post['post_content'] = '';
			$my_post['post_status'] = 'publish';
			$my_post['post_author'] = 1;
			$my_post['post_type'] = 'page';
			$my_post['menu_order'] = 100+$i;
			$my_post['comment_status'] = 'closed';
			$id=wp_insert_post( $my_post );
			if (empty($ids)) { $ids.=$id; } else { $ids.=",".$id; }
			if (!empty($p[1])) add_post_meta($id,'zing_forum_page',$p[1]);
		}
		update_option("zing_forum_pages",$ids);
	}

	//create upload directory if required
	if (!file_exists(BLOGUPLOADDIR.'mybb')) mkdir(BLOGUPLOADDIR.'mybb');

	//login configuration
	if (get_option('zing_forum_mybb_dbname')) $wpdb->select(get_option('zing_forum_mybb_dbname'));
	if (get_option("zing_forum_login")=="MyBB") {
		$user_login=get_option('zing_forum_admin_login');//$current_user->data->user_login;
		$user_pass=zing_forum_admin_password();//get_option("zing_forum_admin_password");
		$salt=create_sessionid(8);
		$loginkey=create_sessionid(50);
		$password=md5(md5($salt).md5($user_pass));
		$query2=sprintf("UPDATE `".$prefix."users` SET `salt`='%s',`loginkey`='%s',`password`='%s' WHERE `username`='%s'",$salt,$loginkey,$password,$user_login);
		$zingForumErrorLog->msg($query2);
		$wpdb->query($query2);
	} else {
		$query2="UPDATE `".$prefix."settings` SET `value`='0' WHERE `name`='failedlogintime'";
		$zingForumErrorLog->msg($query2);
		$wpdb->query($query2);
		$query2="UPDATE `".$prefix."settings` SET `value`='0' WHERE `name`='failedlogincount'";
		$zingForumErrorLog->msg($query2);
		$wpdb->query($query2);
	}
	if (get_option('zing_forum_mybb_dbname')) $wpdb->select(DB_NAME);

	//clean up
	if (isset($_SESSION['ccforum'])) unset($_SESSION['ccforum']);

	restore_error_handler();

	update_option("zing_forum_version",ZING_FORUM_VERSION);

	return true;
}

function zing_forum_hash() {
	if (file_exists(dirname(__FILE__).'/'.ZING_MYBB.'/install/lock')) return filemtime(dirname(__FILE__).'/'.ZING_MYBB.'/install/lock');
}

function zing_forum_mybb_install() {
	global $wpdb,$zingForumErrorLog,$current_user;
	//'license';
	//'requirements_check';
	//'database_info';
	//create tables
	$post['action']='create_tables';
	$post['zing']=zing_forum_hash();
	$post['dbengine']='mysql';
	$post['config']['mysql']['dbhost']=DB_HOST;
	$post['config']['mysql']['dbuser']=DB_USER;
	$post['config']['mysql']['dbpass']=DB_PASSWORD;
	$post['config']['mysql']['dbname']=DB_NAME;
	$post['config']['mysql']['tableprefix']=$wpdb->prefix.'zing_mybb_';
	$post['config']['mysql']['encoding']=DB_CHARSET;
	$http=zing_forum_http("mybb",'install/index.php');
	$zingForumErrorLog->msg($http);
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		$zingForumErrorLog->msg('out='.$output);
	}

	//populate tables
	$post['action']='populate_tables';
	$post['zing']=zing_forum_hash();
	$http=zing_forum_http("mybb",'install/index.php');
	$zingForumErrorLog->msg($http);
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		$zingForumErrorLog->msg('out='.$output);
	}

	//insert templates
	$post['action']='templates';
	$post['zing']=zing_forum_hash();
	$http=zing_forum_http("mybb",'install/index.php');
	$zingForumErrorLog->msg($http);
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		$zingForumErrorLog->msg('out='.$output);
	}

	//configuration - displays the configuration form
	//adminuser
	$post['zing']=zing_forum_hash();
	$post['action']='adminuser';
	$post['bbname']=get_bloginfo('name').' Forum';
	$post['bburl']=ZING_MYBB_URL;
	$post['websitename']=get_bloginfo('name');
	$post['websiteurl']=get_option('home');
	$post['cookiedomain']='';
	$post['cookiepath']='/';
	$post['contactemail']=get_bloginfo('admin_email');
	$http=zing_forum_http("mybb",'install/index.php');
	$zingForumErrorLog->msg($http);
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		$zingForumErrorLog->msg('out='.$output);
	}

	//final
	$post['action']='final';
	$post['zing']=zing_forum_hash();
	$post['adminuser']=get_option('zing_forum_admin_login');//$current_user->data->user_login;
	$post['adminpass']=$post['adminpass2']=zing_forum_admin_password();
	$post['adminemail']=$current_user->data->user_email;
	$http=zing_forum_http("mybb",'install/index.php');
	$zingForumErrorLog->msg($http);
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		$zingForumErrorLog->msg('out='.$output);
	}

}

/**
 * Deactivation: nothing to do
 * @return void
 */
function zing_forum_deactivate() {
}

/**
 * Uninstallation: removal of database tables
 * @return void
 */
function zing_forum_uninstall() {
	global $wpdb;
	
	$prefix=$wpdb->prefix."zing_mybb_";
	$rows=$wpdb->get_results("show tables like '".$prefix."%'",ARRAY_N);
	foreach ($rows as $id => $row) {
		$query="drop table ".$row[0];
		$wpdb->query($query);
	}
	
	zing_forum_rrmdir(ZING_FORUM_DIR.'mybb');
	$ids=get_option("zing_forum_pages");
	$ida=explode(",",$ids);
	foreach ($ida as $id) {
		wp_delete_post($id);
	}
	
	wp_clear_scheduled_hook('zing_forum_cron_hook');
	$zing_forum_options=zing_forum_options();
	foreach ($zing_forum_options as $value) {
		if (isset($value['id'])) delete_option( $value['id'] );
	}
	
	delete_option("zing_forum_version");
	delete_option("zing_forum_pages");
	delete_option("zing_forum_offset");
	delete_option("zing_forum_mode");
	delete_option("zing_mybb_version");
	delete_option("zingiri-forum_news");
	delete_option("zingiri-forum_support-us");
	$fh = fopen(dirname(__FILE__).'/'.ZING_MYBB.'/inc/settings.php', 'w');
	fclose($fh);
}

/**
 * Main function handling content, footer and sidebars
 * @param $process
 * @param $content
 * @return unknown_type
 */
function zing_forum_main($process,$content="") {
	global $zing_forum_content;
	if ($zing_forum_content) {
		if ($zing_forum_content=="redirect") {
			header('Location:'.get_option('home').'/?page_id='.zing_forum_mainpage());
			die();
		}
		else {
			if (isset($_GET['action']) && ($_GET['action']=='logout')) {
				unset($_SESSION['tmpfile']);
			}
			$content=$zing_forum_content;
			$content.=zing_forum_footer(true);
		}
	}
	return $content;
}

function zing_forum_output($process) {
	global $post,$wpdb,$zing_forum_loaded,$zing_forum_to_include,$zing_forum_mode;

	$postVar=array();
	switch ($process)
	{
		case "content":
			if (isset($post)) $cf=get_post_custom($post->ID);
			if (isset($_GET['zforum']))
			{
				$zing_forum_to_include=$_GET['zforum'];
				$zing_forum_mode="forum";
			}
			elseif (isset($_GET['zforumadmin']))
			{
				$zing_forum_to_include="admin/".$_GET['zforumadmin'];
				$zing_forum_mode="admin";
			}
			elseif (isset($_GET['zforuminstall']))
			{
				$zing_forum_to_include="install/".$_GET['zforuminstall'];
				$zing_forum_mode="admin";
			}
			elseif (isset($_GET['module']))
			{
				$zing_forum_to_include="admin/index";
				$zing_forum_mode="admin";
			}
			elseif (isset($cf['zing_forum_page']))
			{
				if ($cf['zing_forum_page'][0]=='forum') {
					$zing_forum_to_include="index";
					$zing_forum_mode="forum";
				}
				if ($cf['zing_forum_page'][0]=='admin') {
					$zing_forum_to_include="admin/index";
					$zing_forum_mode="admin";
				}
			}
			else
			{
				return;
			}
			if (isset($cf['cat'])) {
				$_GET['cat']=$cf['cat'][0];
			}
			break;
	}
	if ($zing_forum_to_include=='attachment') {
		$http=zing_forum_http("mybb",'wp_attachment.php');
		$news = new zHttpRequest($http,'zingiri-forum');
		if (!$news->curlInstalled()) return "cURL not installed";
		elseif (!$news->live()) return "A HTTP Error occured";
		ob_end_clean();
		$output=$news->DownloadToString();
		list($ctype,$filename,$file)=explode(',',$output);
		$file=ZING_MYBB_DIR.'/'.$file;
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public");
		header("Content-Type: $ctype");
		$user_agent = strtolower ($_SERVER["HTTP_USER_AGENT"]);
		if ((is_integer(strpos($user_agent,"msie"))) && (is_integer(strpos($user_agent,"win"))))
		{
			header( "Content-Disposition: filename=".basename($filename).";" );
		} else {
			header( "Content-Disposition: attachment; filename=".basename($filename).";" );
		}
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($file));
		readfile($file);
		die();
	} elseif ($zing_forum_to_include=='misc' && $_GET['action']=='buddypopup') {
		ob_end_clean();
		$http=zing_forum_http("mybb",$zing_forum_to_include);
		$news = new zHttpRequest($http,'zingiri-forum');
		if (!$news->curlInstalled()) return "cURL not installed";
		elseif (!$news->live()) return "A HTTP Error occured";
		$output=$news->DownloadToString();
		$output=zing_forum_ob($output);
		echo $output;
		die();
	} elseif ($zing_forum_to_include=='css') {
		ob_end_clean();
		if (isset($_GET['stylesheet'])) $key=$_GET['stylesheet'];
		else $key=$_GET['url'];
		if (isset($_SESSION['ccforum']['stylesheet'][$key])) {
			$output=$_SESSION['ccforum']['stylesheet'][$key];
		} else {
			if (isset($_GET['stylesheet'])) {
				$http=zing_forum_http("mybb",'css.php',"");
				$news = new zHttpRequest($http,'zingiri-forum');
				if (!$news->curlInstalled()) return "cURL not installed";
				elseif (!$news->live()) return "A HTTP Error occured";
				$output=$news->DownloadToString();
				$output=str_replace('url(images/','url('.ZING_MYBB_URL.'/images/',$output);

			} elseif ($_GET['url']) {
				$url=str_replace('..','',$_GET['url']);
				$output=file_get_contents(ZING_MYBB_DIR.'/cache/themes/'.$url);
			}
			$f[]='/^body.*{(.*?)/';
			$r[]=' {$1';
			$f[]='/.zingbody/';
			$r[]='';
			$f[]='/(.*?).{(.*?)/';
			$r[]='.ccforum $1 {$2';
			$f[]='/(.*?),(.*?).{(.*?)/';
			$r[]='$1,.ccforum $2 {$3';
			$f[]='/(.*?),(.*?),(.*?).{(.*?)/';
			$r[]='$1,$2,.ccforum $3 {$4';
			$output=preg_replace($f,$r,$output,-1,$count);
			if ($output) $_SESSION['ccforum']['stylesheet'][$key]=$output;
		}
		header("Content-type: text/css");
		echo $output;
		die();
	} else {
		if (strstr($zing_forum_to_include,'archive/index.php/')) $http=zing_forum_http("mybb",$zing_forum_to_include);
		else $http=zing_forum_http("mybb",$zing_forum_to_include.'.php');
		$news = new zHttpRequest($http,'zingiri-forum');
		if (!$news->curlInstalled()) return "cURL not installed";
		elseif (!$news->live()) return "A HTTP Error occured";
		else {
			if (count($postVar) > 0) $news->post=$postVar;
			$output=$news->DownloadToString();
			$output=zing_forum_ob($output);
			/*
			 if ($news->redirect) {
				if ($output=='Location: index.php') $output='Location:'.get_option('home').'/?page_id='.zing_forum_mainpage();
				header($output);
				}
				*/
			if (empty($output)) {
				return 'redirect';
			}
			else return '<!--forum:start-->'.$output.'<!--forum:end-->';
		}
	}
}

function zing_forum_mybb_settings() {
	global $mybb_version,$mybb_loggedin,$mybb_status,$mybb_admin_loggedin;

	if (!get_option('zing_forum_version')) {
		$mybb_status='install';
		return;
	}
	$http=zing_forum_http("mybb",'admin/api.php');
	$news = new zHttpRequest($http,'zingiri-forum');
	$output=$news->DownloadToString();
	//echo $output;
	$data=json_decode($output,true);
	if (isset($data['version'])) $mybb_version=$data['version'];
	else $mybb_version=false;
	if (isset($data['mybbuser'])) $mybb_loggedin=true;
	else $mybb_loggedin=false;
	$mybb_admin_loggedin=isset($data['adminloggedin']) ? $data['adminloggedin'] : false;
	if ($data['version'] != $data['newversion']) $mybb_status='upgrade';
	else $mybb_status='active';
}

function zing_forum_http($module,$to_include="index",$page="",$key="") {
	global $wpdb;

	$vars="";
	if (!$to_include) $to_include="index";
	$http=ZING_MYBB_URL.'/';
	$http.= $to_include;
	$and="";
	if (count($_GET) > 0) {
		foreach ($_GET as $n => $v) {
			if ($n!="zforum" && $n!="page_id" && $n!="zforumadmin")
			{
				if (!is_array($v)) {
					if ($n=='mybbpage') {
						$vars.= $and.'page='.zing_urlencode($v);
					} else {
						$vars.= $and.$n.'='.zing_urlencode($v);
					}
					$and="&";
				}
			}
		}
	}
	if (!strstr($to_include,'archive/index.php'))
	{
		$vars.=$and.'zing_url='.zing_urlencode(ZING_MYBB_URL);
		$vars.='&zing_wpdb='.zing_urlencode(DB_NAME);
		$vars.='&zing_wpf='.zing_urlencode($wpdb->prefix."zing_mybb_");
		$vars.='&zing_wph='.zing_urlencode(DB_HOST);
		$vars.='&zing_wpu='.zing_urlencode(DB_USER);
		$vars.='&zing_wpp='.zing_urlencode(DB_PASSWORD);
		$vars.='&zing_wpblogloaddir='.zing_urlencode(BLOGUPLOADDIR);
		if (get_option('zing_forum_mybb_dbname')) {
			$vars.='&zing_dbname='.get_option('zing_forum_mybb_dbname');
			$vars.='&zing_dbprefix='.get_option('zing_forum_mybb_dbprefix');
		} else {
			if (isset($wpdb->base_prefix)) {
				$infix=str_replace($wpdb->base_prefix,"",$wpdb->prefix);
				$vars.='&zing_prefix='.zing_urlencode($infix);
			}
		}
	}
	if ($vars) $http.='?'.$vars;
	//echo $http;
	return $http;
}

/**
 * Page content filter
 * @param $content
 * @return unknown_type
 */
function zing_forum_content($content) {
	return zing_forum_main("content",$content);
}


function zing_forum_admin_header()
{
	echo '<link rel="stylesheet" type="text/css" href="' . ZING_FORUM_URL . 'admin.css" media="screen" />';
}
/**
 * Header hook: loads FWS addons and css files
 * @return unknown_type
 */
function zing_forum_header()
{
	global $zing_forum_content;
	global $zing_forum_menu;
	$output=zing_forum_output("content");

	zing_integrator_cut($output,'<div id="footer">','</div>'); //remove footer
	zing_integrator_cut($output,'<span class="forgot_password">','</span>');

	$zing_forum_content=$output;

	echo '<script type="text/javascript" language="javascript">';
	echo "var zing_forum_url='".ZING_FORUM_URL."ajax/';";
	echo "var zing_forum_index='".get_option('home')."/index.php?';";
	echo "function zing_forum_url_ajax(s) { return zing_forum_url+s; }";
	echo '</script>';

	echo '<link rel="stylesheet" type="text/css" href="' . ZING_FORUM_URL . 'zing.css" media="screen" />';
}

function zing_forum_mainpage() {
	$ids=get_option("zing_forum_pages");
	$ida=explode(",",$ids);
	return $ida[0];

}
function zing_forum_ob($buffer) {
	global $zing_forum_mode,$wpdb;
	$self=str_replace('index.php','',$_SERVER['PHP_SELF']);
	$loc='http://'.$_SERVER['SERVER_NAME'];
	$mybbself=str_replace($loc,'',ZING_MYBB_URL);
	$home=get_option("home")."/";
	$admin=get_option('siteurl').'/wp-admin/';
	$ids=get_option("zing_forum_pages");
	$ida=explode(",",$ids);
	$pid=zing_forum_mainpage();

	//eliminate zing_ variables in MyBB forms
	$f[]='/<input type="hidden" name="zing_(.*)" value="(.*)" \/>/';
	$r[]='';

	$buffer=preg_replace($f,$r,$buffer,-1,$count);

	//	return $buffer;
	if (get_option("zing_forum_login")=="WP") {
		if ($zing_forum_mode=="forum") {
			$buffer=str_replace(ZING_MYBB_URL.'/member.php?action=logout',wp_logout_url(),$buffer);
			$buffer=str_replace(ZING_MYBB_URL.'/member.php?action=login',wp_login_url(get_permalink()),$buffer);
			$buffer=str_replace(ZING_MYBB_URL.'/member.php?action=register',get_option('siteurl').'/wp-login.php?action=register',$buffer);
		} else {
			$buffer=str_replace('href="index.php?action=logout"','href="'.wp_logout_url().'"',$buffer);
		}
	}

	$buffer=str_replace(ZING_MYBB_URL."/admin/index.php",$home."index.php?page_id=".$pid."&zforumadmin=index",$buffer);
	$buffer=str_replace('href="'.ZING_MYBB_URL,'href="'.get_option('home'),$buffer);

	//css
	if (isset($wpdb->base_prefix)) $infix=str_replace($wpdb->base_prefix,"",$wpdb->prefix); else $infix="";

	// replace by zing_integrator_tags($buffer,$bodyclass)
	//page header & footer
	$tagslist='head';
	$tags=explode(',',$tagslist);
	foreach ($tags as $tag)
	{
		$buffer=str_replace('<'.$tag,'<div id="zing'.$tag.'"',$buffer);
		$buffer=str_replace($tag.'>','div>',$buffer);
	}
	$buffer=str_replace('<body','<div class="ccforum zingbody"',$buffer);
	$buffer=str_replace('body>','div>',$buffer);

	$buffer=preg_replace('/<html.*>/','',$buffer);
	$buffer=preg_replace('/<.html>/','',$buffer);
	$buffer=preg_replace('/<meta.*>/','',$buffer);
	$buffer=preg_replace('/<title>.*<.title>/','',$buffer);
	$buffer=preg_replace('/<.DOCTYPE.*>/','',$buffer);
	//images
	$buffer=str_replace('src="images/','src="'.ZING_MYBB_URL.'/images/',$buffer);
	$buffer=str_replace('src="../images/','src="'.ZING_MYBB_URL.'/images/',$buffer);

	//hide logo
	$buffer=str_replace('class="logo"','class="logo" style="display:none"',$buffer);
	$buffer=str_replace('id="logo"','id="logo" style="display:none"',$buffer);
	if ($zing_forum_mode=="forum") {
		$buffer=preg_replace('/href\="(.*?)cache\/themes\/(.*?)"/','href="'.$home.'index.php?page_id='.$pid.'&zforum=css&url='.'$2'.'"',$buffer);
		$buffer=str_replace('onclick="MyBB.quickLogin(); return false;"','',$buffer);

		//pages
		$pageslist='index,css,attachment,misc,announcements,calendar,editpost,forumdisplay,global,managegroup,member,memberlist,modcp,moderation,newreply,newthread,online,polls,portal,printthread,private,ratethread,report,reputation,rss,search,sendthread,showteam,showthread,stats,syndication,usercp,usercp2,warnings,xmlhttp';
		$pages=explode(",",$pageslist);

		$buffer=str_replace('"'.$home.'index.php"','"'.$home.'index.php?page_id='.$pid.'"',$buffer);
		foreach ($pages as $page) {
			$buffer=str_replace(ZING_MYBB_URL."/".$page.".php?",$home."index.php?page_id=".$pid."&zforum=".$page."&",$buffer);
			$buffer=str_replace(ZING_MYBB_URL."/".$page.".php",$home."index.php?page_id=".$pid."&zforum=".$page,$buffer);
		}
		unset($pages[0]);
		foreach ($pages as $page) {
			$buffer=str_replace('action="'.$mybbself.'/'.$page.'.php"','action="'.$home."index.php?page_id=".$pid."&zforum=".$page.'"',$buffer);
			$buffer=str_replace("./".$page.".php?",$home."index.php?page_id=".$pid."&zforum=".$page."&",$buffer);
			$buffer=str_replace($home.$page.".php?",$home."index.php?page_id=".$pid."&zforum=".$page."&",$buffer);
			$buffer=str_replace($home.$page.".php",$home."index.php?page_id=".$pid."&zforum=".$page,$buffer);
			$buffer=str_replace($page.".php?",$home."index.php?page_id=".$pid."&zforum=".$page."&",$buffer);
			$buffer=str_replace($page.".php",$home."index.php?page_id=".$pid."&zforum=".$page,$buffer);
		}

		//upload path
		$buffer=str_replace('./uploads/',ZING_MYBB_URL.'/uploads/',$buffer);

		//menu
		$buffer=str_replace('<div id="header">','<div>',$buffer);

		//javascripts
		$buffer=str_replace('../jscripts/',ZING_MYBB_URL.'/jscripts/',$buffer);
		$buffer=str_replace('./jscripts/',ZING_MYBB_URL.'/jscripts/',$buffer);
		$buffer=str_replace('src="jscripts/','src="'.ZING_MYBB_URL.'/jscripts/',$buffer);

		//captcha
		$buffer=str_replace('src="captcha.php?','src="'.ZING_MYBB_URL.'/captcha.php?',$buffer);

		//redirect form
		$buffer=str_replace('"index.php"','"'.$home.'index.php?page_id='.$pid.'"&zforum=index',$buffer);

		$buffer=preg_replace('/archive(.*).html/','index.php?page_id='.$pid.'&zforum=archive$1',$buffer);
		$buffer=str_replace($home.'archive/index.php',$home.'index.php?page_id='.$pid.'&zforum='.urlencode('archive/index'),$buffer);

		$buffer=str_replace("page=","mybbpage=",$buffer);


	} elseif (isset($_GET['zforumadmin'])) {
		//admin pages
		$pageslist='index,member';
		$mybb=ZING_MYBB_URL."/admin/";
		$pages=explode(",",$pageslist);
		foreach ($pages as $page) {
			$buffer=str_replace($mybb.$page.".php?",$admin."admin.php?page=cc-forum-admin&zforumadmin=".$page."&",$buffer);
			$buffer=str_replace($mybb.$page.".php",$admin."admin.php?page=cc-forum-admin&zforumadmin=".$page,$buffer);
			$buffer=str_replace($self.'wp-content/plugins/zingiri-forum/'.ZING_MYBB.'/admin/'.$page.".php?",$admin."admin.php?page=cc-forum-admin&zforumadmin=".$page.'&',$buffer);
			$buffer=str_replace('../'.$page.'.php?',$admin."admin.php?page=cc-forum-admin&zforumadmin=".$page."&",$buffer);
		}

		$buffer=str_replace('index.php?module=',$admin.'admin.php?page=cc-forum-admin&module=',$buffer);
		$buffer=str_replace('"../admin/index.php"','"'.$admin.'admin.php?page=cc-forum-admin&zforumadmin=index"',$buffer);
		$buffer=str_replace('"../index.php"','"'.$home.'index.php?page_id='.$pid.'"&zforum=index',$buffer);

		//admin style sheets
		$styles=array('default','sharepoint');
		foreach ($styles as $style) {
			$buffer=str_replace('./styles/'.$style.'/',ZING_FORUM_URL.'css/mybb/admin/',$buffer);
			$buffer=str_replace('styles/'.$style.'/',ZING_FORUM_URL.'css/mybb/admin/',$buffer);
		}
		//special cases
		$buffer=str_replace('"index.php"',$admin.'admin.php?page=cc-forum-admin&zforumadmin=index',$buffer);
		//		$buffer=str_replace(ZING_MYBB_URL,$home.'index.php?page_id='.$pid.'',$buffer);
		$buffer=str_replace('"'.get_option("home").'"','"'.$home.'index.php?page_id='.$pid.'"',$buffer);

		//iframe
		$buffer=str_replace('<iframe src="index.php?page_id='.$pid.'&module=tools/php_info','<iframe src="'.ZING_MYBB_URL.'/admin/index.php?module=tools/php_info',$buffer);
		$buffer=str_replace('<iframe src="'.$admin.'admin.php?page=cc-forum-admin','<iframe src="'.ZING_FORUM_URL.'ajax/admin/index.php?',$buffer);

		//javascripts
		$buffer=str_replace('../jscripts/',ZING_MYBB_URL.'/jscripts/',$buffer);
		$buffer=str_replace('./jscripts/',ZING_MYBB_URL.'/jscripts/',$buffer);
		$buffer=str_replace('/jscripts/tabs.js','/admin/jscripts/tabs.js',$buffer);
		$buffer=str_replace('<div id="menu">','<div id="zingmenu">',$buffer);

		//logout
		$buffer=str_replace('"index.php?action=logout"','"'.$admin.'admin.php?page=cc-forum-admin&zforumadmin=index&action=logout"',$buffer);
		$buffer=str_replace('&amp;','&',$buffer);

		//direct login
		$buffer=str_replace('action="'.$mybbself.'/admin/index.php','action="'.$admin.'admin.php',$buffer);


		//redirect form
		$buffer=str_replace('"index.php"','"'.$admin.'admin.php?page=cc-forum-cp"&zforum=index',$buffer);

		//task
		$buffer=str_replace(ZING_MYBB_URL.'task.php',ZING_FORUM_URL.'ajax/task.php',$buffer);
	} else {
		$buffer=str_replace('action="upgrade.php"','action="'.$admin.'admin.php?page=cc-forum-admin&zforuminstall=upgrade"',$buffer);
		$buffer=str_replace('action="index.php"','action="'.$admin.'admin.php?page=cc-forum-admin&zforuminstall=install"',$buffer);
		$buffer=str_replace('../jscripts/',ZING_MYBB_URL.'/jscripts/',$buffer);
		//	$buffer=str_replace('href="stylesheet.css"','href="'.ZING_MYBB_URL.'/install/stylesheet.css"',$buffer);
		$output=file_get_contents(ZING_MYBB_DIR.'/install/stylesheet.css');
		$f[]='/^body.*{(.*?)/';
		$r[]=' {$1';
		$f[]='/.zingbody/';
		$r[]='';
		$f[]='/(.*?).{(.*?)/';
		$r[]='.ccforum $1 {$2';
		$f[]='/(.*?),(.*?).{(.*?)/';
		$r[]='$1,.ccforum $2 {$3';
		$f[]='/(.*?),(.*?),(.*?).{(.*?)/';
		$r[]='$1,$2,.ccforum $3 {$4';
		$output=preg_replace($f,$r,$output,-1,$count);
		$buffer.='<style type="text/css">'.$output.'</style>';

		//$buffer=preg_replace('/href\="stylesheet.css"/','href="'.$home.'index.php?page_id='.$pid.'&zforum=css&url='.'install/stylesheet.css'.'"',$buffer);
	}

	return $buffer;
}
/**
 * Initialization of page, action & page_id arrays
 * @return unknown_type
 */
function zing_forum_init()
{
	global $zing_forum_mode;

	ob_start();
	if (!session_id()) session_start();

	zing_forum_login();
	if (isset($_GET['zforum']))
	{
		$zing_forum_mode="forum";
	}
	elseif (isset($_GET['zforumadmin']) || isset($_GET['module']))
	{
		$zing_forum_mode="admin";
	}
}

function zing_forum_login() {
	global $current_user;
	if (!is_admin() && is_user_logged_in() && get_option("zing_forum_login")=="WP") {
		zing_forum_login_user($current_user->data->user_login,$current_user->data->user_pass);
	}
}

function zing_forum_login_user($login,$password) {
	$post['action']='do_login';
	$post['username']=$login;
	$post['password']=substr($password,1,25);
	$post['url']="";
	$post['submit']='Login';
	$http=zing_forum_http("mybb",'member.php');
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
	}
	if (current_user_can("edit_plugins")) {
		//zing_forum_login_admin();
	}
	return true;
}

function zing_forum_login_admin() {
	global $current_user,$mybb_admin_loggedin;

	if ($mybb_admin_loggedin) return;

	$post['do']='login';
	$post['username']=get_option('zing_forum_admin_login');//$current_user->data->user_login;
	$post['password']=zing_forum_admin_password();//substr($current_user->data->user_pass,1,25);
	$post['url']="";
	$post['submit']='Login';
	if (isset($_GET['zforuminstall'])) $http=zing_forum_http("mybb",'install/'.$_GET['zforuminstall'].'.php');
	else $http=zing_forum_http("mybb",'admin/index.php');;
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		if (isset($_GET['zforuminstall'])) $_SESSION['ccforum']['adminlogin']=1;
	}
}

function zing_forum_login_install() {
	global $current_user;

	//if (isset($_SESSION['ccforum']['adminlogin']) && $_SESSION['ccforum']['adminlogin']) return true;

	$post['action']='do_login';
	$post['zing']=zing_forum_hash();
	$post['username']=get_option('zing_forum_admin_login');
	$post['password']=zing_forum_admin_password();
	$post['submit']='Login';
	$http=zing_forum_http("mybb",'install/'.$_GET['zforuminstall'].'.php');
	$news = new zHttpRequest($http,'zingiri-forum');
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString();
		$_SESSION['ccforum']['adminlogin']=1;
	}
}

function zing_forum_logout() {
	if (isset($_SESSION['zingiri-forum']['cookies'])) {
		unset($_SESSION['zingiri-forum']['cookies']);
	}
}

function zing_forum_check_password($check,$password,$hash,$user_id) {
	global $wpdb;

	$prefix=$wpdb->prefix."zing_mybb_";

	if (!$check) { //the user could be using his old password, pre Web Shop to Wordpress migration
		$user =  new WP_User($user_id);
		$query = sprintf("SELECT * FROM `".$prefix."users` WHERE `username`='%s'", $user->data->user_login);
		$sql = mysql_query($query) or die(mysql_error());
		if ($row = mysql_fetch_array($sql)) {
			if ($row['password']==md5(md5($row['salt']).md5($password))) return true;
		}
		else return false;
	} else return $check;
}

function zing_forum_profile_update($user_id) {
	if (class_exists('wpusers')) return;
	require_once(dirname(__FILE__).'/includes/wpusers.class.php');
	$user=new WP_User($user_id);
	$wpusers=new wpusers();
	$group=$wpusers->getForumGroup($user);
	$wpusers->updateForumUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
}

function zing_forum_user_register($user_id) {
	if (class_exists('wpusers')) return;
	require_once(dirname(__FILE__).'/includes/wpusers.class.php');
	$user=new WP_User($user_id);
	$wpusers=new wpusers();
	$group=$wpusers->getForumGroup($user);
	$wpusers->createForumUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
}

function zing_forum_user_delete($user_id) {
	require_once(dirname(__FILE__).'/includes/wpusers.class.php');
	$user=new WP_User($user_id);
	$wpusers=new wpusers();
	$wpusers->deleteForumUser($user->data->user_login);
}

function zing_forum_admin_password() {
	$login=get_option('zing_forum_admin_login');
	if (get_option("zing_forum_login")=="MyBB") {
		$user_pass=get_option("zing_forum_admin_password");
	} else {
		$user=new WP_User($login);
		$user_pass=substr($user->data->user_pass,1,25);
	}
	return $user_pass;
}

//cron
function zing_forum_cron() {
	if (get_option("zing_forum_login")=="WP") {
		require_once(dirname(__FILE__).'/includes/wpusers.class.php');
		$wpusers=new wpusers();
		$wpusers->sync();
	}

}
if (get_option("zing_forum_version")) {
	if (!wp_next_scheduled('zing_forum_cron_hook')) {
		wp_schedule_event( time(), 'hourly', 'zing_forum_cron_hook' );
	}
	add_action('zing_forum_cron_hook','zing_forum_cron');
}
?>
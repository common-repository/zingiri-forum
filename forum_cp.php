<?php
function zing_forum_options() {
	global $zing_forum_name,$zing_forum_shortname,$zing_login_type,$current_user;
	$zing_forum_name = "Forums";
	$zing_forum_shortname = "zing_forum";
	$zing_login_type = array("WP" => "Yes","MyBB" => "No");

	$version=get_option('zing_forum_version');
	
	$zing_forum_options[] = array(  "name" => "Single Sign On Settings",
            "type" => "heading",
			"desc" => "This section customizes the way Forums interacts with Wordpress.");
	$zing_forum_options[] = array(	"name" => "Single sign on",
			"desc" => "Select the way you and your users want to login. In single sign on mode, users logged in to your blog <br />
			will to have access to the forum. In MyBB mode, users in your blog and in your forum are independent.",
			"id" => $zing_forum_shortname."_login",
			"std" => "WP",
			"type" => "selectwithkey",
			"options" => $zing_login_type);
	$zing_forum_options[] = array(	"name" => "MyBB admin user",
			"desc" => "Default MyBB admin user, used for upgrades and synchronisation of new users.<br /> Unless you have a reason to do so, we recommend to keep the default value.",
			"id" => $zing_forum_shortname."_admin_login",
			"std" => $current_user->data->user_login,
			"type" => "text");
	$zing_forum_options[] = array(	"name" => "MyBB admin password",
			"desc" => "If not using the Wordpress user integration, specify the password of the MyBB admin user,<br />this will be used to easily upgrade your system and for other housekeeping tasks.<br />",
			"id" => $zing_forum_shortname."_admin_password",
			"std" => 'admin',
			"type" => "text");
	$zing_forum_options[] = array(  "name" => "Advanced - Database settings",
            "type" => "heading",
			"desc" => "By default when installing the plugin, forum database tables will be created automatically.<br />If you want to use your own database fill in the following settings, otherwise leave them blank.<br />Also make sure you have an administrator user in your forum with the login name <strong style=\"color:blue\">".$current_user->data->user_login."</strong><br />and that the WP db user has access to your MyBB db.");
	$zing_forum_options[] = array(	"name" => "Name",
			"desc" => "Database name",
			"id" => $zing_forum_shortname."_mybb_dbname",
			"std" => '',
			"type" => "text");
	$zing_forum_options[] = array(	"name" => "MyBB prefix",
			"desc" => "Database tables prefix",
			"id" => $zing_forum_shortname."_mybb_dbprefix",
			"std" => '',
			"type" => "text");
	
	return $zing_forum_options;
}

function zing_forum_add_admin() {

	global $zing_forum_name, $zing_forum_shortname, $mybb_version, $mybb_loggedin, $mybb_status;

	$zing_forum_options=zing_forum_options();

	if ( isset($_GET['page']) && ($_GET['page'] == "cc-forum-cp") ) {

		if (isset($_REQUEST['action']) && ('install' == $_REQUEST['action']) ) {
			foreach ($zing_forum_options as $value) {
				if (isset($value['id'])) {
					if( isset($_REQUEST[ $value['id'] ]) ) update_option( $value['id'], $_REQUEST[ $value['id'] ]  );
					elseif( isset($value['std']) ) update_option( $value['id'], $value['std']  );
					else delete_option($value['id']);
				}
			}
			if (zing_forum_install()) {
				header("Location: options-general.php?page=cc-forum-cp&installed=1");
			} else {
				header("Location: options-general.php?page=cc-forum-cp&installed=0");
			}
			die;
		} elseif (isset($_REQUEST['action']) && ('uninstall' == $_REQUEST['action']) ) {
			zing_forum_uninstall();
			header("Location: options-general.php?page=cc-forum-cp&uninstalled=0");
			die;
		}
	}
	add_menu_page($zing_forum_name, $zing_forum_name, 'administrator', 'cc-forum-cp','zing_forum_admin');
	add_submenu_page('cc-forum-cp', $zing_forum_name.'- Integration', 'Integration', 'administrator', 'cc-forum-cp', 'zing_forum_admin');
	add_submenu_page('cc-forum-cp', $zing_forum_name.'- Log', 'Log', 'administrator', 'cc-forum-log', 'zing_forum_admin_log');
	
	zing_forum_mybb_settings();
	update_option('zing_mybb_version',$mybb_version);
	
	if (file_exists(dirname(__FILE__).'/'.ZING_MYBB.'/install/lock')) unlink(dirname(__FILE__).'/'.ZING_MYBB.'/install/lock');
	if ($mybb_status == 'active') {
		if (!file_exists(dirname(__FILE__).'/'.ZING_MYBB.'/install/lock')) copy(dirname(__FILE__).'/src/install/lock',dirname(__FILE__).'/'.ZING_MYBB.'/install/lock');
		add_submenu_page('cc-forum-cp', $zing_forum_name.'- Administration', 'Administration', 'administrator', 'cc-forum-admin', 'zing_mybb_admin');
	} elseif ($mybb_status == 'upgrade') {
		//lock
		add_submenu_page('cc-forum-cp', $zing_forum_name.'- Upgrade', 'Upgrade', 'administrator', 'cc-forum-admin', 'zing_mybb_admin_upgrade');
	}
}

function zing_forum_admin_log() {
	echo '<div class="wrap">';
	echo '<h2>Log</h2>';
	$log=get_option('zing_forum_log');
	if (!$log) $log='If you have problems during installation, have a look at this log, it may be helpful in debugging the problem.'.chr(10).chr(10).'The log is currently empty.';
	echo '<textarea cols="120" rows="30">'.$log.'</textarea>';
	echo '</div>';
	
}
function zing_mybb_admin_install() {
	global $zing_forum_mode;
	global $zing_forum_content;

	$zing_forum_mode="admin";
	$_GET['zforuminstall']='index';
	echo '<div class="wrap">';
	echo '<div style="width: 100%; float: left; position: relative; min-height: 500px;">';
	zing_forum_login_install();
	zing_forum_header();
	echo $zing_forum_content;
	echo '</div>';
	echo '</div>';
}

function zing_mybb_admin_upgrade() {
	global $zing_forum_mode, $mybb_loggedin, $zing_forum_content;

	$zing_forum_mode="admin";
	$_GET['zforuminstall']='upgrade';
	echo '<div class="wrap">';
	echo '<div style="width: 100%; float: left; position: relative; min-height: 500px;">';
	if (!$mybb_loggedin) zing_forum_login_install();
	//zing_forum_mybb_version();
	zing_forum_header();
	echo $zing_forum_content;
	echo '</div>';
	echo '</div>';
}

function zing_mybb_admin() {
	global $zing_forum_mode;
	global $zing_forum_content;

	$zing_forum_mode="admin";
	if (!isset($_GET['zforumadmin']) || !$_GET['zforumadmin']) $_GET['zforumadmin']='index';
	echo '<div class="wrap">';
	echo '<div style="width: 100%; float: left; position: relative; min-height: 500px;">';
	zing_forum_login_admin();
	zing_forum_header();
	echo $zing_forum_content;
	echo '</div>';
	echo '</div>';
}

function zing_forum_admin() {

	global $zing_forum_name, $zing_forum_shortname;

	$controlpanelOptions=zing_forum_options();

	if ( isset($_REQUEST['installed']) && $_REQUEST['installed'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_forum_name.' installed.</strong></p></div>';
	if ( isset($_REQUEST['installed']) && !$_REQUEST['installed'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_forum_name.' installation failed.</strong></p></div>';
	if ( isset($_REQUEST['uninstalled']) && !$_REQUEST['uninstalled'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_forum_name.' uninstalled.</strong></p></div>';
	
	?>
<div class="wrap">
<div style="width: 100%; float: left; position: relative; min-height: 500px;">

<h2><b><?php echo $zing_forum_name; ?></b></h2>

	<?php
	$zing_forum_version=get_option("zing_forum_version");
	if (empty($zing_forum_version)) {
		$submit='Install';
	} elseif ($zing_forum_version != ZING_FORUM_VERSION) {
		$submit='Upgrade';
	} elseif ($zing_forum_version == ZING_FORUM_VERSION) {
		$submit='Update';
	}

	
	?>
<form method="post">

<?php require(dirname(__FILE__).'/includes/cpedit.inc.php')?>

<p class="submit"><input name="install" type="submit" value="<?php echo $submit;?>" /> <input
	type="hidden" name="action" value="install"
/></p>
</form>
<?php if ($zing_forum_version) { ?>
<form method="post">
<p class="submit"><input name="uninstall" type="submit" value="Uninstall" /> <input
	type="hidden" name="action" value="uninstall"
/></p>
</form>
<?php } ?></div>
<?php
?></div>
<?php }
add_action('admin_menu', 'zing_forum_add_admin'); ?>
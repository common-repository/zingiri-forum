<?php

$wpusers=new wpusers();

class wpusers {
	var $prefix;
	var $base_prefix;
	var $wpAdmin=false;
	var $wpCustomer=false;
	var $dbname;
	var $number=50;

	function wpusers() {
		global $wpdb;
		if (isset($wpdb->base_prefix)) $this->base_prefix=$wpdb->base_prefix;
		else $this->base_prefix=$wpdb->prefix;
		if ($n=get_option('zing_forum_mybb_dbname')) {
			$this->prefix=get_option('zing_forum_mybb_dbprefix');
			$this->dbname=get_option('zing_forum_mybb_dbname');
		} else {
			$this->prefix=$wpdb->prefix."zing_mybb_";
			$this->dbname=DB_NAME;
		}
		if (get_option('zing_forum_login') == "WP") {
			$this->wpAdmin=true;
			$this->wpCustomer=true;
		}
	}

	function getWpUsers() {
		global $wpdb,$blog_id;
		$users=array();
		$offset=get_option('zing_forum_offset');
		$u=get_users(array('blog_id' => $blog_id,'offset' => $offset,'number' => $this->number));
		$count=0;
		foreach ($u as $o) {
			$count++;
			$users[$o->user_login]=$o->ID;
		}
		if ($count < $this->number) { //we reached the end of WP users
			update_option('zing_forum_offset',0);
			update_option('zing_forum_step','mybb');
		} else {
			update_option('zing_forum_offset',$offset+$this->number);
		}
		return $users;
	}

	function sync() {
		global $wpdb,$blog_id;
		global $zingForumErrorLog;

		if (!$this->wpAdmin) return;

		$wpdb->show_errors();

		if (!get_option('zing_forum_offset')) update_option('zing_forum_offset',0);
		//sync Forum to Wordpress - Wordpress is master so we're not changing roles in Wordpress
		if (get_option('zing_forum_step') != 'wp') {
			$bbUsers=$this->getForumUsers();
			foreach ($bbUsers as $row) {
				$zingForumErrorLog->log(0,'Sync Forum to WP: '.$row['username']);
				if ($row['group']['canmodcp']) $role='editor';
				else $role='subscriber';
				$query2=sprintf("SELECT `ID` FROM `".$this->base_prefix."users` WHERE `user_login`='%s'",$row['username']);
				$sql2 = mysql_query($query2) or die(mysql_error());
				if (mysql_num_rows($sql2) == 0) { //WP user doesn't exist
					$data=array();
					$data['user_login']=$row['username'];
					$data['user_email']=$row['email'];
					$data['user_pass']='';
					$id=$this->createWpUser($data,$role);
					if (function_exists('add_user_to_blog')) {
						add_user_to_blog($blog_id,$id,$role);
					}
				}
			}
		} else {
			//sync Wordpress to Forum - Wordpress is master so we're updating roles in Forum
			$users=$this->getWpUsers();
			foreach ($users as $id) {
				$user=new WP_User($id);
				$zingForumErrorLog->log(0,'Sync WP to Forum: '.$id.'/'.$user->data->display_name);
				if (!isset($user->data->first_name)) $user->data->first_name=$user->data->display_name;
				if (!isset($user->data->last_name)) $user->data->last_name=$user->data->display_name;
				$group=$this->getForumGroup($user);
				if (!$this->existsForumUser($user->data->user_login)) { //create user
					$this->createForumUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
				} else { //update user
					$this->updateForumUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
				}
			}
		}
	}

	function getForumUsers() {
		global $wpdb;
		$rows=array();

		$offset=get_option('zing_forum_offset');
		$count=0;
		$wpdb->select($this->dbname);
		$query=sprintf("select * from `##users` LIMIT %s,%s",$offset,$this->number);
		$query=str_replace("##",$this->prefix,$query);
		echo $query;
		$sql = mysql_query($query) or die(mysql_error());
		while ($row = mysql_fetch_array($sql)) {
			$count++;
			$query_group=sprintf("SELECT * FROM `".$this->prefix."usergroups` WHERE `gid`='%s'",$row['usergroup']);
			$sql_group = mysql_query($query_group) or die(mysql_error());
			if ($row_group = mysql_fetch_array($sql_group)) {
				$row['group']=$row_group;
			}
			$rows[]=$row;
		}
		if ($count < $this->number) { //we reached the end of WP users
			update_option('zing_forum_offset',0);
			update_option('zing_forum_step','wp');
		} else {
			update_option('zing_forum_offset',$offset+$this->number);
		}

		$wpdb->select(DB_NAME);
		return $rows;
	}

	function getForumGroup($user) {
		//echo 'ok';
		if ($user->has_cap('level_10')) {
			$group='4'; //admins
		} elseif ($user->has_cap('level_5')) {
			$group='6'; //moderators
		} else {
			$group='2'; //registered
		}
		return $group;
	}

	function currentForumUser() {
		global $current_user;
		global $wpdb;

		$wpdb->select($this->dbname);
		$query=sprintf("SELECT * FROM `".$this->prefix."users` WHERE `username`='".$current_user->data->user_login."'");
		$sql = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($sql);
		$wpdb->select(DB_NAME);
		return $row;
	}

	function existsForumUser($login) {
		global $wpdb;

		$wpdb->select($this->dbname);
		$query2=sprintf("SELECT `uid` FROM `".$this->prefix."users` WHERE `username`='%s'",$login);
		$sql2 = mysql_query($query2) or die(mysql_error());
		if (mysql_num_rows($sql2) == 0) $exists=false;
		else $exists=true;
		$wpdb->select(DB_NAME);
		return $exists;
	}

	function getForumUser($login) {
		global $wpdb;

		$wpdb->select($this->dbname);
		$query=sprintf("SELECT * FROM `".$this->prefix."users` WHERE `username`='".$login."'");
		$sql = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($sql);
		$wpdb->select(DB_NAME);
		return $row;
	}

	function createForumUser($username,$password,$email,$group) {
		global $zingForumErrorLog;

		zing_forum_login_admin();
		$admin=$this->getForumUser(get_option('zing_forum_admin_login'));

		$zingForumErrorLog->log(0,'Create Forum user '.$username);
		$post['username']=$username;
		$post['password']=$post['confirm_password']=substr($password,1,25);
		$post['email']=$email;
		$post['usergroup']=$group;
		$post['displaygroup']=0;
		$post['submit']='Save User';
		$post['my_post_key']=md5($admin['loginkey'].$admin['salt'].$admin['regdate']);
		$_GET['module']='user/users';
		$_GET['action']='add';
		$http=zing_forum_http("mybb",'admin/index.php');
		$news = new zHTTPRequest($http,'zingiri-forum');
		$news->post=$post;
		if ($news->live()) {
			$output=$news->DownloadToString();
			//$zingForumErrorLog->log(0,'out='.$output.'=');
		}

	}

	function updateForumUser($user_login,$user_pass,$user_email,$group) {
		global $wpdb,$zingForumErrorLog;

		$zingForumErrorLog->log(0,'Update Forum user '.$user_login);
		$salt=create_sessionid(8);
		$loginkey=create_sessionid(50);
		$password=md5(md5($salt).md5(substr($user_pass,1,25)));

		$wpdb->select($this->dbname);
		$query2=sprintf("UPDATE `".$this->prefix."users` SET `usergroup`='%s',`salt`='%s',`loginkey`='%s',`password`='%s' WHERE `username`='%s'",$group,$salt,$loginkey,$password,$user_login);
		$zingForumErrorLog->log(0,$query2);
		$wpdb->query($query2);
		$wpdb->select(DB_NAME);
	}

	function createWpUser($user,$role) {
		global $wpdb,$zingForumErrorLog;

		$zingForumErrorLog->log(0,'Create WP user '.$user);
		require_once(ABSPATH.'wp-includes/registration.php');
		$user['role']=$role;
		$id=wp_insert_user($user);
		return $id;
	}

	function deleteForumUser($login) {
		global $zingForumErrorLog;

		$user=$this->getForumUser($login);
		$admin=$this->getForumUser(get_option('zing_forum_admin_login'));
		$zingForumErrorLog->log(0,'Delete Forum user '.$user);

		$post['submit']='Yes';
		$post['my_post_key']=md5($admin['loginkey'].$admin['salt'].$admin['regdate']);
		$_GET['module']='user/users';
		$_GET['action']='delete';
		$_GET['uid']=$user['uid'];
		$http=zing_forum_http("mybb",'admin/index.php');
		$zingForumErrorLog->log(0,$http);
		$news = new zHTTPRequest($http,'zingiri-forum');
		$news->post=$post;
		if ($news->live()) {
			$output=$news->DownloadToString();
			$zingForumErrorLog->log(0,'out='.$output.'=');
		}

	}

	function loggedIn() {
		if ($this->wpAdmin && is_user_logged_in()) return true;
		else return false;
	}

	function isAdmin() {
		if ($this->wpAdmin && (current_user_can('edit_plugins')  || current_user_can('edit_pages'))) return true;
		else return false;
	}

	function loginWpUser($login,$pass) {
		wp_signon(array('user_login'=>$login,'user_password'=>$pass));
	}
}

?>
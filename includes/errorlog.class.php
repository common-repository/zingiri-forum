<?php
/**
 * Output activation messages to log
 * @param $stringData
 * @return unknown_type
 */

class zingForumErrorLog {
	var $debug=false;
	
	function zingForumErrorLog($clear=false,$debug=false) {
		if ($clear) $this->clear();
		$this->debug=$debug;
	}

	function log($severity, $msg, $filename="", $linenum=0) {
		if (is_array($msg)) $msg=print_r($msg,true);
		$toprint=date('Y-m-d h:i:s').' '.$msg.' ('.$filename.'-'.$linenum.')';
		$log=get_option('zing_forum_log');
		$log=$toprint.chr(10).$log;
		update_option('zing_forum_log',$log);
		if ($this->debug) echo $toprint.'<br />';
	}

	function msg($msg) {
		$this->log(0,$msg);
	}
	
	function clear() {
		delete_option('zing_forum_log');
	}
}

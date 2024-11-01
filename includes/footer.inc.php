<?php

function zing_forum_footer($nodisplay='') {
	$bail_out = ( ( defined( 'WP_ADMIN' ) && WP_ADMIN == true ) || ( strpos( $_SERVER[ 'PHP_SELF' ], 'wp-admin' ) !== false ) );
	if ( $bail_out ) return $footer;

	//Please contact us if you wish to remove the Zingiri logo in the footer
	$msg='<center style="margin-top:0px;font-size:x-small">';
	$msg.='Wordpress and MyBB integration by <a href="http://www.zingiri.net">Zingiri</a>';
	$msg.='</center>';
	if ($nodisplay===true) return $msg;
	else echo $msg;

}
?>
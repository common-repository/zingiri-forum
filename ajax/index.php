<?php
require('../../../../wp-blog-header.php');

$page="";
$a=str_replace('index.php','',$_SERVER['PHP_SELF']);
$to_include=str_replace($a,'',$_SERVER['REDIRECT_URL']);
$http=zing_forum_http("mybb",$to_include,$page);
$news = new zHTTPRequest($http,'zingiri-forum');
$output=$news->DownloadToString();
$output=zing_forum_ob($output);
echo $output;
?>
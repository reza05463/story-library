<?php
require "config.php";

$type = $_GET["type"] ?? "";
if(!in_array($type,["login","signup"],true)){ http_response_code(400); exit; }
$text=""; $alphabet="abcdefghjkmnpqrstuvwxyz";
for($i=0;$i<6;$i++){ $text.=$alphabet[random_int(0,strlen($alphabet)-1)]; }
$_SESSION["captcha"][$type] = $text;

$im = imagecreate(80, 40);
$bg = imagecolorallocate($im, 15, 23, 42);
$fg = imagecolorallocate($im, 56, 189, 248);
imagefill($im, 0, 0, $bg);
imagestring($im, 5, 15, 12, $text, $fg);
header("Cache-Control: no-store");
header("content-type: image/png");
imagepng($im);
imagedestroy($im);
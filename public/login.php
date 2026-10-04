<?php
require "config.php";

if(is_logged_in()){
header("location: " . (is_admin() ? "admin.php" : "user.php"));
exit();
}

$message = "";
$color = "";

function check_captcha($type){
$expected = $_SESSION["captcha"][$type] ?? null;
$ok = $expected !== null && $expected === strtolower($_POST["captcha"] ?? "");
unset($_SESSION["captcha"][$type]);
return $ok;
}

if(isset($_POST["action"]) && $_POST["action"] =="login"){
if(check_captcha("login")){
if(!empty($_POST["email"]) && !empty($_POST["password"]) && strlen($_POST["password"]) <= 72){
$stmt = $database->prepare("select * from users where email = ?");
$stmt->execute([$_POST["email"]]);
$user = $stmt->fetch();
if($user && password_verify($_POST["password"], $user["password"])){
$database->prepare("update users set last_login = ?, last_ip = ? where id = ?")->execute([time(), $_SERVER["REMOTE_ADDR"], $user["id"]]);
session_regenerate_id(true);
$_SESSION["user_id"] = $user["id"];
$_SESSION["role"] = $user["role"];
header("location: " . ($user["role"]==1 ? "admin.php" : "user.php"));
exit();
} else {
$message = "ایمیل یا رمز عبور اشتباه است.";
$color = "#ef4444";
}
} else {
$message = "تمامی موارد را تکمیل کنید.";
$color = "#ef4444";
}
} else {
$message = "کد امنیتی اشتباه است.";
$color = "#ef4444";
}
}

if(isset($_POST["action"]) && $_POST["action"] =="signup"){
if(check_captcha("signup")){
if(valid_profile() && !empty($_POST["name"]) && !empty($_POST["family"]) && !empty($_POST["tel"]) && filter_var($_POST["email"] ?? "", FILTER_VALIDATE_EMAIL) && strlen($_POST["password"] ?? "") >= 12 && strlen($_POST["password"] ?? "") <= 72){
$stmt = $database->prepare("select id from users where email = ? or cellphone = ?");
$stmt->execute([$_POST["email"], $_POST["tel"]]);
if($stmt->fetch()){
$message = "این ایمیل یا شماره تماس قبلا ثبت شده است.";
$color = "#ef4444";
} else {
$stmt = $database->prepare("insert into users (name,family,cellphone,email,password,role,time,ip) values (?,?,?,?,?,0,?,?)");
$stmt->execute([$_POST["name"],$_POST["family"],$_POST["tel"],$_POST["email"],password_hash($_POST["password"],PASSWORD_DEFAULT),time(),$_SERVER["REMOTE_ADDR"]]);
session_regenerate_id(true);
$_SESSION["user_id"] = $database->lastInsertId();
$_SESSION["role"] = 0;
header("location: user.php");
exit();
}
} else {
$message = "تمامی موارد را تکمیل کنید.";
$color = "#ef4444";
}
} else {
$message = "کد امنیتی اشتباه است.";
$color = "#ef4444";
}
}

render_header("ورود / ثبت‌نام");
?>

<span id="notif" class="notif" style="background:<?=h(($color))?>;position:static;display:<?=h($message ? "block" : "none")?>;margin-bottom:15px;border-radius:8px"><?=h($message)?></span>

<div class="container" style="max-width:420px;padding-top:20px">
<input type="radio" name="lstab" id="tab1" class="tab-input" checked>
<input type="radio" name="lstab" id="tab2" class="tab-input">
<div class="tabs-nav">
<label for="tab1">ورود</label>
<label for="tab2">ثبت‌نام</label>
</div>

<div class="tab-panels">
<div class="tab-content c1">
<form method="post" action=""><?php csrf_field(); ?>
<input type="email" name="email" placeholder="ایمیل">
<input type="password" name="password" placeholder="رمز عبور">
<img src="captcha.php?type=login" alt="کد امنیتی" style="border-radius:6px;margin-bottom:8px">
<input type="text" name="captcha" placeholder="کد امنیتی">
<input type="hidden" name="action" value="login">
<button type="submit" style="width:100%">ورود</button>
</form>
</div>
<div class="tab-content c2">
<form method="post" action=""><?php csrf_field(); ?>
<input type="text" name="name" placeholder="نام">
<input type="text" name="family" placeholder="نام‌خانوادگی">
<input type="tel" name="tel" placeholder="شماره تماس">
<input type="email" name="email" placeholder="ایمیل">
<input type="password" name="password" placeholder="رمز عبور">
<img src="captcha.php?type=signup" alt="کد امنیتی" style="border-radius:6px;margin-bottom:8px">
<input type="text" name="captcha" placeholder="کد امنیتی">
<input type="hidden" name="action" value="signup">
<button type="submit" style="width:100%">ثبت‌نام</button>
</form>
</div>
</div>
</div>

<?php
render_footer();
?>
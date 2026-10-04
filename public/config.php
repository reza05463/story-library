<?php
ini_set("session.use_strict_mode", "1");
session_set_cookie_params(["httponly"=>true,"samesite"=>"Lax","secure"=>!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]!=="off"]);
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Cache-Control: no-store");
if(!session_id()){
session_start();
}
date_default_timezone_set("Asia/Tehran");

define("DB_HOST", getenv("DB_HOST") ?: "127.0.0.1");
define("DB_PORT", getenv("DB_PORT") ?: "3306");
define("DB_NAME", getenv("DB_NAME") ?: "library");
define("DB_USER", getenv("DB_USER") ?: "");
define("DB_PASS", getenv("DB_PASS") ?: "");
define("DB_CHARSET", "utf8mb4");

if(DB_USER === ""){ http_response_code(503); exit("Set DB_USER and DB_PASS in the PHP environment."); }
try{
$database = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=".DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (PDOException $e){
http_response_code(503); exit("Database unavailable. Check local configuration.");
}

// Reject malformed input before any request handler uses it.
foreach([$_GET,$_POST] as $inputs){ foreach($inputs as $value){ if(!is_string($value)){ http_response_code(400); exit("Invalid input type."); } } }
set_exception_handler(function(Throwable $e){ http_response_code(500); exit("Request could not be completed."); });
$_SESSION["csrf"] ??= bin2hex(random_bytes(32));
if(($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && !hash_equals($_SESSION["csrf"],$_POST["csrf"] ?? "")){ http_response_code(403); exit("Invalid request token. Reload the form."); }
foreach(["id","delete","deleteuser","toggle"] as $key){
 foreach([$_GET,$_POST] as $inputs){ if(isset($inputs[$key]) && filter_var($inputs[$key],FILTER_VALIDATE_INT,["options"=>["min_range"=>1]])===false){ http_response_code(400); exit("Invalid identifier."); } }
}
if(isset($_SESSION["user_id"])){
 $check=$database->prepare("SELECT role FROM users WHERE id=?"); $check->execute([$_SESSION["user_id"]]); $role=$check->fetchColumn();
 if($role===false){ unset($_SESSION["user_id"],$_SESSION["role"]); } else { $_SESSION["role"]=(int)$role; }
}
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.h($_SESSION["csrf"]).'">'; }
function action_button($target,$action,$id,$label,$status=null){
 echo '<form method="post" class="action-form" action="'.h($target).'">'; csrf_field();
 echo '<input type="hidden" name="'.h($action).'" value="'.(int)$id.'">';
 if($status!==null){ echo '<input type="hidden" name="status" value="'.h($status).'">'; }
 echo '<button type="submit">'.h($label).'</button></form>';
}
function valid_profile(){
 foreach(["name"=>100,"family"=>100,"tel"=>20,"email"=>150] as $key=>$limit){ if(trim($_POST[$key] ?? "")==="" || mb_strlen($_POST[$key])>$limit) return false; }
 return filter_var($_POST["email"],FILTER_VALIDATE_EMAIL)!==false;
}
function valid_profile_password(){
 global $database;
 $stmt=$database->prepare("SELECT password FROM users WHERE id=?"); $stmt->execute([current_user_id()]);
 $new=$_POST["password"] ?? "";
 return password_verify($_POST["current_password"] ?? "",$stmt->fetchColumn() ?: "") && ($new==="" || (strlen($new)>=12 && strlen($new)<=72));
}
function valid_story(){
 global $database;
 if(trim($_POST["title"] ?? "")==="" || trim($_POST["content"] ?? "")==="") return false;
 if(mb_strlen($_POST["title"] ?? "")>200 || mb_strlen($_POST["content"] ?? "")>100000 || mb_strlen($_POST["summary"] ?? "")>2000) return false;
 $cover=trim($_POST["cover_image"] ?? ""); if(strlen($cover)>255 || ($cover!=="" && cover_src($cover)==="")) return false;
 if(!empty($_POST["category_id"])){
  if(filter_var($_POST["category_id"],FILTER_VALIDATE_INT,["options"=>["min_range"=>1]])===false) return false;
  $stmt=$database->prepare("SELECT id FROM categories WHERE id=?"); $stmt->execute([$_POST["category_id"]]); if(!$stmt->fetchColumn()) return false;
 }
 return true;
}

if(isset($_POST["logout"])){
$_SESSION=[];
session_destroy();
header("location: login.php");
exit();
}

function current_user_id(){
return $_SESSION["user_id"] ?? false;
}

function is_logged_in(){
return current_user_id() !== false;
}

function is_admin(){
return ($_SESSION["role"] ?? 0) ==1;
}

function require_login(){
if(!is_logged_in()){
header("location: login.php");
exit();
}
}

function require_admin(){
if(!is_admin()){
header("location: login.php");
exit();
}
}

function h($text){
return htmlspecialchars((string)($text ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function cover_src($filename){
return !empty($filename) && preg_match("/^[a-zA-Z0-9_-]+\.(png|jpe?g|webp)$/iD",$filename) ? "assets/uploads/" . $filename : "";
}

function render_header($name){
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/src/css/style.css">

<title><?=h(($name))?></title>
</head>
<body>

<header>
<div class="container flex-between">
<a href="index.php" style="color:#38bdf8;font-size:1.6em;font-weight:bold;text-decoration:none">کتابخانه داستان</a>
<nav>
<ul>
<?php if(is_logged_in()){ ?>
<li><a href="write.php" class="nav-btn">+ داستان جدید</a></li>
<li><a href="<?=h(is_admin() ? "admin.php" : "user.php")?>" class="nav-btn">پنل من</a></li>
<li><?php action_button("index.php","logout",1,"خروج"); ?></li>
<?php } else { ?>
<li><a href="login.php" class="nav-btn">ورود / ثبت‌نام</a></li>
<?php } ?>
</ul>
</nav>
</div>
</header>

<main class="container" style="padding:20px 0">
<?php
}

function render_footer(){
?>
</main>

<footer style="background:#1e293b;padding:25px 20px;margin-top:40px;border-top:3px solid #38bdf8;text-align:center">
<p>کتابخانه،نشر داستان های مردمی</p>
<p style="color:#64748b;font-size:.85em;margin-bottom:0">رضا رنجبر،تمامی حقوق محفوظ است  ©</p>
<p style="color:#64748b;font-size:.85em;margin-bottom:0"> <?=h(date("Y/m/d"))?> </p>

</footer>
</body>
</html>
<?php
}

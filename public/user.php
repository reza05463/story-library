<?php
require "config.php";
require_login();

$user_id = current_user_id();
$message = "";
$color = "";

if(isset($_POST["delete"]) && !empty($_POST["delete"])){
$database->prepare("delete from stories where id = ? and user_id = ?")->execute([$_POST["delete"], $user_id]);
header("location: user.php");
exit();
}

if(isset($_POST["action"]) && $_POST["action"] == "edit_profile"){
if(valid_profile() && valid_profile_password() && !empty($_POST["name"]) && !empty($_POST["family"]) && !empty($_POST["tel"]) && filter_var($_POST["email"] ?? "", FILTER_VALIDATE_EMAIL)){
if(!empty($_POST["password"])){
$stmt = $database->prepare("update users set name=?, family=?, cellphone=?, email=?, password=? where id=?");
$stmt->execute([$_POST["name"],$_POST["family"],$_POST["tel"],$_POST["email"],password_hash($_POST["password"],PASSWORD_DEFAULT),$user_id]);
} else {
$stmt = $database->prepare("update users set name=?, family=?, cellphone=?, email=? where id=?");
$stmt->execute([$_POST["name"],$_POST["family"],$_POST["tel"],$_POST["email"],$user_id]);
}
$message = "پروفایل ویرایش شد.";
$color = "#22c55e";
} else {
$message = "تمامی موارد را کامل کنید.";
$color = "#ef4444";
}
}

$stmt = $database->prepare("select * from users where id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $database->prepare("select * from stories where user_id = ? order by id desc");
$stmt->execute([$user_id]);
$stories = $stmt->fetchAll();

render_header("پنل کاربری");
?>

<span id="notif" class="notif" style="background:<?=h(($color))?>;position:static;display:<?=h($message ? "block" : "none")?>;margin-bottom:15px;border-radius:8px"><?=h($message)?></span>

<h1>سلام <?=h(($user["name"]))?></h1>

<section>
<h2 style="font-size:1.1em">ویرایش پروفایل</h2>
<form method="post" action=""><?php csrf_field(); ?>
<input type="text" name="name" value="<?=h(($user["name"]))?>" placeholder="نام">
<input type="text" name="family" value="<?=h(($user["family"]))?>" placeholder="نام‌خانوادگی">
<input type="tel" name="tel" value="<?=h(($user["cellphone"]))?>" placeholder="شماره تماس">
<input type="email" name="email" value="<?=h(($user["email"]))?>" placeholder="ایمیل">
<label>رمز فعلی برای تأیید تغییرات<input type="password" name="current_password" required autocomplete="current-password"></label>
<input type="password" name="password" placeholder="رمز جدید (اختیاری)">
<input type="hidden" name="action" value="edit_profile">
<button type="submit" style="width:100%">ذخیره تغییرات</button>
</form>
</section>

<div class="flex-between mt">
<h2 style="font-size:1.1em">داستان‌های من</h2>
<a href="write.php" class="btn-outline">+ داستان جدید</a>
</div>

<?php if(count($stories) == 0){ ?>
<p class="mt">هنوز داستانی ننوشته‌اید.</p>
<?php } else { ?>
<table class="mt">
<tr><th>عنوان</th><th>بازدید</th><th>وضعیت</th><th>دسترسی</th></tr>
<?php foreach($stories as $s){ ?>
<tr>
<td><a href="index.php?id=<?=h((int)$s["id"])?>"><?=h(($s["title"]))?></a></td>
<td><?=h((int)$s["views"])?></td>
<td><?=h($s["status"]==1 ? "منتشر شده" : "مخفی شده توسط مدیر")?></td>
<td class="actions">
<a href="write.php?id=<?=h((int)$s["id"])?>">ویرایش</a>
<?php action_button('user.php', "delete", (int)$s["id"], "حذف", null); ?>
</td>
</tr>
<?php } ?>
</table>
<?php } ?>

<?php
render_footer();
?>

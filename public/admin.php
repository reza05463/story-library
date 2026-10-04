<?php
require "config.php";
require_admin();

$section = $_GET["section"] ?? "stories";

if(isset($_POST["delete"]) && !empty($_POST["delete"])){
$database->prepare("delete from stories where id = ?")->execute([$_POST["delete"]]);
header("location: admin.php?section=stories");
exit();
}

if(isset($_POST["toggle"]) && isset($_POST["status"])){
$database->prepare("update stories set status = ? where id = ?")->execute([$_POST["status"]== "1" ? 1 : 0, $_POST["toggle"]]);
header("location: admin.php?section=stories");
exit();
}

if(isset($_POST["deleteuser"]) && !empty($_POST["deleteuser"])){
if($_POST["deleteuser"] != current_user_id()){
$database->prepare("delete from users where id = ? and role = 0")->execute([$_POST["deleteuser"]]);
}
header("location: admin.php?section=users");
exit();
}

render_header("پنل مدیریت");
?>

<div class="mt" style="margin-bottom:20px">
<a href="admin.php?section=stories" class="nav-btn <?=h($section== "stories" ? "active" : "")?>">داستان‌ها</a>
<a href="admin.php?section=users" class="nav-btn <?=h($section== "users" ? "active" : "")?>">کاربران</a>
</div>

<?php if($section == "users"){

$users = $database->query("select * from users order by id desc")->fetchAll();
?>
<h1>کاربران</h1>
<table class="mt">
<tr><th>نقش</th><th>نام</th><th>نام‌خانوادگی</th><th>ایمیل</th><th>تلفن</th><th>تاریخ ثبت‌نام</th><th>دسترسی</th></tr>
<?php foreach($users as $u){ ?>
<tr>
<td><?=h($u["role"]== 1 ? "ادمین" : "کاربر")?></td>
<td><?=h(($u["name"]))?></td>
<td><?=h(($u["family"]))?></td>
<td><?=h(($u["email"]))?></td>
<td><?=h(($u["cellphone"]))?></td>
<td><?=h(date("Y-m-d", $u["time"]))?></td>
<td class="actions">
<?php if($u["role"] != 1){ ?>
<?php action_button('admin.php', "deleteuser", (int)$u["id"], "حذف", null); ?>
<?php } ?>
</td>
</tr>
<?php } ?>
</table>
<?php

} else {

$stories = $database->query("
select s.*, u.name as author_name, u.family as author_family
from stories s
join users u on u.id = s.user_id
order by s.id desc
")->fetchAll();
?>
<h1>داستان‌های کاربران</h1>
<p style="color:#94a3b8">می‌توانید هر داستان را ویرایش، مخفی یا حذف کنید.</p>
<table class="mt">
<tr><th>عنوان</th><th>نویسنده</th><th>بازدید</th><th>وضعیت</th><th>دسترسی</th></tr>
<?php foreach($stories as $s){ ?>
<tr>
<td><a href="index.php?id=<?=h((int)$s["id"])?>"><?=h($s["title"])?></a></td>
<td><?=h($s["author_name"])?> <?=h($s["author_family"])?></td>
<td><?=h((int)$s["views"])?></td>
<td><?=h($s["status"]== 1 ? "منتشر شده" : "مخفی")?></td>
<td class="actions">
<a href="write.php?id=<?=h((int)$s["id"])?>">ویرایش</a>
<?php if($s["status"]== 1){ ?>
<?php action_button('admin.php', "toggle", (int)$s["id"], "مخفی کردن", '0'); ?>
<?php } else { ?>
<?php action_button('admin.php', "toggle", (int)$s["id"], "نمایش دادن", '1'); ?>
<?php } ?>
<?php action_button('admin.php', "delete", (int)$s["id"], "حذف", null); ?>
</td>
</tr>
<?php } ?>
</table>
<?php } ?>

<?php
render_footer();
?>
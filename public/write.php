<?php
require "config.php";
require_login();

$categories = $database->query("select * from categories order by title")->fetchAll();
$message = "";
$color = "";
$story = null;
$is_edit = isset($_GET["id"]) && !empty($_GET["id"]);

if($is_edit){
$stmt = $database->prepare("select * from stories where id = ?");
$stmt->execute([$_GET["id"]]);
$story = $stmt->fetch();

if(!$story || ($story["user_id"] != current_user_id() && !is_admin())){
header("location: index.php");
exit();
}
}

if(isset($_POST["action"]) && in_array($_POST["action"], ["add_story","edit_story"])){
if(valid_story() && !empty($_POST["title"]) && !empty($_POST["content"])){
$category_id = !empty($_POST["category_id"]) ? $_POST["category_id"] : null;
$cover = trim($_POST["cover_image"] ?? "");

if($_POST["action"] == "add_story"){
$stmt = $database->prepare("insert into stories (user_id,category_id,title,summary,content,cover_image,status,created_at) values (?,?,?,?,?,?,1,?)");
$stmt->execute([current_user_id(),$category_id,$_POST["title"],$_POST["summary"] ?? "",$_POST["content"],$cover,time()]);
header("location: index.php?id=" . $database->lastInsertId());
exit();
} else {
if($story && ($story["user_id"] == current_user_id() || is_admin())){
$stmt = $database->prepare("update stories set category_id=?, title=?, summary=?, content=?, cover_image=?, updated_at=? where id=?");
$stmt->execute([$category_id,$_POST["title"],$_POST["summary"] ?? "",$_POST["content"],$cover,time(),$story["id"]]);
header("location: index.php?id=" . $story["id"]);
exit();
}
}
} else {
$message = "عنوان و متن داستان الزامی است.";
$color = "#ef4444";
}
}

render_header($is_edit ? "ویرایش داستان" : "نوشتن داستان جدید");
?>

<span id="notif" class="notif" style="background:<?=h(($color))?>;position:static;display:<?=h($message ? "block" : "none")?>;margin-bottom:15px;border-radius:8px"><?=h(($message))?></span>

<h1><?=h($is_edit ? "ویرایش داستان" : "نوشتن داستان جدید")?></h1>
<section>
<form method="post" action=""><?php csrf_field(); ?>
<input type="text" name="title" placeholder="عنوان داستان" value="<?=h(($story["title"] ?? ""))?>">
<select name="category_id">
<option value="">بدون دسته‌بندی</option>
<?php foreach($categories as $cat){ ?>
<option value="<?=h((int)$cat["id"])?>" <?=h((isset($story["category_id"]) && $story["category_id"]==$cat["id"]) ? "selected" : "")?>><?=h(($cat["title"]))?></option>
<?php } ?>
</select>
<input type="text" name="cover_image" placeholder="نام فایل عکس جلد (مثلا: story1.jpg)" value="<?=h(($story["cover_image"] ?? ""))?>">
<p style="font-size:.8em;color:#64748b;margin-top:-10px">فایل عکس را در پوشه assets/uploads قرار دهید و اسمش را همینجا وارد کنید.</p>
<textarea name="summary" rows="2" placeholder="خلاصه کوتاه (اختیاری)"><?=h(($story["summary"] ?? ""))?></textarea>
<textarea name="content" rows="18" placeholder="متن داستان..."><?=h(($story["content"] ?? ""))?></textarea>
<input type="hidden" name="action" value="<?=h($is_edit ? "edit_story" : "add_story")?>">
<button type="submit" style="width:100%"><?=h($is_edit ? "ذخیره ویرایش" : "انتشار داستان")?></button>
</form>
</section>

<?php
render_footer();
?>

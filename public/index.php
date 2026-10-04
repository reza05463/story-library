<?php
require "config.php";

$categories = $database->query("
select c.*, (select count(*) from stories s where s.category_id = c.id and s.status = 1) as story_count
from categories c
order by c.title
")->fetchAll();
$total_stories = $database->query("select count(*) from stories where status = 1")->fetchColumn();

if(isset($_GET["id"]) && !empty($_GET["id"])){

$id = $_GET["id"];
$database->prepare("update stories set views = views + 1 where id = ? and status = 1")->execute([$id]);

$stmt = $database->prepare("
select s.*, u.id as author_id, u.name as author_name, u.family as author_family, c.title as category_title
from stories s
join users u on u.id = s.user_id
left join categories c on c.id = s.category_id
where s.id = ? and s.status = 1
");
$stmt->execute([$id]);
$story = $stmt->fetch();

if(!$story){ http_response_code(404); }
render_header($story ? $story["title"] : "داستان یافت نشد");

if(!$story){
?>
<section class="text-center">
<h2>داستان مورد نظر یافت نشد یا در دسترس نیست.</h2>
<a href="index.php" class="btn-outline mt">بازگشت به کتابخانه</a>
</section>
<?php
render_footer();
exit();
}

$is_owner = is_logged_in() && (current_user_id() ==$story["author_id"] || is_admin());
?>
<section>
<?php if(!empty($story["category_title"])){ ?><span class="badge"><?=h(($story["category_title"]))?></span><?php } ?>
<h1><?=h($story["title"])?></h1>
<p style="color:#94a3b8">نویسنده: <?=h($story["author_name"])?> <?=h(($story["author_family"]))?> · 👁 <?=h((int)$story["views"])?> بازدید</p>

<?php if($is_owner){ ?>
<p class="actions">
<a href="write.php?id=<?=h((int)$story["id"])?>" class="btn-outline">ویرایش</a>
<?php action_button((is_admin() ? 'admin.php' : 'user.php'), "delete", (int)$story["id"], "حذف", null); ?>
</p>
<?php } ?>

<?php if(!empty($story["cover_image"])){ ?>
<img src="<?=h(cover_src($story["cover_image"]))?>" alt="" style="max-width:100%;border-radius:10px;margin:15px 0">
<?php } ?>

<div class="mt" style="line-height:2">
<?=nl2br(h($story["content"]))?>
</div>
</section>
<?php

} else {

$category = $_GET["category"] ?? null;
if($category){
$stmt = $database->prepare("
select s.*, u.name as author_name, u.family as author_family
from stories s
join users u on u.id = s.user_id
join categories c on c.id = s.category_id
where s.status = 1 and c.slug = ?
order by s.id desc
");
$stmt->execute([$category]);
} else {
$stmt = $database->query("
select s.*, u.name as author_name, u.family as author_family
from stories s
join users u on u.id = s.user_id
where s.status = 1
order by s.id desc
");
}
$stories = $stmt->fetchAll();

render_header("کتابخانه داستان");
$category_title = null;
if($category){
foreach($categories as $cat){ if($cat["slug"]==$category){ $category_title = $cat["title"]; } }
}
?>
<h1>کتابخانه داستان‌ها</h1>
<p style="color:#94a3b8">داستان‌هایی که کاربران این سایت نوشته‌اند.</p>

<h2 style="font-size:1.1em;margin-top:25px">دسته‌بندی‌ها</h2>
<div class="grid mt" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr))">
<a href="index.php" class="project-card text-center" style="<?=h(!$category ? "border-color:#38bdf8" : "")?>">
<strong>همه</strong>
<p style="color:#94a3b8;font-size:.85em;margin-top:4px"><?=h((int)$total_stories)?> داستان</p>
</a>
<?php foreach($categories as $cat){ ?>
<a href="index.php?category=<?=h(($cat["slug"]))?>" class="project-card text-center" style="<?=h($category==$cat["slug"] ? "border-color:#38bdf8" : "")?>">
<strong><?=h(($cat["title"]))?></strong>
<p style="color:#94a3b8;font-size:.85em;margin-top:4px"><?=h((int)$cat["story_count"])?> داستان</p>
</a>
<?php } ?>
</div>

<h2 style="font-size:1.1em;margin-top:35px"><?=h($category_title ? "داستان‌های دسته «".($category_title)."»" : "همه داستان‌ها")?></h2>

<?php if(count($stories) ==0){ ?>
<p class="mt">داستانی در این دسته ثبت نشده است.</p>
<?php } else { ?>
<div class="grid mt">
<?php foreach($stories as $s){ ?>
<a href="index.php?id=<?=h((int)$s["id"])?>" class="project-card">
<?php if(!empty($s["cover_image"])){ ?>
<img src="<?=h(cover_src($s["cover_image"]))?>" alt="<?=h(($s["title"]))?>">
<?php } else { ?>
<div class="book-cover-placeholder"></div>
<?php } ?>
<strong><?=h(($s["title"]))?></strong>
<p style="margin:4px 0;font-size:.9em;color:#94a3b8">نویسنده: <?=h(($s["author_name"]))?>_<?=h(($s["author_family"]))?></p>
<?php $snippet = $s["summary"] ? $s["summary"] : $s["content"]; ?>
<p style="font-size:.85em;color:#64748b"><?=h((mb_substr($snippet,0,80)))?>…</p>
</a>
<?php } ?>
</div>
<?php } ?>
<?php

}

render_footer();
?>

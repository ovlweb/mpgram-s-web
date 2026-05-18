<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
include 'redirect.php';

include 'mp.php';
header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store');
$theme = MP::getSettingInt('theme', 0);
include 'themes.php';
Themes::setTheme($theme);
$lng = MP::initLocale();
echo MP::x('<html><head><title>'.$lng['about'].'</title>');
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo Themes::iframeDetectScript();
echo Themes::appbar($lng['about'] ?? 'About', 'chats.php');

require_once 'vendor/autoload.php';
?>
<div class="container" style="padding: 16px 12px">
<h1>MPGram S Web</h1>
<p>MPGram S Web / MPGram Web Client (aka MIDletPascalGram Web) is lightweight telegram web client based on MadelineProto library, for devices with slow internet access and basic HTML & CSS support</p>

<p>Currently running on MadelineProto <?= \danog\MadelineProto\API::RELEASE ?> / PHP <?= phpversion() ?></p>
<p>Links:<br>
<a href="https://github.com/shinovon/mpgram-web">GitHub</a><br>
<?php
if (MP::getUser()) {
    echo '<a href="chat.php?c=nnmidletschat">Discussion chat</a>';
} else {
    echo '<a href="https://t.me/nnmidletschat">Discussion chat</a>';
}
?>
<br><a href="https://nnproject.cc/mp">Page on nnproject</a><br>
</p>
<p>Developers:<br>
<b>Shinovon</b> <a href="https://github.com/shinovon">github</a>
 <a href="https://t.me/shinovon">t.me</a>
<br>
<b>MuseCat77</b>
 <a href="https://github.com/musecat77">github</a>
 <a href="https://t.me/musecat77">t.me</a>
</p>
<p>Idea author:<br>
<b>twsparkle</b> <a href="https://github.com/diller444">github</a>
 <a href="https://t.me/twsparkle">t.me</a>
</p>
<p>Donate:<br>
<a href="https://boosty.to/nnproject/donate">boosty.to/nnproject/donate</a><br>
</p>
</div>
<?php
echo Themes::bodyEnd();

<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
$user = MP::getUser();
if (!$user) {
    header('Location: login.php?logout=1');
    die;
}

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$id = $_POST['c'] ?? $_GET['c'] ?? die;
$reply_to = $_POST['reply_to'] ?? $_GET['reply_to'] ?? null;
$popup = isset($_POST['popup']) || isset($_GET['popup']);
$popupParam = $popup ? '&popup=1' : '';

header('Content-Type: text/html; charset='.MP::$enc);
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
include 'themes.php';
Themes::setTheme($theme);

echo '<html><head><title>'.MP::x($lng['send_message']).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart('class="sticker-picker-page"');
echo '<p>'.MP::x($lng['stickers_disabled'] ?? 'Sticker sending is temporarily disabled.').'</p>';
echo '<p><a class="bth" href="chat.php?c='.MP::dehtml($id).'">'.MP::x($lng['back']).'</a></p>';
echo Themes::bodyEnd();
die;

function sticker_cache_dir(): ?string {
    $dir = __DIR__.'/cache/stickers';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return (is_dir($dir) && is_writable($dir)) ? $dir : null;
}

function sticker_sets_cached($MP, string $user): array {
    $dir = sticker_cache_dir();
    $file = $dir ? $dir.'/'.sha1($user.'|sets').'.json' : null;
    if ($file && is_file($file) && filemtime($file) + 3600 > time()) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    if (!$MP) return [];
    $sets = $MP->messages->getAllStickers()['sets'] ?? [];
    $out = [];
    foreach ($sets as $set) {
        if (!isset($set['id'], $set['access_hash'])) continue;
        $out[] = [
            'id' => (string)$set['id'],
            'access_hash' => (string)$set['access_hash'],
            'title' => (string)($set['title'] ?? $set['short_name'] ?? 'Stickers'),
        ];
    }
    if ($file) @file_put_contents($file, json_encode($out));
    return $out;
}

function sticker_documents_cached($MP, string $user, array $set): array {
    $dir = sticker_cache_dir();
    $key = sha1($user.'|docs2|'.$set['id'].'|'.$set['access_hash']);
    $file = $dir ? $dir.'/'.$key.'.json' : null;
    if ($file && is_file($file) && filemtime($file) + 3600 > time()) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    if (!$MP) return [];
    $result = $MP->messages->getStickerSet(stickerset: ['_' => 'inputStickerSetID', 'id' => (int)$set['id'], 'access_hash' => (int)$set['access_hash']]);
    $docs = [];
    foreach (($result['documents'] ?? []) as $doc) {
        if (!isset($doc['id'], $doc['access_hash'])) continue;
        $alt = '';
        foreach (($doc['attributes'] ?? []) as $attr) {
            if (($attr['_'] ?? '') === 'documentAttributeSticker' && isset($attr['alt'])) {
                $alt = (string)$attr['alt'];
                break;
            }
        }
        $docs[] = [
            'id' => (string)$doc['id'],
            'access_hash' => (string)$doc['access_hash'],
            'mime_type' => (string)($doc['mime_type'] ?? ''),
            'alt' => $alt,
        ];
    }
    if ($file) @file_put_contents($file, json_encode($docs));
    return $docs;
}

try {
    $MP = null;
    if (isset($_GET['id'])) {
        $MP = MP::getMadelineAPI($user);
        $params = ['peer' => $id, 'message' => ''];
        if ($reply_to) {
            $params['reply_to'] = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $reply_to];
        }
        $params['media'] = ['_' => 'document', 'id' => (int) $_GET['id'], 'access_hash' => (int) $_GET['access_hash']];
        /** @noinspection PhpParamsInspection */
        $MP->messages->sendMedia($params);
        if ($popup) {
            $returnUrl = 'chat.php?c='.$id;
            echo '<html><head><title>'.MP::x($lng['send_message']).'</title>';
            echo Themes::head();
            echo '</head>';
            echo Themes::bodyStart('class="sticker-popup-page"');
            echo '<script>if (parent && parent.location) parent.location.href='.json_encode($returnUrl).';</script>';
            echo '<p><a class="bth" target="_top" href="'.$returnUrl.'">'.MP::x($lng['back']).'</a></p>';
            echo Themes::bodyEnd();
            die;
        }
        header('Location: chat.php?c='.$id);
        die;
    }
    echo '<html><head><title>'.MP::x($lng['send_message']).'</title>';
    echo Themes::head();
    echo '</head>';
    echo Themes::bodyStart('class="sticker-picker-page"');
    $sets = sticker_sets_cached(null, $user);
    if (!$sets) {
        $MP = MP::getMadelineAPI($user);
        $sets = sticker_sets_cached($MP, $user);
    }
    if (!$sets) {
        echo '<p>'.MP::x($lng['no_stickers'] ?? 'No stickers').'</p>';
        echo Themes::bodyEnd();
        die;
    }
    $selectedId = isset($_GET['set']) ? (string)$_GET['set'] : (string)$sets[0]['id'];
    $selected = $sets[0];
    foreach ($sets as $setCandidate) {
        if ((string)$setCandidate['id'] === $selectedId) {
            $selected = $setCandidate;
            break;
        }
    }
    echo '<div class="sticker-set-tabs">';
    foreach ($sets as $setCandidate) {
        $active = ((string)$setCandidate['id'] === (string)$selected['id']) ? ' active' : '';
        echo '<a class="sticker-set-tab'.$active.'" href="sendsticker.php?c='.$id.'&set='.$setCandidate['id'].($reply_to?'&reply_to='.$reply_to:'').$popupParam.'">'.MP::dehtml($setCandidate['title']).'</a>';
    }
    echo '</div>';
    echo '<div class="sticker-grid" aria-label="'.MP::x($lng['choose_sticker']).'">';
    $documents = sticker_documents_cached(null, $user, $selected);
    if (!$documents) {
        if (!$MP) $MP = MP::getMadelineAPI($user);
        $documents = sticker_documents_cached($MP, $user, $selected);
    }
    $idx = 0;
    foreach ($documents as $v) {
    $idx++;
        echo '<a class="sticker-item" href="sendsticker.php?c='.$id.'&id='.$v['id'].'&access_hash='.$v['access_hash'].($reply_to?'&reply_to='.$reply_to:'').$popupParam.'">';
        if (($v['mime_type'] ?? '') == 'application/x-tgsticker') {
            echo '<span class="sticker-alt">'.MP::dehtml($v['alt'] ?: '★').'</span>';
        } else {
            echo '<img data-src="file.php?sticker='.$v['id'].'&access_hash='.$v['access_hash'].'&p=rsprev&s=96" alt="">';
        }
        echo '</a>';
    }
    echo '</div>';
    echo '<script type="text/javascript"><!--
(function(){
  var imgs=document.getElementsByTagName("img"),q=[],i,active=0,max=4;
  for(i=0;i<imgs.length;i++){ if(imgs[i].getAttribute("data-src")) q.push(imgs[i]); }
  function next(){
    while(active<max&&q.length){
      var img=q.shift(),src=img.getAttribute("data-src");
      if(!src)continue;
      active++;
      img.onload=img.onerror=function(){ active--; setTimeout(next,20); };
      img.src=src;
    }
  }
  next();
})();
//--></script>';
    echo Themes::bodyEnd();
} catch (Exception $e) {
    echo $e;
}

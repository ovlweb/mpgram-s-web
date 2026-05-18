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

$theme = MP::getSettingInt('theme', 0);
$pngava = MP::getSettingInt('pngava', 0);

$id = $_POST['c'] ?? $_GET['c'] ?? die;

header('Content-Type: text/html; charset='.MP::$enc);
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

function profile_esc($value) {
    return MP::dehtml((string)$value);
}

function profile_join_name($data) {
    return $data['title'] ?? (isset($data['first_name']) ? $data['first_name'] . (isset($data['last_name']) ? ' '.$data['last_name'] : '') : null) ?? 'Deleted Account';
}

function profile_detail($label, $value, $extraClass = '') {
    if ($value === null || $value === '') {
        return;
    }
    echo '<div class="profile-detail'.($extraClass ? ' '.$extraClass : '').'">';
    echo '<div class="profile-label">'.profile_esc($label).'</div>';
    echo '<div class="profile-value">'.$value.'</div>';
    echo '</div>';
}

function profile_count_text($type, $memberscount, $members, $onlines, $lng) {
    if ($type == 'user') {
        return '';
    }
    $count = $memberscount !== false ? $memberscount : (is_array($members) ? count($members) : 0);
    if ($count <= 0) {
        return '';
    }
    $text = MP::x(MPLocale::number($type == 'chat' || $type == 'supergroup' ? 'members' : 'subscribers', $count));
    if ($onlines > 0) {
        $text .= ', ' . strval($onlines) . ' ' . MP::x($lng['online']);
    }
    return $text;
}

function profile_username_chips($mainUsername, $sources) {
    $seen = [];
    $out = [];
    $main = strtolower((string)$mainUsername);
    foreach ($sources as $list) {
        if (!is_array($list)) continue;
        foreach ($list as $u) {
            if (!is_array($u)) continue;
            $name = $u['username'] ?? '';
            if ($name === '') continue;
            $key = strtolower($name);
            if ($key === $main || isset($seen[$key])) continue;
            $seen[$key] = true;
            $cls = !empty($u['active']) ? 'profile-chip' : 'profile-chip profile-chip-muted';
            $out[] = '<span class="'.$cls.'">@'.profile_esc($name).'</span>';
        }
    }
    return implode(' ', $out);
}

function profile_emoji_status_text($emojiStatus) {
    if (!is_array($emojiStatus)) {
        return null;
    }
    if (($emojiStatus['_'] ?? '') === 'emojiStatusEmpty') {
        return null;
    }
    if (isset($emojiStatus['emoji_status']) && is_array($emojiStatus['emoji_status'])) {
        return profile_emoji_status_text($emojiStatus['emoji_status']);
    }
    $parts = [];
    if (!empty($emojiStatus['title'])) {
        $parts[] = profile_esc($emojiStatus['title']);
    } elseif (!empty($emojiStatus['slug'])) {
        $parts[] = profile_esc($emojiStatus['slug']);
    } elseif (profile_emoji_status_icon($emojiStatus)) {
        $parts[] = 'Custom emoji';
    } else {
        $parts[] = 'Custom emoji';
    }
    if (!empty($emojiStatus['until'])) {
        $parts[] = 'until '.date('Y-m-d', (int)$emojiStatus['until']);
    }
    return implode(' ', $parts);
}

function profile_emoji_status_icon($emojiStatus) {
    if (!is_array($emojiStatus)) {
        return null;
    }
    if (($emojiStatus['_'] ?? '') === 'emojiStatusEmpty') {
        return null;
    }
    if (isset($emojiStatus['emoji_status']) && is_array($emojiStatus['emoji_status'])) {
        return profile_emoji_status_icon($emojiStatus['emoji_status']);
    }
    foreach (['document_id', 'custom_emoji_id', 'emoji_id', 'emoji_status_document_id', 'emoji_status_custom_emoji_id', 'custom_emoji_document_id'] as $key) {
        if (!empty($emojiStatus[$key])) {
            return $emojiStatus[$key];
        }
    }
    if (isset($emojiStatus['_']) && strpos((string)$emojiStatus['_'], 'emojiStatus') !== false && !empty($emojiStatus['id'])) {
        return $emojiStatus['id'];
    }
    return null;
}

function profile_pick_emoji_status($statuses) {
    $fallback = null;
    foreach ($statuses as $emojiStatus) {
        if (!is_array($emojiStatus)) {
            continue;
        }
        if (($emojiStatus['_'] ?? '') === 'emojiStatusEmpty') {
            continue;
        }
        if (profile_emoji_status_icon($emojiStatus)) {
            return $emojiStatus;
        }
        if ($fallback === null) {
            $fallback = $emojiStatus;
        }
    }
    return $fallback;
}

function profile_chat_from_peer($chatId, $infoPeer, $fullPeer, $fullinfo) {
    $peer = is_array($fullPeer) ? $fullPeer : (is_array($infoPeer) ? $infoPeer : []);
    if (!isset($peer['id'])) {
        $peer['id'] = is_numeric($chatId) ? (int)$chatId : $chatId;
    }
    if (!isset($peer['type'])) {
        if (($peer['_'] ?? '') === 'user' || isset($peer['first_name']) || ((int)$peer['id'] > 0)) {
            $peer['type'] = 'user';
        } elseif (!empty($peer['broadcast'])) {
            $peer['type'] = 'channel';
        } elseif (($peer['_'] ?? '') === 'channel') {
            $peer['type'] = 'supergroup';
        } else {
            $peer['type'] = 'chat';
        }
    }
    if (!isset($peer['participants_count']) && is_array($fullinfo)) {
        foreach (['participants_count', 'members_count'] as $key) {
            if (isset($fullinfo[$key])) {
                $peer['participants_count'] = $fullinfo[$key];
                break;
            }
        }
    }
    return $peer;
}

function profile_bot_verification_text($verification, $iconId = null) {
    return (is_array($verification) || $iconId) ? 'Verified via third-party' : null;
}

function profile_bot_verification_icon($verification, $iconId = null) {
    if (is_array($verification) && !empty($verification['icon'])) {
        return $verification['icon'];
    }
    return $iconId;
}

function profile_resize_image($image, $w, $h) {
    $w = (int)$w;
    $h = (int)$h;
    $oldw = imagesx($image);
    $oldh = imagesy($image);
    $temp = imagecreatetruecolor($w, $h);
    imagecopyresampled($temp, $image, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
    return $temp;
}

function profile_avatar_cache_path($user, $cid, $p, $png) {
    $dir = __DIR__.'/cache/avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return null;
    }
    return $dir.'/'.sha1('v2|'.$user.'|'.$cid.'|'.$p).($png ? '.png' : '.jpg');
}

function profile_warm_avatar_cache($MP, $user, $cid, $info, $p) {
    if (!str_starts_with($p, 'r')) return;
    $rp = substr($p, 1);
    $png = str_starts_with($rp, 'c') || str_starts_with($rp, 'p');
    $cacheFile = profile_avatar_cache_path($user, $cid, $p, $png);
    if ($cacheFile === null || is_file($cacheFile)) return;
    try {
        $di = null;
        try {
            $di = $MP->getPropicInfo($info);
        } catch (Throwable $e) {}
        if ($di === null) {
            $img = imagecreatefromstring(file_get_contents((int)$cid < 0 ? 'img/gr.png' : 'img/us.png'));
        } else {
            try {
                $payload = new Amp\ByteStream\Payload($MP->downloadToReturnedStream($di));
                $img = imagecreatefromstring($payload->buffer());
                $payload->close();
            } catch (Throwable $e) {
                $img = imagecreatefromstring(file_get_contents((int)$cid < 0 ? 'img/gr.png' : 'img/us.png'));
            }
        }
        $sizePart = $rp;
        if (str_starts_with($sizePart, 'c')) {
            $png = true;
            $sizePart = substr($sizePart, 1);
            $w = imagesx($img);
            $h = imagesy($img);
            $mask = imagecreatetruecolor($w, $h);
            $c = imagecolorallocate($mask, 255, 0, 0);
            imagecolortransparent($mask, $c);
            imagefilledellipse($mask, (int)($w / 2), (int)($h / 2), $w, $h, $c);
            $r = imagecolorallocate($mask, 0, 0, 0);
            imagecopymerge($img, $mask, 0, 0, 0, 0, $w, $h, 100);
            imagecolortransparent($img, $r);
            imagefill($img, 0, 0, $r);
        }
        if (str_starts_with($sizePart, 'p')) {
            $png = true;
            $sizePart = substr($sizePart, 1);
        }
        if ($sizePart != 'orig') {
            $s = (int)$sizePart;
            if ($s > 0) {
                $img = profile_resize_image($img, $s, $s);
            }
        }
        ob_start();
        if ($png) {
            imagepng($img);
        } else {
            imagejpeg($img, null, 82);
        }
        $data = ob_get_clean();
        @file_put_contents($cacheFile, $data);
        imagedestroy($img);
    } catch (Throwable $e) {}
}

function profile_warm_verify_icon_cache($MP, $user, $icon, $size) {
    if (!$icon || !is_numeric($icon)) return;
    if (!defined('VERIFYICON_LIB_ONLY')) define('VERIFYICON_LIB_ONLY', true);
    include_once 'verifyicon.php';
    $cacheFile = vi_cache_path($user, $icon, $size);
    if ($cacheFile === null || is_file($cacheFile)) return;
    try {
        $docs = $MP->messages->getCustomEmojiDocuments(['document_id' => [(int)$icon]]);
        $doc = $docs[0] ?? null;
        if (!$doc) return;
        $payload = new Amp\ByteStream\Payload($MP->downloadToReturnedStream($doc));
        $data = $payload->buffer();
        $payload->close();
        $img = false;
        try {
            $img = @imagecreatefromstring($data);
        } catch (Throwable $e) {}
        if ($img) {
            $img = vi_resize($img, $size);
        } elseif (($doc['mime_type'] ?? '') === 'application/x-tgsticker') {
            $img = vi_lottie_frame($data, $size);
            if (!$img) {
                $tmpDir = sys_get_temp_dir().'/mp/';
                if (!is_dir($tmpDir)) {
                    @mkdir($tmpDir, 0775, true);
                }
                $prefix = $tmpDir.'profileicon_'.sha1($user.'|'.$icon.'|'.$size);
                $rawPath = $prefix.'.raw';
                @file_put_contents($rawPath, $data);
                $converted = vi_png_from_tgs($rawPath, $prefix, $size);
                if ($converted && is_file($converted)) {
                    try {
                        $img = @imagecreatefromstring(file_get_contents($converted));
                    } catch (Throwable $e) {
                        $img = false;
                    }
                    if ($img) {
                        $img = vi_resize($img, $size);
                    }
                    @unlink($converted);
                }
                @unlink($rawPath);
            }
        } elseif (($doc['mime_type'] ?? '') === 'video/webm') {
            $tmpDir = sys_get_temp_dir().'/mp/';
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0775, true);
            }
            $prefix = $tmpDir.'profileicon_'.sha1($user.'|'.$icon.'|'.$size);
            $rawPath = $prefix.'.raw';
            $pngPath = $prefix.'.png';
            @file_put_contents($rawPath, $data);
            $converted = vi_png_from_webm($rawPath, $pngPath, $size);
            if ($converted && is_file($converted)) {
                try {
                    $img = @imagecreatefromstring(file_get_contents($converted));
                } catch (Throwable $e) {
                    $img = false;
                }
                if ($img) {
                    $img = vi_resize($img, $size);
                }
                @unlink($converted);
            }
            @unlink($rawPath);
            @unlink($pngPath);
        }
        if (!$img) return;
        ob_start();
        imagepng($img);
        $out = ob_get_clean();
        @file_put_contents($cacheFile, $out);
        imagedestroy($img);
    } catch (Throwable $e) {}
}

try {
    $MP = MP::getMadelineAPI($user);

    $lng = MP::initLocale();

    include 'themes.php';
    Themes::setTheme($theme);

    $chatId = $id;
    $full = [];
    try {
        $full = $MP->getFullInfo($chatId);
    } catch (Throwable $e) {}
    $fullinfo = $full['full'] ?? ($full['full_user'] ?? null);
    $fullPeer = $full['users'][0] ?? ($full['chats'][0] ?? null);
    $infoPeer = null;
    try {
        $info = $MP->getInfo($chatId);
        $infoPeer = $info['User'] ?? ($info['Chat'] ?? null);
    } catch (Throwable $e) {}
    try {
        $chat = $MP->getPwrChat($chatId);
    } catch (Throwable $e) {
        $chat = profile_chat_from_peer($chatId, $infoPeer, $fullPeer, $fullinfo);
    }
    
    $name = profile_join_name($chat);
    $type = $chat['type'] ?? (((int)($chat['id'] ?? 0) > 0) ? 'user' : 'chat');

    $desc = null;
    $pin = $fullinfo['pinned_msg_id'] ?? false;

    $members = null;
    $onlines = 0;
    $memberscount = false;
    if ($type != 'user') {
        $desc = $fullinfo['about'] ?? null;
        $members = $chat['participants'] ?? null;
        $memberscount = $chat['participants_count'] ?? false;

        if ($members) {
            foreach ($members as $i => $m) {
                if (isset($m['kicked_by'])) {
                    unset($members[$i]);
                } elseif (isset($m['user']['status']) && $m['user']['status']['_'] == 'userStatusOnline') {
                    $onlines ++;
                }
            }
        }
    }

    $about = $type == 'user' ? ($fullinfo['about'] ?? null) : $desc;
    $mainUsername = $chat['username'] ?? ($fullPeer['username'] ?? null);
    $phone = $chat['phone'] ?? ($fullPeer['phone'] ?? null);
    $emojiStatus = profile_pick_emoji_status([
        $chat['emoji_status'] ?? null,
        $infoPeer['emoji_status'] ?? null,
        $fullPeer['emoji_status'] ?? null,
        $fullinfo['emoji_status'] ?? null,
    ]);
    $emojiStatusText = profile_emoji_status_text($emojiStatus);
    $emojiStatusIcon = profile_emoji_status_icon($emojiStatus);
    $botVerification = $fullinfo['bot_verification'] ?? ($chat['bot_verification'] ?? null);
    $botVerificationIcon = $chat['bot_verification_icon'] ?? ($infoPeer['bot_verification_icon'] ?? ($fullPeer['bot_verification_icon'] ?? null));
    $botVerificationText = profile_bot_verification_text($botVerification, $botVerificationIcon);
    $botVerificationIcon = profile_bot_verification_icon($botVerification, $botVerificationIcon);
    $subtitle = profile_count_text($type, $memberscount, $members, $onlines, $lng);
    $commonChats = $fullinfo['common_chats_count'] ?? null;
    $allUsernames = profile_username_chips($mainUsername, [
        $chat['usernames'] ?? null,
        $infoPeer['usernames'] ?? null,
        $fullPeer['usernames'] ?? null,
        $fullinfo['usernames'] ?? null,
    ]);
    $displayId = $chat['id'] ?? $chatId;
    $avatarParam = ($pngava ? 'rc' : 'r').'128';
    profile_warm_avatar_cache($MP, $user, $chatId, $fullPeer ?: ($infoPeer ?: $chat), $avatarParam);
    if ($emojiStatusIcon) {
        profile_warm_verify_icon_cache($MP, $user, $emojiStatusIcon, 64);
    }
    if ($botVerificationIcon) {
        profile_warm_verify_icon_cache($MP, $user, $botVerificationIcon, 64);
    }

    echo '<html><head><title>'.MP::dehtml($name).'</title>';
    echo Themes::head();
    echo '</head>';
    echo Themes::bodyStart('class="profile-page"');
    echo '<div class="profile-shell">';
    echo '<div class="profile-topbar"><a class="bth profile-back" href="chat.php?c='.$chatId.'">'.MP::x($lng['back']).'</a></div>';
    echo '<div class="profile-hero">';
    echo '<img class="profile-avatar" src="ava.php?c='.$chatId.'&p='.$avatarParam.'" alt="">';
    echo '<div class="profile-hero-text">';
    echo '<div class="profile-name">';
    if ($botVerificationText) {
        if ($botVerificationIcon) {
            echo '<span class="profile-badge-icon profile-verify-icon profile-verify-before"><img src="verifyicon.php?i='.profile_esc($botVerificationIcon).'&s=64" alt=""></span> ';
        } else {
            echo '<span class="profile-badge profile-badge-blue profile-verify-before">✓</span> ';
        }
    }
    echo profile_esc($name);
    if ($emojiStatusIcon) {
        echo ' <span class="profile-badge-icon profile-status-icon"><img src="verifyicon.php?i='.profile_esc($emojiStatusIcon).'&s=64" alt=""></span>';
    }
    if (!empty($chat['verified']) || !empty($fullPeer['verified'])) echo ' <span class="profile-badge profile-badge-blue">✓</span>';
    if (!empty($chat['bot']) || !empty($fullPeer['bot'])) echo ' <span class="profile-badge">BOT</span>';
    echo '</div>';
    if ($subtitle) echo '<div class="profile-subtitle">'.$subtitle.'</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="profile-card">';
    echo '<div class="profile-card-title">'.MP::x($lng['set_profile'] ?? 'Profile').'</div>';
    if ($about) profile_detail($type == 'user' ? MP::x($lng['chat_bio']) : MP::x($lng['chat_about']), nl2br(profile_esc($about)));
    if ($phone) profile_detail(MP::x($lng['chat_phone']), '+'.profile_esc($phone));
    if ($mainUsername) profile_detail(MP::x($lng['chat_username']), '@'.profile_esc($mainUsername));
    if ($allUsernames) profile_detail(MP::x($lng['set_collectible_usernames'] ?? 'Additional usernames'), $allUsernames);
    if ($type != 'user' && $mainUsername) profile_detail(MP::x($lng['chat_link']), 't.me/'.profile_esc($mainUsername));
    if ($emojiStatusText) {
        $emojiStatusValue = $emojiStatusIcon
            ? '<span class="profile-chip-icon"><img src="verifyicon.php?i='.profile_esc($emojiStatusIcon).'&s=64" alt=""></span> '.$emojiStatusText
            : $emojiStatusText;
        profile_detail('Emoji status', $emojiStatusValue);
    }
    if ($botVerificationText) profile_detail('Third-party verification', $botVerificationText);
    if ($commonChats !== null && $commonChats > 0) profile_detail($lng['set_common_chats'] ?? 'Common chats', (string)(int)$commonChats);
    profile_detail('ID', profile_esc($displayId), 'profile-detail-muted');
    echo '</div>';

    if ($pin) {
        try {
            $msg = $MP->messages->getHistory([
                'peer' => $chatId,
                'offset_id' => $pin,
                'offset_date' => 0,
                'add_offset' => -1,
                'limit' => 1,
                'hash' => 0])['messages'];
            echo '<div class="profile-card profile-pinned">';
            echo '<div class="profile-card-title">'.MP::x($lng['pinned_message'] ?? 'Pinned message').'</div>';
            MP::printMessages($MP, $msg, $chatId, false, $type == 'channel', $lng, false, $name, MP::getSettingInt('timeoff'), false, false, null, true, false, 0, false);
            echo '</div>';
        } catch (Exception $e) {
            echo $e;
        }
    }
    if ($type != 'user' && $members) {
        $avas = MP::getSettingInt('avas', 0);
        echo '<div class="profile-card profile-members">';
        echo '<div class="profile-card-title">'.MP::x($lng['chat_members']).'</div>';
        echo '<table class="cl">';
        $i = 0;
        foreach ($members as $m) {
            $i ++;
            if ($i > 100) {
                echo '<tr>...</tr>';
                break;
            }
            echo '<tr class="c">';
            $u = $m['user'] ?? $m['chat'] ?? $m['channel'];
            $memberId = $u['id'];

            $un = profile_join_name($u);
            $status = null;
            if (isset($u['status'])) {
                $status = $u['status']['_'] == 'userStatusOnline';
                $last = $u['status']['was_online'] ?? 0;
            }
            $rank = null;
            if (isset($m['rank'])) {
                $rank = $m['rank'];
            } elseif (isset($m['role'])) {
                $role = $m['role'];
                if ($role == 'creator') {
                    $rank = MP::x($lng['owner']);
                } elseif ($role == 'admin') {
                    $rank = MP::x($lng['admin']);
                }
            }
            if ($avas) {
                echo '<td class="cava cbd"><img class="ri" src="ava.php?c='.$u['id'].'&p='.($pngava?'rc':'r').'36"></td>';
            }
            echo '<td class="ctext cbd">';
            if ($rank) {
                echo '<div class="chr ml">'.MP::dehtml($rank).'</div>';
            }
            echo '<a href="chat.php?c='.$memberId.'">'.MP::dehtml($un).'</a>';
            echo '<div class="ml">'. ($status !== null ? ($status ? MP::x($lng['online']) : '') : '&nbsp;').'</div>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }

    echo '</div>';
    echo '<div class="chat-open-loader" aria-hidden="true"><div class="chat-open-card"><div class="chat-open-spinner"></div><b>'.($lng['lang'] == 'ru' ? 'Назад' : 'Back').'</b><span class="chat-open-tip"></span></div></div>';
    echo '<script type="text/javascript"><!--
(function(){
  var _ovlTips='.json_encode($lng['lang'] == 'ru' ? [
    'Возвращаемся к чату без полного визуального рывка.',
    'MPGram S Web держит профиль лёгким для старых браузеров.',
    'Фото и кастомные иконки остаются в кэше после первого открытия.',
    'Сначала возвращаем экран, детали догоняют следом.',
    'Переход короткий, чтобы не мешать переписке.',
    'На Lumia лучше меньше слоёв и больше предсказуемости.',
    'Если сеть медленная, интерфейс всё равно должен отвечать.',
    'Профиль закрывается как часть чата, а не как новая тяжёлая страница.'
  ] : [
    'Returning to chat without a hard visual jump.',
    'MPGram S Web keeps profiles light for older browsers.',
    'Photos and custom icons stay cached after the first open.',
    'The screen returns first, details can catch up after.',
    'The transition is short so it does not block messaging.',
    'On Lumia, fewer layers and more predictability help.',
    'Even on slow networks, the interface should keep responding.',
    'Profile closes as part of chat, not as a heavy new page.'
  ]).';
  var _ovlTipTimer=null;
  function _ovlTip(){return (!_ovlTips||!_ovlTips.length)?"":_ovlTips[Math.floor(Math.random()*_ovlTips.length)];}
  function _ovlSetTip(el){
    if(!el)return;
    if(!el.innerHTML){
      el.appendChild(document.createTextNode(_ovlTip()));
      el.style.opacity=1;
      return;
    }
    el.style.opacity=0;
    setTimeout(function(){el.innerHTML="";el.appendChild(document.createTextNode(_ovlTip()));el.style.opacity=1;},700);
  }
  function startTips(){
    var el=null;
    if(document.querySelector)el=document.querySelector(".chat-open-loader .chat-open-tip");
    if(!el)return;
    _ovlSetTip(el);
    if(_ovlTipTimer)clearInterval(_ovlTipTimer);
    _ovlTipTimer=setInterval(function(){_ovlSetTip(el);},3600);
  }
  function addClass(c){
    var b=document.body;
    if(!b)return;
    if((" "+(b.className||" ")+" ").indexOf(" "+c+" ")<0)b.className=(b.className||"")+" "+c;
  }
  function showLoading(){
    addClass("nav-loading");
    startTips();
  }
  function setup(){
    var links=document.getElementsByTagName("a"),i;
    for(i=0;i<links.length;i++){
      if((" "+(links[i].className||"")+" ").indexOf(" profile-back ")<0)continue;
      links[i].onclick=function(ev){
        ev=ev||window.event;
        var href=this.getAttribute("href");
        if(!href)return true;
        if(ev&&ev.preventDefault)ev.preventDefault();else ev.returnValue=false;
        addClass("profile-back-leave");
        showLoading();
        setTimeout(function(){location.href=href;},1400);
        return false;
      };
    }
  }
  if(document.addEventListener)document.addEventListener("DOMContentLoaded",setup,false);
  else setTimeout(setup,200);
})();
//--></script>';
    echo Themes::bodyEnd();
} catch (Exception $e) {
    echo '<xmp>';
    echo $e;
    echo '</xmp>';
}

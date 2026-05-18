<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

use function Amp\async;
use danog\MadelineProto\Tools;

$iev = MP::getIEVersion();
$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme');
$autoupd = MP::getSettingInt('autoupd', ($iev == 0 || $iev > 4) ? 1 : 0);
$updint = MP::getSettingInt('updint', 10);
$dynupd = MP::getSettingInt('dynupd', 1);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$sym3 = str_contains($ua, 'Symbian/3') ? 1 : 0;
$reverse = MP::getSettingInt('reverse', $sym3, true) == 1;
$autoscroll = MP::getSettingInt('autoscroll', 1) == 1;
$full = MP::getSettingInt('full', 0) == 1;
$texttop = MP::getSettingInt('texttop', $sym3) == 1;
$imgs = MP::getSettingInt('imgs', 1) == 1;
$longpoll = MP::getSettingInt('longpoll', strpos($ua, 'AppleWebKit') || strpos($ua, 'Chrome') || strpos($ua, 'Symbian') || strpos($ua, 'SymbOS') || strpos($ua, 'Android')) == 1;
$pngava = MP::getSettingInt('pngava', 0);
$old = MP::getSettingInt('oldchat', 0);
$photosize = MP::getSettingInt('photosize', 0);

$lng = MP::initLocale();

$msglimit = MP::getSettingInt('limit', 20);
$msgoffset = 0;
$msgoffsetid = 0;
$msgmaxid = 0;
$thread = null;
if (isset($_GET['offset'])) {
    $msgoffset = (int) $_GET['offset'];
}
if (isset($_GET['offset_from'])) {
    $msgoffsetid = (int) $_GET['offset_from'];
} elseif (isset($_GET['m'])) {
    $msgoffsetid = (int) $_GET['m'];
    $msgoffset = -1;
}
if (isset($_GET['max_id'])) {
    $msgmaxid = (int) $_GET['max_id'];
}
if (isset($_GET['t'])) {
    $thread = (int) $_GET['t'];
}
$user = MP::getUser();
if (!$user) {
    header('Location: login.php?logout=1');
    die;
}
MP::startSession();

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store');

$id = $_GET['c'] ?? $_GET['peer'] ?? die;

// "embedded=1" — page is inside the chats.php right-pane iframe on desktop.
// The chat-page class scopes the flex chat layout so settings/about iframes can scroll normally.
$embedded = isset($_GET['embedded']);
if ($embedded && !isset($_GET['offset']) && !isset($_GET['offset_from']) && !isset($_GET['m']) && !isset($_GET['max_id'])) {
    $msglimit = min($msglimit, 8);
}

$start = $_GET['start'] ?? null;
$botcallback = $_GET['cb'] ?? null;
$random = $_GET['r'] ?? null;
$file = htmlentities($_SERVER['PHP_SELF']);

$query = $_GET['q'] ?? null;
$forum = $_GET['f'] ?? null;

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

function chat_has_stickers($MP): bool {
    $now = time();
    if (isset($_SESSION['chat_has_stickers_at'], $_SESSION['chat_has_stickers']) && $now - (int)$_SESSION['chat_has_stickers_at'] < 300) {
        return (bool)$_SESSION['chat_has_stickers'];
    }
    $has = false;
    try {
        $sets = $MP->messages->getAllStickers()['sets'] ?? [];
        $has = is_array($sets) && count($sets) > 0;
    } catch (Throwable $e) {}
    $_SESSION['chat_has_stickers'] = $has ? 1 : 0;
    $_SESSION['chat_has_stickers_at'] = $now;
    return $has;
}

include 'themes.php';
Themes::setChatTheme($theme);

try {
    $MP = MP::getMadelineAPI($user);
    if (str_starts_with($id, '+')) {
        $id = substr($id, 1);
        $invite = $MP->messages->checkChatInvite(hash: $id);
        if (isset($_GET['join'])) {
            $MP->messages->importChatInvite(hash: $id);
            $id = $invite['chat']['id'];
            header("Location: chat.php?c={$id}");
            die;
        }
        if ($invite['_'] == 'chatInviteAlready') {
            $id = $invite['chat']['id'];
            header("Location: chat.php?c={$id}");
            die;
        }
        //if ($invite['_'] != 'chatInvitePeek') { // TODO
        echo '<html><head><title>'.MP::dehtml($invite['chat']['title']).'</title>';
        echo Themes::head();
        echo '</head>';
        echo Themes::bodyStart();
        echo MP::dehtml($invite['chat']['title']).'<br><br>';
        echo '<a href="chat.php?join&c='.urlencode($id).'">';
        echo MP::x($lng['join']).'</a>';
        echo Themes::bodyEnd();
        die;
    }
    $name = null;
    $pm = false;
    $ch = false;
    $left = false;
    $canpost = false;
    $ar = null;
    $forum = false;
    $info = [];
    $quickUser = $embedded && isset($_GET['quick']) && is_numeric($id) && (int)$id > 0;
    if ($quickUser) {
        $id = (int)$id;
        $pm = true;
        $name = trim((string)($_GET['n'] ?? ''));
        if ($name === '') $name = (string)$id;
    } else {
        $info = $MP->getInfo($id);
        if (!is_numeric($id)) {
            $id = MP::getId($info);
        }
        if (isset($info['Chat'])) {
            $ch = isset($info['type']) && $info['type'] == 'channel';
            $name = $info['Chat']['title'] ?? null;
            $ar = $info['Chat']['admin_rights'] ?? null;
            $canpost = $ar !== null && $ar['post_messages'] ?? false;
            $left = $info['Chat']['left'] ?? false;
            $forum = $info['Chat']['forum'] ?? false;
        } elseif (isset($info['User'])) {
            $pm = true;
            $name = MP::getUserName($info['User'], true);
        }
    }
    $channel = isset($info['channel_id']);
    if ($left && isset($_GET['join'])) {
        $MP->channels->joinChannel(['channel' => $id]);
        $left = false;
    } elseif (!$left && isset($_GET['leave'])) {
        $MP->channels->leaveChannel(['channel' => $id]);
        $left = true;
    }
    if (isset($_GET['poll'])) {
        try {
            $votes = explode('vote=', $_SERVER['QUERY_STRING']);
            $options = [];
            foreach ($votes as $vote) {
                if (str_contains($vote, '=')) continue;
                $i = strpos($vote, '&');
                if ($i !== false) $vote = substr($vote, 0, $i);
                $options[] = $vote;
            }
            $MP->messages->sendVote(['peer' => $id, 'msg_id' => $msgoffsetid, 'options' => $options]);
        } catch (Exception) {}
    }
    if ($start !== null) {
        $MP->messages->startBot(['start_param' => $start, 'bot' => $id, 'random_id' => $random]);
    }
    $alert = null;
    if ($botcallback != null && ($random == null || !isset($_SESSION['random']) || $_SESSION['random'] != $random)) {
        if ($random != null) $_SESSION['random'] = $random;
        try {
            $a = async(
                $MP->messages->getBotCallbackAnswer(...),
                ['peer' => $id, 'msg_id' => $msgoffsetid, 'data' => base64_decode($botcallback)]
            )->await(Tools::getTimeoutCancellation(0.5));
            if (($a['alert'] ?? false) && isset($a['message'])) {
                $alert = $a['message'];
            }
        } catch (Exception) {}
    }
    function printInputField(): void
    {
        global $full;
        global $left;
        global $ch;
        global $id;
        global $lng;
        global $reverse;
        global $canpost;
        global $iev;
        global $texttop;
        global $ua;
        global $file;
        global $MP;
        $afterInput = '';
        echo '<div class="in'.($reverse?' t':'').($texttop?' cb':'').'" id="text">';
        if ($left) {
            echo '<form action="'.$file.'">';
            echo '<input type="hidden" name="c" value="'.$id.'">';
            echo '<input type="hidden" name="join" value="1">';
            echo '<input type="hidden" name="r" value="' .  base64_encode(random_bytes(16)).'">';
            echo '<input type="submit" value="'.MP::x($lng['join']).'">';
            echo '</form>';
        } elseif (!$ch || $canpost) {
            $post = !str_contains($ua, 'Series60/3') && !str_contains($ua, 'EPOC');
            $opera = str_contains($ua, 'Opera') || ($iev != 0 && $iev <= 7);
            $watchos = str_contains($ua, 'Watch OS');
            echo '<div class="chat-tools">';
            echo '<a class="chat-icon-btn chat-attach-btn" href="#file-modal" title="'.MP::x($lng['send_file']).'" aria-label="'.MP::x($lng['send_file']).'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8l-8.6 8.6a4 4 0 0 1-5.7-5.7l9.2-9.2a5.5 5.5 0 0 1 7.8 7.8L10.8 19.4a7 7 0 0 1-9.9-9.9L10.4 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>';
            echo '</div>';
            echo '<form action="write.php"'.($post ? ' method="post"' : '').' class="in chat-write-form">';
            echo '<input type="hidden" name="c" value="'.$id.'">';
            echo '<input type="hidden" name="r" value="' . base64_encode(random_bytes(16)).'">';
            if ($watchos) {
                echo '<input required name="msg" value="" style="width: 100%; height: 2em"><br>';
            } else {
                echo '<textarea required name="msg" value="" class="cta"></textarea><br>';
            }
            echo '<input type="submit" value="'.MP::x($lng['send']).'">';
            //echo '<input type="checkbox" id="format" name="format">';
            //echo '<label for="format">'.MP::x($lng['html_formatting']).'</label>';
            echo '</form>';
            $afterInput .= '<div id="file-modal" class="modal">';
            $afterInput .= '<a href="#" class="modal-backdrop"></a>';
            $afterInput .= '<div class="modal-content chat-compose-modal">';
            $afterInput .= '<div class="modal-head"><span class="modal-title">'.MP::x($lng['send_file']).'</span><a class="modal-close" href="#">×</a></div>';
            $afterInput .= '<form action="msg.php" method="post" enctype="multipart/form-data" class="chat-file-form">';
            $afterInput .= '<input type="hidden" name="c" value="'.$id.'">';
            $afterInput .= '<input type="hidden" name="sent" value="1">';
            $afterInput .= '<input type="hidden" name="r" value="'.base64_encode(random_bytes(16)).'">';
            $afterInput .= '<textarea name="text" class="chat-file-caption" placeholder="'.MP::x($lng['message'] ?? 'Message').'"></textarea>';
            $afterInput .= '<input type="file" id="file" name="file" required>';
            $afterInput .= '<label><input type="checkbox" id="unc" name="unc"> '.MP::x($lng['send_uncompressed']).'</label>';
            $afterInput .= '<label><input type="checkbox" id="sp" name="sp"> '.MP::x($lng['send_spoiler']).'</label>';
            $afterInput .= '<input type="submit" class="btn btn-primary" value="'.MP::x($lng['send']).'">';
            $afterInput .= '</form>';
            $afterInput .= '</div></div>';
        }
        /*
        if ($reverse) {
            echo '<div><a href="chats.php">'.MP::x($lng['back']).'</a>';
            echo ' <a href="chat.php?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a></div>';
        }
        */
        echo '</div>';
        echo $afterInput;
    }
    $r = null;
    $mentions = null;
    if ($query !== null || $thread !== null) {
        $p = [
        'peer' => $id,
        'offset_id' => $msgoffsetid,
        'offset_date' => 0,
        'add_offset' => $msgoffset,
        'limit' => $msglimit,
        'max_id' => $msgmaxid,
        'min_id' => 0,
        'hash' => 0
        ];
        if ($query !== null) {
            $p['q'] = $query;
        }
        if ($thread !== null) {
            $p['top_msg_id'] = $thread;
        }
        $r = $MP->messages->search($p);
    } else {
        $r = $MP->messages->getHistory([
        'peer' => $id,
        'offset_id' => $msgoffsetid,
        'offset_date' => 0,
        'add_offset' => $msgoffset,
        'limit' => $msglimit,
        'max_id' => $msgmaxid,
        'min_id' => 0,
        'hash' => 0]);
    }
    if ($query === null && !$embedded) {
        $p = ['peer' => $id,
        'offset_id' => $msgoffsetid,
        'offset_date' => 0,
        'add_offset' => $msgoffset,
        'limit' => $msglimit,
        'max_id' => $msgmaxid,
        'min_id' => 0];
        if ($thread !== null) {
            $p['top_msg_id'] = $thread;
        }
        // PATCHED for mpgram-web private server: getUnreadMentions may not be implemented.
        try {
            $mentions = $MP->messages->getUnreadMentions($p)['messages'];
        } catch (Exception $e) {
            $mentions = [];
        }
    }
    $top = 0;
    if ($forum && $thread != null) {
        try {
            $topic = $MP->messages->getForumTopics(['peer' => $id, 'limit' => 20])['topics'][0];
            $top = $topic['top_message'];
        } catch (Exception $e) {}
    }
    MP::addUsers($r['users'], $r['chats']);
    $id_offset = null;
    if (isset($r['offset_id_offset'])) {
        $id_offset = $r['offset_id_offset'];
        if ($msgoffset < 0) {
            $id_offset = $id_offset+$msgoffset+1;
        }
    }
    $rm = $r['messages'];
    $firstid = $rm[0]['id'] ?? 0;
    $lastid = $rm[count($rm)-1]['id'] ?? 0;
    $endReached = $id_offset === 0 || ($id_offset === null && $msgoffset <= 0);
    $hasOffset = $msgoffset > 0 || $msgoffsetid > 0;
    $dir = $_GET['d'] ?? null;
    echo '<head><title>'.MP::dehtml($name).'</title>';
    echo Themes::head();
    if ($autoscroll) {
        echo '<script type="text/javascript"><!--
var reverse = '.($reverse&&$texttop?'true':'false').';';
echo file_get_contents('chatscroll.js');
echo '
//--></script>';
    }
    if ((!$hasOffset || $endReached) && $autoupd == 1 && count($rm) > 0 && $query === null) {
        $ii = $rm[0]['id'];
        if ($dynupd == 1) {
            echo '<script type="text/javascript"><!--
var reverse = '.($reverse?'true':'false').';
var autoscroll = '.($autoscroll?'true':'false').';
var longpoll = '.($longpoll?'true':'false').';
var updint = '.($longpoll?'1000':$updint.'000').';
var url = "'.MP::getUrl().'msgs.php?user='.$user.'&id='.$id.'&lang='.$lng['lang'].'&t='.$timeoff.($longpoll?'&l':'').($old?'&ol':'').($thread != null ? '&th='.$thread : '').'";
var msglimit = '.$msglimit.';
var msg = "'.$ii.'";';
echo file_get_contents('chatupdate.js');
echo '
//--></script>';
        } else {
            echo '<script type="text/javascript"><!--
setTimeout("location.reload(true);",'.$updint.'000);
//--></script>';
        }
        if ($alert != null) {
echo '<script type="text/javascript"><!--
alert("'.str_replace('"', '\"', $alert).'");
//--></script>';
        }
    }
    echo '</head>'."\n";
    $body = false;
    if ($autoscroll) {
        $bcls = 'class="chat-page'.($embedded ? ' embedded' : '').'" ';
        if ($reverse && $dir != 'd') {
            echo Themes::bodyStart($bcls.'onload="autoScroll(true, false);"'); $body = true;
        } elseif (!$reverse && $dir == 'u') {
            echo Themes::bodyStart($bcls.'onload="autoScroll(true, true);"'); $body = true;
        }
    }
    if (!$body) {
        $bcls = 'class="chat-page'.($embedded ? ' embedded' : '').'"';
        if ($msgoffsetid > 0)
            echo Themes::bodyStart($bcls.' onload="document.getElementById(\'msg_'.$id.'_'.$msgoffsetid.'\').scrollIntoView();"');
        else
            echo Themes::bodyStart($bcls);
    }
    // Belt-and-suspenders: also detect iframe context client-side so embedded mode
    // applies even if the URL didn't include ?embedded=1.
    echo Themes::iframeDetectScript();
    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $avas = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
    $avas = MP::getSettingInt('avas', $avas) && !str_contains($useragent, 'SymbianOS/9');
    $statussett = MP::getSettingInt('status', 0);
    $peerInfo = $info['User'] ?? $info['Chat'] ?? null;
    $hasAvatar = MP::hasPeerPhoto($peerInfo);
    $avatarImg = '<img class="ri" src="'.((int)$id < 0 ? 'img/gr.png' : 'img/us.png').'"'.($hasAvatar ? ' data-src="ava.php?c='.$id.'&p='.($pngava?'rc':'r').'36"' : '').'>';
    if ($iev != 0 && $iev <= 7) {
        echo '<header>';
        if ($avas) {
            echo '<div class="chava">'.$avatarImg.'</div>';
        }
        echo '<div class="chn">';
        echo MP::dehtml($name);
        echo '</div>';
        echo '<div><small><a href="chats.php">'.MP::x($lng['back']).'</a>';
        echo ' <a href="'.$file.'?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a>';
        echo ' <a class="chat-info" href="chatinfo.php?c='.$id.'">'.MP::x($lng['chat_info']??null).'</a>';
        echo '</small></div>';
        echo '</header>';
    } else {
        echo '<header class="ch">';
        echo '<div class="chc">';
        echo '<a class="chat-action chat-back" href="chats.php">'.MP::x($lng['back']).'</a>';
        $h = "height: 1.2em";
        if ($avas && $statussett) {
            $h = "height: 44px";
            echo '<div class="chava">'.$avatarImg.'</div>';
        }
        echo '<div class="chn">';
        echo MP::dehtml($name);
        if ($statussett) {
            $status = $info['User']['status'] ?? null;
            $status_str = '';
            if ($status) {
                switch ($status['_']) {
                case 'userStatusOnline':
                    $status_str = MP::x($lng['online']);
                    break;
                case 'userStatusOffline':
                    $time = time()-$timeoff;
                    $was = $status['was_online']-$timeoff;
                    if ($was >= $time - 60) {
                        $status_str = MP::x($lng['last_seen'].' '.$lng['just_now']);
                    } elseif ($was >= $time - 60*60) {
                        $status_str = MP::x($lng['last_seen'].' '.MPLocale::number('minutes_ago', intval(($time-$was)/60)));
                    } else /*if ($was >= $time - 24*60*60) {
                        $hours = intval(($time-$was)/60/60);
                        if ($hours == 1) {
                            $status_str = 'last seen '.$hours.' hour ago';
                        } else {
                            $status_str = 'last seen '.$hours.' hours ago';
                        }
                    } else*/ if (date('d.m.y', $was) == date('d.m.y', $time)) {
                        $status_str = MP::x($lng['last_seen']).' '.MP::x($lng['last_seen_at']).' '.date('H:i', $status['was_online']-$timeoff);
                    } elseif (date('d.m.y', $was) == date('d.m.y', $time-24*60*60)) {
                        $status_str = MP::x($lng['last_seen']).' '.MP::x($lng['yesterday'].' '.$lng['last_seen_at']).' '.date('H:i', $was);
                    } else {
                        $status_str = MP::x($lng['last_seen']).' '.date('d.m.y', $was);
                    }
                    break;
                case 'userStatusRecently':
                    $status_str = MP::x($lng['last_seen'].' '.$lng['recently']);
                    break;
                case 'userStatusLastWeek':
                    $status_str = MP::x($lng['last_seen'].' '.$lng['last_week']);
                    break;
                case 'userStatusLastMonth':
                    $status_str = MP::x($lng['last_seen'].' '.$lng['last_month']);
                    break;
                default:
                case 'userStatusEmpty':
                    $status_str = '';
                    break;
                }
            }
            echo '</div>';
            if ($status_str) {
                if (!$avas) $h = "height: 2.2em";
                echo '<small id="cst" class="cst">'.$status_str.'</small>';
            }
        } else {
            echo '</div>';
        }
        echo '<div class="chr"><small>';
        echo '<a class="chat-action chat-refresh" href="'.$file.'?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a>';
        echo ' <a class="chat-action chat-info" href="chatinfo.php?c='.$id.'">'.MP::x($lng['chat_info']??null).'</a>';
        echo '</small></div></div>';
        echo '</header>';
        echo "<div style=\"{$h};\">&nbsp;</div>";
    }
    unset($info);
    $sname = $name ?? '';
    if (MP::utflen($sname) > 30) $sname = MP::utfsubstr($sname, 0, 30);
    $navurl = $file.'?c='.$id
    .($query !== null ? '&q='.urlencode($query) : '')
    .($thread != null ? '&t='.$thread : '');
    $historyUp = '';
    $historyDown = '';
    if (!$reverse) {
        printInputField();
        if ($hasOffset && !$endReached && ($thread == null || $firstid != $top)) {
            if (($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
                $historyUp = '<p class="chat-history-nav chat-history-up"><a href="'.$navurl.'&d=u">'.MP::x($lng['history_up']).'</a></p>';
            } else {
                $historyUp = '<p class="chat-history-nav chat-history-up"><a href="'.$navurl.'&d=u&offset_from='.$firstid.'&offset='.(-$msglimit-1).'">'.MP::x($lng['history_up']).'</a></p>';
            }
        }
    } else {
        if (count($rm) >= $msglimit && ($thread == null || $lastid != $thread)) {
            $historyUp = '<p class="chat-history-nav chat-history-up"><a href="'.$navurl.'&d=u&offset_from='.$lastid.'&reverse=1">'.MP::x($lng['history_up']).'</a></p>';
        }
        $rm = array_reverse($rm);
    }
    if ($forum) {
        echo '<div>';
        try {
            $topics = $MP->messages->getForumTopics(['peer' => $id, 'limit' => 20])['topics'];
            foreach ($topics as $topic) {
                echo "<a href=\"{$file}?c={$id}&t={$topic['id']}"
                .($topic['read_inbox_max_id'] != $topic['top_message']?'&m='.$topic['read_inbox_max_id']:'')
                ."\""
                .($topic['id'] == $thread ?' class="fs"':'')
                .">{$topic['title']}</a> &nbsp;";
            }
        } catch (Exception $e) {}
        echo '</div><p></p>';
    }
    echo '<div id="msgs">';
    echo $historyUp;
    MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name, $timeoff, $channel, true, $ar, $query !== null, $old, $photosize, true, $mentions, $thread);
    if (!$reverse) {
        if (count($rm) >= $msglimit && ($thread == null || $lastid != $thread)) {
            if ($endReached && $autoupd)
                $historyDown = '<p class="chat-history-nav chat-history-down"><a href="'.$navurl.'&offset='.$msglimit.'&d=d">'.MP::x($lng['history_down']).'</a></p>';
            else
                $historyDown = '<p class="chat-history-nav chat-history-down"><a href="'.$navurl.'&d=d&offset_from='.$lastid.'">'.MP::x($lng['history_down']).'</a></p>';
        }
    } else {
        if ($hasOffset && !$endReached && ($thread == null || $firstid != $top)) {
            if (($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
                $historyDown = '<p class="chat-history-nav chat-history-down"><a href="'.$navurl.'&d=d">'.MP::x($lng['history_down']).'</a></p>';
            } else {
                $historyDown = '<p class="chat-history-nav chat-history-down"><a href="'.$navurl.'&d=d&offset_from='.$firstid.'&offset='.(-$msglimit-1).'&reverse=1">'.MP::x($lng['history_down']).'</a></p>';
            }
        }
        printInputField();
    }
    echo $historyDown;
    echo '</div>';
    if ($texttop) echo '<div style="height: 4em;" id="bottom"></div>';
    else echo '<div id="bottom"></div>';
    echo '<div class="chat-open-loader" aria-hidden="true"><div class="chat-open-card"><div class="chat-open-spinner"></div><b>'.($lng['lang'] == 'ru' ? 'Загрузка' : 'Loading').'</b><span class="chat-open-tip"></span></div></div>';
echo '<script type="text/javascript"><!--
function ovlLoadInlineAvatars(){
  var imgs=document.getElementsByTagName("img"),i;
  for(i=0;i<imgs.length;i++){
    var src=imgs[i].getAttribute("data-src");
    if(!src)continue;
    imgs[i].removeAttribute("data-src");
    imgs[i].src=src;
  }
}
if(window.addEventListener)window.addEventListener("load",ovlLoadInlineAvatars,false);
else setTimeout(ovlLoadInlineAvatars,250);
(function(){
  var _ovlTips='.json_encode($lng['lang'] == 'ru' ? [
    'Закрепите важный чат, чтобы он всегда ждал сверху.',
    'В Telegram можно искать сообщения по дате: откройте поиск в чате.',
    'Сохранённые сообщения работают как личная полка для ссылок и заметок.',
    'Долгое нажатие на сообщение открывает быстрые действия.',
    'В группах удобно отвечать на конкретное сообщение, чтобы сохранить контекст.',
    'Папки помогают разделить личные чаты, каналы и работу.',
    'Черновики синхронизируются между устройствами.',
    'В каналах можно переслать пост себе и вернуться к нему позже.'
  ] : [
    'Pin an important chat so it is always waiting at the top.',
    'Telegram chat search can jump through messages by date.',
    'Saved Messages works like a private shelf for links and notes.',
    'Long-press a message to reveal quick actions.',
    'Reply to a specific message in groups to keep context tidy.',
    'Folders can split personal chats, channels, and work into calmer lanes.',
    'Drafts follow you across devices.',
    'Forward a channel post to yourself when you want to revisit it later.'
  ]).';
  var _ovlTipTimer=null;
  var _ovlTipSwapTimer=null;
  function _ovlTip(){return (!_ovlTips||!_ovlTips.length)?"":_ovlTips[Math.floor(Math.random()*_ovlTips.length)];}
  function _ovlSetTip(el){
    if(!el)return;
    if(_ovlTipSwapTimer)clearTimeout(_ovlTipSwapTimer);
    if(!el.firstChild){
      el.appendChild(document.createTextNode(_ovlTip()));
      el.style.opacity=1;
      return;
    }
    el.style.opacity=0;
    _ovlTipSwapTimer=setTimeout(function(){el.innerHTML="";el.appendChild(document.createTextNode(_ovlTip()));el.style.opacity=1;},180);
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
  function showLoading(title){
    addClass("nav-loading");
    var card=document.querySelector?document.querySelector(".chat-open-loader .chat-open-card"):null;
    if(card&&title){
      var b=card.getElementsByTagName("b");
      if(b&&b.length){b[0].innerHTML="";b[0].appendChild(document.createTextNode(title));}
    }
    startTips();
  }
  function wire(sel,cls,delay){
    var nodes=document.querySelectorAll?document.querySelectorAll(sel):document.getElementsByTagName("a"),i;
    for(i=0;i<nodes.length;i++){
      var a=nodes[i];
      if(!document.querySelectorAll){
        if((" "+(a.className||"")+" ").indexOf(" "+sel.substring(1)+" ")<0)continue;
      }
      a.onclick=function(ev){
        ev=ev||window.event;
        var href=this.getAttribute("href");
        if(!href||href.charAt(0)==="#")return true;
        if(ev&&ev.preventDefault)ev.preventDefault();else ev.returnValue=false;
        addClass(cls);
        showLoading(cls==="chat-info-leave"?"'.($lng['lang'] == 'ru' ? 'Открываем инфо' : 'Opening info').'":"'.($lng['lang'] == 'ru' ? 'Возвращаемся к чатам' : 'Returning to chats').'");
        setTimeout(function(){location.href=href;},delay||140);
        return false;
      };
    }
  }
  if(document.addEventListener)document.addEventListener("DOMContentLoaded",function(){wire(".chat-info","chat-info-leave",1400);wire(".chat-back","chat-back-leave",1400);},false);
  else setTimeout(function(){wire(".chat-info","chat-info-leave",1400);wire(".chat-back","chat-back-leave",1400);},200);
})();
//--></script>';
    echo Themes::bodyEnd();
    // MPGram S Web — flush response NOW so the user sees the chat immediately.
    // Read-marks (auth.readHistory etc.) and session GC continue in background after
    // the connection is closed. fastcgi_finish_request is the canonical PHP-FPM hook.
    if (function_exists('fastcgi_finish_request')) {
        @ob_end_flush();
        fastcgi_finish_request();
    }
    // Mark as read
    try {
        if ($query === null && count($rm) > 0) {
            $maxid = ($reverse ? $rm[count($rm)-1]['id'] : $rm[0]['id']);
            if ($thread != null) {
                $MP->messages->readDiscussion(['peer' => $id, 'read_max_id' => $maxid, 'msg_id' => $thread]);
                $MP->messages->readMentions(['peer' => $id, 'top_msg_id' => $thread]);
            } else if ($ch || (int)$id < 0) {
                try { $MP->channels->readHistory(['channel' => $id, 'max_id' => $maxid]); } catch (Exception $e) {}
                try { $MP->messages->readMentions(['peer' => $id]); } catch (Exception $e) {}
            } else {
                try { $MP->messages->readHistory(['peer' => $id, 'max_id' => $maxid]); } catch (Exception $e) {}
                try { $MP->messages->readMentions(['peer' => $id]); } catch (Exception $e) {}
            }
            //$MP->messages->readReactions(['peer' => $id]);
        }
    } catch (Exception $e) {
        // PATCHED for mpgram-web: swallow "API NotImplemented" instead of dumping the
        // exception into the rendered chat. Individual read* calls above are now
        // each in their own try/catch, so this is only reached for outer-level errors.
    }
    unset($rm);
    unset($r);
    MP::gc();
} catch (Exception $e) {
    // If we already flushed the response, this body html goes nowhere — but doesn't error.
    echo '<b>'.MP::x($lng['error']).'!</b><br>';
    echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
}
die;

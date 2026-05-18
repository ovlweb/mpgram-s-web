<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme', 0, true);
$lng = MP::initLocale();

$user = MP::getUser();
if (!$user) {
    header('Location: login.php?logout=1');
    die;
}

$count = MP::getSettingInt('chats', 15, true);
if (isset($_GET['count'])) {
    $count = (int) $_GET['count'];
}
$fid = isset($_GET['f']) ? (int)$_GET['f'] : 0;
// AJAX fragment mode: when set, render only the chat-list table (no head/nav/footer).
// Used by the chats-list poller to refresh without reloading the whole page.
$fragment = isset($_GET['fragment']);
$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$avas = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
$avas = MP::getSettingInt('avas', $avas);
$pngava = MP::getSettingInt('pngava', 0);

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

/**
 * Quick "signature" of the user's dialog list — small read used by long-poll
 * to detect changes without re-rendering the whole list. Combines top-N
 * dialogs' last-message-id + unread-count + read-state into one short hash.
 * Returns '' on error so the caller treats it as "no change since" (safe fail).
 */
function sets_chats_quick_hash(?\danog\MadelineProto\API $MP, int $fid, int $count): string {
    if (!$MP) return '';
    try {
        $r = $MP->messages->getDialogs(
            folder_id: $fid === 1 ? 1 : 0,
            limit: max(10, min(30, $count)),
            exclude_pinned: false
        );
        $sig = [];
        foreach ($r['dialogs'] ?? [] as $d) {
            $peer = $d['peer'] ?? '';
            if (is_array($peer)) $peer = json_encode($peer);
            $sig[] = $peer . ':' . ($d['top_message'] ?? 0) . ':' . ($d['unread_count'] ?? 0) . ':' . ($d['read_inbox_max_id'] ?? 0);
        }
        return md5(implode('|', $sig));
    } catch (Throwable $e) {
        return '';
    }
}

try {
    if (PHP_OS_FAMILY === "Linux") {
        // Automatically kill madeline sessions
        $x = false;
        try {
            $x = file_get_contents('./lastclean');
        } catch (Exception) {}
        if (!$x || (time() - (int)$x) > 30 * 60) {
            exec("kill -9 `ps -ef | grep -v grep | grep 'MadelineProto worker' | awk '{print $2}'` > /dev/null &");
            file_put_contents('./lastclean', time());
        }
    }
} catch (Exception) {}

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

try {
    $MP = MP::getMadelineAPI($user);
    if ($fragment) {
        header('Content-Type: text/html; charset='.MP::$enc);
        header('Cache-Control: no-store');
        // Long-poll: if client provides ?since=<hash>, wait up to ~25s for the
        // dialog list to change. Returns the new hash via X-Chats-Hash header.
        $since = $_GET['since'] ?? '';
        if ($since !== '') {
            $deadline = microtime(true) + 25.0;
            while (microtime(true) < $deadline) {
                $h = sets_chats_quick_hash($MP, $fid, $count);
                if ($h !== '' && $h !== $since) {
                    header('X-Chats-Hash: ' . $h);
                    break;
                }
                // sleep 750ms — fast enough to feel real-time, light on server
                usleep(750000);
            }
            // After the wait (either change or timeout) we fall through to render.
            header('X-Chats-Hash: ' . sets_chats_quick_hash($MP, $fid, $count));
        } else {
            header('X-Chats-Hash: ' . sets_chats_quick_hash($MP, $fid, $count));
        }
    }
    if (!$fragment) {
    echo '<html><head><title>'.MP::x($lng['chats']).'</title>';
    echo Themes::head();
    $iev = MP::getIEVersion();
    if ($iev == 0 || $iev > 4) {
        $dtz = new DateTimeZone(date_default_timezone_get());
        $t = new DateTime('now', $dtz);
        $tof = $dtz->getOffset($t);
        echo
'<script type="text/javascript"><!--
try {
    var d = new Date();
    var c = ((d.getTime()+'.($tof*1000).')-(d.getTime()-(d.getTimezoneOffset()*60*1000)))/1000 | 0;
    var e = new Date();
    e.setTime(e.getTime() + (365*86400*1000));
    document.cookie = "timeoff=" + c + "; expires="+e.toUTCString()+"; path=/";
} catch (e) {
}
//--></script>';
    }
    echo '</head>';
    echo Themes::bodyStart();
    // MPGram S Web — two-pane shell. On desktop the chat opens in the right iframe.
    echo '<div class="app-shell">';
    echo '<aside class="app-sidebar">';
    $self = $MP->getSelf();
    if (!$self) throw new Exception("Could not get user info!");
    $selfid = $self['id'];
    $selfname = MP::dehtml(MP::getUserName($self, true));
    $selfAvatarSrc = MP::hasPeerPhoto($self) ? ' data-src="ava.php?c='.$selfid.'&p='.($pngava?'rc':'r').'34"' : '';
    $hasArchiveChats = false;
    // Sidebar header — own avatar + username + action icons (search, contacts, settings, about)
    $svgSearch   = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    $svgContacts = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
    $svgSettings = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
    $svgInfo     = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

    echo '<div class="hed">';
    echo '<img class="hed-ava" src="img/us.png"'.$selfAvatarSrc.' alt="">';
    echo '<b>'.MP::x($selfname).'</b>';
    echo '<span class="hb">';
    echo '<a href="#search-modal" title="'.MP::x($lng['search']).'" aria-label="'.MP::x($lng['search']).'">'.$svgSearch.'</a>';
    echo '<a href="#contacts-modal" title="'.MP::x($lng['contacts']).'" aria-label="'.MP::x($lng['contacts']).'">'.$svgContacts.'</a>';
    echo '<a href="#settings-modal" title="'.MP::x($lng['settings']).'" aria-label="'.MP::x($lng['settings']).'">'.$svgSettings.'</a>';
    echo '<a href="#about-modal" title="'.MP::x($lng['about']).'" aria-label="'.MP::x($lng['about']).'">'.$svgInfo.'</a>';
    echo '</span>';
    echo '</div>';

    ob_start();
    // Search popup — local, fast search over the loaded chats. Global search is intentionally
    // not exposed here: the main menu should find chats/channels/profiles already known locally.
    echo '<div id="search-modal" class="modal" aria-hidden="true">';
    echo '<a href="#" class="modal-backdrop" tabindex="-1" aria-label="Close"></a>';
    echo '<div class="modal-content">';
    echo '<div class="modal-head">';
    echo '<span class="modal-title">'.MP::x($lng['search']).'</span>';
    echo '<a href="#" class="modal-close" aria-label="Close">×</a>';
    echo '</div>';
    echo '<div class="search-form local-search-form">';
    echo '<input type="search" id="local-search-input" placeholder="'.MP::x($lng['search']).'…" autocomplete="off">';
    echo '<div id="local-search-results" class="local-search-results"></div>';
    echo '<div id="local-search-empty" class="local-search-empty">'.MP::x($lng['contacts']).' / '.MP::x($lng['chats']).'</div>';
    echo '<a class="btn local-search-contacts" href="#contacts-modal">'.MP::x($lng['contacts']).'</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Contacts / Settings / About — each opens in an iframe inside a larger modal,
    // so the existing PHP pages render unchanged. Embedded=1 hides their inner back-bar.
    foreach (['contacts' => ['contacts.php', $lng['contacts']],
              'settings' => ['sets.php',     $lng['settings']],
              'about'    => ['about.php',    $lng['about']]] as $key => [$file, $label]) {
        echo '<div id="'.$key.'-modal" class="modal modal-lg" aria-hidden="true">';
        echo '<a href="#" class="modal-backdrop" tabindex="-1" aria-label="Close"></a>';
        echo '<div class="modal-content modal-content-lg">';
        echo '<div class="modal-head">';
        echo '<span class="modal-title">'.MP::x($label).'</span>';
        echo '<a href="#" class="modal-close" aria-label="Close">×</a>';
        echo '</div>';
        $src = $file.'?embedded=1';
        echo '<div class="modal-iframe-bg"><iframe src="about:blank" data-src="'.$src.'" class="modal-iframe" title="'.MP::x($label).'"></iframe></div>';
        echo '</div>';
        echo '</div>';
    }
    $globalModals = ob_get_clean();
    $folders = $MP->messages->getDialogFilters();
    if (($folders['_'] ?? '') == 'messages.dialogFilters')
        $folders = $folders['filters'];
    $hasArchiveChats = count($MP->messages->getDialogs(
        exclude_pinned: true,
        folder_id: 1,
        limit: 1
    )['dialogs']) > 0;
    if (count($folders) > 1 || $hasArchiveChats) {
        echo '<div class="folder-tabs">';
        //echo MP::x($lng['folders']).': ';
        foreach ($folders as $f) {
            if (($f['_'] ?? '') == 'dialogFilterDefault' || !isset($f['id'])) {
                echo '<a href="chats.php">'.MP::x($lng['all_chats']);
            } else {
                $sel = $fid == $f['id'];
                echo '<a href="chats.php?f='.$f['id'].'"'.($sel?' class="fs"':'').'>'.MP::dehtml($f['title']['text'] ?? $f['title']);
            }
            echo '</a> ';
        }
        if ($hasArchiveChats) {
            $sel = $fid == 1;
            echo '<a href="chats.php?f=1"'.($sel?' class="fs"':'').'>'.MP::x($lng['archived_chats']).'</a>';
        }
        echo '</div>';
    }
    } // end if (!$fragment)
    echo '<div id="chats-list">';
    try {
        $r = null;
        $dialogs = null;
        if ($fid == 1) {
            $r = $MP->messages->getDialogs(
                exclude_pinned: true,
                folder_id: 1,
                limit: $count
            );
            $dialogs = $r['dialogs'];
            foreach ($r['messages'] as $m) {
                foreach ($dialogs as $k => $d) {
                    if ($m['peer_id'] != $d['peer']) continue;
                    $dialogs[$k]['message'] = $m;
                    break;
                }
            }
            unset($r['messages']);
            unset($r['dialogs']);
        } else {
            if ($fid > 1) {
                $folder = null;
                foreach ($folders as $f) {
                    if (!isset($f['id']) || $f['id'] != $fid) continue;
                    $folder = $f;
                    break;
                }
                unset($folders);
                $r = MP::getAllDialogs($MP);
                $dialogs = [];
                $all = $r['dialogs'];
                foreach ($r['messages'] as $m) {
                    foreach ($all as $k => $d) {
                        if ($m['peer_id'] != $d['peer']) continue;
                        $all[$k]['message'] = $m;
                        break;
                    }
                }
                unset($r['messages']);
                unset($r['dialogs']);
                if ($folder['contacts'] || $folder['non_contacts']) {
                    $contacts = $MP->contacts->getContacts()['contacts'];
                    foreach ($all as $d) {
                        if ($d['peer'] < 0) continue;
                        $found = false;
                        foreach ($contacts as $c) {
                            if ($d['peer'] != MP::getId($c)) continue;
                            $found = true;
                            if ($folder['contacts']) $dialogs[] = $d;
                            break;
                        }
                        if ($found || $folder['non_contacts']) continue;
                        if (!in_array($d, $dialogs)) $dialogs[] = $d;
                    }
                    unset($contacts);
                }
                if ($folder['groups']) {
                    foreach ($all as $d) {
                        $peer = $d['peer'];
                        if ($peer > 0) continue;
                        foreach ($r['chats'] as $c) {
                            if ($c['id'] != $peer) continue;
                            if (!($c['broadcast'] ?? false) && !in_array($d, $dialogs))
                                $dialogs[] = $d;
                            break;
                        }
                    }
                }
                if ($folder['broadcasts']) {
                    foreach ($all as $d) {
                        $peer = $d['peer'];
                        if ($peer > 0) continue;
                        foreach ($r['chats'] as $c) {
                            if ($c['id'] != $peer) continue;
                            if (($c['broadcast'] ?? false) && !in_array($d, $dialogs))
                                $dialogs[] = $d;
                            break;
                        }
                    }
                }
                if ($folder['bots']) {
                    foreach ($all as $d) {
                        $peer = $d['peer'];
                        if ($peer < 0) continue;
                        foreach ($r['users'] as $u) {
                            if ($u['id'] != $peer) continue;
                            if (($u['bot'] ?? false) && !in_array($d, $dialogs))
                                $dialogs[] = $d;
                            break;
                        }
                    }
                }
                if (count($folder['exclude_peers']) > 0) {
                    foreach ($folder['exclude_peers'] as $p) {
                        $p = MP::getId($p);
                        foreach ($dialogs as $idx => $d) {
                            if ($d['peer'] != $p) continue;
                            unset($dialogs[$idx]);
                            break;
                        }
                    }
                }
                if ($folder['exclude_archived']) {
                    foreach ($dialogs as $idx => $d) {
                        if (!isset($d['folder_id']) || $d['folder_id'] != 1) continue;
                        unset($dialogs[$idx]);
                    }
                }
                if ($folder['exclude_read']) {
                    foreach ($dialogs as $idx => $d) {
                        if (!isset($d['unread_count']) || $d['unread_count'] > 0) continue;
                        unset($dialogs[$idx]);
                    }
                }
                if (count($folder['include_peers']) > 0) {
                    foreach ($folder['include_peers'] as $p) {
                        $p = MP::getId($p);
                        foreach ($all as $d) {
                            if ($d['peer'] != $p) continue;
                            if (!in_array($d, $dialogs)) $dialogs[] = $d;
                            break;
                        }
                    }
                }
                function cmp($a, $b) {
                    $ma = $a['message'] ?? null;
                    $mb = $b['message'] ?? null;
                    if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
                        return 0;
                    }
                    return ($ma['date'] > $mb['date']) ? -1 : 1;
                }
                usort($dialogs, 'cmp');
                if (count($folder['pinned_peers']) > 0) {
                    $pinned = [];
                    foreach ($folder['pinned_peers'] as $p) {
                        $p = MP::getId($p);
                        foreach ($all as $d) {
                            if ($d['peer'] != $p) continue;
                            if (in_array($d, $dialogs)) {
                                unset($dialogs[array_search($d, $dialogs)]);
                            }
                            $pinned[] = $d;
                            break;
                        }
                    }
                    $dialogs = array_merge($pinned, $dialogs);
                    unset($pinned);
                }
                unset($all);
            } else {
                $r = $MP->messages->getDialogs(
                    folder_id: 0,
                    limit: $count
                );
                $dialogs = $r['dialogs'];
                foreach ($r['messages'] as $m) {
                    foreach ($dialogs as $k => $d) {
                        if ($m['peer_id'] != $d['peer']) continue;
                        $dialogs[$k]['message'] = $m;
                        break;
                    }
                }
                unset($r['messages']);
                unset($r['dialogs']);
            }
        }
        MP::addUsers($r['users'], $r['chats']);
        $c = 0;
        $msglimit = MP::getSettingInt('limit', 20);
        echo '<table class="cl">';
        foreach ($dialogs as $d){
            if ($fid == 0 && isset($d['folder_id']) && $d['folder_id'] == 1) continue;
            try {
                $id = $d['peer'] ?? $d;
                $n = null;
                $cl = 'chat.php?c='.$id;
                $unr = $d['unread_count'] ?? 0;
                $broadcast = false;
                $peerHasPhoto = false;
                $maxid = $d['read_inbox_max_id'] ?? 0;
                if ($unr > $msglimit) {
                    $cl .= '&m='.$maxid.'&offset='.(-$msglimit-1);
                }
                $searchExtra = [$id];
                foreach (($r[$id > 0 ? 'users' : 'chats']) as $p) {
                    if ($p['id'] != $id) continue;
                    $broadcast = $p['broadcast'] ?? false;
                    if (isset($p['title'])) {
                        $n = $p['title'];
                    } elseif (isset($p['first_name'])) {
                        $n = trim($p['first_name']).(isset($p['last_name']) ? ' '.trim($p['last_name']) : '');
                    } elseif (isset($p['last_name'])) {
                        $n = trim($p['last_name']);
                    } else {
                        $n = 'Deleted Account';
                    }
                    $peerHasPhoto = MP::hasPeerPhoto($p);
                    if (!empty($p['username'])) $searchExtra[] = $p['username'];
                    if (!empty($p['usernames']) && is_array($p['usernames'])) {
                        foreach ($p['usernames'] as $u) {
                            if (is_array($u) && !empty($u['username'])) $searchExtra[] = $u['username'];
                        }
                    }
                    break;
                }
                $searchText = trim(MP::removeEmoji((string)$n).' '.implode(' ', $searchExtra));
                echo '<tr class="c" data-chat-url="'.$cl.'" data-search="'.MP::dehtml($searchText).'" onclick="return window._ovlOpenChatRow ? window._ovlOpenChatRow(this,event) : (location.href=\''.$cl.'\', false);">';
                // Always render the avatar — ava.php falls back to img/us.png or img/gr.png
                // when the peer has no profile photo, so we never leave a blank cell.
                $fallbackAvatar = $id > 0 ? 'img/us.png' : 'img/gr.png';
                $avatarSrc = $peerHasPhoto ? ' data-src="ava.php?c='.$id.'&p='.($pngava?'rc':'r').'54"' : '';
                echo '<td class="cava cbd"><img class="ri" src="'.$fallbackAvatar.'"'.$avatarSrc.'></td>';
                echo '<td class="ctext cbd">';
                echo '<a href="'.$cl.'">';
                echo MP::dehtml(MP::removeEmoji($n));
                
                $mention = $d['unread_mentions_count'] > 0;
                if ($unr > 0/*|| $mention*/) {
                    echo ' <b class="unr">';
                    if ($unr > 0) echo '+'.$unr.' ';
                    if ($mention) echo '@';
                    echo '</b>';
                }
                echo '</a>';
                try {
                    $msg = $d['message'] ?? null;
                    if ($msg !== null) {
                        $mfid = $msg['from_id'] ?? null;
                        $mfn = null;
                        if ($mfid !== null && $id < 0) {
                            $mfn = MP::dehtml(MP::getNameFromId($MP, $msg['from_id']));
                        }
                        $t = null;
                        if (date('d.m.Y', time()-$timeoff) !== date('d.m.Y', $msg['date']-$timeoff)) {
                            $t = date('d.m.Y', $msg['date']-$timeoff);
                        } else {
                            $t = date('H:i', $msg['date']-$timeoff);
                        }
                        echo " <a class=\"ctt\">{$t}</a>";
                        echo '<br><div class="cm">';
                        if (isset($msg['message']) && strlen($msg['message']) > 0) {
                            echo '<a href="'.$cl.'" class="ct">';
                            if (!$broadcast && (($msg['out'] ?? false) || $mfid == $selfid))
                                echo '<a class="mn">'.MP::x($lng['you']).'</a>: ';
                            elseif ($mfn !== null)
                                echo '<a class="mn">'.$mfn.'</a>: ';
                            $txt = MP::dehtml(trim(str_replace("\r","",str_replace("\n", " ", $msg['message']))));
                            if (MP::utflen($txt) > 250) $txt = trim(MP::utfsubstr($txt, 0, 250)).'..';
                            echo $txt;
                            echo '</a>';
                        } elseif (isset($msg['action'])) {
                            echo '<a href="'.$cl.'" class="cma">'.MP::parseMessageAction($msg['action'], $mfn, $mfid, $n, $lng, false, $MP).'</a>';
                        } elseif (isset($msg['media'])) {
                            echo '<a href="'.$cl.'" class="cma">';
                            if (!$broadcast && (($msg['out'] ?? false) || $mfid == $selfid))
                                echo MP::x($lng['you']).': ';
                            elseif ($mfn !== null)
                                echo $mfn.': ';
                            echo MP::x($lng['media_att']);
                            echo '</a>';
                        } else {
                            echo '.';
                        }
                        echo '</div>';
                    }
                } catch (Exception) {
                }
                echo '</td>';
                echo '</tr>';
            } catch (Exception $e) {
                echo "<xmp>$e</xmp>";
            }
            $c += 1;
        }
        echo '</table>';
        unset($dialogs);
        unset($r);
    } catch (Exception $e) {
        echo '<b>'.MP::x($lng['error']).'!</b><br>';
        echo "<xmp>$e</xmp>";
    }
    echo '</div>'; // /chats-list
    if ($fragment) { unset($MP); die; }
    // Close the left sidebar; open the right-pane (only visible on desktop via CSS).
    echo '</aside>';
    echo '<main class="app-main">';
    echo '<div class="app-main-empty">';
    echo '<div class="emoji">💬</div>';
    echo '<div>'.MP::x($lng['select_chat'] ?? 'Select a chat to start messaging').'</div>';
    echo '</div>';
    echo '<iframe id="chat-frame" name="chatframe" class="app-pane-frame" style="display:none" src="about:blank"></iframe>';
    echo '</main>';
    echo '</div>'; // /app-shell
    echo $globalModals;
    echo '<div class="chat-open-loader" aria-hidden="true"><div class="chat-open-card"><div class="chat-open-spinner"></div><b>'.($lng['lang'] == 'ru' ? 'Загрузка чата' : 'Loading chat').'</b><span class="chat-open-tip"></span></div></div>';

    // Desktop two-pane click handler: intercepts chat-row clicks and loads
    // them into the right iframe instead of full-page navigation. Mobile/Lumia
    // browsers ignore this (viewport too narrow) and use plain links.
    echo '<script type="text/javascript"><!--
(function(){
  var _ovlTips='.json_encode($lng['lang'] == 'ru' ? [
    'Папки чатов помогают держать каналы отдельно от личных диалогов.',
    'Закрепите до нескольких важных диалогов, чтобы не искать их каждый раз.',
    'В Telegram можно начать сообщение на телефоне и дописать на другом устройстве.',
    'Сохранённые сообщения удобны для чек-листов, ссылок и быстрых заметок.',
    'Поиск по чату умеет находить старые сообщения быстрее, чем прокрутка.',
    'Архив прячет шумные чаты, но оставляет их рядом.',
    'Ответы в группах помогают не потерять нить разговора.',
    'Отключите уведомления у шумного канала, не покидая его.',
    'Перешлите себе важный пост, чтобы вернуться к нему вечером.',
    'Используйте @username, если не хотите делиться номером телефона.'
  ] : [
    'Chat folders keep channels separate from personal conversations.',
    'Pin a few important dialogs so you do not hunt for them every time.',
    'You can start a message on your phone and finish it on another device.',
    'Saved Messages is great for checklists, links, and quick notes.',
    'In-chat search beats scrolling when you need an old message.',
    'Archive hides noisy chats while keeping them close.',
    'Replies in groups keep the thread of a conversation intact.',
    'Mute a busy channel without leaving it.',
    'Forward an important post to yourself and revisit it tonight.',
    'Use a @username when you do not want to share your phone number.'
  ]).';
  var _ovlTipTimer=null;
  var _ovlTipSwapTimer=null;
  function _ovlTip(){
    if(!_ovlTips||!_ovlTips.length)return "";
    return _ovlTips[Math.floor(Math.random()*_ovlTips.length)];
  }
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
  window._ovlStartLoadingTips=function(root){
    var el=null;
    if(root){
      var tips=root.getElementsByTagName("span"),i;
      for(i=0;i<tips.length;i++){if((" "+(tips[i].className||"")+" ").indexOf(" chat-open-tip ")>=0){el=tips[i];break;}}
    }
    if(!el&&document.querySelector)el=document.querySelector(".chat-open-loader .chat-open-tip");
    if(!el)return;
    _ovlSetTip(el);
    if(_ovlTipTimer)clearInterval(_ovlTipTimer);
    _ovlTipTimer=setInterval(function(){_ovlSetTip(el);},3600);
  };
  window.ovlLoadModalIframe=function(id){
    var m=document.getElementById(id);
    if(!m)return;
    var fs=m.getElementsByTagName("iframe");
    if(!fs||!fs.length)return;
    var f=fs[0],src=f.getAttribute("data-src");
    if(src&&f.getAttribute("src")!==src)f.setAttribute("src",src);
  };
  function modalHandler(ev){
    ev=ev||window.event;
    var t=ev.target||ev.srcElement;
    while(t&&t.nodeType==1&&t.tagName!=="A"){t=t.parentNode;}
    if(!t)return;
    var h=t.getAttribute("href")||"";
    if(h.charAt(0)==="#")window.ovlLoadModalIframe(h.substring(1));
  }
  if(document.addEventListener)document.addEventListener("click",modalHandler,true);
  else if(document.attachEvent)document.attachEvent("onclick",modalHandler);
  if(location.hash&&location.hash.length>1)setTimeout(function(){window.ovlLoadModalIframe(location.hash.substring(1));},80);
  window.ovlLoadAvatars=function(){
    if(window._ovlChatOpening){setTimeout(window.ovlLoadAvatars,800);return;}
    var imgs=document.getElementsByTagName("img"),q=[],i;
    for(i=0;i<imgs.length;i++){
      if(imgs[i].getAttribute("data-src"))q.push(imgs[i]);
    }
    var active=0,maxActive=2;
    function next(){
      if(window._ovlChatOpening){setTimeout(next,800);return;}
      if(active>=maxActive)return;
      var img=q.shift();
      if(!img)return;
      var src=img.getAttribute("data-src");
      if(!src){next();return;}
      active++;
      var done=false,timer=null;
      function finish(){if(done)return;done=true;if(timer)clearTimeout(timer);active--;setTimeout(next,60);}
      timer=setTimeout(finish,3500);
      img.onload=img.onerror=finish;
      img.removeAttribute("data-src");
      img.src=src;
      next();
    }
    next();
  };
  function scheduleAvatars(){setTimeout(window.ovlLoadAvatars,250);}
  if(window.addEventListener)window.addEventListener("load",scheduleAvatars,false);
  else if(window.attachEvent)window.attachEvent("onload",scheduleAvatars);
  function isDesktop(){return (window.innerWidth||document.documentElement.clientWidth)>=925;}
  function openInPane(url,name){
    var f=document.getElementById("chat-frame"),e=document.querySelector(".app-main-empty");
    if(!f)return false;
    window._ovlChatOpening=true;
    if(e){
      e.className=(e.className||"")+" loading";
      e.innerHTML="<div class=\"chat-open-card inline\"><div class=\"chat-open-spinner\"></div><b></b><span class=\"chat-open-tip\"></span></div>";
      var b=e.getElementsByTagName("b");
      if(b.length)b[0].appendChild(document.createTextNode(name||"'.($lng['lang'] == 'ru' ? 'Загрузка чата' : 'Loading chat').'"));
      window._ovlStartLoadingTips(e);
      e.style.display="flex";
    }
    f.onload=function(){
      window._ovlChatOpening=false;
      if(e){e.style.display="none";e.className=(e.className||"").replace(/\\bloading\\b/g," ");}
      f.style.display="block";
      setTimeout(window.ovlLoadAvatars,500);
    };
    f.style.display="none";
    f.src=url;
    return true;
  }
  window._ovlShowChatLoading=function(name){
    var b=document.body;
    if(!b)return;
    if((b.className||"").indexOf("chat-opening")<0)b.className=(b.className||"")+" chat-opening";
    var card=document.querySelector?document.querySelector(".chat-open-loader .chat-open-card"):null;
    if(card&&name){
      var title=card.getElementsByTagName("b");
      if(title&&title.length){title[0].innerHTML="";title[0].appendChild(document.createTextNode(name));}
    }
    window._ovlStartLoadingTips(document);
  };
  window._ovlOpenUrl=function(u,name,row){
    if(!u)return false;
    if(!isDesktop()){
      window._ovlShowChatLoading(name);
      document.body.className=(document.body.className||"")+" chat-list-leave";
      setTimeout(function(){location.href=u;},1400);
      return false;
    }
    var url=u+(u.indexOf("?")>-1?"&":"?")+"embedded=1";
    var m=u.match(/[?&]c=([0-9]+)/);
    if(m&&m[1]){
      url+="&quick=1";
      if(name)url+="&n="+encodeURIComponent(name);
    }
    if(row){
      var prev=document.querySelector(".cl tr.active"); if(prev)prev.className=prev.className.replace(/\\bactive\\b/g," ").replace(/\\s+/g," ");
      if(row.className.indexOf("active")<0)row.className+=" active";
    }
    location.hash="";
    return openInPane(url,name);
  };
  window._ovlOpenChatRow=function(t,ev){
    var u=t.getAttribute("data-chat-url");
    if(!u){
      var a=t.getElementsByTagName("a"); if(a.length===0)return true;
      u=a[0].getAttribute("href");
    }
    if(!u)return true;
    if(ev&&ev.preventDefault)ev.preventDefault();else if(ev)ev.returnValue=false;
    var a=t.getElementsByTagName("a"),name="";
    if(a.length)name=a[0].innerText||a[0].textContent||"";
    return window._ovlOpenUrl(u,name,t);
  };
  function setupLocalSearch(){
    var input=document.getElementById("local-search-input"),results=document.getElementById("local-search-results"),empty=document.getElementById("local-search-empty");
    if(!input||!results)return;
    function textOf(el){return el.innerText||el.textContent||"";}
    function clear(el){while(el.firstChild)el.removeChild(el.firstChild);}
    function render(){
      var q=(input.value||"").toLowerCase().replace(/^\\s+|\\s+$/g,"");
      clear(results);
      if(!q){if(empty)empty.style.display="block";return;}
      var rows=document.querySelectorAll?document.querySelectorAll("#chats-list tr.c"):[],shown=0,i;
      for(i=0;i<rows.length&&shown<24;i++){
        var row=rows[i],hay=((row.getAttribute("data-search")||"")+" "+textOf(row)).toLowerCase();
        if(hay.indexOf(q)<0)continue;
        var url=row.getAttribute("data-chat-url")||"",name="",sub="";
        var nameEl=row.querySelector?row.querySelector(".ctext>a"):null;
        var subEl=row.querySelector?row.querySelector(".cm"):null;
        name=nameEl?textOf(nameEl):textOf(row);
        sub=subEl?textOf(subEl):"";
        var a=document.createElement("a");
        a.className="local-search-result";
        a.href=url;
        a.setAttribute("data-row-index",i);
        var b=document.createElement("b"); b.appendChild(document.createTextNode(name));
        var s=document.createElement("span"); s.appendChild(document.createTextNode(sub));
        a.appendChild(b); a.appendChild(s);
        a.onclick=(function(r,u,n){return function(ev){ev=ev||window.event;if(ev&&ev.preventDefault)ev.preventDefault();else ev.returnValue=false;return window._ovlOpenUrl(u,n,r);};})(row,url,name);
        results.appendChild(a);
        shown++;
      }
      if(empty){empty.style.display=shown?"none":"block";empty.innerHTML=shown?"":"'.($lng['lang'] == 'ru' ? 'Ничего не найдено. Откройте контакты ниже.' : 'Nothing found. Open contacts below.').'";}
    }
    input.onkeyup=render;
    input.onsearch=render;
    input.oninput=render;
    render();
  }
  if(document.addEventListener)document.addEventListener("DOMContentLoaded",setupLocalSearch,false);
  else setTimeout(setupLocalSearch,200);
})();
//--></script>';
    $iev = MP::getIEVersion();
    $autoupd = MP::getSettingInt('autoupd', ($iev == 0 || $iev > 4) ? 1 : 0);
    if ($autoupd) {
        $fragUrl = MP::getUrl().'chats.php?fragment=1'.($fid ? '&f='.$fid : '').'&count='.$count;
        $initialHash = sets_chats_quick_hash($MP, $fid, $count);
        echo '<script type="text/javascript"><!--
var _chatsUrl = '.json_encode($fragUrl).';
var _chatsSince = '.json_encode($initialHash).';
function _chatsXhr(){if(typeof XMLHttpRequest!=="undefined")return new XMLHttpRequest();try{return new ActiveXObject("Msxml2.XMLHTTP.6.0");}catch(e){}try{return new ActiveXObject("Microsoft.XMLHTTP");}catch(e){}return null;}
function _chatsPoll(){var x=_chatsXhr();if(!x){return;}try{var u=_chatsUrl+(_chatsSince?"&since="+encodeURIComponent(_chatsSince):"");x.open("GET",u,true);x.onreadystatechange=function(){if(x.readyState===4){if(x.status===200){var newHash=x.getResponseHeader?x.getResponseHeader("X-Chats-Hash"):"";if(newHash&&newHash!==_chatsSince){var el=document.getElementById("chats-list");if(el){var d=document.createElement("div");d.innerHTML=x.responseText;var nu=d.querySelector?d.querySelector("#chats-list"):null;el.innerHTML=nu?nu.innerHTML:x.responseText;if(window.ovlLoadAvatars)setTimeout(window.ovlLoadAvatars,120);}_chatsSince=newHash;}else if(newHash){_chatsSince=newHash;}setTimeout(_chatsPoll,500);}else{setTimeout(_chatsPoll,5000);}}};x.send(null);}catch(e){setTimeout(_chatsPoll,10000);}}
setTimeout(_chatsPoll,1500);
//--></script>';
    }
    echo Themes::bodyEnd();
    // Flush before any tail work / GC
    if (!$fragment && function_exists('fastcgi_finish_request')) {
        @ob_end_flush();
        fastcgi_finish_request();
    }
    unset($MP);
    die;
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'SESSION_REVOKED') || str_contains($e->getMessage(), 'session created on newer PHP')) {
        header('Location: login.php?revoked=1');
        die;
    }
    //if (strpos($e->getMessage(), 'Could not get user info!') !== false) {
    //    header('Location: login.php?logout=1');
    //    die;
    //}
    echo '<b>'.$lng['error'].'!</b><br>';
    echo "<xmp>$e</xmp><br>";
    echo '<b><a href="login.php?logout=1">'.MP::x($lng['logout']).'</a><b>';
}

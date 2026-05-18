<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
MPGram S Web — settings refactored into categories.
*/
$lang = 'ru';
$autoupd = 1;
$dynupd = 1;
$updint = 10;
$theme = 0;
$chats = 15;
$sym3 = str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'Symbian/3');
$reverse = $sym3 ? 1 : 0;
$autoscroll = 1;
$limit = 20;
$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$avas = strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
$texttop = $sym3 ? 1 : 0;
$longpoll = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') ? 1 : 0;
$status = 0;
$imgs = 1;
$pngava = 0;
$oldchat = 0;
$photosize = 180;
$bgsize = 240;
$chatbg = 0;
$chatbgblur = 0;
$chatbgdark = 45;
$set = isset($_GET['set']);
$cat = $_GET['cat'] ?? '';
include 'mp.php';

MP::startSession();

if ($set) {
    // ---- chat-settings save (cookies + session) ----
    $autoupd = isset($_GET['autoupd']) ? 1 : 0;
    $reverse = isset($_GET['reverse']) ? 1 : 0;
    $autoscroll = isset($_GET['autoscroll']) ? 1 : 0;
    $avas = isset($_GET['avas']) ? 1 : 0;
    $idf = $dynupd == 1 ? 10 : 25;
    if (isset($_GET['lang'])) { $lang = $_GET['lang']; }
    if (isset($_GET['updint'])) {
        $updint = $_GET['updint'];
        $updint = is_numeric($updint) ? (int)$updint : $idf;
        if ($updint < 3 || $updint > 60) { $updint = $idf; }
    }
    if (isset($_GET['theme']))  { $theme = (int)$_GET['theme']; }
    if (isset($_GET['chats']))  { $chats = max(10, min(100, (int)$_GET['chats'])); }
    if (isset($_GET['limit']))  { $limit = max(5, min(50, (int)$_GET['limit'])); }
    if (isset($_GET['photosize'])) { $photosize = (int)$_GET['photosize']; }
    if (isset($_GET['bgsize']))    { $bgsize = (int)$_GET['bgsize']; }
    $oldChatbg = isset($_SESSION['chatbg']) ? (int)$_SESSION['chatbg'] : (isset($_COOKIE['chatbg']) ? (int)$_COOKIE['chatbg'] : $chatbg);
    $oldChatbgblur = isset($_SESSION['chatbgblur']) ? (int)$_SESSION['chatbgblur'] : (isset($_COOKIE['chatbgblur']) ? (int)$_COOKIE['chatbgblur'] : $chatbgblur);
    $oldChatbgdark = isset($_SESSION['chatbgdark']) ? (int)$_SESSION['chatbgdark'] : (isset($_COOKIE['chatbgdark']) ? (int)$_COOKIE['chatbgdark'] : $chatbgdark);
    $chatBgFieldsSubmitted = isset($_GET['chatbg_present']);
    if (isset($_GET['chatbgdark'])) { $chatbgdark = max(0, min(85, (int)$_GET['chatbgdark'])); }
    else { $chatbgdark = max(0, min(85, $oldChatbgdark)); }
    $texttop  = isset($_GET['texttop']) ? 1 : 0;
    $longpoll = isset($_GET['longpoll']) ? 1 : 0;
    $status   = isset($_GET['status']) ? 1 : 0;
    $imgs     = isset($_GET['imgs']) ? 1 : 0;
    $pngava   = isset($_GET['pngava']) ? 1 : 0;
    $oldchat  = isset($_GET['oldchat']) ? 1 : 0;
    $chatbg   = $chatBgFieldsSubmitted ? (isset($_GET['chatbg']) ? 1 : 0) : $oldChatbg;
    $chatbgblur = $chatBgFieldsSubmitted ? (isset($_GET['chatbgblur']) ? 1 : 0) : $oldChatbgblur;

    MP::cookie('lang', $lang, time() + (86400 * 365));
    MP::cookie('updint', $updint, time() + (86400 * 365));
    MP::cookie('theme', $theme, time() + (86400 * 365));
    MP::cookie('chatbg', $chatbg, time() + (86400 * 365));
    MP::cookie('chatbgblur', $chatbgblur, time() + (86400 * 365));
    MP::cookie('chatbgdark', $chatbgdark, time() + (86400 * 365));

    foreach (['lang','autoupd','updint','theme','chats','reverse','autoscroll','limit','avas','texttop','longpoll','status','imgs','pngava','oldchat','photosize','bgsize','chatbg','chatbgblur','chatbgdark'] as $k) {
        $_SESSION[$k] = $$k;
    }
} else {
    // ---- chat-settings load (cookies > session) ----
    foreach (['lang','autoupd','updint','theme','chats','reverse','autoscroll','limit','avas','texttop','longpoll','status','imgs','pngava','oldchat','photosize','bgsize','chatbg','chatbgblur','chatbgdark'] as $k) {
        if (isset($_COOKIE[$k])) {
            $$k = ($k === 'lang') ? $_COOKIE[$k] : (int)$_COOKIE[$k];
        }
        if (isset($_SESSION[$k])) {
            $$k = ($k === 'lang') ? $_SESSION[$k] : (int)$_SESSION[$k];
        }
    }
}

$lng = MP::initLocale();

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store');

include 'themes.php';
Themes::setTheme($theme);

// ---- form actions (Account, Privacy, etc.) ----
$flash = null;
$flashErr = null;
$shouldReloadParent = false;
$user = MP::getUser();
$MP = null;
// Initialize $MP for any category that needs to talk to the server.
// Overview also needs it to fetch the profile preview (name/username/etc).
if ($user && in_array($cat, ['', 'account', 'privacy', 'folders', 'business'], true)) {
    try { $MP = MP::getMadelineAPI($user, true); } catch (Throwable $e) { $flashErr = $e->getMessage(); }
}
if ($user && !empty($_POST['action'])) {
    try {
        $localAction = in_array($_POST['action'], ['upload_chat_background', 'delete_chat_background'], true);
        if (!$localAction && !$MP) { $MP = MP::getMadelineAPI($user, true); }
        switch ($_POST['action']) {
            case 'update_profile':
                $first = trim($_POST['first_name'] ?? '');
                $last  = trim($_POST['last_name']  ?? '');
                $about = $_POST['about'] ?? null;
                $params = [];
                if ($first !== '') $params['first_name'] = $first;
                $params['last_name'] = $last;
                if ($about !== null) $params['about'] = trim($about);
                $MP->account->updateProfile($params);
                $flash = $lng['set_profile_updated'] ?? 'Profile updated';
                break;
            case 'update_username':
                $u = trim($_POST['username'] ?? '');
                $MP->account->updateUsername(['username' => $u]);
                $flash = ($u === '')
                    ? ($lng['set_username_cleared'] ?? 'Username cleared')
                    : ($lng['set_username_updated'] ?? 'Username updated');
                break;
            case 'toggle_username_hide':
                // hide/show a collectible username
                $u = $_POST['username'] ?? '';
                $active = isset($_POST['active']) && $_POST['active'] === '1';
                $MP->account->toggleUsername(['username' => $u, 'active' => $active]);
                $flash = $active
                    ? ($lng['set_username_shown'] ?? 'Username made visible')
                    : ($lng['set_username_hidden'] ?? 'Username hidden');
                break;
            case 'update_privacy':
                // last_seen privacy: 0=everyone, 1=contacts, 2=nobody
                $rule = (int)($_POST['last_seen'] ?? 0);
                $rules = [['_' => 'inputPrivacyValueAllowAll']];
                if ($rule === 1) $rules = [['_' => 'inputPrivacyValueAllowContacts']];
                if ($rule === 2) $rules = [['_' => 'inputPrivacyValueDisallowAll']];
                $MP->account->setPrivacy([
                    'key' => ['_' => 'inputPrivacyKeyStatusTimestamp'],
                    'rules' => $rules,
                ]);
                $flash = $lng['set_privacy_updated'] ?? 'Privacy updated';
                break;
            case 'terminate_session':
                $hash = (string)($_POST['hash'] ?? '');
                if ($hash !== '') {
                    $MP->account->resetAuthorization(['hash' => $hash]);
                    $flash = $lng['set_session_terminated'] ?? 'Session terminated';
                }
                break;
            case 'update_business_hours':
                $tz = $_POST['timezone_id'] ?? null;
                // New format: per-day open checkbox + start/end times.
                // Falls back to legacy text parse if no day rows posted.
                $weekly = sets_parse_business_day_rows($_POST);
                if ($weekly === null) {
                    $hoursText = $_POST['hours'] ?? '';
                    $weekly = sets_parse_business_hours($hoursText);
                }
                $params = ['business_work_hours' => null];
                if ($weekly !== null && $tz) {
                    $params['business_work_hours'] = [
                        '_' => 'businessWorkHours',
                        'open_now' => false,
                        'timezone_id' => $tz,
                        'weekly_open' => $weekly,
                    ];
                }
                $MP->account->updateBusinessWorkHours($params);
                $flash = $lng['set_business_hours_saved'] ?? 'Business hours saved';
                break;
            case 'update_business_location':
                $addr = trim($_POST['address'] ?? '');
                if ($addr === '') {
                    $MP->account->updateBusinessLocation(['address' => '']);
                } else {
                    $MP->account->updateBusinessLocation(['address' => $addr]);
                }
                $flash = $lng['set_business_location_saved'] ?? 'Business location saved';
                break;
            case 'upload_profile_photo':
                if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Upload failed');
                }
                // Validate it's an image
                $type = @getimagesize($_FILES['photo']['tmp_name'])['mime'] ?? '';
                if (!in_array($type, ['image/jpeg', 'image/png', 'image/gif'], true)) {
                    throw new Exception('Only JPEG / PNG / GIF allowed');
                }
                // Copy to a path with a sensible extension — MadelineProto needs a real file path
                $ext = $type === 'image/png' ? 'png' : ($type === 'image/gif' ? 'gif' : 'jpg');
                $tmp = sys_get_temp_dir().'/ovl_avatar_'.bin2hex(random_bytes(6)).'.'.$ext;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $tmp)) {
                    throw new Exception('Could not save upload');
                }
                try {
                    $MP->photos->uploadProfilePhoto(['file' => $tmp]);
                    $flash = $lng['set_photo_uploaded'] ?? 'Profile photo updated';
                } finally {
                    @unlink($tmp);
                }
                break;
            case 'delete_profile_photo':
                try {
                    $photos = $MP->photos->getUserPhotos(['user_id' => ['_' => 'inputUserSelf'], 'offset' => 0, 'max_id' => 0, 'limit' => 1]);
                    if (!empty($photos['photos'][0])) {
                        $p = $photos['photos'][0];
                        $MP->photos->deletePhotos(['id' => [[
                            '_'           => 'inputPhoto',
                            'id'          => $p['id'],
                            'access_hash' => $p['access_hash'],
                            'file_reference' => $p['file_reference'],
                        ]]]);
                        $flash = $lng['set_photo_removed'] ?? 'Profile photo removed';
                    } else {
                        $flashErr = $lng['set_no_photo_to_remove'] ?? 'No photo to remove';
                    }
                } catch (Throwable $e) {
                    $flashErr = $e->getMessage();
                }
                break;
            case 'upload_chat_background':
                if (!isset($_FILES['background']) || $_FILES['background']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Upload failed');
                }
                $type = @getimagesize($_FILES['background']['tmp_name'])['mime'] ?? '';
                if (!in_array($type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                    throw new Exception('Only image files are allowed');
                }
                $dir = __DIR__.'/cache/backgrounds';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $target = Themes::chatBackgroundFile($user);
                $saved = false;
                if ($target && function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
                    $bytes = @file_get_contents($_FILES['background']['tmp_name']);
                    $img = $bytes === false ? false : @imagecreatefromstring($bytes);
                    if ($img !== false) {
                        $saved = @imagejpeg($img, $target, 88);
                        @imagedestroy($img);
                    }
                }
                if (!$saved && $target) {
                    $saved = move_uploaded_file($_FILES['background']['tmp_name'], $target);
                }
                if (!$target || !$saved) {
                    throw new Exception('Could not save background');
                }
                $chatbg = 1;
                $_SESSION['chatbg'] = 1;
                MP::cookie('chatbg', 1, time() + (86400 * 365));
                $shouldReloadParent = true;
                $flash = $lng['set_chat_background_uploaded'] ?? 'Chat background updated';
                break;
            case 'delete_chat_background':
                $target = Themes::chatBackgroundFile($user);
                if ($target && file_exists($target)) @unlink($target);
                $chatbg = 0;
                $_SESSION['chatbg'] = 0;
                MP::cookie('chatbg', 0, time() + (86400 * 365));
                $shouldReloadParent = true;
                $flash = $lng['set_chat_background_removed'] ?? 'Chat background removed';
                break;
        }
    } catch (Throwable $e) {
        $flashErr = $e->getMessage();
    }
}

/**
 * Parse the new per-day form rows (day_open_0..6, day_from_0..6, day_to_0..6).
 * Each "open" checkbox triggers a 1-minute-precision range for that day.
 * Returns null if no day-row fields are present (caller falls back to text).
 */
function sets_parse_business_day_rows(array $post): ?array {
    $hasAnyRow = false;
    $out = [];
    for ($d = 0; $d < 7; $d++) {
        if (!isset($post['day_from_'.$d]) && !isset($post['day_to_'.$d]) && !isset($post['day_open_'.$d])) {
            continue;
        }
        $hasAnyRow = true;
        if (empty($post['day_open_'.$d])) continue; // closed for this day
        $from = $post['day_from_'.$d] ?? '09:00';
        $to   = $post['day_to_'.$d]   ?? '18:00';
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $from, $a)) continue;
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $to, $b)) continue;
        $start = $d * 24 * 60 + ((int)$a[1]) * 60 + (int)$a[2];
        $end   = $d * 24 * 60 + ((int)$b[1]) * 60 + (int)$b[2];
        if ($end <= $start) $end += 24 * 60;
        $out[] = ['_' => 'businessWeeklyOpen', 'start_minute' => $start, 'end_minute' => $end];
    }
    return $hasAnyRow ? $out : null;
}

/** Parse "Mon 09:00-18:00\nTue ..." into Telegram weekly_open array (minute-of-week ranges). */
function sets_parse_business_hours(string $text): ?array {
    $days = ['mon'=>0,'tue'=>1,'wed'=>2,'thu'=>3,'fri'=>4,'sat'=>5,'sun'=>6];
    $out = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim(strtolower($line));
        if ($line === '') continue;
        if (!preg_match('/^(mon|tue|wed|thu|fri|sat|sun)\s+(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $line, $m)) {
            return null;
        }
        $base = $days[$m[1]] * 24 * 60;
        $start = $base + ((int)$m[2]) * 60 + (int)$m[3];
        $end   = $base + ((int)$m[4]) * 60 + (int)$m[5];
        if ($end <= $start) $end += 24 * 60;
        $out[] = ['_' => 'businessWeeklyOpen', 'start_minute' => $start, 'end_minute' => $end];
    }
    return $out ?: null;
}

echo '<html><head><title>'.MP::x($lng['settings']).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart('class="settings-page"');
echo '<div class="settings-viewport">';
echo Themes::iframeDetectScript();
if ($set || $shouldReloadParent) {
    echo '<script type="text/javascript"><!--
try {
    if (window.parent && window.parent !== window) {
        setTimeout(function(){ window.parent.location.reload(); }, 250);
    }
} catch (e) {}
//--></script>';
}

// ---- app bar (hidden via CSS when body.embedded is set by the iframe-detect script) ----
echo Themes::appbar($lng['settings'], 'chats.php');

// ---- top nav: categories ----
// SVG icon set for category tabs (Feather-style)
$catIcons = [
    ''         => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    'account'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'chat'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'privacy'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'folders'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
    'business' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
    'language' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
];
$cats = [
    ''          => $lng['set_cat_overview']  ?? 'Overview',
    'account'   => $lng['set_cat_account']   ?? 'Account',
    'chat'      => $lng['set_cat_chat']      ?? 'Chat settings',
    'privacy'   => $lng['set_cat_privacy']   ?? 'Privacy and Security',
    'folders'   => $lng['set_cat_folders']   ?? 'Chat folders',
    'business'  => $lng['set_cat_business']  ?? 'Business',
    'language'  => $lng['set_cat_language']  ?? 'Language',
];
$qs = isset($_GET['embedded']) ? '&embedded=1' : '';
$base = 'sets.php'.(isset($_GET['embedded']) ? '?embedded=1' : '');
echo '<div class="set-nav">';
foreach ($cats as $k => $label) {
    $href = $k === '' ? $base : ('sets.php?cat='.$k.$qs);
    $cur = ($cat === $k);
    $icon = $catIcons[$k] ?? '';
    $lblText = MP::dehtml($label);
    echo '<a class="set-nav-tab'.($cur ? ' active' : '').'" href="'.htmlspecialchars($href).'" target="_self" title="'.$lblText.'" aria-label="'.$lblText.'">'.$icon.'</a>';
}
echo '</div>';

// ---- flash ----
if ($flash !== null) echo '<div class="alert alert-success">'.MP::dehtml($flash).'</div>';
if ($flashErr !== null) echo '<div class="alert alert-danger">'.MP::dehtml($flashErr).'</div>';

// ---- per-category render: wrap in a card for visual grouping ----
echo '<div class="card settings-content">';
switch ($cat) {
    case 'account':   sets_render_account($MP, $user, $lng);   break;
    case 'chat':      sets_render_chat($lng, compact('autoupd','reverse','autoscroll','avas','texttop','longpoll','status','imgs','pngava','oldchat','updint','limit','chats','photosize','theme','bgsize','chatbg','chatbgblur','chatbgdark')); break;
    case 'privacy':   sets_render_privacy($MP, $user, $lng);   break;
    case 'folders':   sets_render_folders($MP, $user, $lng);   break;
    case 'business':  sets_render_business($MP, $user, $lng);  break;
    case 'language':  sets_render_language($lang, $lng);       break;
    default:          sets_render_overview($MP, $user, $lng, $cats, $catIcons, $qs);
}
echo '</div>'; // /card

if (MP::getUser()) {
    echo '<p class="mt-3"><a class="btn btn-danger" href="login.php?logout=2">'.MP::x($lng['logout']).'</a></p>';
}
echo '</div>';
echo Themes::bodyEnd();
// Flush response immediately so the user sees the page; session GC continues in BG.
if (function_exists('fastcgi_finish_request')) {
    @ob_end_flush();
    fastcgi_finish_request();
}

// ============================================================
// Category renderers
// ============================================================

function sets_emoji_status_icon($emojiStatus) {
    if (!is_array($emojiStatus)) {
        return null;
    }
    if (($emojiStatus['_'] ?? '') === 'emojiStatusEmpty') {
        return null;
    }
    if (isset($emojiStatus['emoji_status']) && is_array($emojiStatus['emoji_status'])) {
        return sets_emoji_status_icon($emojiStatus['emoji_status']);
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

function sets_pick_emoji_status($statuses) {
    $fallback = null;
    foreach ($statuses as $emojiStatus) {
        if (!is_array($emojiStatus)) {
            continue;
        }
        if (($emojiStatus['_'] ?? '') === 'emojiStatusEmpty') {
            continue;
        }
        if (sets_emoji_status_icon($emojiStatus)) {
            return $emojiStatus;
        }
        if ($fallback === null) {
            $fallback = $emojiStatus;
        }
    }
    return $fallback;
}

function sets_render_overview(?\danog\MadelineProto\API $MP, ?string $user, array $lng, array $cats, array $catIcons = [], string $qs = ''): void {
    if (!$user || !$MP) {
        echo '<p>'.MP::x($lng['set_login_required'] ?? 'Login required.').'</p>';
        return;
    }
    // getSelf() is cached locally — fast.
    $me = null;
    try { $me = $MP->getSelf(); } catch (Throwable $e) {}

    // getFullUser/getInfo are network round-trips. The overview must open
    // quickly inside the settings modal, so keep it to getSelf() by default.
    $full = null;
    $fastOverview = isset($_GET['embedded']) || isset($_GET['fast']) || !isset($_GET['full']);
    if (!$fastOverview) {
        try { $full = $MP->users->getFullUser(['id' => ['_' => 'inputUserSelf']]); } catch (Throwable $e) {}
    }
    $infoMe = null;
    if (!$fastOverview) {
        try {
            $info = $MP->getInfo(['_' => 'inputUserSelf']);
            $infoMe = $info['User'] ?? null;
        } catch (Throwable $e) {}
    }

    $first    = $me['first_name'] ?? '';
    $last     = $me['last_name']  ?? '';
    $name     = trim($first.' '.$last);
    $username = $me['username']   ?? '';
    $phone    = $me['phone']      ?? '';
    $fu       = $full['full_user'] ?? [];
    $about    = $fu['about']       ?? '';
    $selfid   = $me['id'] ?? null;
    $pngava   = MP::getSettingInt('pngava', 0);
    $hasPhoto = !empty($me['photo']) && (($me['photo']['_'] ?? '') !== 'userProfilePhotoEmpty');
    $verified = !empty($me['verified']);
    $isBot    = !empty($me['bot']);
    $emojiStatus = sets_pick_emoji_status([
        $me['emoji_status'] ?? null,
        $infoMe['emoji_status'] ?? null,
        $full['users'][0]['emoji_status'] ?? null,
        $full['full_user']['emoji_status'] ?? null,
    ]);
    $emojiStatusIcon = sets_emoji_status_icon($emojiStatus);
    $commonChats = $fu['common_chats_count'] ?? 0;

    // ---- header card with avatar + name ----
    echo '<div class="profile-preview">';
    if ($selfid !== null) {
        echo '<img class="profile-preview-ava" src="ava.php?c='.(int)$selfid.'&p='.($pngava?'rc':'r').'128" alt="" width="120" height="120" loading="eager" decoding="async" fetchpriority="high">';
    }
    echo '<div class="profile-preview-info">';
    echo '<div class="profile-preview-name">'.htmlspecialchars($name ?: '—');
    if ($verified)        echo ' <span class="badge badge-verified" title="Verified">✓</span>';
    if ($emojiStatusIcon) echo ' <span class="badge-emoji badge-emoji-icon" title="Emoji status"><img src="verifyicon.php?i='.htmlspecialchars((string)$emojiStatusIcon).'&s=96" alt="" width="42" height="42" loading="eager" decoding="async" fetchpriority="high"></span>';
    if ($isBot)           echo ' <span class="badge">BOT</span>';
    echo '</div>';
    if ($phone !== '') echo '<div class="profile-preview-row"><span class="profile-preview-label">'.MP::x($lng['chat_phone']).':</span> +'.htmlspecialchars($phone).'</div>';
    if ($username !== '') echo '<div class="profile-preview-row"><span class="profile-preview-label">'.MP::x($lng['chat_username']).':</span> @'.htmlspecialchars($username).'</div>';
    // Additional (Fragment/collectible) usernames
    $allUsernames = $me['usernames'] ?? ($full['users'][0]['usernames'] ?? []);
    if (is_array($allUsernames) && count($allUsernames) > 0) {
        $extras = [];
        foreach ($allUsernames as $u) {
            $n = $u['username'] ?? '';
            if ($n === '' || !empty($u['editable'])) continue;
            $extras[] = ($u['active'] ?? false ? '@'.$n : '<s>@'.$n.'</s>');
        }
        if ($extras) echo '<div class="profile-preview-row"><span class="profile-preview-label">'.MP::x($lng['set_collectible_usernames'] ?? 'Additional').':</span> '.implode(', ', $extras).'</div>';
    }
    if ($about !== '') echo '<div class="profile-preview-row"><span class="profile-preview-label">'.MP::x($lng['chat_bio']).':</span> '.nl2br(htmlspecialchars($about)).'</div>';
    if ($commonChats > 0) echo '<div class="profile-preview-row"><span class="profile-preview-label">'.MP::x($lng['set_common_chats'] ?? 'Common chats').':</span> '.(int)$commonChats.'</div>';
    if ($selfid !== null) echo '<div class="profile-preview-row"><span class="profile-preview-label">ID:</span> '.(int)$selfid.'</div>';
    echo '</div>';
    echo '</div>';

    // ---- profile photo upload ----
    echo '<div class="card mt-3 profile-photo-card">';
    echo '<div class="card-title">'.MP::x($lng['set_profile_photo'] ?? 'Profile photo').'</div>';
    if ($hasPhoto) {
        echo '<form class="profile-photo-form profile-photo-delete" action="sets.php?cat='.$qs.'" method="post" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="delete_profile_photo">';
        echo '<input type="submit" class="btn btn-danger" value="'.MP::x($lng['set_remove_photo'] ?? 'Remove').'">';
        echo '</form>';
    }
    echo '<form class="profile-photo-form profile-photo-upload" action="sets.php?cat='.$qs.'" method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="upload_profile_photo">';
    echo '<input type="file" name="photo" accept="image/jpeg,image/png,image/gif" required>';
    echo '<input type="submit" class="btn btn-primary" value="'.MP::x($lng['set_upload_photo'] ?? 'Upload new').'">';
    echo '</form>';
    echo '</div>';

    // ---- quick actions ----
    echo '<div class="card mt-3 quick-actions-card">';
    echo '<div class="card-title">'.MP::x($lng['set_quick_actions'] ?? 'Quick actions').'</div>';
    echo '<a class="btn mt-1" href="sets.php?cat=account'.$qs.'">'.MP::x($lng['set_cat_account']).' →</a> ';
    echo '<a class="btn mt-1" href="sets.php?cat=privacy'.$qs.'">'.MP::x($lng['set_cat_privacy']).' →</a> ';
    echo '<a class="btn mt-1" href="sets.php?cat=language'.$qs.'">'.MP::x($lng['set_cat_language']).' →</a>';
    echo '</div>';
}

function sets_render_language(string $lang, array $lng): void {
    echo '<h3>'.MP::x($lng['set_language']).'</h3>';
    echo '<form action="sets.php" method="get">';
    echo '<input type="hidden" name="cat" value="language">';
    echo '<input type="hidden" name="set" value="1">';
    $langs = json_decode(file_get_contents('./locale/list.json'), true);
    foreach ($langs as $k=>$v) {
        echo '<input type="radio" name="lang"'.($lang==$k ? ' checked' : '').' value="'.htmlspecialchars($k).'"> '.MP::x($v).'<br>';
    }
    echo '<p><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></p>';
    echo '</form>';
}

function sets_render_chat(array $lng, array $s): void {
    extract($s);
    $embedded = isset($_GET['embedded']);
    echo '<div class="settings-chat-head">';
    echo '<h3>'.MP::x($lng['set_chat']).'</h3>';
    echo '<p>'.MP::x($lng['set_chat_hint'] ?? 'Tune chat loading, media, layout, and color theme.').'</p>';
    echo '</div>';
    echo '<form action="sets.php" method="get" class="chat-settings-form">';
    echo '<input type="hidden" name="cat" value="chat">';
    echo '<input type="hidden" name="set" value="1">';
    if ($embedded) echo '<input type="hidden" name="embedded" value="1">';

    $groups = [
        $lng['set_chat_behavior'] ?? 'Behavior' => [
            'autoupd'    => $lng['set_chat_autoupdate'],
            'autoscroll' => $lng['set_chat_autoscroll'],
            'longpoll'   => 'Longpoll',
            'reverse'    => $lng['set_chat_reverse_mode'],
            'texttop'    => $lng['set_chat_texttop'],
        ],
        $lng['set_chat_media'] ?? 'Media and identity' => [
            'avas'       => $lng['set_chat_avas'],
            'status'     => $lng['set_chat_status'],
            'imgs'       => $lng['set_msg_photos'],
            'pngava'     => $lng['set_png_avatar'],
            'oldchat'    => $lng['set_old_chat'],
        ],
    ];
    foreach ($groups as $title => $checkboxes) {
        echo '<section class="settings-section">';
        echo '<h4>'.MP::x($title).'</h4>';
        echo '<div class="settings-toggle-list">';
        foreach ($checkboxes as $name => $label) {
            $v = ${$name};
            echo '<label class="settings-toggle" for="'.$name.'">';
            echo '<input type="checkbox" id="'.$name.'" name="'.$name.'"'.($v ? ' checked' : '').'>';
            echo '<span class="settings-toggle-ui" aria-hidden="true"></span>';
            echo '<span class="settings-toggle-text">'.MP::x($label).'</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</section>';
    }

    echo '<section class="settings-section">';
    echo '<h4>'.MP::x($lng['set_chat_limits'] ?? 'Loading limits').'</h4>';
    echo '<div class="settings-number-row">';
    echo '<label>'.MP::x($lng['set_chat_autoupdate_interval']).'<input type="number" min="3" max="60" name="updint" value="'.(int)$updint.'"></label>';
    echo '<label>'.MP::x($lng['set_msgs_limit']).'<input type="number" min="5" max="50" name="limit" value="'.(int)$limit.'"></label>';
    echo '<label>'.MP::x($lng['set_chats_count']).'<input type="number" min="10" max="100" name="chats" value="'.(int)$chats.'"></label>';
    echo '</div>';
    echo '</section>';

    echo '<section class="settings-section">';
    echo '<h4>'.MP::x($lng['set_chat_photos_size']).'</h4>';
    echo '<div class="segmented-options">';
    foreach ([80, 120, 180, 240, 360] as $v) {
        echo '<label class="segmented-option"><input type="radio" name="photosize"'.($photosize==$v ? ' checked' : '').' value="'.$v.'"><span>'.$v.'</span></label>';
    }
    echo '</div>';
    echo '</section>';

    echo '<section class="settings-section">';
    echo '<h4>'.MP::x($lng['set_theme']).'</h4>';
    $themes = [
        0  => $lng['set_theme_dark'],
        1  => $lng['set_theme_light'],
        2  => $lng['set_theme_light_bg'],
        6  => 'Tint',
        7  => 'Dark variant',
        8  => 'Light variant',
        10 => 'OLED Black',
        11 => 'Indigo Night',
        12 => 'Forest',
        13 => 'Sunset',
        14 => 'Ocean',
        15 => 'Crimson',
        16 => 'Login Purple',
    ];
    echo '<div class="theme-picker">';
    foreach ($themes as $k=>$v) {
        $checked = ($theme == $k) ? ' checked' : '';
        $bg = '#222';
        $fg = '#eee';
        $accent = '#8774e1';
        $msg = '#333';
        $cfile = './colors/colors_'.$k.'.json';
        if (file_exists($cfile)) {
            $c = json_decode(@file_get_contents($cfile), true);
            if (is_array($c)) {
                $bg = $c['background'] ?? $bg;
                $fg = $c['foreground'] ?? $fg;
                $accent = $c['message_link'] ?? ($c['button_background'] ?? $accent);
                $msg = $c['message_out_background'] ?? ($c['message_background'] ?? $msg);
            }
        }
        echo '<label class="theme-choice">';
        echo '<input type="radio" name="theme"'.$checked.' value="'.$k.'">';
        echo '<span class="theme-choice-card" style="background:'.$bg.';color:'.$fg.'">';
        echo '<span class="theme-choice-dots"><i style="background:'.$accent.'"></i><i style="background:'.$msg.'"></i><i style="background:'.$fg.'"></i></span>';
        echo '<span class="theme-choice-name">'.MP::x($v).'</span>';
        echo '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</section>';

    echo '<section class="settings-section">';
    echo '<h4>'.MP::x($lng['set_bg_size']).'</h4>';
    echo '<div class="segmented-options">';
    foreach ([240, 320, 640, 720, 1000] as $v) {
        echo '<label class="segmented-option"><input type="radio" name="bgsize"'.($bgsize==$v ? ' checked' : '').' value="'.$v.'"><span>'.$v.'</span></label>';
    }
    echo '</div>';
    echo '</section>';

    $hasChatBg = Themes::chatBackgroundUrl() !== null;
    echo '<section class="settings-section">';
    echo '<h4>'.MP::x($lng['set_chat_background'] ?? 'Chat background image').'</h4>';
    echo '<input type="hidden" name="chatbg_present" value="1">';
    echo '<div class="settings-toggle-list">';
    echo '<label class="settings-toggle" for="chatbg"><input type="checkbox" id="chatbg" name="chatbg" value="1"'.($chatbg && $hasChatBg ? ' checked' : '').($hasChatBg ? '' : ' disabled').'><span class="settings-toggle-ui" aria-hidden="true"></span><span class="settings-toggle-text">'.MP::x($lng['set_chat_background_enable'] ?? 'Use uploaded background').'</span></label>';
    echo '<label class="settings-toggle" for="chatbgblur"><input type="checkbox" id="chatbgblur" name="chatbgblur" value="1"'.($chatbgblur ? ' checked' : '').'><span class="settings-toggle-ui" aria-hidden="true"></span><span class="settings-toggle-text">'.MP::x($lng['set_chat_background_blur'] ?? 'Blur background').'</span></label>';
    echo '</div>';
    echo '<div class="settings-range-row"><label>'.MP::x($lng['set_chat_background_dark'] ?? 'Darken background (%)').'<input type="range" min="0" max="85" name="chatbgdark" value="'.(int)$chatbgdark.'"><span>'.(int)$chatbgdark.'%</span></label></div>';
    echo '</section>';

    echo '<div class="settings-save-row"><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></div>';
    echo '</form>';

    echo '<section class="settings-section chat-bg-upload">';
    echo '<h4>'.MP::x($lng['set_chat_background_upload'] ?? 'Upload background').'</h4>';
    echo '<form action="sets.php?cat=chat'.($embedded ? '&embedded=1' : '').'" method="post" enctype="multipart/form-data" class="chat-bg-form">';
    echo '<input type="hidden" name="action" value="upload_chat_background">';
    echo '<input type="file" name="background" accept="image/*">';
    echo '<input type="submit" class="btn btn-primary" value="'.MP::x($lng['set_chat_background_upload'] ?? 'Upload background').'">';
    echo '</form>';
    if ($hasChatBg) {
        echo '<form action="sets.php?cat=chat'.($embedded ? '&embedded=1' : '').'" method="post" class="chat-bg-form">';
        echo '<input type="hidden" name="action" value="delete_chat_background">';
        echo '<input type="submit" class="btn btn-danger" value="'.MP::x($lng['set_chat_background_remove'] ?? 'Remove background').'">';
        echo '</form>';
    }
    echo '</section>';
}

function sets_render_account(?\danog\MadelineProto\API $MP, ?string $user, array $lng): void {
    if (!$user || !$MP) {
        echo '<p>'.MP::x($lng['set_login_required'] ?? 'Login required.').'</p>';
        return;
    }
    $me = null; $full = null;
    try { $me = $MP->getSelf(); } catch (Throwable $e) {}
    try { $full = $MP->users->getFullUser(['id' => ['_' => 'inputUserSelf']]); } catch (Throwable $e) {}
    $first    = $me['first_name'] ?? '';
    $last     = $me['last_name']  ?? '';
    $username = $me['username']   ?? '';
    $about    = $full['full_user']['about'] ?? '';
    $phone    = $me['phone'] ?? '';

    echo '<h3>'.MP::x($lng['set_cat_account'] ?? 'Account').'</h3>';
    if ($phone !== '') echo '<p>'.MP::x($lng['chat_phone']).': +'.htmlspecialchars($phone).'</p>';

    // --- Profile (name + about/bio)
    echo '<h4>'.MP::x($lng['set_profile'] ?? 'Profile').'</h4>';
    echo '<form action="sets.php?cat=account" method="post">';
    echo '<input type="hidden" name="action" value="update_profile">';
    echo MP::x($lng['signup_first_name'] ?? 'First name').':<br>';
    echo '<input type="text" name="first_name" value="'.htmlspecialchars($first).'"><br>';
    echo MP::x($lng['signup_last_name']  ?? 'Last name').':<br>';
    echo '<input type="text" name="last_name"  value="'.htmlspecialchars($last).'"><br>';
    echo MP::x($lng['chat_bio'] ?? 'Bio').':<br>';
    echo '<textarea name="about" rows="3" cols="40">'.htmlspecialchars($about).'</textarea><br>';
    echo '<p><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></p>';
    echo '</form>';

    // --- Username
    echo '<h4>'.MP::x($lng['chat_username'] ?? 'Username').'</h4>';
    echo '<form action="sets.php?cat=account" method="post">';
    echo '<input type="hidden" name="action" value="update_username">';
    echo '@<input type="text" name="username" value="'.htmlspecialchars($username).'"><br>';
    echo '<small>'.MP::x($lng['set_username_hint'] ?? 'Leave blank to remove. 5–32 chars, a–z, 0–9, underscores.').'</small><br>';
    echo '<p><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></p>';
    echo '</form>';

    // --- Collectible/additional usernames (Fragment)
    $allUsernames = $me['usernames'] ?? ($full['users'][0]['usernames'] ?? []);
    if (is_array($allUsernames) && count($allUsernames) > 0) {
        echo '<h4>'.MP::x($lng['set_collectible_usernames'] ?? 'Additional usernames').'</h4>';
        foreach ($allUsernames as $u) {
            $name = $u['username'] ?? '';
            $active = !empty($u['active']);
            $editable = !empty($u['editable']);
            if ($name === '' || $editable) continue;
            echo '<form action="sets.php?cat=account" method="post" style="display:inline">';
            echo '<input type="hidden" name="action" value="toggle_username_hide">';
            echo '<input type="hidden" name="username" value="'.htmlspecialchars($name).'">';
            echo '<input type="hidden" name="active" value="'.($active ? '0' : '1').'">';
            echo '@'.htmlspecialchars($name).' ';
            $btn = $active
                ? MP::x($lng['set_username_hide_btn'] ?? 'Hide')
                : MP::x($lng['set_username_show_btn'] ?? 'Show');
            echo '<input type="submit" value="'.$btn.'">';
            echo '</form><br>';
        }
    }
}

function sets_render_privacy(?\danog\MadelineProto\API $MP, ?string $user, array $lng): void {
    if (!$user || !$MP) { echo '<p>'.MP::x($lng['set_login_required'] ?? 'Login required.').'</p>'; return; }
    echo '<h3>'.MP::x($lng['set_cat_privacy'] ?? 'Privacy and Security').'</h3>';

    // --- last seen privacy
    $current = 0;
    try {
        $p = $MP->account->getPrivacy(['key' => ['_' => 'inputPrivacyKeyStatusTimestamp']]);
        foreach ($p['rules'] as $r) {
            if ($r['_'] === 'privacyValueDisallowAll') $current = 2;
            elseif ($r['_'] === 'privacyValueAllowContacts') $current = 1;
        }
    } catch (Throwable $e) {}
    echo '<h4>'.MP::x($lng['set_priv_last_seen'] ?? 'Last seen & online').'</h4>';
    echo '<form action="sets.php?cat=privacy" method="post">';
    echo '<input type="hidden" name="action" value="update_privacy">';
    $opts = [
        0 => $lng['set_priv_everyone'] ?? 'Everybody',
        1 => $lng['set_priv_contacts'] ?? 'My contacts',
        2 => $lng['set_priv_nobody']   ?? 'Nobody',
    ];
    foreach ($opts as $v => $label) {
        echo '<input type="radio" name="last_seen" value="'.$v.'"'.($current==$v?' checked':'').'> '.MP::x($label).'<br>';
    }
    echo '<p><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></p>';
    echo '</form>';

    // --- active sessions
    echo '<h4>'.MP::x($lng['set_priv_sessions'] ?? 'Active sessions').'</h4>';
    try {
        $auths = $MP->account->getAuthorizations();
        echo '<ul>';
        foreach ($auths['authorizations'] ?? [] as $a) {
            $cur = !empty($a['current']);
            echo '<li>'.htmlspecialchars(($a['device_model'] ?? '?').' — '.($a['app_name'] ?? '?').' '.($a['app_version'] ?? ''));
            if ($cur) {
                echo ' <i>('.MP::x($lng['set_priv_current'] ?? 'current').')</i>';
            } else {
                echo ' <form action="sets.php?cat=privacy" method="post" style="display:inline">';
                echo '<input type="hidden" name="action" value="terminate_session">';
                echo '<input type="hidden" name="hash" value="'.htmlspecialchars((string)($a['hash'] ?? '')).'">';
                echo '<input type="submit" value="'.MP::x($lng['set_priv_terminate'] ?? 'Terminate').'">';
                echo '</form>';
            }
            echo '</li>';
        }
        echo '</ul>';
    } catch (Throwable $e) {
        echo '<p><i>'.MP::x($lng['set_unavailable'] ?? 'Not available on this server').'</i></p>';
    }
}

function sets_render_folders(?\danog\MadelineProto\API $MP, ?string $user, array $lng): void {
    if (!$user || !$MP) { echo '<p>'.MP::x($lng['set_login_required'] ?? 'Login required.').'</p>'; return; }
    echo '<h3>'.MP::x($lng['set_cat_folders'] ?? 'Chat folders').'</h3>';
    try {
        $f = $MP->messages->getDialogFilters();
        $filters = $f['filters'] ?? $f ?? [];
        if (count($filters) === 0) {
            echo '<p><i>'.MP::x($lng['set_folders_empty'] ?? 'You have no chat folders yet.').'</i></p>';
        } else {
            echo '<ul>';
            foreach ($filters as $flt) {
                if (($flt['_'] ?? '') === 'dialogFilterDefault') continue;
                $title = $flt['title']['text'] ?? $flt['title'] ?? ($lng['set_folder_unnamed'] ?? 'Unnamed');
                if (is_array($title)) $title = $title['text'] ?? '?';
                $pinned = count($flt['pinned_peers'] ?? []);
                $incl   = count($flt['include_peers'] ?? []);
                $excl   = count($flt['exclude_peers'] ?? []);
                echo '<li><b>'.htmlspecialchars($title).'</b> — '.($pinned + $incl).' '
                    .MP::x($lng['set_folder_peers'] ?? 'peers').($excl ? ', '.$excl.' '.MP::x($lng['set_folder_excluded'] ?? 'excluded') : '').'</li>';
            }
            echo '</ul>';
        }
    } catch (Throwable $e) {
        echo '<p><i>'.MP::x($lng['set_unavailable'] ?? 'Not available on this server').'</i></p>';
    }
    echo '<p><i>'.MP::x($lng['set_folders_edit_hint'] ?? 'Folder editing UI not implemented yet — manage folders from the official app.').'</i></p>';
}

function sets_render_business(?\danog\MadelineProto\API $MP, ?string $user, array $lng): void {
    if (!$user || !$MP) { echo '<p>'.MP::x($lng['set_login_required'] ?? 'Login required.').'</p>'; return; }
    echo '<h3>'.MP::x($lng['set_cat_business'] ?? 'Business').'</h3>';

    // Pull current values
    $hours = null; $addr = null; $tz = null;
    try {
        $full = $MP->users->getFullUser(['id' => ['_' => 'inputUserSelf']]);
        $fu = $full['full_user'] ?? [];
        $hours = $fu['business_work_hours'] ?? null;
        $addr  = $fu['business_location']['address'] ?? null;
        $tz    = $hours['timezone_id'] ?? null;
    } catch (Throwable $e) {}

    // hours — render per-day picker (checkbox + time inputs)
    echo '<h4>'.MP::x($lng['set_business_hours'] ?? 'Opening hours').'</h4>';
    echo '<form action="sets.php?cat=business" method="post">';
    echo '<input type="hidden" name="action" value="update_business_hours">';
    echo '<label>'.MP::x($lng['set_business_tz'] ?? 'Timezone ID (e.g. Europe/Moscow)').'</label>';
    echo '<input type="text" name="timezone_id" value="'.htmlspecialchars((string)($tz ?? 'UTC')).'">';

    // Convert existing weekly_open ranges into per-day open/from/to
    $dayDefaults = []; // 0..6 => ['open'=>bool,'from'=>'HH:MM','to'=>'HH:MM']
    for ($d = 0; $d < 7; $d++) $dayDefaults[$d] = ['open' => false, 'from' => '09:00', 'to' => '18:00'];
    if (is_array($hours) && !empty($hours['weekly_open'])) {
        foreach ($hours['weekly_open'] as $w) {
            $s = (int)$w['start_minute']; $e = (int)$w['end_minute'];
            $d = intdiv($s, 24*60) % 7;
            $sh = intdiv($s % (24*60), 60); $sm = $s % 60;
            $eh = intdiv($e % (24*60), 60); $em = $e % 60;
            $dayDefaults[$d] = [
                'open' => true,
                'from' => sprintf('%02d:%02d', $sh, $sm),
                'to'   => sprintf('%02d:%02d', $eh, $em),
            ];
        }
    }
    $dayNames = [
        0 => $lng['day_mon'] ?? 'Monday',
        1 => $lng['day_tue'] ?? 'Tuesday',
        2 => $lng['day_wed'] ?? 'Wednesday',
        3 => $lng['day_thu'] ?? 'Thursday',
        4 => $lng['day_fri'] ?? 'Friday',
        5 => $lng['day_sat'] ?? 'Saturday',
        6 => $lng['day_sun'] ?? 'Sunday',
    ];
    echo '<div class="biz-hours">';
    for ($d = 0; $d < 7; $d++) {
        $def = $dayDefaults[$d];
        echo '<div class="biz-day">';
        echo '<label class="biz-day-name"><input type="checkbox" name="day_open_'.$d.'" value="1"'.($def['open'] ? ' checked' : '').'> '.MP::x($dayNames[$d]).'</label>';
        echo '<input type="time" name="day_from_'.$d.'" value="'.$def['from'].'">';
        echo ' – ';
        echo '<input type="time" name="day_to_'.$d.'" value="'.$def['to'].'">';
        echo '</div>';
    }
    echo '</div>';
    echo '<p><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></p>';
    echo '</form>';

    // location
    echo '<h4>'.MP::x($lng['set_business_location'] ?? 'Location').'</h4>';
    echo '<form action="sets.php?cat=business" method="post">';
    echo '<input type="hidden" name="action" value="update_business_location">';
    echo MP::x($lng['set_business_address'] ?? 'Address').':<br>';
    echo '<input type="text" name="address" size="40" value="'.htmlspecialchars((string)($addr ?? '')).'"><br>';
    echo '<small>'.MP::x($lng['set_business_location_clear'] ?? 'Leave blank to clear.').'</small>';
    echo '<p><input type="submit" class="btn btn-primary" value="'.MP::x($lng['save'] ?? 'Save').'"></p>';
    echo '</form>';
}

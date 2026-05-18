<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$user = null;
$nouser = true;

$confirm = $_GET['confirm'] ?? $_POST['confirm'] ?? null;

$post = isset($_SERVER['HTTP_USER_AGENT']) && !str_contains($_SERVER['HTTP_USER_AGENT'], 'Series60/3');

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

include 'mp.php';
MP::startSession();
$user = MP::getUser();
$nouser = empty($user) || strlen($user) < 32 || strlen($user) > 200 || !file_exists(sessionspath.$user.'.madeline');

$theme = 0;
$ua = '';
$iev = MP::getIEVersion();
if ($iev > 0 && $iev < 4) $theme = 1;
$theme = MP::getSettingInt('theme', $theme);
$lng = MP::initLocale();
MP::cookie('theme', $theme, time() + (86400 * 365));
include 'themes.php';
Themes::setTheme($theme);
include 'authui.php';

function htmlStart(): void
{
    global $lng;
    header("Content-Type: text/html; charset=utf-8");
    echo '<html><head><title>'.MP::x($lng['login']).'</title>';
    echo Themes::head();
    echo auth_loading_script();
    echo '</head>';
    echo Themes::bodyStart('class="auth-page"');
    echo '<div class="auth-shell"><div class="auth-card auth-card-qr">';
    echo auth_logo_html('auth-logo-small');
}

function htmlEnd(): void
{
    echo '</div></div>';
    echo Themes::bodyEnd();
}

$ipass = $_GET['ipass'] ?? $_POST['ipass'] ?? null;
$load = isset($_GET['load']);
$ready = isset($_GET['ready']);

if ($load && !$ready) {
    htmlStart();
    $next = 'qrlogin.php?ready=1'.($ipass !== null ? '&ipass='.rawurlencode($ipass) : '');
    echo '<div class="auth-loading-panel">';
    echo '<div class="auth-spinner" aria-hidden="true"></div>';
    echo auth_heading(auth_text('Preparing QR code', 'Готовим QR-код'), auth_text('Telegram QR login will appear in a moment.', 'Вход по QR-коду появится через мгновение.'));
    echo '<p class="auth-secondary"><a href="'.MP::dehtml($next).'">'.MP::x(auth_text('Continue', 'Продолжить')).'</a></p>';
    echo '</div>';
    echo '<script type="text/javascript">setTimeout(function(){location.href="'.MP::dehtml($next).'";},650);</script>';
    htmlEnd();
    die;
}

$MP = null;
if (defined('INSTANCE_PASSWORD') && INSTANCE_PASSWORD !== null) {
    if ($ipass === null || $ipass != INSTANCE_PASSWORD) {
        htmlStart();
        echo auth_heading('MPGram S', auth_text('Enter the instance password to continue.', 'Введите пароль экземпляра, чтобы продолжить.'));
        echo '<form class="auth-submit-form" action="qrlogin.php"';
        if ($post) echo ' method="post"';
        echo '>';
        echo auth_field('Instance password', 'ipass', '', 'password', 'autocomplete="current-password"');
        echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
        echo '</form>';
        if ($ipass !== null) echo '<b>Wrong password</b>';
        htmlEnd();
        die;
    }
}
if ($user === null || $nouser) {
    // PATCHED for mpgram-web: respect LOGIN_CAPTCHA flag (was: hardcoded captcha gate).
    if ((!defined('LOGIN_CAPTCHA') || LOGIN_CAPTCHA) && (empty($confirm) || !isset($_SESSION['captcha']) || strtolower($confirm) !== $_SESSION['captcha'])) {
        unset($_SESSION['captcha']);
        htmlStart();
        echo auth_heading('CAPTCHA', auth_text('Enter the characters from the picture.', 'Введите символы с картинки.'));
        echo '<p class="auth-captcha"><img src="captcha.php?r='.time().'"></p>';
        echo '<form class="auth-submit-form" action="qrlogin.php"'.($post?' method="post"':'').'>';
        echo auth_field('CAPTCHA', 'confirm', '', 'text', 'autocomplete="off"');
        if ($ipass !== null)
            echo "<input type=\"hidden\" name=\"ipass\" value=\"".MP::dehtml($ipass)."\">";
        echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
        echo '</form>';
        htmlEnd();
        die;
    } else {
        unset($_SESSION['captcha']);
        $user = 'qr_'.hash('sha384', sha1(random_bytes(32).rand(0,1000)).sha1(random_bytes(32)));
        MP::cookie('user', $user, time() + (86400 * 365));
        $MP = MP::getMadelineAPI($user, true);
    }
} else {
    $MP = MP::getMadelineAPI($user, true);
}
$qr = $MP->qrLogin();
if (!$qr) {
    if (isset($_POST['pass']) || isset($_GET['pass'])) {
        // 2fa check
        try {
            $password = null;
            if (isset($_POST['pass'])) {
                $password = $_POST['pass'];
            } elseif (isset($_GET['pass'])) {
                $password = $_GET['pass'];
            }
            $MP->complete2faLogin($password);
            unset($_SESSION['qr_token']);
            MP::cookie('code', '1', time() + (86400 * 365));
            header('Location: chats.php');
            die;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'PASSWORD_HASH_INVALID')) {
                htmlStart();
                echo auth_heading($lng['pass_code'], auth_text('Enter your Telegram password.', 'Введите пароль Telegram.'));
                echo '<form class="auth-submit-form" action="qrlogin.php"'.($post?' method="post"':'').'>';
                echo auth_field($lng['pass_code'], 'pass', '', 'password', 'autocomplete="current-password"');
                //if ($phone !== null)
                //    echo '<input type="hidden" name="phone" value="'.$phone.'">';
                if ($ipass !== null)
                    echo "<input type=\"hidden\" name=\"ipass\" value=\"".MP::dehtml($ipass)."\">";
                echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
                echo '</form>';
                echo '<b>'.MP::x($lng['password_hash_invalid']).'</b><br>';
                htmlEnd();
                die;
            } else {
                echo '<xmp>';
                echo $e;
                echo '</xmp>';
                die;
            }
            die;
        }
    }
    if ($MP->getAuthorization() === \danog\MadelineProto\API::WAITING_PASSWORD) {
        // 2fa start
        htmlStart();
        echo auth_heading($lng['pass_code'], auth_text('Enter your Telegram password.', 'Введите пароль Telegram.'));
        echo '<form class="auth-submit-form" action="qrlogin.php"'.($post?' method="post"':'').'>';
        echo auth_field($lng['pass_code'], 'pass', '', 'password', 'autocomplete="current-password"');
        //if ($phone !== null)
        //    echo '<input type="hidden" name="phone" value="'.$phone.'">';
        if ($ipass !== null)
            echo "<input type=\"hidden\" name=\"ipass\" value=\"".MP::dehtml($ipass)."\">";
        echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
        echo '</form>';
        htmlEnd();
        die;
    }
    if (isset($_SESSION['qr_token'])) {
        unset($_SESSION['qr_token']);
        MP::cookie('user', $user, time() + (86400 * 365));
        MP::cookie('code', '1', time() + (86400 * 365));
    }
    header('Location: chats.php');
    die;
}
$qrtext = $qr->{'link'};
$_SESSION['qr_token'] = base64_encode($qrtext);
htmlStart();
echo '<div class="auth-qr-box"><div class="auth-qr-frame"><img src="qrcode.php" alt="QR code"><span class="auth-qr-logo">'.auth_logo_html('auth-logo-tiny').'</span></div></div>';
echo auth_heading(auth_text('Log in to Telegram by QR Code', 'Вход в Telegram по QR-коду'), auth_text('Open Telegram on your phone, go to Settings > Devices, and scan this code.', 'Откройте Telegram на телефоне, перейдите в Настройки > Устройства и отсканируйте код.'));
echo '<ol class="auth-qr-steps"><li>'.MP::x(auth_text('Open Telegram on your phone', 'Откройте Telegram на телефоне')).'</li><li>'.MP::x(auth_text('Go to', 'Перейдите в')).' <b>'.MP::x(auth_text('Settings', 'Настройки')).'</b> &gt; <b>'.MP::x(auth_text('Devices', 'Устройства')).'</b></li><li>'.MP::x(auth_text('Point your phone at this screen', 'Наведите телефон на этот экран')).'</li></ol>';
echo '<p><a class="btn auth-link-btn auth-delay-link" href="login.php">'.MP::x(auth_text('LOG IN BY PHONE NUMBER', 'ВОЙТИ ПО НОМЕРУ ТЕЛЕФОНА')).'</a></p>';
echo '<p class="auth-secondary"><a class="auth-delay-link" href="qrlogin.php?check'.($ipass !== null ? '&ipass='.MP::dehtml($ipass) : '').'">'.MP::x(auth_text('Check login', 'Проверить вход')).'</a></p>';
htmlEnd();

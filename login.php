<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

MP::startSession();

if (!defined('LOGIN_CAPTCHA')) define('LOGIN_CAPTCHA', true);

$theme = 0;
$ua = '';
$iev = MP::getIEVersion();
if ($iev > 0 && $iev < 4) $theme = 1;
$theme = MP::getSettingInt('theme', $theme, true);
$post = (isset($_SERVER['HTTP_USER_AGENT']) && !str_contains($_SERVER['HTTP_USER_AGENT'], 'Series60/3')) && ($iev < 4 && $iev == 0);

$lng = MP::initLocale();
//MP::cookie('theme', $theme, time() + (86400 * 365));

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

include 'themes.php';
Themes::setTheme($theme);
include 'authui.php';

$revoked = isset($_GET['revoked']);
$logout = false;
$wrong = isset($_GET['wrong']);

$user = null;
$nouser = true;

$phone = $_GET['phone'] ?? $_POST['phone'] ?? null;

$user = MP::getUser();

$ipass = $_GET['ipass'] ?? $_POST['ipass'] ?? null;

// Check session existance
$nouser = empty($user) || strlen($user) < 32 || strlen($user) > 200 || !file_exists(sessionspath.$user.'.madeline');
function removeSession($logout=false): void
{
    global $user;
    $_SESSION = [];
    MP::delcookie('user');
    MP::delcookie('code');
    MP::delcookie('PHPSESSID');
    try {
        // Remove all session files
        if (file_exists(sessionspath.$user.'.madeline')) {
            if ($logout) {
                try {
                    $MP = MP::getMadelineAPI($user, true);
                    $MP->logout();
                    unset($MP);
                } catch (Exception) {}
            }
            try {
                if (PHP_OS_FAMILY === "Linux") {
                    exec('kill -9 `ps -ef | grep -v grep | grep '.$user.'.madeline | awk \'{print $2}\'`');
                }
            } catch (Exception) {}
            MP::deleteSessionFile($user);
        }
    } catch (Exception $e) {
        echo $e;
    }
}

function htmlStart(): void
{
    if (defined('HTML_STARTED')) return;
    define('HTML_STARTED', 1);
    global $lng;
    header("Content-Type: text/html; charset=".MP::$enc);
    echo '<html><head><title>'.MP::x($lng['login']).'</title>';
    echo Themes::head();
    echo auth_loading_script();
    // определение часового пояса
    $iev = MP::getIEVersion();
    if ($iev == 0 || $iev > 4) {
        $dtz = new DateTimeZone(date_default_timezone_get());
        $t = new DateTime('now', $dtz);
        $tof = $dtz->getOffset($t);
        echo '<script type="text/javascript"><!--
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
    echo Themes::bodyStart('class="auth-page"');
    echo '<div class="auth-shell"><div class="auth-card">';
    echo auth_logo_html();
}

function htmlEnd(): void
{
    echo '</div></div>';
    echo Themes::bodyEnd();
}

if (isset($_GET['logout']) || $revoked || $wrong) {
    $logout = true;
    $nouser = true;
    removeSession(($_GET['logout'] ?? '') == '2' && !$nouser);
    $user = null;
}

$MP = null;
if ($user != null && !$logout && !$nouser) {
    // Already logged in
    if (isset($_COOKIE['code']) && !empty($_COOKIE['code'])) {
        header('Location: chats.php');
        die;
    } else {
        $MP = MP::getMadelineAPI($user, true);
        if ($MP->getAuthorization() === 3) {
            MP::cookie('code', '1', time() + (86400 * 365));
            header('Location: chats.php');
            die;
        }
        if ($phone === null) {
            unset($MP);
            removeSession();
        }
    }
}
if (defined('INSTANCE_PASSWORD') && INSTANCE_PASSWORD !== null) {
    if ($ipass === null || $ipass != INSTANCE_PASSWORD) {
        htmlStart();
        echo auth_heading('MPGram S', auth_text('Enter the instance password to continue.', 'Введите пароль экземпляра, чтобы продолжить.'));
        echo '<form class="auth-submit-form" action="login.php"';
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
if ($phone !== null) {
    $p = $phone;
    // PATCHED: lowered length threshold from 10 → 5 so Fragment (+888…) and short
    // test numbers (+42…) reach the server's validator instead of being blocked
    // client-side as "Wrong number format".
    if (empty($p) || strlen($p) < 5 || !is_numeric(str_replace('-','',str_replace('+','', $p)))) {
        header('Location: login.php?wrong=number');
        die;
    }
    if (!isset($_SESSION['captcha_entered']) && LOGIN_CAPTCHA) {
        if (!isset($_POST['c']) && !isset($_GET['c'])) {
            htmlStart();
            echo auth_heading('CAPTCHA', auth_text('Enter the characters from the picture.', 'Введите символы с картинки.'));
            echo '<p class="auth-captcha"><img src="captcha.php?r='.time().'"/></p>';
            echo '<form class="auth-submit-form" action="login.php"';
            if ($post) echo ' method="post"';
            echo '>';
            if (isset($_GET['code']))
                echo "<input type=\"hidden\" name=\"code\" value=\"".MP::dehtml($_GET['code'])."\">";
            elseif (isset($_POST['code']))
                echo "<input type=\"hidden\" name=\"code\" value=\"".MP::dehtml($_POST['code'])."\">";
            if ($phone !== null)
                echo "<input type=\"hidden\" name=\"phone\" value=\"".MP::dehtml($phone)."\">";
            if ($ipass !== null)
                echo "<input type=\"hidden\" name=\"ipass\" value=\"".MP::dehtml($ipass)."\">";
            echo auth_field('CAPTCHA', 'c', '', 'text', 'autocomplete="off"');
            echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
            echo '</form>';
            echo MP::x('<a href="login.php?logout=2">'.$lng['logout'].'</a>');
            htmlEnd();
            die;
        } else {
            $c = null;
            if (isset($_POST['c'])) {
                $c = $_POST['c'];
            } elseif (isset($_GET['c'])) {
                $c = $_GET['c'];
            }
            $b = isset($_SESSION['captcha']);
            if (!$b || strtolower($c) !== $_SESSION['captcha']) {
                htmlStart();
                if ($b) unset($_SESSION['captcha']);
                echo auth_heading('CAPTCHA', auth_text('Enter the characters from the picture.', 'Введите символы с картинки.'));
                echo '<p class="auth-captcha"><img src="captcha.php"></p>';
                echo '<form class="auth-submit-form" action="login.php"';
                if ($post) echo ' method="post"';
                echo '>';
                if (isset($_GET['code']))
                    echo "<input type=\"hidden\" name=\"code\" value=\"".MP::dehtml($_GET['code'])."\">";
                elseif (isset($_POST['code']))
                    echo "<input type=\"hidden\" name=\"code\" value=\"".MP::dehtml($_POST['code'])."\">";
                if ($phone !== null)
                    echo "<input type=\"hidden\" name=\"phone\" value=\"".MP::dehtml($phone)."\">";
                if ($ipass !== null)
                    echo "<input type=\"hidden\" name=\"ipass\" value=\"".MP::dehtml($ipass)."\">";
                echo auth_field('CAPTCHA', 'c', '', 'text', 'autocomplete="off"');
                echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
                echo '</form>';
                if ($b) echo '<b>'.MP::x($lng['wrong_captcha']).'</b>';
                htmlEnd();
                die;
            }
            $_SESSION['captcha_entered'] = 1;
        }
    }
    if (!isset($user) || $nouser) {
        $_SESSION['user'] = $user = rtrim(strtr(base64_encode(hash('sha384', sha1(md5($phone.rand(0,1000).random_bytes(6))).random_bytes(30), true)), '+/', '-_'), '=');
        MP::cookie('user', $user, time() + (86400 * 365));
        $MP = MP::getMadelineAPI($user, true);
    } else {
        if (isset($_COOKIE['code']) && !empty($_COOKIE['code'])) {
            unset($_SESSION['captcha_entered']);
            header('Location: chats.php');
            die;
        } elseif (isset($_POST['pass']) || isset($_GET['pass'])) {
            // PATCHED for mpgram-web: reuse $MP if already created (avoid double-instance flock contention)
            if (!isset($MP) || !$MP) { $MP = MP::getMadelineAPI($user, true); }
            try {
                $password = $_POST['pass'] ?? $_GET['pass'] ?? null;
                $MP->complete2faLogin($password);
                MP::cookie('code', '1', time() + (86400 * 365));
                header('Location: chats.php');
                die;
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'PASSWORD_HASH_INVALID')) {
                    htmlStart();
                    echo auth_heading($lng['pass_code'], auth_text('Enter your Telegram password.', 'Введите пароль Telegram.'));
                    echo '<form class="auth-submit-form" action="login.php"';
                    if ($post) echo ' method="post"';
                    echo '>';
                    echo auth_field($lng['pass_code'], 'pass', '', 'password', 'autocomplete="current-password"');
                    echo auth_hidden($phone, $ipass);
                    echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
                    echo '</form>';
                    echo '<b>'.MP::x($lng['password_hash_invalid']).'</b><br>';
                    htmlEnd();
                    die;
                } elseif (str_contains($e->getMessage(), 'AUTH_RESTART')/* || strpos($e->getMessage(), 'I\'m not waiting') !== false*/) {
                } else {
                    echo '<xmp>';
                    echo $e;
                    echo '</xmp>';
                    die;
                }
            }
        } elseif (isset($_POST['first']) || isset($_GET['first'])) {
            // Account registration — server requires signup for this phone.
            $first = trim($_POST['first'] ?? $_GET['first'] ?? '');
            $last  = trim($_POST['last']  ?? $_GET['last']  ?? '');
            try {
                if (!isset($MP) || !$MP) { $MP = MP::getMadelineAPI($user, true); }
                if ($first === '') { throw new Exception('FIRSTNAME_REQUIRED'); }
                $MP->completeSignup($first, $last);
                MP::cookie('code', '1', time() + (86400 * 365));
                header('Location: chats.php');
                die;
            } catch (Exception $e) {
                htmlStart();
                $errKey = 'signup_error';
                if (str_contains($e->getMessage(), 'FIRSTNAME_REQUIRED') || str_contains($e->getMessage(), 'FIRSTNAME_INVALID')) {
                    $errKey = 'signup_firstname_invalid';
                } elseif (str_contains($e->getMessage(), 'LASTNAME_INVALID')) {
                    $errKey = 'signup_lastname_invalid';
                }
                echo auth_heading($lng['signup_title'] ?? 'Create your account', auth_text('Enter your name to finish creating this Telegram account.', 'Введите имя, чтобы завершить создание аккаунта Telegram.'));
                echo '<form class="auth-submit-form" action="login.php"' . ($post ? ' method="post"' : '') . '>';
                echo auth_field($lng['signup_first_name'] ?? 'First name', 'first', $first, 'text', 'autocomplete="given-name"');
                echo auth_field(($lng['signup_last_name'] ?? 'Last name') . ' (' . ($lng['signup_optional'] ?? 'optional') . ')', 'last', $last, 'text', 'autocomplete="family-name"');
                echo auth_hidden($phone, $ipass);
                echo auth_submit($lng['signup_create'] ?? 'Create account', auth_text('Creating', 'Создаем'));
                echo '</form>';
                echo '<b>' . MP::x($lng[$errKey] ?? $e->getMessage()) . '</b>';
                htmlEnd();
                die;
            }
        } elseif (isset($_POST['code']) || isset($_GET['code'])) {
            $code = $_POST['code'] ?? $_GET['code'] ?? null;
            if (!empty($code) && is_numeric($code)) {
                try {
                    // PATCHED for mpgram-web: reuse the $MP created at line ~124 instead of
                    // creating a second instance — two API objects on the same session at
                    // once cause an indefinite flock wait → "Could not connect to MadelineProto".
                    if (!isset($MP) || !$MP) { $MP = MP::getMadelineAPI($user, true); }
                    $a = $MP->completePhoneLogin($code);
                    $hash = null;
                    if (isset($a['phone_code_hash'])) {
                        $hash = $a['phone_code_hash'];
                    }
                    if (isset($a['_']) && $a['_'] === 'account.noPassword') {
                        MP::flushMadelineSession($MP);
                        htmlStart();
                        echo '<b>'.MP::x($lng['no_pass_code']).'</b>';
                        htmlEnd();
                        die;
                    } elseif (isset($a['_']) && $a['_'] === 'account.password') {
                        MP::flushMadelineSession($MP);
                        htmlStart();
                        echo auth_heading($lng['pass_code'], auth_text('Enter your Telegram password.', 'Введите пароль Telegram.'));
                        echo '<form class="auth-submit-form" action="login.php"';
                        if ($post) echo ' method="post"';
                        echo '>';
                        echo auth_field($lng['pass_code'], 'pass', '', 'password', 'autocomplete="current-password"');
                        echo auth_hidden($phone, $ipass);
                        echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
                        echo '</form>';
                        htmlEnd();
                        die;
                    } elseif (isset($a['_']) && $a['_'] === 'account.needSignup') {
                        // New account: render the signup form so completeSignup() can be called.
                        MP::flushMadelineSession($MP);
                        htmlStart();
                        echo auth_heading($lng['signup_title'] ?? 'Create your account', $lng['need_signup']);
                        echo '<form class="auth-submit-form" action="login.php"' . ($post ? ' method="post"' : '') . '>';
                        echo auth_field($lng['signup_first_name'] ?? 'First name', 'first', '', 'text', 'required autocomplete="given-name"');
                        echo auth_field(($lng['signup_last_name'] ?? 'Last name') . ' (' . ($lng['signup_optional'] ?? 'optional') . ')', 'last', '', 'text', 'autocomplete="family-name"');
                        echo auth_hidden($phone, $ipass);
                        echo auth_submit($lng['signup_create'] ?? 'Create account', auth_text('Creating', 'Создаем'));
                        echo '</form>';
                        htmlEnd();
                        die;
                    } else {
                        MP::cookie('code', '1', time() + (86400 * 365));
                        header('Location: chats.php');
                        die;
                    }
                } catch (Exception $e) {
                    htmlStart();
                    if (str_contains($e->getMessage(), 'PHONE_CODE_INVALID')) {
                        try {
                            $MP->phoneLogin($phone);
                            MP::flushMadelineSession($MP);
                        } catch (Exception) {}
                        echo '<b>'.MP::x($lng['phone_code_invalid']).'</b><br>';
                    } elseif (str_contains($e->getMessage(), 'PHONE_CODE_EXPIRED')) {
                        try {
                            $MP->phoneLogin($phone);
                            MP::flushMadelineSession($MP);
                        } catch (Exception) {}
                        echo '<b>'.MP::x($lng['phone_code_expired']).'</b><br>';
                    } elseif (str_contains($e->getMessage(), 'not waiting') || str_contains($e->getMessage(), 'не ожидаю код')) {
                        try {
                            $MP->phoneLogin($phone);
                            MP::flushMadelineSession($MP);
                        } catch (Exception) {}
                        echo '<b>'.MP::x($lng['phone_code_expired']).'</b><br>';
                    } elseif (str_contains($e->getMessage(), 'AUTH_RESTART')) {
                        unset($hash);
                    } else {
                        echo '<b>'.MP::x($lng['error']).'</b><br>';
                        echo $e->getMessage();
                        htmlEnd();
                        die;
                    }
                }
            } else {
                echo auth_heading($lng['phone_code'], auth_text('Enter the code sent in Telegram.', 'Введите код, который пришел в Telegram.'));
                echo '<form class="auth-submit-form" action="login.php"';
                if ($post) echo ' method="post"';
                echo '>';
                echo auth_field($lng['phone_code'], 'code', '', 'text', 'inputmode="numeric" autocomplete="one-time-code"');
                echo auth_hidden($phone, $ipass);
                echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
                echo '</form>';
                htmlEnd();
                die;
            }
        } else {
            // PATCHED for mpgram-web: reuse $MP if already created (avoid double-instance flock contention)
            if (!isset($MP) || !$MP) { $MP = MP::getMadelineAPI($user, true); }
            htmlStart();
        }
    }
    // ввод кода
    if (isset($hash)) {
        try {
            $MP->auth->resendCode(phone_number: $phone, phone_code_hash: $hash);
            MP::flushMadelineSession($MP);
        } catch (Exception $e) {
            htmlStart();
            echo $e->getMessage();
            htmlEnd();
            die;
        }
    } else {
        try {
            $MP->phoneLogin($phone);
            MP::flushMadelineSession($MP);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'PHONE_NUMBER_INVALID')) {
                header('Location: login.php?wrong=number');
                die;
            } else {
                htmlStart();
                echo $e->getMessage();
                htmlEnd();
                die;
            }
        }
    }
    htmlStart();
    echo auth_heading($lng['phone_code'], auth_text('Enter the code sent in Telegram.', 'Введите код, который пришел в Telegram.'));
    if ($phone !== null) echo '<p class="auth-phone-pill">'.MP::dehtml($phone).'</p>';
    echo '<form class="auth-submit-form" action="login.php"';
    if ($post) echo ' method="post"';
    echo '>';
    echo auth_field($lng['phone_code'], 'code', '', 'text', 'inputmode="numeric" autocomplete="one-time-code" autofocus');
    echo auth_hidden($phone, $ipass);
    echo auth_submit(auth_text('Continue', 'Продолжить'), auth_text('Checking', 'Проверяем'));
    echo '</form>';
    echo '<p class="auth-secondary"><a href="login.php?logout=1">'.MP::x($lng['back'] ?? 'Back').'</a></p>';
    htmlEnd();
} else {
    // ввод телефона
    htmlStart();
    //if ($revoked) {
    //    echo MP::x('<b>Ваша сессия истекла!</b><br>');
    //}
    echo auth_heading(auth_text('Sign in to Telegram', 'Вход в Telegram'), auth_text('Please confirm your country code and enter your phone number.', 'Проверьте код страны и введите номер телефона.'));
    echo '<form class="auth-submit-form" action="login.php"';
    if ($post) echo ' method="post"';
    echo '>';
    echo auth_phone_picker($lng['phone_number'], '+42333');
    echo auth_submit(auth_text('NEXT', 'ДАЛЕЕ'), auth_text('Sending', 'Отправляем'));
    if ($ipass !== null)
        echo '<input type="hidden" name="ipass" value="'.MP::dehtml($ipass).'">';
    echo '</form>';
    if ($wrong) {
        echo '<b>'.MP::x($lng['wrong_number_format']).'</b><br>';
    } else {
        echo '<p class="auth-note">'.MP::x($lng['login_hint'] ?? 'Use your Telegram phone number with country code.').'</p>';
        echo '<p><a class="btn auth-link-btn auth-delay-link" href="qrlogin.php?load=1">'.MP::x($lng['qr_login']).'</a></p>';
    }
    echo '<div class="auth-footer">';
    echo '<a href="about.php">'.MP::x($lng['about']).'</a> <a href="login.php?lang=en">English</a> <a href="login.php?lang=ru">'.MP::x('Русский').'</a>';
    //echo ' <a href="sets.php">'.$lng['settings'].'</a>';
    echo '</div>';
    htmlEnd();
}

<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
if (!defined('mp_loaded'))
require_once 'mp.php';
class Themes {
    static $theme = 0;
    static $bg = 0;
    static $fillMsg = 0;
    static $round = 1;
    static $bgsize = 240;
    static $iev;
    static $colors = [];
    static $fillChats = 0;
    static $chat;
    
    static function loadColors($theme): bool
    {
        $file = './colors/colors_'.$theme.'.json';
        if (!file_exists($file)) {
            return false;
        }
        $file = file_get_contents($file);
        if (!$file) {
            return false;
        }
        $json = json_decode($file, true);
        if (!$json) {
            return false;
        }
        foreach ($json as $k => $v) {
            static::$colors[$k] = $v;
        }
        static::$fillMsg = static::$fillMsg || ($json['fill_messages'] ?? 0);
        static::$fillChats = static::$fillChats || ($json['fill_chats'] ?? 0);
        static::$bg = static::$bg || ($json['force_background_image'] ?? 0);
        return true;
    }
    
    static function color($d)
    {
        if (static::$theme == 4) {
            $a = dechex(rand(0,0xfff));
            while (strlen($a) < 3) $a = '0'.$a;
            return "#{$a}";
        }
        if (str_starts_with($d, '!')) {
            return static::$colors[substr($d, 1)];
        }
        return $d;
    }
    
    static function setTheme($theme, $chat=false): void
    {
        switch ($theme) {
        case 2:
            $theme = 1;
            static::$bg = $chat;
            break;
        case 3:
            $theme = 0;
            static::$bg = $chat;
            break;
        case 4:
            $theme = 4;
            static::$fillMsg = 1;
            break;
        case 5:
            $theme = 1;
            static::$bg = 1;
            break;
        case 7:
            $theme = 0;
            static::$fillMsg = 1;
            static::$fillChats = 1;
            break;
        case 8:
            $theme = 1;
            static::$fillMsg = 1;
            break;
        }
        $bgsize = MP::getSettingInt('bgsize', 0);
        switch ($bgsize) {
        case 240:
        case 320:
        case 640:
        case 720:
        case 1000:
            static::$bgsize = $bgsize;
            break;
        default:
            static::$bgsize = '';
            break;
        }
        static::$chat = $chat;
        static::$theme = $theme;
        if ($theme != 0) static::loadColors(0);
        static::loadColors($theme);
    }
    
    static function setChatTheme($theme): void
    {
        static::setTheme($theme, true);
    }
    
    static function bodyStart($a = null): string
    {
        if ($a) {
            return '<body '.$a.'>'.(static::$iev > 0 ? '<div class="bc">' : '');
        }
        return '<body>'.(static::$iev > 0 ? '<div class="bc">' : '');
    }
    
    static function bodyEnd(): string
    {
        return (static::$iev > 0 ? '</div>' : '').'</body></html>';
    }

    static function chatBackgroundFile($user = null): ?string
    {
        if ($user === null) $user = MP::getUser();
        if (!$user) return null;
        return __DIR__.'/cache/backgrounds/'.sha1($user).'.jpg';
    }

    static function chatBackgroundUrl($user = null): ?string
    {
        $file = static::chatBackgroundFile($user);
        if (!$file || !file_exists($file)) return null;
        return 'cache/backgrounds/'.basename($file);
    }

    /**
     * Tiny inline script that marks the body as "embedded" when the page is loaded
     * inside an iframe. CSS uses `body.embedded` to hide the appbar (the modal X
     * close button is enough). Works on Lumia/Edge 15 (no Promises, no arrow fns).
     */
    static function iframeDetectScript(): string
    {
        return '<script type="text/javascript"><!--
try { if (window.self !== window.top) { var b=document.body || document.documentElement; b.className=(b.className||"")+" embedded"; } } catch (e) {}
//--></script>';
    }

    /**
     * Render the global top app bar (Webogram steel-blue).
     *  - Pass $title === null for the main chats.php style (brand + Contacts/Settings/About icons)
     *  - Pass a non-null $title for an inner page (back arrow + title)
     */
    static function appbar(?string $title = null, string $backUrl = 'chats.php', ?string $brand = 'MPGram S Web'): string
    {
        $lng = MP::initLocale();
        // Inline SVG icons (24x24, stroke-current so they pick up the appbar text color)
        $svgBack     = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>';
        $svgContacts = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
        $svgSettings = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
        $svgInfo     = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

        $out = '<div class="appbar">';
        if ($title !== null) {
            $out .= '<a class="appbar-back" href="'.htmlspecialchars($backUrl).'">'.$svgBack.'<span class="lbl">'.MP::x($lng['back']).'</span></a>';
            $out .= '<span class="appbar-brand">'.MP::dehtml($title).'</span>';
        } else {
            $out .= '<span class="appbar-brand">';
            $out .= '<span class="brand-full">'.MP::dehtml($brand).'</span>';
            $out .= '<span class="brand-short">MPGram S</span>';
            $out .= '</span>';
            $out .= '<span class="appbar-right">';
            $out .= '<a href="contacts.php" title="'.MP::x($lng['contacts']).'"><span class="ic">'.$svgContacts.'</span><span class="lbl">'.MP::x($lng['contacts']).'</span></a>';
            $out .= '<a href="sets.php" title="'.MP::x($lng['settings']).'"><span class="ic">'.$svgSettings.'</span><span class="lbl">'.MP::x($lng['settings']).'</span></a>';
            $out .= '<a href="about.php" title="'.MP::x($lng['about']).'"><span class="ic">'.$svgInfo.'</span><span class="lbl">'.MP::x($lng['about']).'</span></a>';
            $out .= '</span>';
        }
        $out .= '</div>';
        return $out;
    }
    
    static function head(): string
    {
        $nocss = MP::getSettingInt('nocss', 0, true) == 1;
        if ($nocss) {
            return (MP::$enc == null ? '<meta charset="UTF-8">' : '').
            '<meta name="viewport" content="width=device-width, initial-scale=1">';
        }
        static::$iev = MP::getIEVersion();
        $full = MP::getSettingInt('full', 0, true) == 1;
        // ovl.css = MPGram S Web UI kit (Bootstrap-inspired, Lumia-safe)
        $ovlCss = '';
        if (file_exists(__DIR__ . '/ovl.css')) {
            $ovlCss = "\n        ".file_get_contents(__DIR__ . '/ovl.css');
        }
        $chatBgUrl = static::chatBackgroundUrl();
        $chatBgEnabled = MP::getSettingInt('chatbg', 0, true) == 1 && $chatBgUrl;
        $chatBgBlur = MP::getSettingInt('chatbgblur', 0, true) == 1;
        $chatBgDark = max(0, min(85, MP::getSettingInt('chatbgdark', 45, true)));
        $chatBgCss = '';
        if ($chatBgEnabled) {
            $chatBgUrlCss = str_replace(['\\', "'", ')'], ['/', "\\'", '\\)'], $chatBgUrl);
            $overlay = 'rgba(0,0,0,'.($chatBgDark / 100).')';
            $blurCss = $chatBgBlur ? '
	        .app-shell:before,
	        body.chat-page:before {
	            content: "";
	            position: fixed;
            left: -14px;
            top: -14px;
            right: -14px;
            bottom: -14px;
	            background-image: linear-gradient('.$overlay.', '.$overlay.'), url(\''.$chatBgUrlCss.'\');
	            background-size: cover;
	            background-position: center center;
	            background-repeat: no-repeat;
	            filter: blur(10px);
	            -webkit-filter: blur(10px);
	            z-index: 0;
            pointer-events: none;
        }
        .app-shell > *,
        body.chat-page > * {
            position: relative;
	            z-index: 1;
	        }
	        .app-shell > .chat-open-loader,
	        body.chat-page > .chat-open-loader {
	            position: fixed !important;
	            z-index: 1400 !important;
	        }' : '';
	            $chatBgCss = '
	        .app-shell,
	        body.chat-page {
	            background-image: linear-gradient('.$overlay.', '.$overlay.'), url(\''.$chatBgUrlCss.'\') !important;
	            background-size: cover !important;
	            background-position: center center !important;
	            background-repeat: no-repeat !important;
	            background-attachment: scroll !important;
	        }
	        .app-shell,
	        body.chat-page {
	            position: relative;
	            overflow: hidden;
	        }
	        body.chat-page.embedded {
	            background: transparent !important;
	            background-image: none !important;
	        }
	        body.chat-page.embedded:before {
	            display: none !important;
	        }
	        .app-shell .app-sidebar,
	        .app-shell .app-main,
	        .app-shell #chats-list,
	        .app-shell table.cl {
	            background: transparent !important;
	            background-image: none !important;
	        }
	        body.chat-page #msgs {
	            background-color: rgba(0,0,0,0.24) !important;
	            background-image: none !important;
	        }
	        body.chat-page.embedded #msgs {
	            background-color: rgba(0,0,0,0.18) !important;
	        }
	        .chat-open-loader {
	            position: fixed !important;
	            z-index: 1400 !important;
	        }'.$blurCss;
	        }
	        $themeFixCss = '
	        html {
	            background: '.static::color('!background').' !important;
	        }
	        html,
	        body.settings-page,
	        body.settings-page.embedded {
	            min-width: 100%;
	            min-height: 100vh;
	            background: '.static::color('!background').' !important;
	            background-image: none !important;
	            color: '.static::color('!foreground').' !important;
	        }
	        .settings-page:before,
	        .settings-page:after {
	            display: none !important;
	            content: none !important;
	        }
	        .settings-page .settings-viewport {
	            display: block;
	            min-height: 100vh;
	            background: '.static::color('!background').' !important;
	            color: '.static::color('!foreground').' !important;
	            box-sizing: border-box;
	            overflow: hidden;
	        }
	        .appbar,
	        .hed,
	        .settings-page .set-nav,
        body.chat-page header,
        body.chat-page header.ch,
        body.chat-page .ch,
        body.chat-page .in,
        body.chat-page div.in.cb {
            background: '.static::color('!chat_header_background').' !important;
            color: '.static::color('!foreground').' !important;
            border-color: '.static::color('!chat_list_border').' !important;
        }
	        .card,
	        .profile-preview,
	        .set-grid-card,
	        .modal-content,
	        .modal-iframe-bg,
	        .modal-iframe,
	        .chat-open-card.inline,
	        table.cl .c td {
	            background: '.static::color('!message_background').' !important;
            color: '.static::color('!foreground').' !important;
            border-color: '.static::color('!chat_list_border').' !important;
        }
        .app-shell .app-sidebar,
        #chats-list,
        table.cl {
            background: '.static::color('!chat_list_background').' !important;
            color: '.static::color('!chat_list_text').' !important;
        }
		        .app-shell .app-main,
	        body.chat-page #msgs {
            background-color: '.static::color('!background').' !important;
            color: '.static::color('!foreground').' !important;
        }
        input[type=text], input[type=password], input[type=email],
        input[type=tel], input[type=number], input[type=search], select, textarea {
            background-color: '.static::color('!textbox_background').' !important;
            color: '.static::color('!textbox_text').' !important;
            border-color: '.static::color('!textbox_border').' !important;
        }
        body .btn, body .bth, input[type=submit], input[type=button], button {
            background: '.static::color('!button_background').' !important;
            color: '.static::color('!button_text').' !important;
            border-color: '.static::color('!button_border').' !important;
        }
        .settings-toggle input:checked + .settings-toggle-ui,
        .segmented-option input:checked + span {
            background: '.static::color('!button_background').' !important;
            border-color: '.static::color('!button_border').' !important;
            color: '.static::color('!button_text').' !important;
        }
        .theme-choice input:checked + .theme-choice-card {
            border-color: '.static::color('!button_background').' !important;
            box-shadow: 0 0 0 2px '.static::color('!message_mentioned_background').' !important;
        }
        .theme-choice-card,
        .segmented-option span,
        .settings-toggle-ui,
        .settings-section {
            border-color: '.static::color('!chat_list_border').' !important;
        }
        .ct, table.cl .ctext a, table.cl .cm {
            color: '.static::color('!chat_list_text').' !important;
        }
        .ctt, table.cl .ctext a.ctt {
            color: '.static::color('!chat_list_time').' !important;
        }
        .cma, .ml, .mf, .mn {
            color: '.static::color('!message_link').' !important;
        }
        body.auth-page {
            background: #212121 !important;
            color: #f5f5f5 !important;
        }
        body.auth-page .auth-card {
            background: transparent !important;
            color: #f5f5f5 !important;
            border-color: transparent !important;
        }
        body.auth-page .auth-field span {
            background: #212121 !important;
            color: #998af0 !important;
        }
        body.auth-page input[type=text],
        body.auth-page input[type=password],
        body.auth-page select {
            background: transparent !important;
            color: #fff !important;
            border-color: #343434 !important;
        }
        body.auth-page input[type=text]:focus,
        body.auth-page input[type=password]:focus,
        body.auth-page select:focus {
            border-color: #8774e1 !important;
        }
        body.auth-page select option {
            background: #212121 !important;
            color: #fff !important;
        }
        body.auth-page .btn,
        body.auth-page input[type=submit] {
            background: #8774e1 !important;
            color: #fff !important;
            border-color: #8774e1 !important;
        }
        body.auth-page .auth-link-btn {
            background: transparent !important;
            color: #998af0 !important;
            border-color: transparent !important;
        }
        body.settings-page.embedded .set-nav,
        body.settings-page .set-nav {
            background: '.static::color('!chat_header_background').' !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }';
        return (MP::$enc == null ? '<meta charset="UTF-8">' : '').
        '<meta name="viewport" content="width=device-width, initial-scale=1">
        <style type="text/css"><!--'.$ovlCss.'
        '.(static::$iev > 0 ? '.bc {
            text-align: left;
            width: 420;
            margin-left: auto;
            margin-right: auto;
        }
        ' : ''). 'body {
            '.(static::$iev > 0 ? 'text-align: center;' : ($full ? (static::$chat ? '' : 'max-width: 540px;
            margin-right: auto;') : 'max-width: 540px;
            margin-left: auto;
            margin-right: auto;')).'
            font-family: sans-serif, system-ui;
            '.(static::$theme !== 1 ?
            'background: '.static::color('!background').';
            color: '.static::color('!foreground').';' : 'color: '.static::color('!foreground').';').'
            '.(static::$bg ?
            'background-attachment: fixed;
            background-image: url(/img/bg'.static::$bgsize.'.png);
            '.(static::$bgsize == 1000 ?
            'background-repeat: repeat;' : 
            'background-size: cover;
            background-repeat: no-repeat;') : '').'
        }
        a {
            color: '.static::color('!foreground').';'.'
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        input[type=text], select, textarea {
            '.(static::$theme != 1 ? 'background-color: '.static::color('!textbox_background').';
            color: '.static::color('!textbox_text').';
            border-color: '.static::color('!textbox_border').';
            ' : '').'border-style: solid;
        }
        .ct {
            margin-left: 2px;
            overflow: hidden;
            color: '.static::color('!chat_list_text').';
        }
        .m {
            margin-left: 2px;
            margin-bottom: 7px;
            width: 99%;
        }
        .mc {
            display: table;
            width: auto;
            overflow: hidden;
            '.(static::$round ?
            'border-radius: 6px;
            padding-top: 4px;
            padding-left: 6px;
            padding-bottom: 4px;
            padding-right: 4px;' :
            'padding-left: 4px;
            padding-top: 2px;
            padding-bottom: 4px;
            padding-right: 4px;
            ').'
        }
        .mca {
            width: auto;
            overflow: hidden;
            '.(static::$round ?
            'border-radius: 6px;
            padding-top: 4px;
            padding-left: 6px;
            padding-bottom: 4px;
            padding-right: 4px;' :
            'padding-left: 4px;
            padding-top: 2px;
            padding-bottom: 4px;
            padding-right: 4px;
            ').'
        }
        .my {
            margin-left: auto; 
            '.(static::$bg || static::$fillMsg ? 'background-color: ' : 'border: 1px solid ').
            static::color('!message_out_background').';
        }
        .mo {
            '.(static::$bg || static::$fillMsg ? 'background-color: ' : 'border: 1px solid ').
            static::color('!message_background').';
        }
        .mpc {
            '.(static::$bg || static::$fillMsg ? 'background-color: ' : 'border: 1px solid ').
            static::color('!message_background').';
        }
        .mpd {
            background-color: '.static::color('!message_mentioned_background').';
        }
        .r, .mw {
            display: block;
            text-align: left;
            border-left: 2px solid '.static::color('!message_attachment_border').';
            padding-left: 4px;
            margin-bottom: 2px;
            margin-top: 2px;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }
        .rn, .mwt {
            color: '.static::color('!message_attachment_title').';'.
            'overflow: hidden;
            max-width: 200px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .rt {
            overflow: hidden;
            max-width: 300px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .cl {
            border-spacing: 0;
            border-color: '.static::color('!chat_list_border').';
            border-collapse: collapse;
            width: 100%;
        }
        .c {
            min-height: 42px;
            margin: 0px;'
            .(static::$bg || static::$fillChats ? ('background: '.static::color('!chat_list_background').';') : '').'
        }
        .cm {
            color: '.static::color('!chat_list_time').';
            display: -webkit-box;
            text-overflow: ellipsis;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            max-height: 2.5em;
            line-height: 1.25em;
        }
        .ctt {
            color: '.static::color('!chat_list_time').';
        }
        .cava {
            vertical-align: top;
            padding-left: 2px;
            padding-top: 4px;
            padding-bottom: 4px;
            padding-right: 4px;
        }
        .ctext {
            vertical-align: top;
            width: 100%;
        }
        .cbd {
            border-bottom: 1px solid '.static::color('!chat_list_border').';
        }
        '.(static::$theme == 0 ? '' : '.mf, .mn {
            color: '.static::color('!message_link').';
        }').
        '.ma {
            text-align: center;
            margin-bottom: 10px;
        }
        .cma {
            color: '.static::color('!chat_list_action').';
        }
        .u {
            color: '.static::color('!message_options').';
        }
        .in {
            display: inline;
        }
        .inr {
            display: inline;
            float: right;
        }
        .unr {
            color: '.static::color('!chat_list_unread').';
        }
        input[type="file"] {
            color: '.static::color('!foreground').';
        }
        .ml {
            color: '.static::color('!message_link').';
        }
        .ch {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1;
            background: '.static::color('!chat_header_background').';
        }
        .cb {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 1;
            background: '.static::color('!chat_header_background').';
        }
        .chc {
            './*($full ? '' : 'max-width: 540px;
            margin-left: auto;
            margin-right: auto;
            ') .*/'padding-top: 2px;
        }
        .chr {
            float: right;
            text-align: right;
        }
        .ri {
            border-radius: 50%;
            height: 36px;
            width: 36px;
        }
        .chn {
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
            vertical-align: top;
        }
        .cin {
            text-overflow: ellipsis;
            overflow: hidden;
            vertical-align: top;
        }
        .cst {
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        .chava {
            display: inline;
            padding-left: 2px;
            margin-top: 4px;
            padding-right: 4px;
            float: left;
        }
        .mi {
            max-width: 50vw;
        }
        .mci {
            text-align: right;
        }
        textarea {
            resize: none;
        }
        .t {
            '.($full ?
            'display: inline;
            z-index: 1;
            position: fixed;
            left: 0;
            width: 100%;
            bottom: 0;
            background: '.static::color('!textbox_background').';
            height: 4em;
        ' : '').'}
        .rc {
            width: 100%;
        }
        .btn {
            background-color: '.static::color('!button_background').';
            color: '.static::color('!button_text').';
            padding: 1px;
            border: solid 1px '.static::color('!button_border').';
            width: 100%;
            display: block;
            text-align: center;
        }
        .btd {
            padding: 2px;
        }
        .cta {
            width: 100%;
            '.(static::$iev > 0 && static::$iev < 5 ? 'height: 48px;' : 'height: 2.7em;').'
        }
        .acv {
            padding-left: 2px;
            margin-top: 4px;
            padding-right: 4px;
            float: left;
            max-height: 36px;
        }
        .mm {
            display: inline;
            margin-right: 4px;
            margin-bottom: 4px;
            margin-top: 4px;
        }
        pre {
            display: inline;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .bth {
            border: 1px solid '.static::color('!logout_button_border').';
            padding: 0 2px 0 2px;
            border-radius: 4px;
            background: '.static::color('!logout_button_background').';
        }
        .ra {
            color: '.static::color('!red_text').';
        }
        .hb {
            margin: 2px 0 2px 0;
        }
        .hed {
            '.(static::$bg || static::$fillChats ? ('background: '.static::color('!chat_list_header_background').';') : '').'
        }
        .fs {
            '.(static::$fillChats?('color: '.static::color('!chat_list_selected_folder').';') : '') .'
        }

        /* ============================================================ */
        /* MPGram S Web — responsive + polite transitions            */
        /* All inside @media or simple selectors; safe on Lumia/Edge 15. */
        /* ============================================================ */

        /* polite transitions (additive — only color/background/opacity) */
        a, input[type=submit], input[type=button], button, .bth {
            transition: color 150ms ease, background-color 150ms ease, opacity 150ms ease;
        }
        a:active, input[type=submit]:active, button:active, .bth:active { opacity: 0.7; }

        /* body/page-level color transition — smooth theme switch */
        body, .hed, .cl, .c, .cbd {
            transition: background-color 250ms ease, color 250ms ease;
        }

        /* chat-row hover: subtle, doesn\'t add layout shift */
        .cl .c {
            transition: background-color 120ms ease;
        }
        .cl .c:hover {
            background-color: rgba(255,255,255,0.04);
        }

        /* message-bubble fade-in (CSS @keyframes works in Edge 12+, fine for Lumia) */
        @keyframes _ovl_msg_in {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .m {
            animation: _ovl_msg_in 220ms ease both;
        }
        @media (prefers-reduced-motion: reduce) {
            .m, body, .hed, .cl, .c, .cbd { transition: none; animation: none; }
        }

        /* mobile (Lumia, small phones) */
        @media (max-width: 600px) {
            body {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 5px !important;
                box-sizing: border-box;
            }
            .bc { width: 100% !important; max-width: 100% !important; }
            input[type=text], input[type=password], input[type=email], input[type=tel],
            input[type=number], input[type=search], select, textarea {
                width: 100%;
                max-width: 100%;
                font-size: 16px; /* prevents iOS auto-zoom */
                padding: 6px;
                box-sizing: border-box;
            }
            input[type=submit], input[type=button], button, .bth {
                padding: 8px 12px;
                min-height: 36px;
                font-size: 15px;
            }
            a {
                padding: 2px 0;
                display: inline-block;
            }
            h1 { font-size: 1.4em; margin: 8px 0; }
            h2 { font-size: 1.2em; margin: 6px 0; }
            h3 { font-size: 1.05em; margin: 4px 0; }
            .m  { width: 100%; }
            .mc, .mca { max-width: 90%; }
            .set-nav a, .set-nav b {
                display: inline-block;
                padding: 3px 6px;
                margin: 1px 0;
            }
        }

        /* desktop — more comfortable reading width */
        @media (min-width: 1024px) {
            body { max-width: 720px; }
        }
        @media (min-width: 1440px) {
            body { max-width: 820px; }
        }

        /* respect users who disabled motion */
        @media (prefers-reduced-motion: reduce) {
            a, input[type=submit], input[type=button], button, .bth {
                transition: none;
            }
        }

        '.$themeFixCss.'
        '.$chatBgCss.'
        --></style>';
    }
}

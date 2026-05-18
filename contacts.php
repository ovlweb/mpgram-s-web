<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$user = MP::getUser();
if (!$user) {
    header('Location: login.php?logout=1');
    die;
}

$count = 300;
if (isset($_GET['count'])) {
    $count = (int) $_GET['count'];
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');


header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

$avas = false;
try {
    $MP = MP::getMadelineAPI($user);
    echo '<html><head><title>'.MP::x($lng['contacts']).'</title>';
    echo Themes::head();
    echo '</head>';
    echo Themes::bodyStart('class="contacts-page"');
    echo Themes::iframeDetectScript();
    $backurl = 'chats.php';
    echo Themes::appbar($lng['contacts'] ?? 'Contacts', $backurl);
    echo '<div class="container" style="padding: 12px">';
    echo '<div class="contacts-search-wrap"><input type="search" id="contacts-search" placeholder="'.MP::x($lng['search']).'…" autocomplete="off"></div>';
    echo '<div id="contacts-list" class="contacts-list">';
    try {
        $r = $MP->contacts->getContacts();
        $c = 0;
        foreach ($r['contacts'] as $contact){
            $id = $contact['user_id'];
            $name = null;
            foreach ($r['users'] as $user) {
                if ($user['id'] == $id) {
                    if (isset($user['first_name'])) {
                        $name = trim($user['first_name']).(isset($user['last_name']) ? ' '.trim($user['last_name']) : '');
                    } elseif (isset($user['last_name'])) {
                        $name = trim($user['last_name']);
                    } else {
                        $name = 'Deleted Account';
                    }
                    if (isset($user['username'])) {
                        $name .= ' ('.$user['username'].')';
                    }
                    break;
                }
            }
            try {
                $n = ($c %2 ==0 ? '1': '0');
                echo '<div class="c'.$n.' contact-row" data-search="'.MP::dehtml($name.' '.$id).'">';
                echo '<a class="contact-link" href="chat.php?c='.$id.'">'.MP::dehtml($name).'</a>';
                echo '</div>';
            } catch (Exception $e) {
                echo "<xmp>$e</xmp>";
            }
            $c += 1;
        }
        unset($r);
    } catch (Exception $e) {
        echo '<b>'.MP::x($lng['error']).'!</b><br>';
        echo "<xmp>$e</xmp>";
    }
    echo '</div>';
    echo '</div>';
    echo '<script type="text/javascript"><!--
(function(){
  function textOf(el){return el.innerText||el.textContent||"";}
  function setupFilter(){
    var input=document.getElementById("contacts-search");
    if(!input)return;
    function run(){
      var q=(input.value||"").toLowerCase().replace(/^\\s+|\\s+$/g,"");
      var rows=document.getElementsByTagName("div"),i;
      for(i=0;i<rows.length;i++){
        if((" "+(rows[i].className||"")+" ").indexOf(" contact-row ")<0)continue;
        var hay=((rows[i].getAttribute("data-search")||"")+" "+textOf(rows[i])).toLowerCase();
        rows[i].style.display=(!q||hay.indexOf(q)>=0)?"block":"none";
      }
    }
    input.onkeyup=run; input.onsearch=run; input.oninput=run;
  }
  function setupOpen(){
    var links=document.getElementsByTagName("a"),i;
    for(i=0;i<links.length;i++){
      if((" "+(links[i].className||"")+" ").indexOf(" contact-link ")<0)continue;
      links[i].onclick=function(ev){
        ev=ev||window.event;
        var href=this.getAttribute("href"),name=textOf(this);
        try {
          if(window.parent&&window.parent!==window&&window.parent._ovlOpenUrl){
            if(ev&&ev.preventDefault)ev.preventDefault();else ev.returnValue=false;
            window.parent._ovlOpenUrl(href,name,null);
            return false;
          }
        } catch(e) {}
        return true;
      };
    }
  }
  if(document.addEventListener)document.addEventListener("DOMContentLoaded",function(){setupFilter();setupOpen();},false);
  else setTimeout(function(){setupFilter();setupOpen();},200);
})();
//--></script>';
    echo Themes::bodyEnd();
    unset($MP);
} catch (Exception $e) {
    echo '<b>'.$lng['error'].'!</b><br>';
    echo "<xmp>$e</xmp><br>";
}

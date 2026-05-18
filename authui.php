<?php
/*
Small shared auth UI helpers. Keep this dependency-light: login.php and
qrlogin.php both include it after mp.php/themes.php.
*/

function auth_logo_html(string $class = ''): string
{
    return '<div class="auth-logo '.$class.'" aria-hidden="true">'
        .'<svg viewBox="0 0 24 24" focusable="false">'
        .'<circle class="auth-logo-disc" cx="12" cy="12" r="10.8"/>'
        .'<g class="auth-logo-plane">'
        .'<path class="auth-logo-plane-body" d="M18.384 7.411c.224-1.24-.48-1.725-1.56-1.308L4.29 10.94c-1.204.469-1.19 1.142-.218 1.44l3.215 1.004 1.235 3.843c.15.412.075.574.514.574.333 0 .48-.152.665-.333l1.6-1.56 3.33 2.46c.615.34 1.056.164 1.21-.57l2.543-10.387z"/>'
        .'<path class="auth-logo-plane-cut" d="M7.8 13.2l7.7-4.85c.384-.233.737-.108.448.149l-6.6 5.96-.257 2.74L7.8 13.2z"/>'
        .'</g>'
        .'</svg>'
        .'</div>';
}

function auth_text(string $en, string $ru): string
{
    global $lng;
    return (($lng['lang'] ?? 'en') === 'ru') ? $ru : $en;
}

function auth_loading_script(): string
{
    return '<script type="text/javascript">
function ovlAuthParent(el, tag){
  tag=tag.toUpperCase();
  while(el&&el.tagName!==tag)el=el.parentNode;
  return el;
}
function ovlAuthBusy(el){
  var b=document.body;
  var f=el&&el.tagName==="FORM"?el:ovlAuthParent(el,"form");
  if(b&&(" "+(b.className||"")+" ").indexOf(" auth-busy ")<0)b.className=(b.className||"")+" auth-busy";
  if(f&&(" "+(f.className||"")+" ").indexOf(" auth-form-busy ")<0)f.className=(f.className||"")+" auth-form-busy";
  var controls=f?f.getElementsByTagName("input"):[];
  for(var i=0;i<controls.length;i++){
    if(controls[i].type==="submit"){
      controls[i].setAttribute("data-old-value",controls[i].value);
      controls[i].value=controls[i].getAttribute("data-loading")||"Loading";
    }
  }
  return true;
}
function ovlAuthCountryChange(sel){
  var id=sel.getAttribute("data-phone-target");
  var input=id?document.getElementById(id):null;
  if(!input)return;
  var old=sel.getAttribute("data-old-code")||"";
  var next=sel.options[sel.selectedIndex].getAttribute("data-code")||"";
  var value=input.value||"";
  var def=input.getAttribute("data-default-phone")||"";
  if(value===def){
    input.value=next;
    sel.setAttribute("data-old-code",next);
    try{input.focus();}catch(e){}
    return;
  }
  if(!value||value===old||value.indexOf(old)===0){
    input.value=next+value.substring(old.length);
  }
  sel.setAttribute("data-old-code",next);
  try{input.focus();}catch(e){}
}
function ovlAuthCountryForPhone(input){
  var id=input.getAttribute("data-country-target");
  var sel=id?document.getElementById(id):null;
  if(!sel||!sel.options)return;
  var value=input.value||"";
  var best=-1,bestLen=0,code="";
  for(var i=0;i<sel.options.length;i++){
    code=sel.options[i].getAttribute("data-code")||"";
    if(code&&value.indexOf(code)===0&&code.length>bestLen){
      best=i;
      bestLen=code.length;
    }
  }
  if(best>=0&&sel.selectedIndex!==best){
    sel.selectedIndex=best;
    sel.setAttribute("data-old-code",sel.options[best].getAttribute("data-code")||"");
  }
}
function ovlAuthWire(){
  var forms=document.getElementsByTagName("form");
  for(var i=0;i<forms.length;i++){
    if((" "+(forms[i].className||"")+" ").indexOf(" auth-submit-form ")>=0){
      forms[i].onsubmit=function(){return ovlAuthBusy(this);};
    }
  }
  var links=document.getElementsByTagName("a");
  for(var j=0;j<links.length;j++){
    if((" "+(links[j].className||"")+" ").indexOf(" auth-delay-link ")>=0){
      links[j].onclick=function(){ovlAuthBusy(this);return true;};
    }
  }
  var selects=document.getElementsByTagName("select");
  for(var k=0;k<selects.length;k++){
    if((" "+(selects[k].className||"")+" ").indexOf(" auth-country-select ")>=0){
      selects[k].onchange=function(){ovlAuthCountryChange(this);};
      if(selects[k].options&&selects[k].options.length){
        selects[k].setAttribute("data-old-code",selects[k].options[selects[k].selectedIndex].getAttribute("data-code")||"");
      }
    }
  }
  var inputs=document.getElementsByTagName("input");
  for(var m=0;m<inputs.length;m++){
    if(inputs[m].getAttribute("data-country-target")){
      inputs[m].onkeyup=function(){ovlAuthCountryForPhone(this);};
      inputs[m].oninput=function(){ovlAuthCountryForPhone(this);};
      inputs[m].onchange=function(){ovlAuthCountryForPhone(this);};
      ovlAuthCountryForPhone(inputs[m]);
    }
  }
}
window.ovlAuthBusy=ovlAuthBusy;
window.ovlAuthCountryChange=ovlAuthCountryChange;
window.ovlAuthCountryForPhone=ovlAuthCountryForPhone;
if(document.addEventListener)document.addEventListener("DOMContentLoaded",ovlAuthWire,false);
else setTimeout(ovlAuthWire,250);
</script>';
}

function auth_heading(string $title, string $subtitle = ''): string
{
    $out = '<h1 class="auth-title">'.MP::x($title).'</h1>';
    if ($subtitle !== '') {
        $out .= '<p class="auth-subtitle">'.MP::x($subtitle).'</p>';
    }
    return $out;
}

function auth_field(string $label, string $name, string $value = '', string $type = 'text', string $attrs = ''): string
{
    return '<label class="auth-field"><span>'.MP::x($label).'</span>'
        .'<input type="'.$type.'" name="'.$name.'" value="'.MP::dehtml($value).'" '.$attrs.'>'
        .'</label>';
}

function auth_phone_picker(string $label, string $value = '+42333'): string
{
    $countries = [
        ['Liechtenstein', 'Лихтенштейн', '+423'],
        ['Anonymous Number', 'Анонимный номер', '+888'],
        ['Argentina', 'Аргентина', '+54'],
        ['Armenia', 'Армения', '+374'],
        ['Australia', 'Австралия', '+61'],
        ['Austria', 'Австрия', '+43'],
        ['Azerbaijan', 'Азербайджан', '+994'],
        ['Belarus', 'Беларусь', '+375'],
        ['Belgium', 'Бельгия', '+32'],
        ['Brazil', 'Бразилия', '+55'],
        ['Bulgaria', 'Болгария', '+359'],
        ['Canada', 'Канада', '+1'],
        ['Chile', 'Чили', '+56'],
        ['China', 'Китай', '+86'],
        ['Colombia', 'Колумбия', '+57'],
        ['Czechia', 'Чехия', '+420'],
        ['Denmark', 'Дания', '+45'],
        ['Estonia', 'Эстония', '+372'],
        ['Finland', 'Финляндия', '+358'],
        ['Georgia', 'Грузия', '+995'],
        ['Greece', 'Греция', '+30'],
        ['Hong Kong', 'Гонконг', '+852'],
        ['India', 'Индия', '+91'],
        ['Indonesia', 'Индонезия', '+62'],
        ['Ireland', 'Ирландия', '+353'],
        ['Israel', 'Израиль', '+972'],
        ['Japan', 'Япония', '+81'],
        ['Kyrgyzstan', 'Кыргызстан', '+996'],
        ['Latvia', 'Латвия', '+371'],
        ['Lithuania', 'Литва', '+370'],
        ['Mexico', 'Мексика', '+52'],
        ['Moldova', 'Молдова', '+373'],
        ['Netherlands', 'Нидерланды', '+31'],
        ['Norway', 'Норвегия', '+47'],
        ['Poland', 'Польша', '+48'],
        ['Portugal', 'Португалия', '+351'],
        ['Romania', 'Румыния', '+40'],
        ['Serbia', 'Сербия', '+381'],
        ['Singapore', 'Сингапур', '+65'],
        ['South Korea', 'Южная Корея', '+82'],
        ['Sweden', 'Швеция', '+46'],
        ['Thailand', 'Таиланд', '+66'],
        ['Turkey', 'Турция', '+90'],
        ['United Arab Emirates', 'ОАЭ', '+971'],
        ['Russia', 'Россия', '+7'],
        ['United States', 'США', '+1'],
        ['Switzerland', 'Швейцария', '+41'],
        ['United Kingdom', 'Великобритания', '+44'],
        ['Germany', 'Германия', '+49'],
        ['France', 'Франция', '+33'],
        ['Italy', 'Италия', '+39'],
        ['Spain', 'Испания', '+34'],
        ['Kazakhstan', 'Казахстан', '+7'],
        ['Ukraine', 'Украина', '+380'],
        ['Uzbekistan', 'Узбекистан', '+998'],
        ['Vietnam', 'Вьетнам', '+84'],
    ];
    $selectedCode = '+423';
    foreach ($countries as $c) {
        if (strpos($value, $c[2]) === 0 && strlen($c[2]) >= strlen($selectedCode)) {
            $selectedCode = $c[2];
        }
    }
    $out = '<label class="auth-field auth-country-field"><span>'.MP::x(auth_text('Country', 'Страна')).'</span><select id="auth-country-select" class="auth-country-select" data-phone-target="auth-phone-input" data-old-code="'.$selectedCode.'">';
    foreach ($countries as $c) {
        $name = auth_text($c[0], $c[1]);
        $out .= '<option value="'.MP::dehtml($c[2]).'" data-code="'.MP::dehtml($c[2]).'"'.($selectedCode === $c[2] ? ' selected' : '').'>'.MP::x($name).' '.MP::x($c[2]).'</option>';
    }
    $out .= '</select></label>';
    $out .= '<label class="auth-field"><span>'.MP::x($label).'</span><input id="auth-phone-input" type="text" name="phone" value="'.MP::dehtml($value).'" data-default-phone="'.MP::dehtml($value).'" data-country-target="auth-country-select" inputmode="tel" autocomplete="tel" placeholder="+42333" autofocus></label>';
    return $out;
}

function auth_hidden(?string $phone, ?string $ipass): string
{
    $out = '';
    if ($phone !== null) $out .= '<input type="hidden" name="phone" value="'.MP::dehtml($phone).'">';
    if ($ipass !== null) $out .= '<input type="hidden" name="ipass" value="'.MP::dehtml($ipass).'">';
    return $out;
}

function auth_submit(string $label, string $loading = 'Loading'): string
{
    return '<input type="submit" class="btn btn-primary auth-submit" value="'.MP::x($label).'" data-loading="'.MP::x($loading).'">';
}

#ifndef INDEX_HTML_H
#define INDEX_HTML_H

const char DASHBOARD_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html><html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<meta name="theme-color" content="#0f172a">
<title>Tewe Smart Home</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;flex-direction:column;-webkit-tap-highlight-color:transparent}
header{position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:rgba(15,23,42,.8);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,0.1)}
.logo{font-size:1.2em;font-weight:800;background:linear-gradient(135deg,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.badge{font-size:.7em;padding:4px 10px;border-radius:20px;font-weight:600;text-transform:uppercase;border:1px solid transparent}
.b-off{background:rgba(239,68,68,0.15);color:#ef4444;border-color:rgba(239,68,68,0.3)}
.b-on{background:rgba(16,185,129,0.15);color:#10b981;border-color:rgba(16,185,129,0.3)}
.btn-icon{background:none;border:none;color:#94a3b8;font-size:1.2em;cursor:pointer;padding:8px}
.btn-icon:hover{color:#f1f5f9}
.tab{display:none;flex:1;flex-direction:column;align-items:center}
.tab.active{display:flex}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:16px;padding:20px;max-width:800px;width:100%}
.card{background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.05);border-radius:16px;padding:20px;display:flex;flex-direction:column;align-items:center;gap:12px;cursor:pointer;user-select:none;transition:all .3s ease;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)}
.card:active{transform:scale(.96)}
.card.on{background:rgba(16,185,129,0.05);border-color:rgba(16,185,129,0.3);box-shadow:0 10px 15px -3px rgba(16,185,129,0.1)}
.name{font-size:.9em;color:#e2e8f0;font-weight:500;text-align:center}
.sw{width:56px;height:30px;border-radius:15px;background:rgba(255,255,255,0.1);position:relative;transition:all .3s}
.sw.on{background:#10b981;box-shadow:0 0 15px rgba(16,185,129,0.4)}
.sw::after{content:'';width:24px;height:24px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;transition:transform .3s cubic-bezier(.34,1.56,.64,1);box-shadow:0 2px 4px rgba(0,0,0,.2)}
.sw.on::after{transform:translateX(26px)}
.status-text{font-size:0.75em;font-weight:700;color:#94a3b8;transition:color 0.3s}
.card.on .status-text{color:#10b981}
.form{width:100%;max-width:400px;padding:20px;background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.05);border-radius:16px;margin-top:20px}
.fg{margin-bottom:15px}
label{display:block;font-size:0.85em;color:#94a3b8;margin-bottom:6px;font-weight:500}
input,select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.2);color:#fff;font-size:1em;outline:none;transition:border-color 0.3s}
input:focus,select:focus{border-color:#10b981}
button.submit{width:100%;padding:12px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:8px;color:white;font-weight:600;font-size:1em;cursor:pointer;margin-top:10px;box-shadow:0 4px 12px rgba(16,185,129,0.3)}
button.submit:active{transform:scale(0.98)}
footer{display:flex;justify-content:space-between;padding:12px 20px;font-size:.7em;color:#64748b;border-top:1px solid rgba(255,255,255,0.05)}
.empty{grid-column:1/-1;text-align:center;padding:60px 0;color:#64748b;font-size:1.1em}
</style></head><body>
<header>
<span class="logo">TeweLocal</span>
<div style="display:flex;align-items:center;gap:12px">
<span class="badge b-off" id="sync">OFFLINE</span>
<button class="btn-icon" onclick="toggleTab()">⚙️</button>
<button class="btn-icon" style="color:#ef4444;font-size:1.1em" onclick="logout()" title="Logout">🚪</button>
</div>
</header>
<div id="dashboard" class="tab active"><div class="grid" id="g"><div class="empty">Connecting...</div></div></div>
<div id="settings" class="tab">
<div class="form">
<div class="fg"><label>WiFi SSID</label>
<div style="display:flex;gap:8px">
<input type="text" id="ssid" placeholder="Network Name">
<button class="submit" style="width:auto;margin:0;padding:0 15px" onclick="scan()">Scan</button>
</div>
<select id="ssids" style="display:none;margin-top:8px" onchange="document.getElementById('ssid').value=this.value"></select>
</div>
<div class="fg"><label>WiFi Password</label><input type="password" id="pass" placeholder="Password"></div>
<div class="fg"><label>API Base URL</label><input type="text" id="api" placeholder="https://..."></div>
<div class="fg"><label>Device Code</label><input type="text" id="dev" placeholder="DEV_..."></div>

<div style="color:#10b981;font-weight:600;margin:20px 0 10px;font-size:1.1em">Local Login</div>
<div class="fg"><label>New Username</label><input type="text" id="a_usr" placeholder="admin"></div>
<div class="fg"><label>New Password</label><input type="password" id="a_pwd" placeholder="(unchanged if empty)"></div>

<button class="submit" onclick="save()">Save & Restart</button>
</div>
</div>
<footer><span id="ft1">---</span><span id="ft2">---</span><span id="ft3">---</span></footer>
<script>
var ws,W=[],cf={};
function toggleTab(){
document.getElementById('dashboard').classList.toggle('active');
document.getElementById('settings').classList.toggle('active');
if(document.getElementById('settings').classList.contains('active')) loadCfg();
}
function logout(){
if(!confirm("Logout dari Local UI?")) return;
fetch('/api/logout',{method:'POST'}).then(()=>{window.location.reload()});
}
function cn(){var p=location.protocol==='https:'?'wss:':'ws:';ws=new WebSocket(p+'//'+location.host+'/ws');
ws.onmessage=function(e){var d=JSON.parse(e.data);
if(d.event==='init'){W=d.widgets||[];cf=d.config||{};
document.getElementById('sync').className='badge '+(d.internet?'b-on':'b-off');
document.getElementById('sync').textContent=d.mqtt?'SYNCED':d.internet?'ONLINE':'LOCAL';
document.getElementById('ft1').textContent='IP: '+(d.ip||'AP-only');
document.getElementById('ft2').textContent='Heap: '+(d.heap/1024).toFixed(1)+'KB';
document.getElementById('ft3').textContent='Up: '+Math.floor((d.uptime||0)/60)+'m';rn()}
else if(d.event==='state'){up(d.key,d.value)}};
ws.onclose=function(){setTimeout(cn,3000)};}
function rn(){var g=document.getElementById('g');g.innerHTML='';
if(!W.length){g.innerHTML='<div class="empty">No widgets</div>';return}
W.forEach(function(w){
if(w.type==='toggle'){
var on=w.value==='1'?' on':'';
g.innerHTML+=`<div class="card${on}" id="c-${w.key}" onclick="ws.send(JSON.stringify({action:'toggle',key:'${w.key}'}))">
<div class="name">${w.name}</div><div class="sw${on}" id="s-${w.key}"></div>
<div class="status-text" id="st-${w.key}">${w.value==='1'?'ON':'OFF'}</div></div>`;
}else{
g.innerHTML+=`<div class="card" id="c-${w.key}" style="cursor:default">
<div class="name">${w.name}</div>
<div style="font-size:1.6em;font-weight:700;color:#10b981;margin-top:10px" id="v-${w.key}">${w.value} <span style="font-size:0.5em;color:#94a3b8">${w.unit||''}</span></div>
</div>`;
}
})}
function up(k,v){
for(var i=0;i<W.length;i++)if(W[i].key===k)W[i].value=v;
var w=W.find(x=>x.key===k);if(!w)return;
if(w.type==='toggle'){
var c=document.getElementById('c-'+k),s=document.getElementById('s-'+k),t=document.getElementById('st-'+k);
if(c){c.className='card'+(v==='1'?' on':'');s.className='sw'+(v==='1'?' on':'');t.textContent=v==='1'?'ON':'OFF';}
}else{
var e=document.getElementById('v-'+k);
if(e)e.innerHTML=v+' <span style="font-size:0.5em;color:#94a3b8">'+(w.unit||'')+'</span>';
}
}
function loadCfg(){
document.getElementById('ssid').value=cf.ssid||'';
document.getElementById('api').value=cf.api||'';
document.getElementById('dev').value=cf.dev||'';
document.getElementById('a_usr').value=cf.auth_user||'admin';
}
function scan(){
var s=document.getElementById('ssids');s.innerHTML='<option>Scanning...</option>';s.style.display='block';
fetch('/api/scan').then(r=>r.json()).then(d=>{
s.innerHTML='<option value="">Select Network</option>';
d.forEach(n=>s.innerHTML+=`<option value="${n.s}">${n.s} (${n.r}dBm)</option>`);
});
}
function save(){
var d=new URLSearchParams();d.append('ssid',document.getElementById('ssid').value);
d.append('pass',document.getElementById('pass').value);
d.append('api',document.getElementById('api').value);
d.append('dev',document.getElementById('dev').value);
d.append('a_usr',document.getElementById('a_usr').value);
d.append('a_pwd',document.getElementById('a_pwd').value);
fetch('/api/config',{method:'POST',body:d}).then(()=>{alert('Saved! ESP will restart.');location.reload();});
}
cn();
</script></body></html>
)rawliteral";

#endif

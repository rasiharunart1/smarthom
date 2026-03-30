#ifndef INDEX_HTML_H
#define INDEX_HTML_H

const char DASHBOARD_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html><html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<meta name="theme-color" content="#0f172a">
<title>TeweLocal</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;flex-direction:column;-webkit-tap-highlight-color:transparent}
header{position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:rgba(15,23,42,.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,0.08)}
.logo{font-size:1.2em;font-weight:800;background:linear-gradient(135deg,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.badge{font-size:.7em;padding:4px 10px;border-radius:20px;font-weight:600;text-transform:uppercase;border:1px solid transparent}
.b-off{background:rgba(239,68,68,0.15);color:#ef4444;border-color:rgba(239,68,68,0.3)}
.b-on{background:rgba(16,185,129,0.15);color:#10b981;border-color:rgba(16,185,129,0.3)}
.btn-icon{background:none;border:none;color:#94a3b8;font-size:1.2em;cursor:pointer;padding:8px;line-height:1}
.btn-icon:hover{color:#f1f5f9}
.tab{display:none;flex:1;flex-direction:column;align-items:center}
.tab.active{display:flex}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:16px;padding:20px;max-width:800px;width:100%}
.card{background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.05);border-radius:16px;padding:16px;display:flex;flex-direction:column;align-items:center;gap:10px;user-select:none;transition:all .3s ease;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);position:relative}
.card.toggle-card{cursor:pointer}
.card.toggle-card:active{transform:scale(.96)}
.card.on{background:rgba(16,185,129,0.05);border-color:rgba(16,185,129,0.3);box-shadow:0 10px 15px -3px rgba(16,185,129,0.1)}
.name{font-size:.85em;color:#e2e8f0;font-weight:500;text-align:center;word-break:break-word}
.sw{width:56px;height:30px;border-radius:15px;background:rgba(255,255,255,0.1);position:relative;transition:all .3s;pointer-events:none}
.sw.on{background:#10b981;box-shadow:0 0 15px rgba(16,185,129,0.4)}
.sw::after{content:'';width:24px;height:24px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;transition:transform .3s cubic-bezier(.34,1.56,.64,1);box-shadow:0 2px 4px rgba(0,0,0,.2)}
.sw.on::after{transform:translateX(26px)}
.status-text{font-size:0.75em;font-weight:700;color:#94a3b8;transition:color 0.3s}
.card.on .status-text{color:#10b981}
/* Edit btn in card */
.edit-btn{position:absolute;top:8px;right:8px;background:rgba(255,255,255,0.06);border:none;border-radius:6px;color:#64748b;font-size:.75em;cursor:pointer;padding:3px 7px;z-index:2;transition:all .2s}
.edit-btn:hover{background:rgba(16,185,129,0.2);color:#10b981}
/* Schedule badge on card */
.sched-badge{font-size:.6em;background:rgba(100,116,139,0.2);color:#94a3b8;border-radius:10px;padding:2px 7px;border:1px solid rgba(100,116,139,0.2)}
.sched-badge.active{background:rgba(16,185,129,0.15);color:#10b981;border-color:rgba(16,185,129,0.3)}
/* Settings form */
.form{width:100%;max-width:400px;padding:20px;background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.05);border-radius:16px;margin-top:20px}
.fg{margin-bottom:15px}
label{display:block;font-size:0.85em;color:#94a3b8;margin-bottom:6px;font-weight:500}
input,select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.25);color:#fff;font-size:1em;outline:none;transition:border-color 0.3s}
input:focus,select:focus{border-color:#10b981}
button.submit{width:100%;padding:12px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:8px;color:white;font-weight:600;font-size:1em;cursor:pointer;margin-top:10px;box-shadow:0 4px 12px rgba(16,185,129,0.3);transition:transform .2s}
button.submit:active{transform:scale(0.98)}
footer{display:flex;justify-content:space-between;padding:10px 20px;font-size:.68em;color:#64748b;border-top:1px solid rgba(255,255,255,0.05)}
.empty{grid-column:1/-1;text-align:center;padding:60px 0;color:#64748b;font-size:1.1em}
/* ===== MODAL ===== */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:100;align-items:center;justify-content:center;padding:16px}
.overlay.show{display:flex}
.modal{background:#1e293b;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:24px;width:100%;max-width:360px;animation:slideUp .25s ease}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal h3{font-size:1.1em;font-weight:700;color:#f1f5f9;margin-bottom:18px}
.modal-close{float:right;background:none;border:none;color:#64748b;font-size:1.3em;cursor:pointer;line-height:1}
.modal-close:hover{color:#f1f5f9}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.section-title{font-size:.8em;font-weight:700;color:#10b981;text-transform:uppercase;letter-spacing:.05em;margin:16px 0 8px}
.sched-item{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
.sched-item:last-child{border-bottom:none}
.sched-toggle{position:relative;width:38px;height:22px;flex-shrink:0}
.sched-toggle input{opacity:0;width:0;height:0;position:absolute}
.sched-slider{position:absolute;inset:0;background:rgba(255,255,255,0.1);border-radius:11px;cursor:pointer;transition:.3s}
.sched-slider::before{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;left:3px;transition:.3s}
.sched-toggle input:checked+.sched-slider{background:#10b981}
.sched-toggle input:checked+.sched-slider::before{transform:translateX(16px)}
.sched-info{flex:1;font-size:.8em;color:#cbd5e1}
.sched-del{background:none;border:none;color:#ef4444;font-size:1em;cursor:pointer;padding:2px 6px}
.add-sched{width:100%;padding:8px;background:rgba(16,185,129,0.1);border:1px dashed rgba(16,185,129,0.4);border-radius:8px;color:#10b981;font-size:.85em;cursor:pointer;margin-top:10px;transition:all .2s}
.add-sched:hover{background:rgba(16,185,129,0.2)}
</style></head><body>
<header>
<span class="logo">TeweLocal</span>
<div style="display:flex;align-items:center;gap:10px">
<span class="badge b-off" id="sync">OFFLINE</span>
<button class="btn-icon" onclick="toggleTab()" title="Settings">⚙️</button>
<button class="btn-icon" style="color:#ef4444" onclick="logout()" title="Logout">🚪</button>
</div>
</header>

<div id="dashboard" class="tab active"><div class="grid" id="g"><div class="empty">Connecting...</div></div></div>

<div id="settings" class="tab">
<div class="form">
<div class="fg"><label>WiFi SSID</label>
<div style="display:flex;gap:8px">
<input type="text" id="ssid" placeholder="Network Name">
<button class="submit" style="width:auto;margin:0;padding:0 14px" onclick="scan()">Scan</button>
</div>
<select id="ssids" style="display:none;margin-top:8px" onchange="document.getElementById('ssid').value=this.value"></select>
</div>
<div class="fg"><label>WiFi Password</label><input type="password" id="pass" placeholder="Password"></div>
<div class="fg"><label>API Base URL</label><input type="text" id="api" placeholder="https://..."></div>
<div class="fg"><label>Device Code</label><input type="text" id="dev" placeholder="DEV_..."></div>
<div style="color:#10b981;font-weight:600;margin:18px 0 10px">Local Login</div>
<div class="fg"><label>New Username</label><input type="text" id="a_usr" placeholder="admin"></div>
<div class="fg"><label>New Password</label><input type="password" id="a_pwd" placeholder="(unchanged if empty)"></div>
<button class="submit" onclick="save()">Save &amp; Restart</button>
</div>
</div>

<footer><span id="ft1">---</span><span id="ft2">---</span><span id="ft3">---</span></footer>

<!-- ===== MODAL EDIT WIDGET ===== -->
<div class="overlay" id="ov" onclick="if(event.target===this)closeModal()">
<div class="modal">
<button class="modal-close" onclick="closeModal()">✕</button>
<h3 id="m-title">Edit Widget</h3>
<div class="fg"><label>Nama</label><input type="text" id="m-name" placeholder="Nama widget"></div>

<div class="section-title">⏰ Schedule</div>
<div id="sched-list"></div>
<button class="add-sched" onclick="addSched()">＋ Tambah Jadwal</button>

<div style="display:flex;gap:10px;margin-top:20px">
<button class="submit" style="background:rgba(239,68,68,0.2);color:#ef4444;box-shadow:none" onclick="closeModal()">Batal</button>
<button class="submit" onclick="saveModal()">Simpan</button>
</div>
</div>
</div>

<script>
var ws, W=[], cf={}, schedules={}, editKey='';

// ── WebSocket ──────────────────────────────────────────────
function cn(){
  var p=location.protocol==='https:'?'wss:':'ws:';
  ws=new WebSocket(p+'//'+location.host+'/ws');
  ws.onmessage=function(e){
    var d=JSON.parse(e.data);
    if(d.event==='init'){
      W=d.widgets||[]; cf=d.config||{};
      document.getElementById('sync').className='badge '+(d.internet?'b-on':'b-off');
      document.getElementById('sync').textContent=d.mqtt?'SYNCED':d.internet?'ONLINE':'LOCAL';
      document.getElementById('ft1').textContent='IP: '+(d.ip||'AP-only');
      document.getElementById('ft2').textContent='Heap: '+(d.heap/1024).toFixed(1)+'KB';
      document.getElementById('ft3').textContent='Up: '+Math.floor((d.uptime||0)/60)+'m';
      rn();
    } else if(d.event==='state'){
      up(d.key, d.value);
    }
  };
  ws.onclose=function(){setTimeout(cn,3000)};
}

// ── Render cards ────────────────────────────────────────────
function rn(){
  var g=document.getElementById('g'); g.innerHTML='';
  if(!W.length){g.innerHTML='<div class="empty">No widgets</div>';return;}
  W.forEach(function(w){
    var sc=schedules[w.key]||[];
    var hasActiveSched=sc.some(function(s){return s.on;});
    var schedBadge='<span class="sched-badge'+(hasActiveSched?' active':'')+'">⏰ '+sc.length+'</span>';
    if(w.type==='toggle'){
      var on=w.value==='1'?' on':'';
      var el=document.createElement('div');
      el.className='card toggle-card'+on;
      el.id='c-'+w.key;
      el.innerHTML=
        '<button class="edit-btn" data-key="'+w.key+'">✏️</button>'+
        '<div class="name">'+w.name+'</div>'+
        '<div class="sw'+on+'" id="s-'+w.key+'"></div>'+
        '<div class="status-text" id="st-'+w.key+'">'+(w.value==='1'?'ON':'OFF')+'</div>'+
        schedBadge;
      // Toggle on card click (not on edit button)
      el.addEventListener('click', function(ev){
        if(ev.target.classList.contains('edit-btn')||ev.target.closest('.edit-btn')) return;
        ws.send(JSON.stringify({action:'toggle',key:w.key}));
      });
      // Edit button
      el.querySelector('.edit-btn').addEventListener('click', function(ev){
        ev.stopPropagation();
        openModal(w.key);
      });
      g.appendChild(el);
    } else {
      var el=document.createElement('div');
      el.className='card';
      el.id='c-'+w.key;
      el.innerHTML=
        '<button class="edit-btn" data-key="'+w.key+'">✏️</button>'+
        '<div class="name">'+w.name+'</div>'+
        '<div style="font-size:1.6em;font-weight:700;color:#10b981;margin-top:8px" id="v-'+w.key+'">'+
          w.value+' <span style="font-size:0.5em;color:#94a3b8">'+(w.unit||'')+'</span></div>'+
        schedBadge;
      el.querySelector('.edit-btn').addEventListener('click', function(ev){
        ev.stopPropagation();
        openModal(w.key);
      });
      g.appendChild(el);
    }
  });
}

function up(k,v){
  for(var i=0;i<W.length;i++) if(W[i].key===k) W[i].value=v;
  var w=W.find(function(x){return x.key===k;});
  if(!w) return;
  if(w.type==='toggle'){
    var c=document.getElementById('c-'+k),s=document.getElementById('s-'+k),t=document.getElementById('st-'+k);
    if(c){c.className='card toggle-card'+(v==='1'?' on':'');s.className='sw'+(v==='1'?' on':'');t.textContent=v==='1'?'ON':'OFF';}
  } else {
    var e=document.getElementById('v-'+k);
    if(e) e.innerHTML=v+' <span style="font-size:0.5em;color:#94a3b8">'+(w.unit||'')+'</span>';
  }
}

// ── Settings tab ────────────────────────────────────────────
function toggleTab(){
  document.getElementById('dashboard').classList.toggle('active');
  document.getElementById('settings').classList.toggle('active');
  if(document.getElementById('settings').classList.contains('active')) loadCfg();
}
function logout(){
  if(!confirm('Logout dari Local UI?')) return;
  fetch('/api/logout',{method:'POST'}).then(function(){window.location.reload();});
}
function loadCfg(){
  document.getElementById('ssid').value=cf.ssid||'';
  document.getElementById('api').value=cf.api||'';
  document.getElementById('dev').value=cf.dev||'';
  document.getElementById('a_usr').value=cf.auth_user||'admin';
}
function scan(){
  var s=document.getElementById('ssids');
  s.innerHTML='<option>Scanning...</option>';s.style.display='block';
  fetch('/api/scan').then(function(r){return r.json();}).then(function(d){
    s.innerHTML='<option value="">Select Network</option>';
    d.forEach(function(n){s.innerHTML+='<option value="'+n.s+'">'+n.s+' ('+n.r+'dBm)</option>';});
  });
}
function save(){
  var d=new URLSearchParams();
  d.append('ssid',document.getElementById('ssid').value);
  d.append('pass',document.getElementById('pass').value);
  d.append('api',document.getElementById('api').value);
  d.append('dev',document.getElementById('dev').value);
  d.append('a_usr',document.getElementById('a_usr').value);
  d.append('a_pwd',document.getElementById('a_pwd').value);
  fetch('/api/config',{method:'POST',body:d}).then(function(){alert('Saved! ESP will restart.');location.reload();});
}

// ── Modal Edit Widget ────────────────────────────────────────
function openModal(key){
  editKey=key;
  var w=W.find(function(x){return x.key===key;});
  if(!w) return;
  document.getElementById('m-title').textContent='Edit: '+w.name;
  document.getElementById('m-name').value=w.name;
  renderSchedList();
  document.getElementById('ov').classList.add('show');
}
function closeModal(){
  document.getElementById('ov').classList.remove('show');
  editKey='';
}
function saveModal(){
  // Nama widget: simpan di local W[] saja (tidak ada endpoint rename)
  var newName=document.getElementById('m-name').value.trim();
  if(newName){
    var w=W.find(function(x){return x.key===editKey;});
    if(w){ w.name=newName; rn(); }
  }
  // Jadwal sudah tersimpan saat addSched/toggleSched
  closeModal();
}

// ── Schedule (client-side, stored in localStorage) ──────────
function loadScheds(){
  try{ schedules=JSON.parse(localStorage.getItem('tw_scheds')||'{}'); }catch(e){ schedules={}; }
}
function saveScheds(){
  localStorage.setItem('tw_scheds', JSON.stringify(schedules));
}
function renderSchedList(){
  var sc=schedules[editKey]||[];
  var html='';
  if(!sc.length) html='<div style="color:#64748b;font-size:.82em;padding:8px 0">Belum ada jadwal</div>';
  sc.forEach(function(s,i){
    var lbl=s.days.join(',')+' '+s.time+' → '+(s.action==='on'?'<b style="color:#10b981">ON</b>':'<b style="color:#ef4444">OFF</b>');
    html+='<div class="sched-item">'+
      '<label class="sched-toggle"><input type="checkbox"'+(s.on?' checked':'')+' onchange="toggleSched('+i+',this.checked)"><span class="sched-slider"></span></label>'+
      '<span class="sched-info">'+lbl+'</span>'+
      '<button class="sched-del" onclick="delSched('+i+')">🗑</button>'+
    '</div>';
  });
  document.getElementById('sched-list').innerHTML=html;
}
function addSched(){
  var days=['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
  // Buat popup form sederhana
  var time=prompt('Jam (HH:MM):','07:00');
  if(!time||!/^\d{2}:\d{2}$/.test(time)){alert('Format jam salah. Gunakan HH:MM (contoh: 07:00)');return;}
  var act=confirm('Aksi: OK = ON, Cancel = OFF')?'on':'off';
  var dayStr=prompt('Hari (1-7, pisah koma: 1=Sen ... 7=Min)\nContoh: 1,2,3,4,5 = Senin-Jumat','1,2,3,4,5');
  if(!dayStr) return;
  var selDays=dayStr.split(',').map(function(x){
    var n=parseInt(x.trim())-1;
    return (n>=0&&n<7)?days[n]:null;
  }).filter(Boolean);
  if(!selDays.length){alert('Pilihan hari tidak valid');return;}
  if(!schedules[editKey]) schedules[editKey]=[];
  schedules[editKey].push({time:time,action:act,days:selDays,on:true});
  saveScheds();
  renderSchedList();
  rn(); // refresh badge
}
function toggleSched(i,checked){
  if(!schedules[editKey]) return;
  schedules[editKey][i].on=checked;
  saveScheds();
}
function delSched(i){
  if(!confirm('Hapus jadwal ini?')) return;
  schedules[editKey].splice(i,1);
  saveScheds();
  renderSchedList();
  rn();
}

// ── Schedule Runner (cek setiap menit) ──────────────────────
var _lastRun={};
function runSchedules(){
  var now=new Date();
  var hm=String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
  var dayIdx=now.getDay(); // 0=Sun..6=Sat
  var dayNames=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
  var todayName=dayNames[dayIdx];
  Object.keys(schedules).forEach(function(key){
    var sc=schedules[key]||[];
    sc.forEach(function(s,i){
      if(!s.on) return;
      if(s.time!==hm) return;
      if(s.days.indexOf(todayName)<0) return;
      var runId=key+'_'+i+'_'+hm;
      if(_lastRun[runId]) return; // sudah jalan menit ini
      _lastRun[runId]=true;
      // Kirim via WebSocket
      if(ws&&ws.readyState===1){
        ws.send(JSON.stringify({action:'set',key:key,value:s.action==='on'?'1':'0'}));
        console.log('Schedule fired: '+key+' → '+s.action);
      }
      // Bersihkan token lama (simpan maks 60 entry)
      var keys=Object.keys(_lastRun);
      if(keys.length>60) delete _lastRun[keys[0]];
    });
  });
}

// ── Init ────────────────────────────────────────────────────
loadScheds();
cn();
// Jalankan schedule runner setiap 10 detik (presisi cukup untuk menit)
setInterval(runSchedules, 10000);
// Juga jalankan segera saat load
runSchedules();
</script></body></html>
)rawliteral";

#endif

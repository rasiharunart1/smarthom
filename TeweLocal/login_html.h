#ifndef LOGIN_HTML_H
#define LOGIN_HTML_H

const char LOGIN_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html><html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<meta name="theme-color" content="#0f172a">
<title>TeweLocal Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.form{width:100%;max-width:360px;padding:30px;background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.05);border-radius:24px;box-shadow:0 10px 30px rgba(0,0,0,0.5)}
.logo{font-size:1.8em;font-weight:800;background:linear-gradient(135deg,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;text-align:center;display:block;margin-bottom:24px}
.fg{margin-bottom:18px}
label{display:block;font-size:0.85em;color:#94a3b8;margin-bottom:6px;font-weight:500}
input:not([type="checkbox"]){width:100%;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.2);color:#fff;font-size:1em;outline:none;transition:border-color 0.3s}
input:focus{border-color:#10b981}
button{width:100%;padding:14px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:10px;color:white;font-weight:600;font-size:1em;cursor:pointer;margin-top:10px;box-shadow:0 4px 12px rgba(16,185,129,0.3);transition:transform 0.2s}
button:active{transform:scale(0.98)}
.error{color:#ef4444;font-size:0.85em;text-align:center;margin-bottom:15px;display:none;background:rgba(239,68,68,0.1);padding:10px;border-radius:8px;border:1px solid rgba(239,68,68,0.2)}
.chk{display:flex;align-items:center;gap:8px;font-size:0.85em;color:#94a3b8;cursor:pointer;margin-bottom:20px;user-select:none}
input[type="checkbox"]{accent-color:#10b981;width:16px;height:16px;cursor:pointer}
</style></head><body>
<div class="form">
<span class="logo">TeweLocal</span>
<div class="error" id="err">Invalid credentials</div>
<form id="lf" onsubmit="login(); return false">
<div class="fg"><label>Username</label><input type="text" id="user" required></div>
<div class="fg"><label>Password</label><input type="password" id="pass" required></div>
<label class="chk"><input type="checkbox" id="rem"> <span>Remember Me</span></label>
<button type="submit" id="btn">Login</button>
</form>
</div>
<script>
function login(){
  var e=document.getElementById('user').value, p=document.getElementById('pass').value,
      r=document.getElementById('rem').checked?1:0, err=document.getElementById('err'), btn=document.getElementById('btn');
  if(!e||!p) return;
  btn.textContent='Authenticating...'; btn.disabled=true; err.style.display='none';
  var d=new URLSearchParams(); d.append('user',e); d.append('pass',p); d.append('rem',r);
  fetch('/api/login',{method:'POST',body:d}).then(res=>{
    if(res.ok){ window.location.href='/'; }
    else { err.style.display='block'; btn.textContent='Login'; btn.disabled=false; }
  }).catch(()=>{ err.style.display='block'; btn.textContent='Login'; btn.disabled=false; });
}
</script></body></html>
)rawliteral";

#endif

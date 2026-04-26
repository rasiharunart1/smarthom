/***************************************************
 *  NHSmart — SmartHome ESP8266 + PCF8574 I/O Expander
 *  
 *  Auto-scan I2C untuk PCF8574 (0x20-0x27)
 *  Max 8 chip × 8 pin = 64 channel relay
 *  3 Mode: ONLINE / LOCAL / OFFLINE (AP)
 ***************************************************/

#include <NHSmart.h>
#include <ESP8266WebServer.h>
#include <DNSServer.h>
#include <Wire.h>
#include <EEPROM.h>

#define EEPROM_SIZE     128
#define EEPROM_MAGIC    0xAB
#define EEPROM_ADDR_MAGIC   0
#define EEPROM_ADDR_SLIDER  1
#define EEPROM_ADDR_RELAYS  2

// ══════════ KONFIGURASI ══════════
#define DEVICE_CODE "DEV_XXXXXXXXXX"
#define SERVER_URL  "https://your-server.com"
const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASS = "YOUR_WIFI_PASSWORD";
const char* AP_SSID   = "NHSmart-Home";
const char* AP_PASS   = "12345678";

// I2C Pins (ESP8266: D1=SCL, D2=SDA)
#define I2C_SDA D2  // GPIO4
#define I2C_SCL D1  // GPIO5

// Slider PWM
#define PIN_LED_PWM D7 // GPIO13

// ══════════ PCF8574 DRIVER ══════════
#define PCF_BASE_ADDR 0x20
#define PCF_MAX_CHIPS 8
#define PCF_PINS_PER_CHIP 8
#define MAX_RELAYS (PCF_MAX_CHIPS * PCF_PINS_PER_CHIP)

struct PCFChip { uint8_t addr; uint8_t state; };
PCFChip pcfChips[PCF_MAX_CHIPS];
int pcfCount = 0;
int totalRelays = 0;
bool relayStates[MAX_RELAYS];

void pcfScan() {
  pcfCount = 0; totalRelays = 0;
  for (uint8_t a = PCF_BASE_ADDR; a < PCF_BASE_ADDR + PCF_MAX_CHIPS; a++) {
    Wire.beginTransmission(a);
    if (Wire.endTransmission() == 0) {
      pcfChips[pcfCount].addr = a;
      pcfChips[pcfCount].state = 0xFF;
      pcfCount++;
      Serial.printf("[PCF] Found 0x%02X\n", a);
    }
  }
  totalRelays = pcfCount * PCF_PINS_PER_CHIP;
  Serial.printf("[PCF] %d chips, %d channels\n", pcfCount, totalRelays);
  memset(relayStates, 0, sizeof(relayStates));
  for (int i = 0; i < pcfCount; i++) {
    Wire.beginTransmission(pcfChips[i].addr);
    Wire.write(0xFF);
    Wire.endTransmission();
  }
}

void pcfSetRelay(int ch, bool on) {
  if (ch < 0 || ch >= totalRelays) return;
  int chip = ch / PCF_PINS_PER_CHIP;
  int pin  = ch % PCF_PINS_PER_CHIP;
  relayStates[ch] = on;
  if (on) pcfChips[chip].state &= ~(1 << pin);
  else    pcfChips[chip].state |=  (1 << pin);
  Wire.beginTransmission(pcfChips[chip].addr);
  Wire.write(pcfChips[chip].state);
  Wire.endTransmission();
}

void saveState() {
  EEPROM.write(EEPROM_ADDR_MAGIC, EEPROM_MAGIC);
  EEPROM.write(EEPROM_ADDR_SLIDER, (uint8_t)sliderVal);
  for (int i = 0; i < MAX_RELAYS; i++)
    EEPROM.write(EEPROM_ADDR_RELAYS + i, relayStates[i] ? 1 : 0);
  EEPROM.commit();
}

void loadState() {
  if (EEPROM.read(EEPROM_ADDR_MAGIC) != EEPROM_MAGIC) {
    Serial.println("[EEPROM] No saved state");
    return;
  }
  sliderVal = EEPROM.read(EEPROM_ADDR_SLIDER);
  analogWrite(PIN_LED_PWM, sliderVal);
  for (int i = 0; i < totalRelays; i++)
    pcfSetRelay(i, EEPROM.read(EEPROM_ADDR_RELAYS + i) == 1);
  Serial.printf("[EEPROM] Restored %d relays, slider=%d\n", totalRelays, sliderVal);
}

void syncToCloud() {
  Serial.println("[Sync] Pushing local state to cloud...");
  for (int i = 0; i < totalRelays; i++) {
    char key[16]; snprintf(key, sizeof(key), "toggle%d", i + 1);
    NHSmart.virtualWrite(key, relayStates[i] ? 1 : 0);
    delay(20);
  }
  NHSmart.virtualWrite("slider1", sliderVal);
  sendToCloud();
  Serial.println("[Sync] Done!");
}

// ══════════ STATE ══════════
enum OpMode { MODE_ONLINE, MODE_LOCAL, MODE_OFFLINE };
OpMode currentMode = MODE_OFFLINE;
ESP8266WebServer server(80);
DNSServer dnsServer;
NHTimer timer;

int sliderVal = 0;
float temp=28, humidity=65, voltage=220, current_a=1.5;
int gasVal=200, ldrVal=50;
unsigned long lastReconnect = 0;
int reconnectAttempts = 0;
bool apActive = false;

float smooth(float p,float c,float a=0.3){return a*c+(1-a)*p;}

// ══════════ CALLBACKS ══════════
void onRelayCallback(int ch, String value) {
  bool on = (value.toInt()==1||value=="true"||value=="on");
  pcfSetRelay(ch, on);
  saveState();
}

#define RELAY_CB(n) void onRelay##n(String v){onRelayCallback(n,v);}
RELAY_CB(0)  RELAY_CB(1)  RELAY_CB(2)  RELAY_CB(3)
RELAY_CB(4)  RELAY_CB(5)  RELAY_CB(6)  RELAY_CB(7)
RELAY_CB(8)  RELAY_CB(9)  RELAY_CB(10) RELAY_CB(11)
RELAY_CB(12) RELAY_CB(13) RELAY_CB(14) RELAY_CB(15)
RELAY_CB(16) RELAY_CB(17) RELAY_CB(18) RELAY_CB(19)
RELAY_CB(20) RELAY_CB(21) RELAY_CB(22) RELAY_CB(23)
RELAY_CB(24) RELAY_CB(25) RELAY_CB(26) RELAY_CB(27)
RELAY_CB(28) RELAY_CB(29) RELAY_CB(30) RELAY_CB(31)
RELAY_CB(32) RELAY_CB(33) RELAY_CB(34) RELAY_CB(35)
RELAY_CB(36) RELAY_CB(37) RELAY_CB(38) RELAY_CB(39)
RELAY_CB(40) RELAY_CB(41) RELAY_CB(42) RELAY_CB(43)
RELAY_CB(44) RELAY_CB(45) RELAY_CB(46) RELAY_CB(47)
RELAY_CB(48) RELAY_CB(49) RELAY_CB(50) RELAY_CB(51)
RELAY_CB(52) RELAY_CB(53) RELAY_CB(54) RELAY_CB(55)
RELAY_CB(56) RELAY_CB(57) RELAY_CB(58) RELAY_CB(59)
RELAY_CB(60) RELAY_CB(61) RELAY_CB(62) RELAY_CB(63)

typedef void(*RelayCB)(String);
RelayCB relayCBs[MAX_RELAYS] = {
  onRelay0,onRelay1,onRelay2,onRelay3,onRelay4,onRelay5,onRelay6,onRelay7,
  onRelay8,onRelay9,onRelay10,onRelay11,onRelay12,onRelay13,onRelay14,onRelay15,
  onRelay16,onRelay17,onRelay18,onRelay19,onRelay20,onRelay21,onRelay22,onRelay23,
  onRelay24,onRelay25,onRelay26,onRelay27,onRelay28,onRelay29,onRelay30,onRelay31,
  onRelay32,onRelay33,onRelay34,onRelay35,onRelay36,onRelay37,onRelay38,onRelay39,
  onRelay40,onRelay41,onRelay42,onRelay43,onRelay44,onRelay45,onRelay46,onRelay47,
  onRelay48,onRelay49,onRelay50,onRelay51,onRelay52,onRelay53,onRelay54,onRelay55,
  onRelay56,onRelay57,onRelay58,onRelay59,onRelay60,onRelay61,onRelay62,onRelay63
};

void registerRelayCallbacks() {
  for (int i = 0; i < totalRelays && i < MAX_RELAYS; i++) {
    char key[16]; snprintf(key, sizeof(key), "toggle%d", i+1);
    NHSmart.onWrite(key, relayCBs[i]);
  }
}

void onSlider(String v) {
  sliderVal = constrain(v.toInt(), 0, 255);
  analogWrite(PIN_LED_PWM, sliderVal);
  saveState();
}

// ══════════ SENSOR ══════════
void readSensors() {
  temp      = smooth(temp, random(250,350)/10.0);
  humidity  = smooth(humidity, random(400,800)/10.0);
  voltage   = smooth(voltage, random(215,225));
  current_a = smooth(current_a, random(10,50)/10.0);
  gasVal    = (int)smooth(gasVal, random(100,600));
  ldrVal    = map(analogRead(A0), 0, 1023, 0, 100);
}

void sendToCloud() {
  readSensors();
  if (currentMode != MODE_ONLINE) return;
  NHSmart.virtualWrite("temp1", temp);
  NHSmart.virtualWrite("humidity", humidity);
  NHSmart.virtualWrite("voltage", voltage);
  NHSmart.virtualWrite("current", current_a);
  NHSmart.virtualWrite("power", voltage*current_a);
  NHSmart.virtualWrite("gas", gasVal);
  NHSmart.virtualWrite("ldr", ldrVal);
}

// ══════════ WEB UI ══════════
const char HTML_PAGE[] PROGMEM = R"rawliteral(
<!DOCTYPE html><html><head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NHSmart Panel</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#0a0e1a;color:#e2e8f0;min-height:100vh}
.hdr{background:linear-gradient(135deg,#0f1629,#1a1f3a);padding:14px 18px;border-bottom:1px solid rgba(16,185,129,.2);display:flex;justify-content:space-between;align-items:center}
.hdr h1{font-size:1.1rem;background:linear-gradient(135deg,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.badge{padding:3px 10px;border-radius:20px;font-size:.65rem;font-weight:700}
.b-online{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3)}
.b-local{background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3)}
.b-offline{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.c{max-width:600px;margin:0 auto;padding:12px}
.card{background:rgba(15,22,41,.8);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;margin-bottom:10px}
.ct{font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}
.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.stat{text-align:center;padding:8px;background:rgba(255,255,255,.03);border-radius:8px}
.stat .v{font-size:1.2rem;font-weight:700;color:#f1f5f9}
.stat .l{font-size:.6rem;color:#64748b;margin-top:2px}
.rg{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.rr{display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(255,255,255,.02);border-radius:8px}
.rn{font-weight:600;font-size:.8rem}
.rs{font-size:.65rem;color:#64748b}
.tgl{position:relative;width:44px;height:24px;cursor:pointer;flex-shrink:0}
.tgl input{opacity:0;width:0;height:0}
.tgl .s{position:absolute;inset:0;background:#1e293b;border-radius:24px;transition:.3s;border:1px solid rgba(255,255,255,.1)}
.tgl .s:before{content:'';position:absolute;height:18px;width:18px;left:2px;bottom:2px;background:#475569;border-radius:50%;transition:.3s}
.tgl input:checked+.s{background:rgba(16,185,129,.3);border-color:rgba(16,185,129,.5)}
.tgl input:checked+.s:before{transform:translateX(20px);background:#10b981}
.sw input[type=range]{width:100%;accent-color:#10b981;height:5px;margin:6px 0}
.sv{text-align:center;font-size:1rem;font-weight:700;color:#34d399}
.info{font-size:.65rem;color:#475569;text-align:center;margin-top:8px}
</style></head><body>
<div class="hdr"><h1>⚡ NHSmart</h1><span class="badge" id="mb">...</span></div>
<div class="c">
<div class="card"><div class="ct">📊 Sensors</div>
<div class="grid">
<div class="stat"><div class="v" id="s0">--</div><div class="l">🌡 Suhu°C</div></div>
<div class="stat"><div class="v" id="s1">--</div><div class="l">💧 Hum%</div></div>
<div class="stat"><div class="v" id="s2">--</div><div class="l">⚡ Volt</div></div>
<div class="stat"><div class="v" id="s3">--</div><div class="l">🔌 Amp</div></div>
<div class="stat"><div class="v" id="s4">--</div><div class="l">🔥 Gas</div></div>
<div class="stat"><div class="v" id="s5">--</div><div class="l">☀ Light</div></div>
</div></div>
<div class="card"><div class="ct">🎮 Relay (<span id="rc">0</span> CH)</div>
<div class="rg" id="rl"></div></div>
<div class="card"><div class="ct">🎚 Slider PWM</div>
<div class="sv" id="sv">0</div>
<div class="sw"><input type="range" min="0" max="255" value="0" id="sl" oninput="setSl(this.value)"></div></div>
<div class="info" id="inf">Loading...</div>
</div>
<script>
function poll(){
fetch('/api/status').then(r=>r.json()).then(d=>{
document.getElementById('s0').textContent=d.t;
document.getElementById('s1').textContent=d.h;
document.getElementById('s2').textContent=d.v;
document.getElementById('s3').textContent=d.a;
document.getElementById('s4').textContent=d.g;
document.getElementById('s5').textContent=d.l;
document.getElementById('sl').value=d.sl;
document.getElementById('sv').textContent=d.sl;
var b=document.getElementById('mb');
b.textContent=d.m;b.className='badge b-'+d.m.toLowerCase();
document.getElementById('rc').textContent=d.rc;
document.getElementById('inf').textContent='IP:'+d.ip+' | '+d.rs+'dBm | Up:'+d.up+'s | Heap:'+d.hp;
var c=document.getElementById('rl');
if(c.childElementCount!==d.rc){
c.innerHTML='';
for(var i=0;i<d.rc;i++){
var on=d.rs_str[i]==='1';
c.innerHTML+='<div class="rr"><div><div class="rn">CH '+(i+1)+'</div><div class="rs" id="rs'+i+'">'+(on?'ON':'OFF')+'</div></div><label class="tgl"><input type="checkbox" id="r'+i+'" '+(on?'checked':'')+' onchange="setR('+i+',this.checked)"><span class="s"></span></label></div>';
}
}else{
for(var i=0;i<d.rc;i++){
var on=d.rs_str[i]==='1';
var cb=document.getElementById('r'+i);if(cb)cb.checked=on;
var st=document.getElementById('rs'+i);if(st)st.textContent=on?'ON':'OFF';
}}
}).catch(e=>{});
}
function setR(n,s){fetch('/api/relay',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ch:n,state:s?1:0})}).then(poll);}
function setSl(v){document.getElementById('sv').textContent=v;fetch('/api/slider',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({value:parseInt(v)})});}
poll();setInterval(poll,2000);
</script></body></html>
)rawliteral";

// ══════════ HANDLERS ══════════
void handleRoot(){server.send_P(200,"text/html",HTML_PAGE);}

void handleStatus(){
  readSensors();
  const char* ms=(currentMode==MODE_ONLINE)?"ONLINE":(currentMode==MODE_LOCAL)?"LOCAL":"OFFLINE";
  String ip=(WiFi.status()==WL_CONNECTED)?WiFi.localIP().toString():WiFi.softAPIP().toString();
  char rs[MAX_RELAYS+1];
  for(int i=0;i<totalRelays;i++) rs[i]=relayStates[i]?'1':'0';
  rs[totalRelays]='\0';
  char json[384];
  snprintf(json,sizeof(json),
    "{\"m\":\"%s\",\"t\":%.1f,\"h\":%.1f,\"v\":%.1f,\"a\":%.2f,"
    "\"g\":%d,\"l\":%d,\"rc\":%d,\"rs_str\":\"%s\",\"sl\":%d,"
    "\"ip\":\"%s\",\"rs\":%d,\"up\":%lu,\"hp\":%u}",
    ms,temp,humidity,voltage,current_a,
    gasVal,ldrVal,totalRelays,rs,sliderVal,
    ip.c_str(),WiFi.RSSI(),millis()/1000,ESP.getFreeHeap());
  server.send(200,"application/json",json);
}

void handleRelay(){
  if(server.hasArg("plain")){
    DynamicJsonDocument doc(64);
    deserializeJson(doc,server.arg("plain"));
    int ch=doc["ch"]|0; int state=doc["state"]|0;
    pcfSetRelay(ch, state==1);
    saveState();
    if(currentMode==MODE_ONLINE){
      char key[16];snprintf(key,sizeof(key),"toggle%d",ch+1);
      NHSmart.virtualWrite(key,state);
    }
  }
  server.send(200,"application/json","{\"ok\":true}");
}

void handleSlider(){
  if(server.hasArg("plain")){
    DynamicJsonDocument doc(64);
    deserializeJson(doc,server.arg("plain"));
    sliderVal=constrain(doc["value"]|0,0,255);
    analogWrite(PIN_LED_PWM,sliderVal);
    saveState();
    if(currentMode==MODE_ONLINE) NHSmart.virtualWrite("slider1",sliderVal);
  }
  server.send(200,"application/json","{\"ok\":true}");
}

// ══════════ WiFi ══════════
void startAP(){
  if(apActive)return;
  WiFi.mode(WIFI_AP_STA);WiFi.softAP(AP_SSID,AP_PASS);
  dnsServer.start(53,"*",WiFi.softAPIP());apActive=true;
  Serial.printf("[WiFi] AP:%s @ %s\n",AP_SSID,WiFi.softAPIP().toString().c_str());
}
void stopAP(){
  if(!apActive)return;
  WiFi.softAPdisconnect(true);WiFi.mode(WIFI_STA);
  dnsServer.stop();apActive=false;
}
bool tryConnectSTA(){
  WiFi.begin(WIFI_SSID,WIFI_PASS);
  for(int i=0;i<30&&WiFi.status()!=WL_CONNECTED;i++){delay(500);Serial.print(".");}
  Serial.println();
  if(WiFi.status()==WL_CONNECTED){reconnectAttempts=0;return true;}
  reconnectAttempts++;return false;
}
void updateMode(){
  if(WiFi.status()==WL_CONNECTED){
    if(NHSmart.connected()){
      if(currentMode!=MODE_ONLINE){
        OpMode prev=currentMode;
        currentMode=MODE_ONLINE;
        if(apActive)stopAP();
        if(prev==MODE_OFFLINE||prev==MODE_LOCAL) syncToCloud();
      }
    }
    else{if(currentMode!=MODE_LOCAL)currentMode=MODE_LOCAL;}
  }else{if(currentMode!=MODE_OFFLINE){currentMode=MODE_OFFLINE;startAP();}}
}
void wifiReconnect(){
  if(WiFi.status()==WL_CONNECTED)return;
  unsigned long bk=min(120000UL,15000UL*(1UL<<min(reconnectAttempts,3)));
  if(millis()-lastReconnect<bk)return;
  lastReconnect=millis();
  WiFi.disconnect();WiFi.begin(WIFI_SSID,WIFI_PASS);
  unsigned long st=millis();
  while(WiFi.status()!=WL_CONNECTED&&millis()-st<10000){
    if(apActive){dnsServer.processNextRequest();server.handleClient();}
    delay(100);
  }
  if(WiFi.status()==WL_CONNECTED)reconnectAttempts=0;
  else{reconnectAttempts++;if(!apActive)startAP();}
}

// ══════════ SETUP ══════════
void setup(){
  Serial.begin(115200);delay(100);
  Serial.println("\n[NHSmart] ESP8266 + PCF8574");

  EEPROM.begin(EEPROM_SIZE);

  Wire.begin(I2C_SDA,I2C_SCL);
  pcfScan();
  loadState();

  pinMode(PIN_LED_PWM,OUTPUT);analogWrite(PIN_LED_PWM,0);
  randomSeed(analogRead(0));

  registerRelayCallbacks();
  NHSmart.onWrite("slider1",onSlider);

  server.on("/",handleRoot);
  server.on("/api/status",HTTP_GET,handleStatus);
  server.on("/api/relay",HTTP_POST,handleRelay);
  server.on("/api/slider",HTTP_POST,handleSlider);
  server.onNotFound(handleRoot);

  WiFi.mode(WIFI_STA);
  if(tryConnectSTA()) NHSmart.begin(DEVICE_CODE,WIFI_SSID,WIFI_PASS,SERVER_URL);
  else startAP();

  server.begin();
  timer.setInterval(2000,sendToCloud);
}

// ══════════ LOOP ══════════
void loop(){
  wifiReconnect();
  updateMode();
  if(WiFi.status()==WL_CONNECTED) NHSmart.loop();
  server.handleClient();
  if(apActive) dnsServer.processNextRequest();
  timer.run();
}

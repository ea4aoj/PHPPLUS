<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');

$SERVICES = [
    'ais' => [
        'name'    => 'AIS-catcher',
        'systemd' => 'ais-catcher.service',
        'config'  => '/etc/AIS-catcher/config.cmd',
        'webport' => 8080
    ],
    'sxfeeder' => [
        'name'    => 'SXFeeder',
        'systemd' => 'sxfeeder.service',
        'config'  => '/etc/sxfeeder.ini',
        'webport' => null
    ]
];

$serviceKey = $_GET['service'] ?? 'ais';
$action = $_GET['action'] ?? '';

if (!isset($SERVICES[$serviceKey])) die('Servicio inválido');

$SVC = $SERVICES[$serviceKey];
$SYSTEMD = $SVC['systemd'];
$CONFIG_FILE = $SVC['config'];

/* ================= STATUS ================= */
if ($action === 'status') {

    $st  = trim(shell_exec("systemctl is-active $SYSTEMD 2>/dev/null"));
    $en  = trim(shell_exec("systemctl is-enabled $SYSTEMD 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode([
        'active'  => $st === 'active',
        'status'  => $st,
        'enabled' => $en === 'enabled'
    ]);
    exit;
}

/* ================= ON ================= */
if ($action === 'on') {

    shell_exec("sudo systemctl enable $SYSTEMD 2>/dev/null");
    shell_exec("sudo systemctl start $SYSTEMD 2>/dev/null");

    sleep(1);
    $st = trim(shell_exec("systemctl is-active $SYSTEMD 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode($st === 'active'
        ? ['ok'=>true,'msg'=>"$SVC[name] iniciado"]
        : ['ok'=>false,'error'=>'No arrancó']);
    exit;
}

/* ================= OFF ================= */
if ($action === 'off') {

    shell_exec("sudo systemctl stop $SYSTEMD 2>/dev/null");
    shell_exec("sudo systemctl disable $SYSTEMD 2>/dev/null");

    sleep(1);
    $st = trim(shell_exec("systemctl is-active $SYSTEMD 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode($st !== 'active'
        ? ['ok'=>true,'msg'=>"$SVC[name] detenido"]
        : ['ok'=>false,'error'=>'No se pudo detener']);
    exit;
}

/* ================= LOG ================= */
if ($action === 'log') {
    header('Content-Type: text/plain');
    echo shell_exec("sudo journalctl -u $SYSTEMD -n 80 --no-pager 2>/dev/null");
    exit;
}

/* ================= CONFIG ================= */
if ($action === 'config-read') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'=>true,
        'content'=>file_get_contents($CONFIG_FILE)
    ]);
    exit;
}

if ($action === 'config-save') {
    header('Content-Type: application/json');
    file_put_contents($CONFIG_FILE, $_POST['content'] ?? '');
    echo json_encode(['ok'=>true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= $SVC['name'] ?> Control</title>

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">

<style>

:root{
--bg:#0a0e14;
--panel:#111720;
--border:#1e2d3d;
--cyan:#00d4ff;
--green:#00ff9f;
--red:#ff4560;
--text:#a8b9cc;
}

body{
margin:0;
background:var(--bg);
color:var(--text);
font-family:Rajdhani;
}

/* HEADER */
.header{
display:flex;
justify-content:space-between;
align-items:center;
padding:10px 14px;
background:var(--panel);
border-bottom:1px solid var(--border);
flex-wrap:wrap;
gap:8px;
}

.title{
font-family:Orbitron;
color:var(--cyan);
font-size:13px;
}

.row{
display:flex;
align-items:center;
gap:8px;
flex-wrap:wrap;
}

/* SELECT */
select{
background:#0d131b;
color:var(--cyan);
border:1px solid var(--border);
padding:4px;
}

/* BUTTONS */
.btn{
background:transparent;
border:1px solid var(--cyan);
color:var(--cyan);
padding:5px 8px;
cursor:pointer;
font-size:11px;
}

.btn:hover{background:#12202b;}
.btn-red{border-color:var(--red);color:var(--red);}
.btn-green{border-color:var(--green);color:var(--green);}

/* SWITCH tipo dump1090 */
.switch{
position:relative;
width:52px;
height:24px;
display:inline-block;
}

.switch input{display:none;}

.slider{
position:absolute;
inset:0;
background:#1b2430;
border:1px solid var(--red);
cursor:pointer;
transition:.3s;
border-radius:2px;
}

.slider:before{
content:'';
position:absolute;
height:18px;width:18px;
left:3px;top:2px;
background:var(--red);
transition:.3s;
}

input:checked + .slider{
border-color:var(--green);
}

input:checked + .slider:before{
transform:translateX(26px);
background:var(--green);
}

/* LAYOUT */
.container{padding:14px;}

.state{
font-size:12px;
min-width:140px;
}

/* PANELS */
.panel{
margin-top:10px;
border:1px solid var(--border);
background:#0c121a;
padding:10px;
}

textarea{
width:100%;
height:240px;
background:#05090d;
color:#ccc;
border:1px solid var(--border);
}

/* hidden */
.hidden{display:none;}

</style>
</head>

<body>

<div class="header">

<div class="title">AIS / SX CONTROL</div>

<div class="row">

<select id="svc" onchange="chg()">
<option value="ais" <?= $serviceKey==='ais'?'selected':'' ?>>AIS-catcher</option>
<option value="sxfeeder" <?= $serviceKey==='sxfeeder'?'selected':'' ?>>SXFeeder</option>
</select>

<button class="btn btn-green" onclick="toggleCfg()">CONFIG</button>
<button class="btn btn-green" onclick="toggleLog()">LOG</button>

<button class="btn btn-green" onclick="openWeb()">WEB</button>

</div>

<div class="row">

<a href="mmdvm.php" class="btn btn-green" style="text-decoration:none;">
🏠 Panel PHPPLUS
</a>

</div>

</div>

<div class="container">

<div class="row">

<label class="switch">
<input type="checkbox" id="sw" onchange="toggle()">
<span class="slider"></span>
</label>

<div class="state" id="state">—</div>

</div>

<div id="cfgPanel" class="panel hidden">
<textarea id="cfgTxt"></textarea>
<button class="btn btn-green" onclick="saveCfg()">Guardar</button>
</div>

<pre id="logPanel" class="panel hidden"></pre>

</div>

<script>

const svc='<?= $serviceKey ?>';

/* API */
function api(a,p=null){
return fetch('?service='+svc+'&action='+a,{
method:p?'POST':'GET',
headers:p?{'Content-Type':'application/x-www-form-urlencoded'}:{},
body:p
});
}

/* STATUS */
async function status(){
const r=await api('status');
const d=await r.json();

document.getElementById('sw').checked=d.active;

document.getElementById('state').innerHTML =
d.active
? '<span style="color:#00ff9f">ACTIVE</span>'
: '<span style="color:#ff4560">'+d.status.toUpperCase()+'</span>';
}

/* SWITCH */
async function toggle(){
if(document.getElementById('sw').checked){
await api('on');
}else{
await api('off');
}
status();
}

/* SERVICE SELECT */
function chg(){
location.href='?service='+document.getElementById('svc').value;
}

/* WEB AIS ONLY */
function openWeb(){
if(svc!=='ais') return alert('SXFeeder no tiene web');
window.open('http://'+location.hostname+':8080','_blank');
}

/* CONFIG TOGGLE */
let cfgOpen=false;
function toggleCfg(){
cfgOpen=!cfgOpen;
document.getElementById('cfgPanel').classList.toggle('hidden');
if(cfgOpen) loadCfg();
}

async function loadCfg(){
const r=await api('config-read');
const d=await r.json();
document.getElementById('cfgTxt').value=d.content;
}

async function saveCfg(){
await api('config-save','content='+encodeURIComponent(document.getElementById('cfgTxt').value));
}

/* LOG TOGGLE */
let logOpen=false;
function toggleLog(){
logOpen=!logOpen;
document.getElementById('logPanel').classList.toggle('hidden');
if(logOpen) loadLog();
}

async function loadLog(){
const r=await api('log');
document.getElementById('logPanel').textContent=await r.text();
}

/* INIT */
setInterval(status,3000);
status();

</script>

</body>
</html>

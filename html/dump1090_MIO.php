<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
$action = $_GET['action'] ?? '';

if ($action === 'dump1090-start') {
    shell_exec('sudo systemctl start dump1090-fa 2>/dev/null');
    $st = 'activating';
    for ($i = 0; $i < 15; $i++) {
        sleep(1);
        $st = trim(shell_exec('systemctl is-active dump1090-fa 2>/dev/null'));
        if ($st !== 'activating') break;
    }
    header('Content-Type: application/json');
    echo json_encode($st === 'active'
        ? ['ok'=>true,  'output'=>'dump1090-fa iniciado correctamente']
        : ['ok'=>false, 'error'=>'El servicio no arrancó (estado final: '.$st.')']);
    exit;
}

if ($action === 'dump1090-stop') {
    shell_exec('sudo systemctl stop dump1090-fa 2>/dev/null');
    sleep(1);
    $st = trim(shell_exec('systemctl is-active dump1090-fa 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode($st !== 'active'
        ? ['ok'=>true,  'msg'=>'dump1090-fa detenido correctamente']
        : ['ok'=>false, 'msg'=>'No se pudo detener (estado: '.$st.')']);
    exit;
}

if ($action === 'dump1090-status') {
    $st  = trim(shell_exec('systemctl is-active dump1090-fa 2>/dev/null'));
    $pid = trim(shell_exec('systemctl show dump1090-fa --property=MainPID --value 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['active' => $st === 'active', 'status' => $st, 'pid' => $pid]);
    exit;
}

if ($action === 'dump1090-log') {
    header('Content-Type: text/plain');
    $log = shell_exec('sudo journalctl -u dump1090-fa -n 80 --no-pager --output=short 2>/dev/null');
    echo !empty(trim($log)) ? $log : '(sin log disponible)';
    exit;
}

if ($action === 'aircraft-json') {
    header('Content-Type: application/json');
    $paths = [
        '/run/dump1090-fa/aircraft.json',
        '/run/dump1090/aircraft.json',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            echo file_get_contents($path);
            exit;
        }
    }
    $json = @file_get_contents('http://127.0.0.1:8080/data/aircraft.json');
    if ($json !== false) { echo $json; exit; }
    echo json_encode(['aircraft'=>[], 'error'=>'No se encuentra aircraft.json en /run/dump1090-fa/']);
    exit;
}

if ($action === 'terminal') {
    $cmd = trim($_POST['cmd'] ?? '');
    if (preg_match('/^\s*(vim|vi|less|more|top|htop|su)\s*/i', $cmd)) {
        header('Content-Type: application/json');
        echo json_encode(['output' => 'Comando interactivo no soportado.']);
        exit;
    }
    if (preg_match('/(rm\s+-rf|shutdown|mkfs|dd\s+if=)/i', $cmd)) {
        header('Content-Type: application/json');
        echo json_encode(['output' => '❌ Comando bloqueado por seguridad']);
        exit;
    }
    $out = $cmd !== ''
        ? (shell_exec('/usr/bin/sudo -n -u pi -H bash -c ' . escapeshellarg($cmd) . ' 2>&1') ?? '')
        : '';
    header('Content-Type: application/json');
    echo json_encode(['output' => htmlspecialchars($out)]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>✈ dump1090-fa · ADS-B</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0a0e14; --surface: #111720; --border: #1e2d3d;
    --green: #00ff9f; --red: #ff4560; --cyan: #00d4ff;
    --amber: #ffb300; --text: #a8b9cc; --text-dim: #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-ui: 'Rajdhani', sans-serif;
    --font-orb: 'Orbitron', monospace;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

.ex-header { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1.4rem; background: var(--surface); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.ex-title { font-family: var(--font-orb); font-size: 1rem; font-weight: 700; color: var(--cyan); letter-spacing: .1em; text-transform: uppercase; }
.ex-subtitle { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); letter-spacing: .1em; margin-top: .15rem; }
.ex-btns { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; }

.btn-ex { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; border-radius: 4px; padding: .28rem .85rem; cursor: pointer; transition: background .2s, opacity .2s; border: 1px solid; background: transparent; }
.btn-ex:disabled { opacity: .4; cursor: not-allowed; }
.btn-cyan:hover:not(:disabled)  { background: rgba(0,212,255,.1); }
.btn-green:hover:not(:disabled) { background: rgba(0,255,159,.1); }
.btn-red:hover:not(:disabled)   { background: rgba(255,69,96,.15); }
.btn-cyan  { color: var(--cyan);  border-color: var(--cyan);  }
.btn-green { color: var(--green); border-color: var(--green); }
.btn-red   { color: var(--red);   border-color: var(--red);   }
.btn-dim   { color: var(--text-dim); border-color: #1e2d3d; }
.btn-active { background: rgba(0,212,255,.15) !important; border-color: var(--cyan) !important; color: var(--cyan) !important; }

/* Toggle switch */
.sw { position: relative; width: 56px; height: 28px; flex-shrink: 0; cursor: pointer; }
.sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid #f00; transition: background .3s, border-color .3s; }
.sw-knob  { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #f00; transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s, box-shadow .3s; }
.sw input:checked ~ .sw-track { border-color: #00ff4c; }
.sw input:checked ~ .sw-knob  { transform: translateX(28px); background: #00ff4c; box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw-busy-dot { display: none; position: absolute; top: 50%; right: -20px; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--amber); border-top-color: transparent; animation: spin .7s linear infinite; }
.sw.busy .sw-busy-dot { display: block; }
@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

/* Status bar */
.ex-status { display: flex; align-items: center; gap: 1.2rem; padding: .45rem 1.4rem; background: rgba(0,0,0,.3); border-bottom: 1px solid var(--border); flex-shrink: 0; font-family: var(--font-mono); font-size: .72rem; flex-wrap: wrap; }
.dot-status { width: 8px; height: 8px; border-radius: 50%; background: var(--text-dim); display: inline-block; margin-right: .4rem; transition: background .4s, box-shadow .4s; }
.dot-status.on  { background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 2s infinite; }
.dot-status.err { background: var(--red);   box-shadow: 0 0 6px var(--red); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.sep { color: var(--border); }

/* Tabs */
.ex-tabs { display: flex; gap: .4rem; padding: .6rem 1.4rem; background: var(--surface); border-bottom: 1px solid var(--border); flex-shrink: 0; }

/* Content */
.ex-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.tab-pane { flex: 1; display: none; flex-direction: column; overflow: hidden; }
.tab-pane.active { display: flex; }

/* Terminal */
.xterm-out { font-family: var(--font-mono); font-size: .75rem; color: #7a9ab5; background: #060c10; padding: 1rem 1.4rem; flex: 1; overflow-y: auto; white-space: pre-wrap; word-break: break-all; line-height: 1.55; }
.xterm-out::-webkit-scrollbar { width: 4px; }
.xterm-out::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.xterm-row { display: flex; align-items: center; gap: .5rem; background: #060c10; border-top: 1px solid var(--border); padding: .5rem 1.4rem; flex-shrink: 0; }
.xterm-pr  { font-family: var(--font-mono); font-size: .78rem; color: #00ff9f; white-space: nowrap; }
.xterm-inp { flex: 1; background: transparent; border: none; outline: none; font-family: var(--font-mono); font-size: .78rem; color: #c9d1d9; caret-color: #00ff9f; }
.xt-cmd { color: #c9d1d9; }
.xt-out { color: #7a9ab5; }
.xt-err { color: #f85149; }
.xt-ok  { color: var(--green); }

/* Mapa */
#dump1090MapFrame { width: 100%; height: 100%; border: none; background: #000; flex: 1; }

/* Tabla aviones */
.ac-toolbar { display: flex; align-items: center; gap: 1rem; padding: .5rem 1.4rem; background: rgba(0,0,0,.3); border-bottom: 1px solid var(--border); flex-shrink: 0; font-family: var(--font-mono); font-size: .72rem; flex-wrap: wrap; }
.ac-counter { color: var(--amber); }
.ac-updated { color: var(--text-dim); margin-left: auto; }
.ac-wrap { flex: 1; overflow-y: auto; }
.ac-wrap::-webkit-scrollbar { width: 4px; }
.ac-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

table.ac-table { width: 100%; border-collapse: collapse; font-family: var(--font-mono); font-size: .76rem; }
table.ac-table thead { position: sticky; top: 0; z-index: 2; }
table.ac-table thead tr { background: #0d1520; border-bottom: 2px solid var(--border); }
table.ac-table thead th { padding: .5rem .8rem; text-align: left; color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase; font-size: .65rem; white-space: nowrap; }
table.ac-table thead th.r { text-align: right; }
table.ac-table tbody tr { border-bottom: 1px solid rgba(30,45,61,.5); transition: background .15s; }
table.ac-table tbody tr:hover { background: rgba(0,212,255,.04); }
table.ac-table tbody tr.ac-active { background: rgba(0,255,159,.05); }
table.ac-table tbody tr.ac-stale  { opacity: .45; }
table.ac-table td { padding: .45rem .8rem; white-space: nowrap; vertical-align: middle; }
table.ac-table td.r { text-align: right; }

.col-hex    { color: var(--text-dim); font-size: .7rem; }
.col-flight { color: var(--cyan); font-weight: bold; letter-spacing: .05em; }
.col-alt    { color: var(--amber); }
.col-spd    { color: var(--green); }
.col-squawk { color: #d4a8ff; font-size: .7rem; }
.col-coord  { color: #7a9ab5; }
.col-msgs   { color: var(--text-dim); font-size: .7rem; }

.rssi-bar-wrap { display: flex; align-items: center; gap: .4rem; }
.rssi-bar { height: 6px; border-radius: 2px; background: var(--green); min-width: 2px; transition: width .3s; }
.rssi-bar.med { background: var(--amber); }
.rssi-bar.low { background: var(--red); }

.ac-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 1.5s infinite; margin-right: .35rem; }
.ac-empty { display: flex; align-items: center; justify-content: center; flex: 1; font-family: var(--font-mono); font-size: .8rem; color: var(--text-dim); padding: 2rem; text-align: center; }

/* Launch card */
.launch-card { margin: 2rem auto; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 2rem 2.5rem; max-width: 480px; text-align: center; }
.launch-icon  { font-size: 3rem; margin-bottom: 1rem; }
.launch-title { font-family: var(--font-orb); font-size: 1.1rem; color: var(--cyan); letter-spacing: .08em; margin-bottom: .6rem; }
.launch-desc  { font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim); line-height: 1.6; margin-bottom: 1.5rem; }
.launch-params { text-align: left; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem 1rem; margin-bottom: 1.5rem; font-family: var(--font-mono); font-size: .72rem; color: var(--amber); line-height: 1.7; }
</style>
</head>
<body>

<!-- Header -->
<header class="ex-header">
    <div>
        <div class="ex-title">✈ dump1090-fa · ADS-B Receiver</div>
        <div class="ex-subtitle">SDR · FlightAware · dump1090-fa.service</div>
    </div>
    <div class="ex-btns">
        <label class="sw" id="swDump" title="Iniciar / Parar dump1090-fa.service">
            <input type="checkbox" id="chkDump" onchange="toggleDump1090(this)">
            <span class="sw-track"></span>
            <span class="sw-knob"></span>
            <span class="sw-busy-dot"></span>
        </label>
        <span id="swLabel" style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);letter-spacing:.08em;text-transform:uppercase;min-width:2rem;">OFF</span>
        <button class="btn-ex btn-green" onclick="fetchDump1090Log()">⟳ Log</button>
        <button class="btn-ex btn-red"   onclick="cerrarVentana()">✖ Cerrar</button>
    </div>
</header>

<!-- Status bar -->
<div class="ex-status">
    <span><span class="dot-status" id="dotStatus"></span><span id="statusTxt">Comprobando servicio…</span></span>
    <span class="sep">|</span>
    <span style="color:var(--text-dim)">Servicio: <span id="svcStatus" style="color:var(--amber)">—</span></span>
    <span class="sep">|</span>
    <span style="color:var(--text-dim)">PID: <span id="svcPid" style="color:var(--cyan)">—</span></span>
    <span class="sep">|</span>
    <span style="color:var(--text-dim)">JSON: <span style="color:var(--green)">/run/dump1090-fa/aircraft.json</span></span>
</div>

<!-- Tabs -->
<div class="ex-tabs">
    <button id="tabBtnLog" class="btn-ex btn-active" onclick="switchTab('log')">📋 Terminal</button>
    <button id="tabBtnAc"  class="btn-ex btn-dim"    onclick="switchTab('ac')">✈ Aviones</button>
    <button id="tabBtnMap" class="btn-ex btn-dim"    onclick="switchTab('map')">🗺 Mapa en vivo</button>
</div>

<!-- Contenido -->
<div class="ex-content">

    <!-- Tab Terminal -->
    <div id="paneLog" class="tab-pane active">
        <div id="launchCard" class="launch-card">
            <div class="launch-icon">✈</div>
            <div class="launch-title">dump1090-fa · ADS-B</div>
            <div class="launch-desc">Activa el toggle para arrancar <strong>dump1090-fa.service</strong> y recibir tráfico aéreo en tiempo real.</div>
            <div class="launch-params">
                ⚙ Servicio: dump1090-fa.service<br>
                📝 Log:     journalctl -u dump1090-fa<br>
                📄 JSON:    /run/dump1090-fa/aircraft.json<br>
                🌐 Mapa:    http://[IP]:8080
            </div>
        </div>
        <div id="terminalWrap" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
            <div class="xterm-out" id="xtOut">pi@raspberry:~$ Terminal lista
</div>
            <div class="xterm-row">
                <span class="xterm-pr" id="xtPr">pi@raspberry:~$</span>
                <input id="xtInp" class="xterm-inp" autocomplete="off" spellcheck="false" placeholder="escribe un comando…">
            </div>
        </div>
    </div>

    <!-- Tab Aviones -->
    <div id="paneAc" class="tab-pane">
        <div class="ac-toolbar">
            <span>✈ Aeronaves: <span class="ac-counter" id="acCount">—</span></span>
            <span class="sep">|</span>
            <span style="color:var(--text-dim)">Con posición: <span style="color:var(--green)" id="acWithPos">—</span></span>
            <span class="sep">|</span>
            <span style="color:var(--text-dim)">En tierra: <span style="color:var(--amber)" id="acOnGround">—</span></span>
            <span class="ac-updated" id="acUpdated">—</span>
        </div>
        <div class="ac-wrap">
            <div class="ac-empty" id="acEmpty">Esperando datos de dump1090-fa…</div>
            <table class="ac-table" id="acTable" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:22px;"></th>
                        <th>Hex</th>
                        <th>Vuelo</th>
                        <th>Squawk</th>
                        <th class="r">Alt (ft)</th>
                        <th class="r">Vel (kt)</th>
                        <th class="r">Rumbo</th>
                        <th class="r">Lat</th>
                        <th class="r">Lon</th>
                        <th>RSSI</th>
                        <th class="r">Msgs</th>
                    </tr>
                </thead>
                <tbody id="acBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Tab Mapa -->
    <div id="paneMap" class="tab-pane">
        <iframe id="dump1090MapFrame" src=""></iframe>
    </div>

</div>

<script>
const mapUrl = 'http://' + window.location.hostname + ':8080';
const jsonUrl = '?action=aircraft-json';
let logPollInterval = null;
let acPollInterval  = null;

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function setStatus(state, txt) {
    document.getElementById('dotStatus').className = 'dot-status ' + (state==='on'?'on':state==='err'?'err':'');
    document.getElementById('statusTxt').textContent = txt;
}
function setSwitch(on) {
    document.getElementById('chkDump').checked = on;
    const lbl = document.getElementById('swLabel');
    lbl.textContent = on ? 'ON' : 'OFF';
    lbl.style.color  = on ? 'var(--green)' : 'var(--text-dim)';
}
function updateStatusBar(d) {
    const el = document.getElementById('svcStatus');
    el.textContent = d.status || '—';
    el.style.color  = d.active ? 'var(--green)' : 'var(--red)';
    document.getElementById('svcPid').textContent = (d.pid && d.pid !== '0') ? d.pid : '—';
}
function cerrarVentana() {
    window.close();
    setTimeout(() => {
        if (!window.closed) {
            if (window.history.length > 1) window.history.back();
            else document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:\'Share Tech Mono\',monospace;color:#00d4ff;font-size:1rem;">Puedes cerrar esta pestaña manualmente.</div>';
        }
    }, 300);
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
function switchTab(tab) {
    ['log','ac','map'].forEach(t => {
        document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1)).classList.remove('active');
        document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1)).className = 'btn-ex btn-dim';
    });
    document.getElementById('pane' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
    document.getElementById('tabBtn' + tab.charAt(0).toUpperCase() + tab.slice(1)).className = 'btn-ex btn-active';
    if (tab === 'ac') startAcPoll();
    if (tab === 'map') {
        const frame = document.getElementById('dump1090MapFrame');
        if (!frame.src || frame.src === 'about:blank') frame.src = mapUrl;
    }
}

// ── Toggle dump1090-fa.service ────────────────────────────────────────────────
async function toggleDump1090(chk) {
    const wasOn = !chk.checked;
    chk.checked = wasOn;
    const sw = document.getElementById('swDump');
    sw.classList.add('busy');
    document.getElementById('launchCard').style.display = 'none';
    document.getElementById('terminalWrap').style.display = 'flex';
    xtApp('<span class="xt-out">⏳ ' + (wasOn ? 'Parando' : 'Iniciando') + ' dump1090-fa.service…</span>');
    try {
        const r = await fetch('?action=' + (wasOn ? 'dump1090-stop' : 'dump1090-start'));
        const d = await r.json();
        if (d.ok) {
            const isNowOn = !wasOn;
            setSwitch(isNowOn);
            setStatus(isNowOn ? 'on' : '', isNowOn ? 'dump1090-fa.service activo' : 'dump1090-fa.service detenido');
            xtApp('<span class="xt-ok">✅ ' + esc(d.output || d.msg) + '</span>');
            if (isNowOn) startLogPoll(); else { stopLogPoll(); stopAcPoll(); }
        } else {
            xtApp('<span class="xt-err">❌ ' + esc(d.error || d.msg) + '</span>');
            setStatus('err', 'Error al cambiar estado');
        }
    } catch(e) {
        xtApp('<span class="xt-err">❌ Error de red: ' + esc(e.message) + '</span>');
        setStatus('err', 'Error de red');
    } finally {
        sw.classList.remove('busy');
        checkServiceStatus();
    }
}

// ── Estado del servicio ───────────────────────────────────────────────────────
async function checkServiceStatus() {
    try {
        const r = await fetch('?action=dump1090-status');
        const d = await r.json();
        setSwitch(d.active);
        updateStatusBar(d);
        if (d.active) {
            setStatus('on', 'dump1090-fa.service activo');
            document.getElementById('launchCard').style.display = 'none';
            document.getElementById('terminalWrap').style.display = 'flex';
            if (!logPollInterval) startLogPoll();
        } else {
            setStatus('', 'dump1090-fa.service inactivo');
            stopLogPoll(); stopAcPoll();
        }
    } catch(e) { setStatus('err', 'Error al comprobar el servicio'); }
}

// ── Log polling ───────────────────────────────────────────────────────────────
function startLogPoll() { stopLogPoll(); fetchDump1090Log(); logPollInterval = setInterval(fetchDump1090Log, 3000); }
function stopLogPoll()  { clearInterval(logPollInterval); logPollInterval = null; }
function fetchDump1090Log() {
    fetch('?action=dump1090-log&t=' + Date.now())
        .then(r => r.text())
        .then(text => { const o=document.getElementById('xtOut'); o.textContent=text; o.scrollTop=o.scrollHeight; });
}

// ── Aircraft polling ──────────────────────────────────────────────────────────
function startAcPoll() { if (acPollInterval) return; fetchAircraft(); acPollInterval = setInterval(fetchAircraft, 2000); }
function stopAcPoll()  { clearInterval(acPollInterval); acPollInterval = null; }

async function fetchAircraft() {
    try {
        const r = await fetch(jsonUrl + '&t=' + Date.now());
        const d = await r.json();
        if (d.error) {
            document.getElementById('acEmpty').textContent = '⚠ ' + d.error;
            document.getElementById('acEmpty').style.display = 'flex';
            document.getElementById('acTable').style.display = 'none';
            return;
        }
        renderAircraft(d);
    } catch(e) {
        document.getElementById('acEmpty').textContent = '⚠ Error: ' + e.message;
        document.getElementById('acEmpty').style.display = 'flex';
        document.getElementById('acTable').style.display = 'none';
    }
}

function rssiBar(rssi) {
    const val = parseFloat(String(rssi||'').replace('+','')) || -20;
    const pct = Math.max(0, Math.min(100, ((val + 20) / 19) * 100));
    const cls = pct > 60 ? '' : pct > 30 ? ' med' : ' low';
    return `<div class="rssi-bar-wrap"><div class="rssi-bar${cls}" style="width:${Math.round(pct*0.6)}px"></div><span style="font-size:.68rem;color:var(--text-dim)">${String(rssi||'—')}</span></div>`;
}

function hdgArrow(hdg) {
    if (hdg === undefined || hdg === null || hdg === '') return '—';
    return `<span style="display:inline-block;transform:rotate(${hdg}deg);font-size:1rem;line-height:1;">▲</span> ${Math.round(hdg)}°`;
}

function renderAircraft(data) {
    const now      = data.now || (Date.now() / 1000);
    const aircraft = (data.aircraft || []).sort((a,b) => (b.messages||0) - (a.messages||0));
    const total    = aircraft.length;
    const withPos  = aircraft.filter(a => a.lat !== undefined).length;
    const onGround = aircraft.filter(a => a.alt_baro === 'ground' || a.altitude === 'ground').length;

    document.getElementById('acCount').textContent    = total;
    document.getElementById('acWithPos').textContent  = withPos;
    document.getElementById('acOnGround').textContent = onGround;
    document.getElementById('acUpdated').textContent  = 'Actualizado: ' + new Date().toLocaleTimeString('es-ES');

    if (total === 0) {
        document.getElementById('acEmpty').textContent = 'Sin aeronaves detectadas…';
        document.getElementById('acEmpty').style.display = 'flex';
        document.getElementById('acTable').style.display = 'none';
        return;
    }
    document.getElementById('acEmpty').style.display = 'none';
    document.getElementById('acTable').style.display = 'table';

    document.getElementById('acBody').innerHTML = aircraft.map(a => {
        const seenAgo  = now - (a.seen || 0);
        const isActive = seenAgo < 5;
        const isStale  = seenAgo > 30;
        const dot      = isActive ? '<span class="ac-dot"></span>' : '';
        const rowCls   = isActive ? 'ac-active' : isStale ? 'ac-stale' : '';

        // dump1090-fa usa alt_baro en lugar de altitude
        const altVal  = a.alt_baro ?? a.altitude;
        const altHtml = altVal !== undefined
            ? (altVal === 'ground'
                ? '<span style="color:var(--green);font-size:.7rem;">TIERRA</span>'
                : '<span class="col-alt">' + Number(altVal).toLocaleString() + '</span>')
            : '<span style="color:var(--text-dim)">—</span>';

        // dump1090-fa usa gs (ground speed) en lugar de speed
        const spd = a.gs ?? a.speed;
        // dump1090-fa usa track
        const trk = a.track;

        return `<tr class="${rowCls}">
            <td style="padding-left:1rem;">${dot}</td>
            <td class="col-hex">${esc((a.hex||'').toUpperCase())}</td>
            <td class="col-flight">${a.flight ? esc(a.flight.trim()) : '<span style="color:var(--text-dim)">—</span>'}</td>
            <td class="col-squawk">${a.squawk || '—'}</td>
            <td class="r">${altHtml}</td>
            <td class="r col-spd">${spd !== undefined ? Math.round(spd) : '—'}</td>
            <td class="r">${hdgArrow(trk)}</td>
            <td class="r col-coord">${a.lat !== undefined ? a.lat.toFixed(4) : '—'}</td>
            <td class="r col-coord">${a.lon !== undefined ? a.lon.toFixed(4) : '—'}</td>
            <td>${rssiBar(a.rssi)}</td>
            <td class="r col-msgs">${a.messages || 0}</td>
        </tr>`;
    }).join('');
}

// ── Terminal interactivo ──────────────────────────────────────────────────────
let xtHist = [], xtHidx = -1, xtCwd = '/home/pi';
function xtPrStr() { return 'pi@raspberry:' + xtCwd.replace('/home/pi','~') + '$'; }
function xtApp(html) { const o=document.getElementById('xtOut'); o.innerHTML+=html+'\n'; o.scrollTop=o.scrollHeight; }

async function xtExec(cmd) {
    xtHist.unshift(cmd); xtHidx=-1;
    document.getElementById('xtInp').value='';
    xtApp('<span class="xt-cmd">'+esc(xtPrStr())+' '+esc(cmd)+'</span>');
    try {
        const resp = await fetch('?action=terminal',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'cmd='+encodeURIComponent('cd '+xtCwd+' && '+cmd)});
        const dat  = await resp.json();
        if (dat.output) xtApp('<span class="xt-out">'+dat.output+'</span>');
    } catch(err) { xtApp('<span class="xt-err">Error: '+esc(err.message)+'</span>'); }
    document.getElementById('xtPr').textContent = xtPrStr();
}

document.getElementById('xtInp').addEventListener('keydown', async function(e) {
    if (e.key==='ArrowUp')   { e.preventDefault(); if(xtHidx<xtHist.length-1) this.value=xtHist[++xtHidx]||''; return; }
    if (e.key==='ArrowDown') { e.preventDefault(); xtHidx>0?this.value=xtHist[--xtHidx]:(xtHidx=-1,this.value=''); return; }
    if (e.key!=='Enter') return;
    const cmd=this.value.trim(); if(!cmd) return;
    if (/^\s*clear\s*$/.test(cmd))  { document.getElementById('xtOut').innerHTML=''; this.value=''; return; }
    if (/^\s*(edit|nano)(\s+\S+)?\s*$/.test(cmd)) { xtApp('<span class="xt-err">Editor no disponible.</span>'); this.value=''; return; }
    if (/^\s*(sudo\s+su|su\s*$|top|htop|vim|vi|less|more)\s*/.test(cmd)) { xtApp('<span class="xt-err">Comando interactivo no soportado.</span>'); this.value=''; return; }
    if (/^\s*cd(\s|$)/.test(cmd)) {
        const t=cmd.replace(/^\s*cd\s*/,'').trim()||'~';
        if(t==='~'||t==='') xtCwd='/home/pi';
        else if(t.startsWith('/')) xtCwd=t;
        else if(t==='..'){const p=xtCwd.split('/').filter(Boolean);p.pop();xtCwd='/'+p.join('/')||'/';}
        else xtCwd=xtCwd.replace(/\/$/,'')+'/'+t;
        xtApp('<span class="xt-cmd">'+esc(xtPrStr())+' '+esc(cmd)+'</span>');
        xtHist.unshift(cmd); xtHidx=-1; this.value='';
        document.getElementById('xtPr').textContent=xtPrStr();
        return;
    }
    await xtExec(cmd);
});

// ── Init ──────────────────────────────────────────────────────────────────────
checkServiceStatus();
setInterval(checkServiceStatus, 10000);
</script>
</body>
</html>

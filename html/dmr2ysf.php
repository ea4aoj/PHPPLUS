<?php
// ============================================================================
// dmr2ysf_panel.php - Control de puente DMR ⇄ YSF (MODO DIRECTO)
// Captura de tráfico idéntica a mmdvm.php (vía journalctl) + Logs /tmp para paneles
// ============================================================================

require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

define('START_SCRIPT', '/usr/local/bin/dmr2ysf-start.sh');
define('STOP_SCRIPT',  '/usr/local/bin/dmr2ysf-stop.sh');

define('INI_MMDVM',   '/home/pi/MMDVMHost/MMDVMDMR2YSF.ini');
define('INI_DMR2YSF', '/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF.ini');
define('INI_YSFGW',   '/home/pi/YSFClients/YSFGateway/YSFGateway.ini');
define('INI_TGLIST',  '/home/pi/MMDVM_CM/DMR2YSF/TG-YSFList.txt');

define('PID_MMDVM',   '/tmp/MMDVMDMR2YSF.pid');
define('PID_D2Y',     '/tmp/DMR2YSF.pid');
define('PID_YSFGW',   '/tmp/YSFGateway.pid');

// ✅ Logs stdout para los paneles visuales
define('LOG_MMDVM',   '/tmp/MMDVMDMR2YSF.log');
define('LOG_D2Y',     '/tmp/DMR2YSF.log');
define('LOG_YSFGW',   '/tmp/YSFGateway.log');

$CONFIG_FILES = [
    'mmdvm'   => INI_MMDVM,
    'dmr2ysf' => INI_DMR2YSF,
    'ysf'     => INI_YSFGW,
    'tglist'  => INI_TGLIST
];

function getFlagByCall($callsign) {
    if (!$callsign) return '';
    $cs = strtoupper(trim($callsign));
    $flags = [
        ['re' => '/^E[ABCDEFGH][1-9]/', 'emoji' => '🇪🇸', 'code' => '1f1ea-1f1f8'],
        ['re' => '/^C[TUQ]/', 'emoji' => '🇵🇹', 'code' => '1f1f5-1f1f9'],
        ['re' => '/^F[A-Z]/', 'emoji' => '🇫🇷', 'code' => '1f1eb-1f1f7'],
        ['re' => '/^I[0-9]|^IK|^IW|^IZ/', 'emoji' => '🇮🇹', 'code' => '1f1ee-1f1f9'],
        ['re' => '/^G[0-9]|^M[0-9]|^2E|^GB|^MJ|^MU/', 'emoji' => '🇬🇧', 'code' => '1f1ec-1f1e7'],
        ['re' => '/^D[A-R]|^Y[2-9]/', 'emoji' => '🇩🇪', 'code' => '1f1e9-1f1ea'],
        ['re' => '/^[KWN][0-9]|^AA|^AB|^AC|^AD|^AE|^AF/', 'emoji' => '🇺🇸', 'code' => '1f1fa-1f1f8'],
        ['re' => '/^VE|^VA|^VO|^VY/', 'emoji' => '🇨🇦', 'code' => '1f1e8-1f1e6'],
        ['re' => '/^PY|^PU|^PV|^PW|^PX/', 'emoji' => '🇧🇷', 'code' => '1f1e7-1f1f7'],
        ['re' => '/^LU|^LV|^LW|^LX/', 'emoji' => '🇦🇷', 'code' => '1f1e6-1f1f7'],
        ['re' => '/^JA|^JE|^JF|^JG|^JH|^JI|^JJ|^JK|^JL|^JR/', 'emoji' => '🇯🇵', 'code' => '1f1ef-1f1f5'],
        ['re' => '/^VK/', 'emoji' => '🇦🇺', 'code' => '1f1e6-1f1fa'],
        ['re' => '/^ZS|^ZT|^ZU/', 'emoji' => '🇿🇦', 'code' => '1f1ff-1f1e6'],
        ['re' => '/^OH|^OG/', 'emoji' => '🇫🇮', 'code' => '1f1eb-1f1ee'],
        ['re' => '/^PA|^PB|^PC|^PD|^PE|^PF|^PG|^PH/', 'emoji' => '🇳🇱', 'code' => '1f1f3-1f1f1'],
        ['re' => '/^HB/', 'emoji' => '🇨🇭', 'code' => '1f1e8-1f1ed'],
        ['re' => '/^OE/', 'emoji' => '🇦🇹', 'code' => '1f1e6-1f1f9'],
        ['re' => '/^SP|^SQ|^SR|^HF/', 'emoji' => '🇵🇱', 'code' => '1f1f5-1f1f1'],
        ['re' => '/^UA|^UB|^UC|^UD|^UE|^UF|^RA|^RB|^RC/', 'emoji' => '🇷🇺', 'code' => '1f1f7-1f1fa'],
        ['re' => '/^SV|^SW|^SX|^SY|^SZ/', 'emoji' => '🇬🇷', 'code' => '1f1ec-1f1f7'],
        ['re' => '/^LY/', 'emoji' => '🇱🇹', 'code' => '1f1f1-1f1f9'],
        ['re' => '/^9A/', 'emoji' => '🇭🇷', 'code' => '1f1ed-1f1f7'],
    ];
    $isWin = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Windows') !== false;
    foreach ($flags as $f) {
        if (preg_match($f['re'], $cs)) {
            return $isWin 
                ? '<img class="flag-emoji-img" src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/'.$f['code'].'.png" alt="">'
                : '<span class="flag-emoji">'.$f['emoji'].'</span>';
        }
    }
    return '';
}

function saveState($key, $value) {
    $file = '/var/lib/mmdvm-state';
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    foreach ($lines as &$line) { if (strpos($line, $key . '=') === 0) { $line = $key . '=' . $value; $found = true; } }
    unset($line);
    if (!$found) $lines[] = $key . '=' . $value;
    @file_put_contents($file, implode("\n", $lines) . "\n");
}

function checkPid($pidFile, $binName) {
    clearstatcache(true, $pidFile);
    if (!file_exists($pidFile)) return 'inactive';
    $pid = trim(@file_get_contents($pidFile));
    if ($pid === '' || $pid === false) $pid = trim(shell_exec("cat " . escapeshellarg($pidFile) . " 2>/dev/null"));
    if (!ctype_digit($pid)) { @unlink($pidFile); return 'inactive'; }
    if (!is_dir("/proc/{$pid}")) { @unlink($pidFile); return 'inactive'; }
    $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
    if ($cmdline && strpos($cmdline, $binName) === false) { @unlink($pidFile); return 'inactive'; }
    return 'active';
}

function tailLive($path, $lines = 80) {
    if (!file_exists($path)) return '';
    return shell_exec("tail -n {$lines} " . escapeshellarg($path) . " 2>/dev/null");
}

function lookupCall($callsign) {
    $cs = strtoupper(trim($callsign));
    $datFiles = ['/home/pi/MMDVMHost/DMRIds.dat', '/etc/DMRIds.dat', '/usr/local/etc/DMRIds.dat'];
    foreach ($datFiles as $f) {
        if (!file_exists($f)) continue;
        $row = trim(shell_exec("awk -F'\t' '{if (toupper(\$2)==\"".$cs."\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' ".escapeshellarg($f)." 2>/dev/null"));
        if ($row !== '') {
            $parts = explode("\t", $row);
            return ['dmrid'=>trim($parts[0]??''), 'name'=>trim($parts[2]??'')];
        }
    }
    return ['dmrid'=>'', 'name'=>''];
}

function colorizeLog($text) {
    return implode("\n", array_map(function($l) {
        $ll = strtolower($l);
        if (preg_match('/error|fail|abort|exception|denied|segfault/i', $ll)) return '<span class="log-err">'.htmlspecialchars($l).'</span>';
        if (preg_match('/warn|warning|timeout/i', $ll)) return '<span class="log-warn">'.htmlspecialchars($l).'</span>';
        if (preg_match('/connect|start|open|loaded|success|tx|rx|linked|tg|ysf|slot|mode|sigterm|stopped/i', $ll)) return '<span class="log-ok">'.htmlspecialchars($l).'</span>';
        return '<span class="log-info">'.htmlspecialchars($l).'</span>';
    }, explode("\n", $text)));
}

// ============================================================================
// ROUTER AJAX
// ============================================================================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'status') {
    $mmd = checkPid(PID_MMDVM, 'MMDVMDMR2YSF');
    $d2y = checkPid(PID_D2Y,   'DMR2YSF');
    $ysf = checkPid(PID_YSFGW, 'YSFGateway');
    $perms = [];
    foreach ($CONFIG_FILES as $k => $p) $perms[$k] = ['exists' => file_exists($p), 'writable' => is_writable($p)];
    header('Content-Type: application/json');
    echo json_encode(['mmdvm'=>$mmd, 'dmr2ysf'=>$d2y, 'ysfgateway'=>$ysf, 'bridge_active'=>($mmd==='active'&&$d2y==='active'&&$ysf==='active'), 'perms'=>$perms, 'ts'=>time()]);
    exit;
}

if ($action === 'start') {
    saveState('dmr2ysf', 'on');
    $out = shell_exec('sudo ' . START_SCRIPT . ' 2>&1'); sleep(4);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true, 'msg'=>'Puente iniciado', 'log'=>trim($out)?:'Sin salida']); exit;
}

if ($action === 'stop') {
    saveState('dmr2ysf', 'off');
    $out = shell_exec('sudo ' . STOP_SCRIPT . ' 2>&1'); sleep(2);
    shell_exec('sudo pkill -9 -f DMR2YSF 2>/dev/null'); shell_exec('sudo rm -f /tmp/DMR2YSF.pid'); sleep(1);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true, 'msg'=>'Puente detenido', 'log'=>trim($out)?:'Sin salida']); exit;
}

if ($action === 'logs') {
    $n = intval($_GET['lines'] ?? 80);
    header('Content-Type: application/json');
    echo json_encode(['mmdvm'=>htmlspecialchars(tailLive(LOG_MMDVM,$n)?:''), 'dmr2ysf'=>htmlspecialchars(tailLive(LOG_D2Y,$n)?:''), 'ysf'=>htmlspecialchars(tailLive(LOG_YSFGW,$n)?:'')]);
    exit;
}

if ($action === 'cfg-read') {
    $id = $_POST['id'] ?? ''; $path = $CONFIG_FILES[$id] ?? null;
    if (!$path || !file_exists($path)) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'No encontrado']); exit; }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true, 'path'=>$path, 'content'=>file_get_contents($path), 'id'=>$id]); exit;
}

if ($action === 'cfg-save') {
    $id = $_POST['id'] ?? ''; $path = $CONFIG_FILES[$id] ?? null;
    if (!$path) { header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
    header('Content-Type: application/json'); echo json_encode(['ok'=>(file_put_contents($path, $_POST['content']??'')!==false)]); exit;
}

if ($action === 'restart-svc') {
    shell_exec('sudo '.STOP_SCRIPT.' >/dev/null 2>&1'); sleep(1); shell_exec('sudo '.START_SCRIPT.' >/dev/null 2>&1'); usleep(1000000);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

// ============================================================================
// ✅ CAPTURA DE TRÁFICO (CLON EXACTO DE TU mmdvm.php ADAPTADO)
// ============================================================================
if ($action === 'transmission') {
    $stateFile = '/tmp/mmdvm2ysf_tx_state.json';
    $lhFile    = '/tmp/mmdvm2ysf_lastheard.json';
    
    // ✅ Leemos EXACTAMENTE como tu mmdvm.php: vía journalctl
    $log = shell_exec("sudo journalctl -u MMDVMDMR2YSF -n 500 --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) {
        // Fallback al log nativo si no hay servicio systemd
        $logFile = glob('/home/pi/MMDVMHost/MMDVMDMR2YSF-*.log');
        if (!empty($logFile)) { rsort($logFile); $log = shell_exec("tail -n 500 ".escapeshellarg($logFile[0])." 2>/dev/null"); }
    }
    $lines = array_reverse(explode("\n", $log ?? ''));

    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','slot'=>'','source'=>''];
    if (file_exists($stateFile)) { $saved = json_decode(file_get_contents($stateFile), true); if (is_array($saved)) $state = $saved; }
    
    foreach ($lines as $line) {
        if (preg_match('/DMR Slot \d.*end of voice/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $cs = strtoupper(rtrim($m[3], ',')); $inf = lookupCall($cs);
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>$m[4],'slot'=>$m[1],'source'=>strtoupper($m[2])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
    }

    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $cs = strtoupper(rtrim($m[4], ','));
            if (!in_array($cs, $seen)) {
                $inf = lookupCall($cs);
                $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]];
                $seen[] = $cs; if (count($lastHeard) >= 5) break;
            }
        }
    }
    if (!empty($lastHeard)) file_put_contents($lhFile, json_encode($lastHeard));
    elseif (file_exists($lhFile)) $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];

    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json'); echo json_encode($state); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🔗 Puente DMR  YSF | Modo Directo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg:#0a0e14; --surface:#111720; --border:#1e2d3d; --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --violet:#b57aff; --text:#a8b9cc; --text-dim:#4a5568; --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif; --font-orb:'Orbitron',monospace; }
* { box-sizing:border-box; margin:0; padding:0; }
body { background:var(--bg); color:var(--text); font-family:var(--font-ui); font-size:1rem; min-height:100vh; line-height:1.5; }
.header { border-bottom:1px solid var(--border); padding:1rem 2rem; background:var(--surface); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
.header h1 { font-family:var(--font-ui); font-weight:700; font-size:1.4rem; letter-spacing:.08em; color:#e2eaf5; text-transform:uppercase; margin:0; }
.badge-direct { font-family:var(--font-mono); font-size:.7rem; background:rgba(181,122,255,.15); color:var(--violet); border:1px solid var(--violet); border-radius:4px; padding:.2rem .5rem; }
.nav-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
.btn-nav { font-family:var(--font-mono); font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; background:transparent; border-radius:4px; padding:.35rem .8rem; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; border:1px solid var(--border); color:var(--text); }
.btn-nav:hover { background:rgba(0,212,255,.1); border-color:var(--cyan); color:var(--cyan); }
.btn-nav.primary { background:rgba(181,122,255,.1); border-color:var(--violet); color:var(--violet); }
.btn-nav.primary:hover { background:rgba(181,122,255,.25); }
.container { max-width:1200px; margin:0 auto; padding:2rem; }
.status-bar { display:flex; gap:1.5rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center; padding:.8rem 1.2rem; background:var(--surface); border:1px solid var(--border); border-radius:6px; }
.s-item { display:flex; align-items:center; gap:.5rem; font-family:var(--font-mono); font-size:.8rem; }
.s-dot { width:10px; height:10px; border-radius:50%; background:var(--text-dim); transition:all .3s; }
.s-dot.on { background:var(--green); box-shadow:0 0 8px var(--green); }
.s-dot.off { background:var(--red); }
.s-label { color:var(--text-dim); text-transform:uppercase; letter-spacing:.08em; font-size:.75rem; }
.s-val { font-weight:600; color:var(--cyan); }
.s-val.on { color:var(--green); } .s-val.off { color:var(--red); }
.card { background:linear-gradient(135deg,var(--surface) 60%,#0d1e2a 100%); border:1px solid var(--border); border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; position:relative; overflow:hidden; }
.card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,transparent,var(--violet),var(--cyan),transparent); }
.c-title { font-family:var(--font-mono); font-size:.85rem; letter-spacing:.12em; text-transform:uppercase; color:var(--violet); margin-bottom:1.2rem; display:flex; align-items:center; gap:.5rem; }
.c-title::before { content:'▸'; color:var(--cyan); }
.toggle-row { display:flex; align-items:center; gap:1rem; padding:.5rem 0; margin-bottom:.5rem; }
.t-label { font-family:var(--font-mono); font-size:.9rem; letter-spacing:.06em; color:var(--text); text-transform:uppercase; flex:1; }
.t-status { font-family:var(--font-mono); font-size:.75rem; letter-spacing:.1em; color:var(--text-dim); min-width:3.5rem; text-align:right; }
.t-status.on { color:var(--green); } .t-status.off { color:var(--red); }
.sw { position:relative; width:60px; height:32px; flex-shrink:0; cursor:pointer; }
.sw input { opacity:0; width:0; height:0; position:absolute; }
.sw-track { position:absolute; inset:0; border-radius:4px; background:#1a2535; border:2px solid var(--red); transition:all .3s; }
.sw-knob { position:absolute; top:4px; left:4px; width:22px; height:22px; background:var(--red); border-radius:3px; transition:all .3s cubic-bezier(.4,0,.2,1); box-shadow:0 2px 6px rgba(0,0,0,.4); }
.sw input:checked ~ .sw-track { background:#1a2535; border-color:var(--green); }
.sw input:checked ~ .sw-knob { transform:translateX(28px); background:var(--green); box-shadow:0 0 10px rgba(0,255,159,.5); }
.sw.busy .sw-knob { animation:pulse-knob 1s infinite alternate; }
@keyframes pulse-knob { from{box-shadow:0 0 10px rgba(0,255,159,.5)} to{box-shadow:0 0 20px rgba(0,255,159,.9),0 0 30px rgba(181,122,255,.6)} }
.cfg-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.6rem; margin-top:1rem; }
.btn-cfg { font-family:var(--font-mono); font-size:.72rem; text-transform:uppercase; padding:.4rem .6rem; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text); cursor:pointer; transition:all .2s; text-align:center; }
.btn-cfg:hover:not(.muted) { background:rgba(255,179,0,.1); border-color:var(--amber); color:var(--amber); }
.btn-cfg.muted { color:var(--text-dim); border-color:var(--border); cursor:not-allowed; }
.logs { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-top:1.5rem; }
@media(max-width:900px){.logs{grid-template-columns:1fr;}}
.l-panel { background:var(--surface); border:1px solid var(--border); border-radius:6px; overflow:hidden; display:flex; flex-direction:column; }
.l-head { display:flex; align-items:center; justify-content:space-between; padding:.5rem 1rem; background:rgba(0,0,0,.25); border-bottom:1px solid var(--border); }
.l-title { font-family:var(--font-mono); font-size:.8rem; color:var(--violet); text-transform:uppercase; letter-spacing:.08em; }
.l-actions { display:flex; gap:.4rem; }
.btn-log { font-family:var(--font-mono); font-size:.7rem; color:var(--text-dim); background:none; border:none; cursor:pointer; padding:.2rem .5rem; border-radius:3px; transition:color .2s; }
.btn-log:hover { color:var(--text); }
/* ✅ LOGS FIJOS 300PX */
.l-out { 
    flex:1 !important; font-family:var(--font-mono) !important; font-size:.75rem !important; line-height:1.5 !important; color:#7a9ab5 !important; 
    padding:.8rem 1rem !important; height: 300px !important; max-height: 300px !important; 
    overflow-y: auto !important; overflow-x: hidden !important; white-space:pre-wrap !important; word-break:break-word !important; scroll-behavior: smooth;
}
.l-out::-webkit-scrollbar{width:4px;} .l-out::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px;}
.log-info{color:#7a9ab5;} .log-ok{color:var(--green);} .log-warn{color:var(--amber);} .log-err{color:var(--red);}
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:1000; align-items:center; justify-content:center; }
.modal.open { display:flex; }
.m-box { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1.5rem; width:900px; max-width:95vw; max-height:90vh; display:flex; flex-direction:column; gap:1rem; }
.m-title { font-family:var(--font-mono); font-size:.85rem; color:var(--cyan); letter-spacing:.1em; text-transform:uppercase; }
.m-path { font-family:var(--font-mono); font-size:.75rem; color:var(--amber); letter-spacing:.05em; word-break:break-all; }
.m-editor { flex:1; font-family:var(--font-mono); font-size:.8rem; color:#c9d1d9; background:#060c10; border:1px solid var(--border); border-radius:4px; padding:.8rem; resize:vertical; outline:none; line-height:1.5; min-height:300px; }
.m-editor:focus { border-color:var(--cyan); }
.m-msg { font-family:var(--font-mono); font-size:.75rem; padding:.4rem .8rem; border-radius:4px; display:none; border:1px solid; }
.m-msg.ok { color:var(--green); border-color:var(--green); background:rgba(0,255,159,.06); }
.m-msg.err { color:var(--red); border-color:var(--red); background:rgba(255,69,96,.06); }
.m-acts { display:flex; gap:.8rem; justify-content:flex-end; }
.footer { text-align:center; padding:1.5rem; color:var(--text-dim); font-family:var(--font-mono); font-size:.75rem; border-top:1px solid var(--border); margin-top:2rem; }
.tx-panel { min-height:120px; display:flex; align-items:center; justify-content:space-between; background:rgba(0,0,0,.2); border-radius:8px; padding:1rem 2rem; font-family:var(--font-mono); position:relative; overflow:hidden; }
.tx-panel::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(0,212,255,.05),transparent 60%); pointer-events:none; }
.tx-panel.active { border:2px solid var(--green); box-shadow:0 0 20px rgba(0,255,159,.3); }
.tx-info { display:flex; flex-direction:column; align-items:flex-start; gap:.3rem; z-index:1; }
.tx-callsign { font-family:var(--font-orb); font-size:2.8rem; font-weight:900; color:var(--green); text-shadow:0 0 15px rgba(0,255,159,.5); letter-spacing:.04em; display:flex; align-items:center; gap:.5rem; }
.flag-emoji { font-family:'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif; font-size:2.4rem; line-height:1; }
.flag-emoji-img { height:36px; width:auto; vertical-align:middle; border-radius:3px; }
.tx-name { font-family:var(--font-ui); font-size:1.3rem; color:var(--cyan); font-weight:500; }
.tx-meta { display:flex; gap:1rem; align-items:center; font-size:.9rem; margin-top:.3rem; }
.tx-badge { padding:.2rem .6rem; border-radius:4px; font-weight:bold; }
.tx-badge.rf { background:rgba(0,255,159,.15); color:var(--green); border:1px solid rgba(0,255,159,.3); }
.tx-badge.net { background:rgba(0,212,255,.15); color:var(--cyan); border:1px solid rgba(0,212,255,.3); }
.tx-dest { color:var(--amber); }
.tx-time { color:var(--text-dim); }
.vu-container { display:flex; flex-direction:column; gap:2px; z-index:1; }
.vu-bar { width:8px; height:6px; border-radius:1px; background:#1a2535; transition:background .08s; }
.vu-bar.lit-g { background:var(--green); box-shadow:0 0 4px var(--green); }
.vu-bar.lit-a { background:var(--amber); box-shadow:0 0 4px var(--amber); }
.vu-bar.lit-r { background:var(--red); box-shadow:0 0 4px var(--red); }
.vu-right { flex-direction:column-reverse; }
.tx-idle { color:var(--text-dim); font-size:1rem; width:100%; text-align:center; }
.lh-table { width:100%; border-collapse:collapse; font-family:var(--font-mono); font-size:.8rem; }
.lh-table th { text-align:left; padding:.5rem .8rem; background:rgba(0,0,0,.25); color:var(--text-dim); text-transform:uppercase; letter-spacing:.08em; font-size:.7rem; border-bottom:1px solid var(--border); }
.lh-table td { padding:.45rem .8rem; border-bottom:1px solid rgba(30,45,61,.5); color:var(--text); }
.lh-table tr:hover td { background:rgba(181,122,255,.05); }
.tx-row td { background:rgba(0,255,159,.08); }
.lh-cs { color:var(--violet); font-weight:bold; letter-spacing:.04em; display:flex; align-items:center; gap:.3rem; }
.lh-empty { text-align:center; color:var(--text-dim); padding:1rem !important; }
</style>
</head>
<body>
<header class="header">
    <div style="display:flex;align-items:center;gap:1rem;">
        <a href="mmdvm.php" class="btn-nav">← Panel Principal</a>
        <h1>🔗 Puente DMR ⇄ YSF</h1>
        <span class="badge-direct">MODO DIRECTO</span>
    </div>
    <div class="nav-actions">
        <button class="btn-nav primary" onclick="forceRefresh()">🔄 Refresco</button>
    </div>
</header>

<main class="container">
    <div class="status-bar">
        <div class="s-item"><span class="s-dot" id="dot-mmd"></span><span class="s-label">MMDVMDMR2YSF:</span><span class="s-val" id="val-mmd">—</span></div>
        <div class="s-item"><span class="s-dot" id="dot-d2y"></span><span class="s-label">DMR2YSF:</span><span class="s-val" id="val-d2y">—</span></div>
        <div class="s-item"><span class="s-dot" id="dot-ysf"></span><span class="s-label">YSFGateway:</span><span class="s-val" id="val-ysf">—</span></div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:.5rem;">
            <span class="s-label">Actualizado:</span><span class="s-val" id="ts" style="color:var(--amber)">—</span>
        </div>
    </div>

    <div class="card">
        <div class="c-title">Control Principal del Puente</div>
        <div class="toggle-row">
            <span class="t-label">Arrancar / Detener los 3 sistemas</span>
            <label class="sw" id="sw"><input type="checkbox" id="chk" onchange="toggle(this)"><span class="sw-track"></span><span class="sw-knob"></span></label>
            <span class="t-status" id="sts">OFF</span>
        </div>
        <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--text-dim);margin:.5rem 0 1rem;">
            <span style="color:var(--violet);">ℹ️</span> MMDVMHost ⇄ DMR2YSF ⇄ YSFGateway. Sin DMRGateway intermedio.
        </div>
        <div class="cfg-row">
            <button class="btn-cfg" onclick="openCfg('mmdvm')">📄 MMDVMDMR2YSF.ini</button>
            <button class="btn-cfg" onclick="openCfg('dmr2ysf')">📄 DMR2YSF.ini</button>
            <button class="btn-cfg" onclick="openCfg('ysf')">📄 YSFGateway.ini</button>
            <button class="btn-cfg" onclick="openCfg('tglist')">📋 TG-YSFList.txt</button>
        </div>
    </div>

    <div class="card">
        <div class="c-title">📡 Transmisión Activa</div>
        <div id="txDisplay" class="tx-panel">
            <div class="tx-idle">⏸ Canal libre - Esperando actividad...</div>
        </div>
    </div>

    <div class="card">
        <div class="c-title">📋 Últimas Estaciones Escuchadas</div>
        <table class="lh-table">
            <thead><tr><th>Indicativo</th><th>Nombre</th><th>TG</th><th>Hora</th><th>Origen</th></tr></thead>
            <tbody id="lhBody"><tr><td colspan="5" class="lh-empty">Sin actividad reciente</td></tr></tbody>
        </table>
    </div>

    <div class="logs">
        <div class="l-panel">
            <div class="l-head"><span class="l-title">📋 MMDVMDMR2YSF</span><div class="l-actions"><button class="btn-log" onclick="fetchLogs()">🔄</button><button class="btn-log" onclick="clearLog('lMmd')">🗑</button></div></div>
            <div class="l-out" id="lMmd">Esperando…</div>
        </div>
        <div class="l-panel">
            <div class="l-head"><span class="l-title" style="color:#c9a0ff">📋 DMR2YSF</span><div class="l-actions"><button class="btn-log" onclick="fetchLogs()">🔄</button><button class="btn-log" onclick="clearLog('lD2Y')">🗑</button></div></div>
            <div class="l-out" id="lD2Y">Esperando…</div>
        </div>
        <div class="l-panel">
            <div class="l-head"><span class="l-title" style="color:var(--green)">📋 YSFGateway</span><div class="l-actions"><button class="btn-log" onclick="fetchLogs()">🔄</button><button class="btn-log" onclick="clearLog('lYsf')">🗑</button></div></div>
            <div class="l-out" id="lYsf">Esperando…</div>
        </div>
    </div>
</main>

<div id="cfgModal" class="modal" onclick="if(event.target===this)closeCfg()">
    <div class="m-box">
        <div class="m-title">✏️ Editor de Configuración</div>
        <div class="m-path" id="cfgPath">—</div>
        <textarea class="m-editor" id="cfgArea" spellcheck="false"></textarea>
        <div class="m-msg" id="cfgMsg"></div>
        <div class="m-acts">
            <button class="btn-act stop" onclick="closeCfg()">✖ Cerrar</button>
            <button class="btn-act start" onclick="saveCfg()">💾 Guardar</button>
        </div>
    </div>
</div>

<footer class="footer">🔗 Panel DMR⇄YSF Directo | <a href="mmdvm.php" style="color:var(--cyan)">Volver al panel principal</a></footer>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const fmtT = ts => new Date(ts*1000).toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
const api = async (a, p={}, m='GET') => {
    const u = new URL(location.href); u.searchParams.set('action',a);
    const o = { method:m, headers:{'Content-Type':'application/x-www-form-urlencoded'} };
    if(m==='POST' && Object.keys(p).length) o.body = new URLSearchParams(p).toString();
    const r = await fetch(u.toString(),o);
    if(!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
};

let S = { active:false, poll:null, logT:null, txT:null, last:null, busy:false, cfgId:null };
let vuTimer = null;

function setDot(id, v) { const e=$(id); const vEl=$(id.replace('dot-','val-')); if(!e||!vEl)return; e.className='s-dot '+(v==='active'?'on':'off'); vEl.textContent=v==='active'?'ON':'OFF'; vEl.className='s-val '+(v==='active'?'on':'off'); }
function setToggle(on, busy=false) {
    const chk=$('chk'), sts=$('sts'), sw=$('sw');
    if (!busy) { chk.checked = on; sts.textContent = on ? 'ON' : 'OFF'; sts.className = 'toggle-status ' + (on ? 'on' : 'off'); }
    sw.classList.toggle('busy', busy); S.busy = busy;
}

function animateVU(on) {
    clearInterval(vuTimer);
    const left = document.querySelectorAll('#vuLeft .vu-bar');
    const right = document.querySelectorAll('#vuRight .vu-bar');
    [...left, ...right].forEach(bar => bar.className = 'vu-bar');
    if (!on) return;
    vuTimer = setInterval(() => {
        const lvl = Math.floor(Math.random() * 16) + 1;
        [...left, ...right].forEach((bar, i) => {
            let cls = 'vu-bar';
            if (i < lvl) cls += i < 10 ? ' lit-g' : i < 14 ? ' lit-a' : ' lit-r';
            bar.className = cls;
        });
    }, 80);
}

async function status() {
    try {
        const d = await api('status');
        setDot('dot-mmd', d.mmdvm); setDot('dot-d2y', d.dmr2ysf); setDot('dot-ysf', d.ysfgateway);
        const newActive = d.bridge_active;
        if (!S.busy && newActive !== S.active) setToggle(newActive, false);
        S.active = newActive; $('ts').textContent=fmtT(d.ts);
        document.querySelectorAll('.btn-cfg').forEach(btn=>{
            const id=btn.getAttribute('onclick').match(/'([^']+)'/)[1];
            if(d.perms[id]) btn.classList.toggle('muted', !d.perms[id].writable);
        });
    } catch(e){ console.warn('status err',e); }
}

async function fetchLogs() {
    try {
        const d = await api('logs',{lines:80});
        const panels = {lMmd: d.mmdvm, lD2Y: d.dmr2ysf, lYsf: d.ysf};
        for(let [id, txt] of Object.entries(panels)) {
            const el = $(id);
            const atBot = el.scrollHeight - el.clientHeight <= el.scrollTop + 20;
            el.innerHTML = txt ? colorizeLog(txt) : '<span class="log-info">Sin salida en vivo</span>';
            if(atBot || txt) el.scrollTop = el.scrollHeight;
        }
    } catch(e){ console.warn('logs err',e); }
}

async function fetchTransmission() {
    try {
        const r = await api('transmission');
        const d = r.state || {};
        const display = $('txDisplay');
        if (d.active && d.callsign) {
            display.classList.add('active'); animateVU(true);
            const flag = getFlagByCall(d.callsign);
            display.innerHTML = `
                <div class="tx-info">
                    <span class="tx-callsign">${flag}${esc(d.callsign)}</span>
                    ${d.name ? `<div class="tx-name">${esc(d.name)}</div>` : ''}
                    <div class="tx-meta">
                        <span class="tx-badge ${d.source==='RF'?'rf':'net'}">${d.source||'—'}</span>
                        ${d.tg ? `<span class="tx-dest">→ TG ${esc(d.tg)}</span>` : ''}
                        <span class="tx-time">${esc(d.time||'')}</span>
                    </div>
                </div>
                <div class="vu-container" id="vuLeft"></div>
                <div class="vu-container vu-right" id="vuRight"></div>
            `;
            for(let i=0; i<18; i++) { $('<div class="vu-bar"></div>').appendTo('#vuLeft'); $('<div class="vu-bar"></div>').appendTo('#vuRight'); }
        } else {
            display.classList.remove('active'); animateVU(false);
            display.innerHTML = '<div class="tx-idle">⏸ Canal libre - Esperando actividad...</div>';
        }
        const tbody = $('lhBody');
        const list = r.lastHeard || [];
        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="lh-empty">Sin actividad reciente</td></tr>';
        } else {
            tbody.innerHTML = list.map(r => {
                const isTx = d.active && r.callsign === d.callsign;
                const flag = getFlagByCall(r.callsign);
                return `<tr class="${isTx?'tx-row':''}">
                    <td class="lh-cs">${flag}${esc(r.callsign)}</td>
                    <td>${esc(r.name||'—')}</td>
                    <td>TG ${esc(r.tg||'—')}</td>
                    <td>${esc(r.time||'—')}</td>
                    <td><span class="tx-badge ${r.source==='RF'?'rf':'net'}">${r.source||'—'}</span></td>
                </tr>`;
            }).join('');
        }
    } catch(e){ console.warn('tx err',e); }
}

async function toggle(chk) {
    const target = chk.checked; setToggle(target, true);
    try {
        const res = await api(target ? 'start' : 'stop', {}, 'POST');
        if (!res.ok) { alert('❌ ' + res.msg); setToggle(!target, false); return; }
        await new Promise(r => setTimeout(r, 3000));
        await status(); fetchLogs(); fetchTransmission();
        if (target === false) ['lMmd','lD2Y','lYsf'].forEach(id => $(id).innerHTML = '<span class="log-info">Logs limpiados.</span>');
    } catch (e) { console.error(e); setToggle(!target, false); alert('⚠️ Error: ' + e.message); }
}

async function openCfg(id) {
    S.cfgId=id; const m=$('cfgModal'), a=$('cfgArea'), p=$('cfgPath'), msg=$('cfgMsg');
    msg.style.display='none'; a.disabled=true; m.classList.add('open');
    try { const d=await api('cfg-read',{id},'POST'); if(d.ok){ p.textContent=d.path; a.value=d.content; a.disabled=false; a.focus(); } else { p.textContent='Error'; msg.className='m-msg err'; msg.textContent='✖ '+d.msg; msg.style.display='block'; } } catch(e){ msg.className='m-msg err'; msg.textContent='✖ '+e.message; msg.style.display='block'; }
}
function closeCfg(){$('cfgModal').classList.remove('open'); S.cfgId=null;}
async function saveCfg() {
    if(!S.cfgId)return;
    const msg=$('cfgMsg'); msg.style.display='block'; msg.className='m-msg'; msg.textContent=' Guardando…';
    try { const d=await api('cfg-save',{id:S.cfgId,content:$('cfgArea').value},'POST'); msg.className='m-msg '+(d.ok?'ok':'err'); msg.textContent=(d.ok?'✅ ':'❌ ')+d.msg; if(d.ok) setTimeout(closeCfg,1500); } catch(e){ msg.className='m-msg err'; msg.textContent='✖ '+e.message; }
}

const clearLog=id=>{ if(!confirm('¿Limpiar logs?'))return; $(id).innerHTML='<span class="log-info">Limpiado.</span>'; };
const forceRefresh=()=>{status(); fetchLogs(); fetchTransmission();};
function colorizeLog(text) {
    return text.split('\n').map(l => {
        const ll = l.toLowerCase();
        if (/error|fail|abort|exception|denied|segfault/i.test(ll)) return `<span class="log-err">${l}</span>`;
        if (/warn|warning|timeout/i.test(ll)) return `<span class="log-warn">${l}</span>`;
        if (/connect|start|open|loaded|success|tx|rx|linked|tg|ysf|slot|mode|sigterm|stopped/i.test(ll)) return `<span class="log-ok">${l}</span>`;
        return `<span class="log-info">${l}</span>`;
    }).join('\n');
}

function startPoll(){ status(); fetchLogs(); fetchTransmission(); S.poll=setInterval(status,5000); S.logT=setInterval(fetchLogs,7000); S.txT=setInterval(fetchTransmission,2500); }
function stopPoll(){ clearInterval(S.poll); clearInterval(S.logT); clearInterval(S.txT); clearInterval(vuTimer); }
document.addEventListener('keydown',e=>{ if(e.key==='Escape' && $('cfgModal').classList.contains('open')) closeCfg(); });
window.addEventListener('load', startPoll);
</script>
</body>
</html>

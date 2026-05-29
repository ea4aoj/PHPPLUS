<?php
// ============================================================================
// dmr2ysf_panel.php - Control de puente DMR ⇄ YSF (MODO DIRECTO)
// Con: RF/NET detection, banderas por país, VU meters animados
// ============================================================================

require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Rutas de Scripts ──
define('START_SCRIPT', '/usr/local/bin/dmr2ysf-start.sh');
define('STOP_SCRIPT',  '/usr/local/bin/dmr2ysf-stop.sh');

// ── Archivos de Configuración ─
define('INI_MMDVM',   '/home/pi/MMDVMHost/MMDVMDMR2YSF.ini');
define('INI_DMR2YSF', '/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF.ini');
define('INI_YSFGW',   '/home/pi/YSFClients/YSFGateway/YSFGateway.ini');
define('INI_TGLIST',  '/home/pi/MMDVM_CM/DMR2YSF/TG-YSFList.txt');

// ─ Archivos PID ──
define('PID_MMDVM',   '/tmp/MMDVMDMR2YSF.pid');
define('PID_D2Y',     '/tmp/DMR2YSF.pid');
define('PID_YSFGW',   '/tmp/YSFGateway.pid');

// ── Logs en vivo ──
define('LOG_MMDVM',   '/tmp/MMDVMDMR2YSF.log');
define('LOG_D2Y',     '/tmp/DMR2YSF.log');
define('LOG_YSFGW',   '/tmp/YSFGateway.log');

$CONFIG_FILES = [
    'mmdvm'   => INI_MMDVM,
    'dmr2ysf' => INI_DMR2YSF,
    'ysf'     => INI_YSFGW,
    'tglist'  => INI_TGLIST
];

// ── Funciones auxiliares ─
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
    $pid = trim(file_get_contents($pidFile));
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

// 🌍 Bandera por prefijo de callsign
function getFlagInfo($callsign) {
    $prefixes = [
        'EA'=>['ESP','🇪🇸'],'EB'=>['ESP','🇪🇸'],'EC'=>['ESP','🇪🇸'],'ED'=>['ESP','🇪🇸'],'EE'=>['ESP','🇪🇸'],'EF'=>['ESP','🇪🇸'],
        'F'=>['FRA','🇫🇷'],'FB'=>['FRA','🇫🇷'],'FC'=>['FRA','🇫🇷'],'FD'=>['FRA','🇫🇷'],'FE'=>['FRA','🇫🇷'],'FF'=>['FRA','🇫🇷'],
        'I'=>['ITA','🇮🇹'],'IZ'=>['ITA','🇮🇹'],'IW'=>['ITA','🇮🇹'],'IV'=>['ITA','🇮🇹'],'IX'=>['ITA','🇮🇹'],
        'G'=>['GBR','🇬🇧'],'M'=>['GBR','🇬🇧'],'2E'=>['GBR','🇬🇧'],'M6'=>['GBR','🇬🇧'],'M7'=>['GBR','🇬🇧'],
        'DL'=>['DEU','🇩🇪'],'DA'=>['DEU','🇩🇪'],'DB'=>['DEU','🇩🇪'],'DC'=>['DEU','🇩🇪'],'DD'=>['DEU','🇩🇪'],'DF'=>['DEU','🇩🇪'],
        'ON'=>['BEL','🇧🇪'],'OR'=>['BEL','🇧🇪'],'OT'=>['BEL','🇧🇪'],
        'PA'=>['NLD','🇳🇱'],'PB'=>['NLD','🇳🇱'],'PC'=>['NLD','🇳🇱'],'PD'=>['NLD','🇳🇱'],'PE'=>['NLD','🇳🇱'],'PF'=>['NLD','🇳🇱'],
        'OE'=>['AUT','🇦🇹'],
        'HB'=>['CHE','🇨🇭'],'HE'=>['CHE','🇨🇭'],
        'LY'=>['LTU','🇱🇹'],'ES'=>['EST','🇪🇪'],'YL'=>['LVA','🇱🇻'],
        'SP'=>['POL','🇵🇱'],'SQ'=>['POL','🇵🇱'],'SN'=>['POL','🇵🇱'],'SO'=>['POL','🇵🇱'],
        'OK'=>['CZE','🇨🇿'],'OM'=>['SVK','🇸🇰'],'HA'=>['HUN','🇭🇺'],
        'YO'=>['ROU','🇷🇴'],'YR'=>['ROU','🇷🇴'],
        'SV'=>['GRC','🇬🇷'],'SW'=>['GRC','🇬🇷'],'SX'=>['GRC','🇬🇷'],'SY'=>['GRC','🇬🇷'],'SZ'=>['GRC','🇬🇷'],
        'UA'=>['RUS','🇷🇺'],'UB'=>['RUS','🇷🇺'],'UC'=>['RUS','🇷🇺'],'UD'=>['RUS','🇷🇺'],'UE'=>['RUS','🇷🇺'],
        'UW'=>['UKR','🇺🇦'],'UX'=>['UKR','🇺🇦'],'UY'=>['UKR','🇺🇦'],'UZ'=>['UKR','🇺🇦'],
        'K'=>['USA','🇺🇸'],'N'=>['USA','🇺🇸'],'W'=>['USA','🇺🇸'],'AA'=>['USA','🇺🇸'],'AB'=>['USA','🇺🇸'],
        'VE'=>['VEN','🇻🇪'],'YV'=>['VEN','🇻🇪'],
        'PY'=>['BRA','🇧🇷'],'PU'=>['BRA','🇧🇷'],'PP'=>['BRA','🇧🇷'],'PQ'=>['BRA','🇧🇷'],'PR'=>['BRA','🇧🇷'],'PS'=>['BRA','🇧🇷'],'PT'=>['BRA','🇧🇷'],
        'CE'=>['CHL','🇨🇱'],'CA'=>['CHL','🇨🇱'],'CD'=>['CHL','🇨🇱'],
        'CX'=>['URY','🇺🇾'],'CW'=>['URY','🇺🇾'],
        'LV'=>['ARG','🇦🇷'],'LU'=>['ARG','🇦🇷'],'LW'=>['ARG','🇦🇷'],'LX'=>['ARG','🇦🇷'],
        'HC'=>['ECU','🇪🇨'],'HD'=>['ECU','🇪🇨'],
        'HK'=>['COL','🇨🇴'],'HJ'=>['COL','🇨🇴'],'5J'=>['COL','🇨🇴'],'5K'=>['COL','🇨🇴'],
        'TI'=>['CRI','🇨🇷'],'TE'=>['CRI','🇨🇷'],
        'CP'=>['BOL','🇧🇴'],
        'JA'=>['JPN','🇯🇵'],'JB'=>['JPN','🇯🇵'],'JC'=>['JPN','🇯🇵'],'JD'=>['JPN','🇯🇵'],'JE'=>['JPN','🇯🇵'],
        'BV'=>['TWN','🇹🇼'],'BU'=>['TWN','🇹🇼'],
        'VR'=>['HKG','🇭🇰'],'VS'=>['HKG','🇭🇰'],
        'XX'=>['MAC','🇲🇴'],
        'HL'=>['KOR','🇰🇷'],'DS'=>['KOR','🇰🇷'],'DT'=>['KOR','🇰🇷'],'DU'=>['KOR','🇰🇷'],
        'BY'=>['CHN','🇨🇳'],'BA'=>['CHN','🇨🇳'],'BD'=>['CHN','🇨🇳'],
        'VU'=>['IND','🇮🇳'],'AT'=>['IND','🇮🇳'],'AU'=>['IND','🇮🇳'],
        'AP'=>['PAK','🇵🇰'],'A2'=>['BWA','🇧🇼'],'A3'=>['TON','🇹🇴'],'A4'=>['OMN','🇴🇲'],'A5'=>['BTN','🇧🇹'],'A6'=>['ARE','🇦🇪'],'A7'=>['QAT','🇶🇦'],'A9'=>['BHR','🇧🇭'],
        '4X'=>['ISR','🇮🇱'],'4Z'=>['ISR','🇮🇱'],
        'ZS'=>['ZAF','🇿🇦'],'ZT'=>['ZAF','🇿🇦'],'ZU'=>['ZAF','🇿🇦'],
        'VK'=>['AUS','🇦🇺'],'VH'=>['AUS','🇦🇺'],'VI'=>['AUS','🇦🇺'],
        'ZL'=>['NZL','🇳🇿'],'ZM'=>['NZL','🇳🇿'],
        '9A'=>['HRV','🇭🇷'],'S5'=>['SVN','🇸🇮'],'T7'=>['BIH','🇧🇦'],'E7'=>['BIH','🇧🇦'],
        'YT'=>['SRB','🇷🇸'],'YU'=>['SRB','🇷🇸'],'Z3'=>['MKD','🇲🇰'],'ZA'=>['ALB','🇦🇱'],
        'PZ'=>['SUR','🇸🇷'],'8P'=>['BRB','🇧🇧'],'9Y'=>['TTO','🇹🇹'],'9Z'=>['TTO','🇹🇹'],
        'J6'=>['LCA','🇱🇨'],'J7'=>['DMA','🇩🇲'],'J8'=>['GRD','🇬🇩'],
        'VP2'=>['AIA','🇦🇮'],'VP5'=>['TCA','🇹🇨'],'VP8'=>['FLK','🇫🇰'],
        'ZD8'=>['SHN','🇸🇭'],'C6'=>['BHS','🇧🇸'],'C9'=>['MOZ','🇲🇿'],'D4'=>['CPV','🇨🇻'],
        'EA8'=>['ESH','🇪🇭'],'EA9'=>['ESH','🇪🇭'],'ZB2'=>['GIB','🇬🇮'],
        'CT'=>['PRT','🇵🇹'],'CU'=>['PRT','🇵🇹'],'CV'=>['PRT','🇵🇹'],'CW'=>['PRT','🇵🇹'],'CS'=>['PRT','🇵🇹'],'CR'=>['PRT','🇵🇹']
    ];
    $cs = strtoupper(trim($callsign));
    for ($len = 4; $len >= 1; $len--) {
        $prefix = substr($cs, 0, $len);
        if (isset($prefixes[$prefix])) return $prefixes[$prefix];
    }
    return ['XXX', '🌐'];
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
    $d2y = checkPid(PID_D2Y, 'DMR2YSF');
    $ysf = checkPid(PID_YSFGW, 'YSFGateway');
    $perms = [];
    foreach ($CONFIG_FILES as $k => $p) {
        $perms[$k] = ['exists' => file_exists($p), 'writable' => is_writable($p)];
    }
    header('Content-Type: application/json');
    echo json_encode([
        'mmdvm' => $mmd, 'dmr2ysf' => $d2y, 'ysfgateway' => $ysf,
        'bridge_active' => ($mmd==='active' && $d2y==='active' && $ysf==='active'),
        'perms' => $perms, 'ts' => time()
    ]);
    exit;
}

if ($action === 'start') {
    saveState('dmr2ysf', 'on');
    $out = shell_exec('sudo ' . START_SCRIPT . ' 2>&1');
    sleep(4);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'msg'=>'Puente iniciado', 'log'=>trim($out)?:'Sin salida']);
    exit;
}

if ($action === 'stop') {
    saveState('dmr2ysf', 'off');
    $out = shell_exec('sudo ' . STOP_SCRIPT . ' 2>&1');
    sleep(2);
    shell_exec('sudo pkill -9 -f DMR2YSF 2>/dev/null');
    shell_exec('sudo rm -f /tmp/DMR2YSF.pid');
    sleep(1);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'msg'=>'Puente detenido', 'log'=>trim($out)?:'Sin salida']);
    exit;
}

if ($action === 'logs') {
    $n = intval($_GET['lines'] ?? 80);
    header('Content-Type: application/json');
    echo json_encode([
        'mmdvm' => htmlspecialchars(tailLive(LOG_MMDVM, $n) ?: ''),
        'dmr2ysf' => htmlspecialchars(tailLive(LOG_D2Y, $n) ?: ''),
        'ysf' => htmlspecialchars(tailLive(LOG_YSFGW, $n) ?: '')
    ]);
    exit;
}

if ($action === 'cfg-read') {
    $id = $_POST['id'] ?? '';
    $path = $CONFIG_FILES[$id] ?? null;
    if (!$path || !file_exists($path)) {
        header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'No encontrado']); exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'path'=>$path, 'content'=>file_get_contents($path), 'id'=>$id]);
    exit;
}

if ($action === 'cfg-save') {
    $id = $_POST['id'] ?? '';
    $path = $CONFIG_FILES[$id] ?? null;
    if (!$path) { header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
    $res = file_put_contents($path, $_POST['content'] ?? '');
    header('Content-Type: application/json');
    echo json_encode(['ok' => ($res !== false)]);
    exit;
}

if ($action === 'restart-svc') {
    shell_exec('sudo '.STOP_SCRIPT.' >/dev/null 2>&1'); sleep(1);
    shell_exec('sudo '.START_SCRIPT.' >/dev/null 2>&1');
    usleep(1000000);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

// ── Transmisión y Last Heard (MMDVMDMR2YSF.log con RF/NET + flags) ──
if ($action === 'transmission') {
    $log = tailLive(LOG_MMDVM, 500);
    if (empty(trim($log))) {
        header('Content-Type: application/json');
        echo json_encode(['state'=>['active'=>false], 'lastHeard'=>[], 'vu'=>['slot1'=>0,'slot2'=>0]]);
        exit;
    }
    $lines = array_reverse(explode("\n", $log));
    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','slot'=>'','time'=>'','source'=>'','duration'=>'','loss'=>''];
    $lastHeard = []; $seen = []; $namesMap = [];
    $vu = ['slot1'=>0, 'slot2'=>0]; // Niveles simulados 0-100
    
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*FindWithName\s*=\s*([A-Z0-9]+)\s+(.+)/i', $line, $m)) {
            $namesMap[strtoupper(trim($m[2]))] = trim($m[3]);
        }
        // INICIO: "received (RF|network) voice header from XXX to TG Y"
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d+).*DMR Slot ([12]),\s*received\s+(RF|network)\s+voice header from\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+)/i', $line, $m)) {
            $time = explode('.', $m[1])[0]; $slot = $m[2];
            $source = strtoupper($m[3]) === 'RF' ? 'RF' : 'NET';
            $callsign = strtoupper(trim($m[4])); $tg = $m[5];
            if (empty($state['active']) || strtotime($time) >= strtotime($state['time'])) {
                $state = ['active'=>true,'callsign'=>$callsign,'name'=>$namesMap[$callsign]??'','tg'=>$tg,'slot'=>$slot,'time'=>$time,'source'=>$source,'duration'=>'','loss'=>''];
            }
            if (!in_array($callsign, $seen) && count($lastHeard) < 8) {
                $lastHeard[] = ['callsign'=>$callsign,'name'=>$namesMap[$callsign]??'','tg'=>$tg,'slot'=>$slot,'time'=>$time,'source'=>$source,'status'=>'TX'];
                $seen[] = $callsign;
            }
            // VU meter: activar slot con nivel alto
            $vu['slot'.$slot] = 85 + rand(0,15);
        }
        // FIN: "end of voice transmission from XXX to TG Y, X.X seconds, X% packet loss"
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d+).*DMR Slot ([12]),\s*received\s+(RF|network)\s+end of voice transmission from\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+),\s*([\d.]+)\s*seconds,\s*([\d.]+)%\s*packet loss/i', $line, $m)) {
            $time = explode('.', $m[1])[0]; $slot = $m[2];
            $source = strtoupper($m[3]) === 'RF' ? 'RF' : 'NET';
            $callsign = strtoupper(trim($m[4])); $tg = $m[5];
            $duration = $m[6]; $loss = $m[7];
            if ($state['active'] && $state['callsign']===$callsign && $state['tg']===$tg) {
                $state['duration'] = $duration.'s'; $state['loss'] = $loss.'%';
            }
            if (!in_array($callsign, $seen) && count($lastHeard) < 8) {
                $lastHeard[] = ['callsign'=>$callsign,'name'=>$namesMap[$callsign]??'','tg'=>$tg,'slot'=>$slot,'time'=>$time,'source'=>$source,'status'=>'RX','duration'=>$duration.'s','loss'=>$loss.'%'];
                $seen[] = $callsign;
            }
            // VU meter: bajar nivel gradualmente
            $vu['slot'.$slot] = max(0, $vu['slot'.$slot] - 30);
        }
    }
    // Si no hay transmisión activa, decaer VU meters
    if (!$state['active']) {
        $vu['slot1'] = max(0, $vu['slot1'] - 10);
        $vu['slot2'] = max(0, $vu['slot2'] - 10);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['state'=>$state, 'lastHeard'=>$lastHeard, 'vu'=>$vu]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🔗 Puente DMR ⇄ YSF | Modo Directo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg:#0a0e14; --surface:#111720; --border:#1e2d3d; --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --violet:#b57aff; --text:#a8b9cc; --text-dim:#4a5568; --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif; }
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

/* ── VU METERS ── */
.vu-container { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem; }
.vu-box { background:rgba(0,0,0,.25); border:1px solid var(--border); border-radius:6px; padding:.8rem 1rem; }
.vu-label { font-family:var(--font-mono); font-size:.75rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.08em; margin-bottom:.5rem; display:flex; justify-content:space-between; }
.vu-track { height:8px; background:#1a2535; border-radius:4px; overflow:hidden; position:relative; }
.vu-fill { height:100%; width:0%; background:linear-gradient(90deg,var(--cyan),var(--green)); border-radius:4px; transition:width .15s ease-out; box-shadow:0 0 10px rgba(0,212,255,.4); }
.vu-fill.peak { background:linear-gradient(90deg,var(--amber),var(--red)); box-shadow:0 0 10px rgba(255,179,0,.5); }
.vu-peak { position:absolute; top:0; width:2px; height:100%; background:var(--amber); opacity:.7; transition:left .1s; }

.logs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 900px) { .logs { grid-template-columns: 1fr; } }
.l-panel { height: 200px; display: flex; flex-direction: column; border: 1px solid var(--border); }
.l-head { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; background: rgba(0,0,0,.25); border-bottom: 1px solid var(--border); }
.l-title { font-family: var(--font-mono); font-size: .8rem; color: var(--violet); text-transform: uppercase; letter-spacing: .08em; }
.l-actions { display: flex; gap: .4rem; }
.btn-log { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); background: none; border: none; cursor: pointer; padding: .2rem .5rem; border-radius: 3px; transition: color .2s; }
.btn-log:hover { color: var(--text); }
.l-out { flex: 1; font-family: var(--font-mono); font-size: .75rem; line-height: 1.5; color: #7a9ab5; padding: .8rem 1rem; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
.l-out::-webkit-scrollbar { width: 4px; }
.l-out::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
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

.tx-panel { min-height:80px; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.2); border-radius:6px; padding:1rem; font-family:var(--font-mono); }
.tx-idle { color:var(--text-dim); font-size:.9rem; }
.tx-active { width:100%; display:flex; flex-direction:column; align-items:center; gap:.4rem; }
.tx-callsign { font-family:var(--font-ui); font-size:2rem; font-weight:700; color:var(--green); text-shadow:0 0 12px rgba(0,255,159,.4); letter-spacing:.04em; display:flex; align-items:center; gap:.4rem; }
.tx-flag { font-size:1.4rem; }
.tx-name { font-size:1rem; color:var(--cyan); font-weight:500; }
.tx-meta { display:flex; gap:.8rem; align-items:center; font-size:.75rem; flex-wrap:wrap; justify-content:center; }
.tx-src { padding:.1rem .45rem; border-radius:3px; font-weight:600; font-family:var(--font-mono); text-transform:uppercase; font-size:.7rem; }
.tx-src.rf { background:rgba(0,255,159,.15); color:var(--green); border:1px solid rgba(0,255,159,.3); }
.tx-src.net { background:rgba(0,212,255,.15); color:var(--cyan); border:1px solid rgba(0,212,255,.3); }
.tx-dest { color:var(--amber); font-weight:600; }
.tx-time { color:var(--text-dim); font-family:var(--font-mono); }
.tx-slot { color:var(--violet); font-weight:600; }

.lh-table { width:100%; border-collapse:collapse; font-family:var(--font-mono); font-size:.75rem; }
.lh-table th { text-align:left; padding:.5rem .6rem; background:rgba(0,0,0,.25); color:var(--text-dim); text-transform:uppercase; letter-spacing:.08em; font-size:.7rem; border-bottom:1px solid var(--border); }
.lh-table td { padding:.4rem .6rem; border-bottom:1px solid rgba(30,45,61,.5); color:var(--text); vertical-align:middle; }
.lh-table tr:hover td { background:rgba(181,122,255,.05); }
.tx-row td { background:rgba(0,255,159,.08); }
.lh-cs { color:var(--violet); font-weight:bold; letter-spacing:.04em; display:flex; align-items:center; gap:.3rem; }
.lh-flag { font-size:1.1rem; }
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
            <button class="btn-cfg" onclick="openCfg('tglist')"> TG-YSFList.txt</button>
        </div>
    </div>

    <div class="card">
        <div class="c-title">📡 Transmisión Activa</div>
        <div id="txDisplay" class="tx-panel">
            <div class="tx-idle">⏸ Canal libre - Esperando actividad...</div>
        </div>
        <!-- VU METERS -->
        <div class="vu-container">
            <div class="vu-box">
                <div class="vu-label"><span>Slot 1</span><span id="vu1Val">0%</span></div>
                <div class="vu-track"><div class="vu-fill" id="vu1"></div><div class="vu-peak" id="vu1Peak"></div></div>
            </div>
            <div class="vu-box">
                <div class="vu-label"><span>Slot 2</span><span id="vu2Val">0%</span></div>
                <div class="vu-track"><div class="vu-fill" id="vu2"></div><div class="vu-peak" id="vu2Peak"></div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="c-title">📋 Últimas Estaciones Escuchadas</div>
        <table class="lh-table">
            <thead><tr><th>Indicativo</th><th>Nombre</th><th>TG</th><th>Slot</th><th>Hora</th><th>Origen</th></tr></thead>
            <tbody id="lhBody"><tr><td colspan="6" class="lh-empty">Sin actividad reciente</td></tr></tbody>
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

<footer class="footer"> Panel DMR⇄YSF Directo | <a href="mmdvm.php" style="color:var(--cyan)">Volver al panel principal</a></footer>

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

// 🌍 Helper JS para banderas (mismo mapeo que PHP)
function getFlag(cs) {
    const prefixes = {
        'EA':'🇪🇸','EB':'🇪🇸','EC':'🇪🇸','ED':'🇪🇸','EE':'🇪🇸','EF':'🇪🇸',
        'F':'🇫🇷','FB':'🇫🇷','FC':'🇫🇷','FD':'🇫🇷','FE':'🇫🇷','FF':'🇫🇷',
        'I':'🇮🇹','IZ':'🇮🇹','IW':'🇮🇹','IV':'🇮🇹','IX':'🇮🇹',
        'G':'🇬🇧','M':'🇬🇧','2E':'🇬🇧','M6':'🇬🇧','M7':'🇬🇧',
        'DL':'🇩🇪','DA':'🇩🇪','DB':'🇩🇪','DC':'🇩🇪','DD':'🇩🇪','DF':'🇩🇪',
        'ON':'🇧🇪','OR':'🇧🇪','OT':'🇧🇪',
        'PA':'🇳🇱','PB':'🇳🇱','PC':'🇳🇱','PD':'🇳🇱','PE':'🇳🇱','PF':'🇳🇱',
        'OE':'🇦🇹','HB':'🇨🇭','HE':'🇨🇭',
        'LY':'🇱🇹','ES':'🇪🇪','YL':'🇱🇻',
        'SP':'🇵🇱','SQ':'🇵🇱','SN':'🇵🇱','SO':'🇵🇱',
        'OK':'🇨🇿','OM':'🇸🇰','HA':'🇭🇺',
        'YO':'🇷🇴','YR':'🇷🇴',
        'SV':'🇬🇷','SW':'🇬🇷','SX':'🇬🇷','SY':'🇬🇷','SZ':'🇬🇷',
        'UA':'🇷🇺','UB':'🇷🇺','UC':'🇷🇺','UD':'🇷🇺','UE':'🇷🇺',
        'UW':'🇺🇦','UX':'🇺🇦','UY':'🇺🇦','UZ':'🇺🇦',
        'K':'🇺🇸','N':'🇺🇸','W':'🇺🇸','AA':'🇺🇸','AB':'🇺🇸',
        'VE':'🇻🇪','YV':'🇻🇪',
        'PY':'🇧🇷','PU':'🇧🇷','PP':'🇧🇷','PQ':'🇧🇷','PR':'🇧🇷','PS':'🇧🇷','PT':'🇧🇷',
        'CE':'🇨🇱','CA':'🇨🇱','CD':'🇨🇱',
        'CX':'🇺🇾','CW':'🇺🇾',
        'LV':'🇦🇷','LU':'🇦🇷','LW':'🇦🇷','LX':'🇦🇷',
        'HC':'🇪🇨','HD':'🇪🇨',
        'HK':'🇨🇴','HJ':'🇨🇴','5J':'🇨🇴','5K':'🇨🇴',
        'TI':'🇨🇷','TE':'🇨🇷',
        'CP':'🇧🇴',
        'JA':'🇯🇵','JB':'🇯🇵','JC':'🇯🇵','JD':'🇯🇵','JE':'🇯🇵',
        'BV':'🇹🇼','BU':'🇹🇼',
        'VR':'🇭🇰','VS':'🇭🇰',
        'XX':'🇲🇴',
        'HL':'🇰🇷','DS':'🇰🇷','DT':'🇰🇷','DU':'🇰🇷',
        'BY':'🇨🇳','BA':'🇨🇳','BD':'🇨🇳',
        'VU':'🇮🇳','AT':'🇮🇳','AU':'🇮🇳',
        'AP':'🇵🇰','A2':'🇧🇼','A3':'🇹🇴','A4':'🇴🇲','A5':'🇧🇹','A6':'🇦🇪','A7':'🇶🇦','A9':'🇧🇭',
        '4X':'🇮🇱','4Z':'🇮🇱',
        'ZS':'🇿🇦','ZT':'🇿🇦','ZU':'🇿🇦',
        'VK':'🇦🇺','VH':'🇦🇺','VI':'🇦🇺',
        'ZL':'🇳🇿','ZM':'🇳🇿',
        '9A':'🇭🇷','S5':'🇸🇮','T7':'🇧🇦','E7':'🇧🇦',
        'YT':'🇷🇸','YU':'🇷🇸','Z3':'🇲🇰','ZA':'🇦🇱',
        'PZ':'🇸🇷','8P':'🇧🇧','9Y':'🇹🇹','9Z':'🇹🇹',
        'J6':'🇱🇨','J7':'🇩🇲','J8':'🇬🇩',
        'VP2':'🇦🇮','VP5':'🇹🇨','VP8':'🇫🇰',
        'ZD8':'🇸🇭','C6':'🇧🇸','C9':'🇲🇿','D4':'🇨🇻',
        'EA8':'🇪🇭','EA9':'🇪🇭','ZB2':'🇬🇮',
        'CT':'🇵🇹','CU':'🇵🇹','CV':'🇵🇹','CW':'🇵🇹','CS':'🇵🇹','CR':'🇵🇹'
    };
    cs = cs.toUpperCase();
    for (let len = 4; len >= 1; len--) {
        const pfx = cs.substring(0, len);
        if (prefixes[pfx]) return prefixes[pfx];
    }
    return '🌐';
}

let S = { active:false, poll:null, logT:null, txT:null, last:null, busy:false, cfgId:null };

function setDot(id, v) { const e=$(id); const vEl=$(id.replace('dot-','val-')); if(!e||!vEl)return; e.className='s-dot '+(v==='active'?'on':'off'); vEl.textContent=v==='active'?'ON':'OFF'; vEl.className='s-val '+(v==='active'?'on':'off'); }
function setToggle(on, busy=false) {
    const chk=$('chk'), sts=$('sts'), sw=$('sw');
    if (!busy) { chk.checked = on; sts.textContent = on ? 'ON' : 'OFF'; sts.className = 'toggle-status ' + (on ? 'on' : 'off'); }
    sw.classList.toggle('busy', busy); S.busy = busy;
}

// 🎚️ Actualizar VU meters
function updateVU(slot, level) {
    const fill = $('vu'+slot), peak = $('vu'+slot+'Peak'), val = $('vu'+slot+'Val');
    if(!fill || !peak || !val) return;
    level = Math.max(0, Math.min(100, level));
    fill.style.width = level + '%';
    val.textContent = level + '%';
    peak.style.left = level + '%';
    fill.classList.toggle('peak', level >= 90);
}

async function status() {
    try {
        const d = await api('status');
        setDot('dot-mmd', d.mmdvm); setDot('dot-d2y', d.dmr2ysf); setDot('dot-ysf', d.ysfgateway);
        const newActive = d.bridge_active;
        if (!S.busy && newActive !== S.active) { setToggle(newActive, false); }
        S.active = newActive;
        $('ts').textContent=fmtT(d.ts);
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
            if(atBot) el.scrollTop = el.scrollHeight;
        }
    } catch(e){ console.warn('logs err',e); }
}

async function fetchTransmission() {
    try {
        const r = await api('transmission');
        const d = r.state || {};
        const display = $('txDisplay');
        
        if (d.active && d.callsign) {
            const flag = getFlag(d.callsign);
            const nameHtml = d.name ? `<span class="tx-name">(${esc(d.name)})</span>` : '';
            const sourceBadge = `<span class="tx-src ${d.source==='RF'?'rf':'net'}">${d.source||'—'}</span>`;
            const metaHtml = `
                <div class="tx-meta">
                    ${sourceBadge}
                    <span class="tx-dest">→ TG ${d.tg||'—'}</span>
                    <span class="tx-slot">📡 Slot ${d.slot||'-'}</span>
                    ${d.duration ? `<span class="tx-time">⏱ ${esc(d.duration)}</span>` : ''}
                    ${d.loss ? `<span class="tx-time">📉 ${esc(d.loss)}</span>` : ''}
                    <span class="tx-time">${esc(d.time||'')}</span>
                </div>
            `;
            display.innerHTML = `
                <div class="tx-active">
                    <div class="tx-callsign"><span class="tx-flag">${flag}</span>${esc(d.callsign)} ${nameHtml}</div>
                    ${metaHtml}
                </div>
            `;
        } else {
            display.innerHTML = '<div class="tx-idle">⏸ Canal libre - Esperando actividad...</div>';
        }
        
        // Actualizar VU meters
        if (r.vu) {
            updateVU(1, r.vu.slot1||0);
            updateVU(2, r.vu.slot2||0);
        }
        
        // Actualizar tabla Last Heard
        const tbody = $('lhBody');
        const list = r.lastHeard || [];
        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="lh-empty">Sin actividad reciente</td></tr>';
        } else {
            tbody.innerHTML = list.map(r => {
                const flag = getFlag(r.callsign);
                const isTx = d.active && r.callsign === d.callsign && r.tg === d.tg;
                const durLoss = (r.duration || r.loss) ? `<small style="color:var(--text-dim)">(${r.duration||''} ${r.loss||''})</small>` : '';
                return `<tr class="${isTx?'tx-row':''}">
                    <td><span class="lh-cs"><span class="lh-flag">${flag}</span>${esc(r.callsign)}</span></td>
                    <td>${esc(r.name||'—')}</td>
                    <td>TG ${esc(r.tg||'—')}</td>
                    <td style="text-align:center">${esc(r.slot||'—')}</td>
                    <td>${esc(r.time||'—')}</td>
                    <td><span class="tx-src ${r.source==='RF'?'rf':'net'}">${r.source||'—'}</span> ${durLoss}</td>
                </tr>`;
            }).join('');
        }
    } catch(e){ console.warn('tx err',e); }
}

async function toggle(chk) {
    const target = chk.checked;
    setToggle(target, true);
    try {
        const res = await api(target ? 'start' : 'stop', {}, 'POST');
        if (!res.ok) { alert('❌ ' + res.msg); setToggle(!target, false); return; }
        await new Promise(r => setTimeout(r, 2500));
        await status(); fetchLogs();
        if (target === false) ['lMmd','lD2Y','lYsf'].forEach(id => $(id).innerHTML = '<span class="log-info">Logs limpiados.</span>');
    } catch (e) { console.error(e); setToggle(!target, false); alert('⚠️ Error: ' + e.message); }
}

async function openCfg(id) {
    S.cfgId=id; const m=$('cfgModal'), a=$('cfgArea'), p=$('cfgPath'), msg=$('cfgMsg');
    msg.style.display='none'; a.disabled=true; m.classList.add('open');
    try { const d=await api('cfg-read',{id},'POST'); if(d.ok){ p.textContent=d.path; a.value=d.content; a.disabled=false; a.focus(); } else { p.textContent='Error'; msg.className='m-msg err'; msg.textContent='✖ '+d.msg; msg.style.display='block'; } } catch(e){ msg.className='m-msg err'; msg.textContent='️ '+e.message; msg.style.display='block'; }
}
function closeCfg(){$('cfgModal').classList.remove('open'); S.cfgId=null;}
async function saveCfg() {
    if(!S.cfgId)return;
    const msg=$('cfgMsg'); msg.style.display='block'; msg.className='m-msg'; msg.textContent='⏳ Guardando…';
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
function stopPoll(){ clearInterval(S.poll); clearInterval(S.logT); clearInterval(S.txT); }

document.addEventListener('keydown',e=>{ if(e.key==='Escape' && $('cfgModal').classList.contains('open')) closeCfg(); });
window.addEventListener('load', startPoll);
</script>
</body>
</html>

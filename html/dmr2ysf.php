<?php
// =============================================================
// dmr2ysf_panel.php - Control de puente DMR ⇄ YSF by EA4AOJ
// =============================================================

// 🔧 Permitir acceso sin sesión SOLO desde localhost (systemd/terminal)
if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
    // Bypass auth para control local
} else {
   // 🔧 Permitir polling automático de estado desde localhost
if (!isset($_GET['action']) || ($_GET['action'] !== 'status' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1')) {
    require_once __DIR__ . '/auth.php';
}
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Rutas de Scripts ──
//define('START_SCRIPT', '/usr/local/bin/dmr2ysf-start.sh');
//define('STOP_SCRIPT',  '/usr/local/bin/dmr2ysf-stop.sh');

// ── Archivos de Configuración ──
define('INI_MMDVM',   '/home/pi/MMDVMHost/MMDVMDMR2YSF.ini');
define('INI_DMR2YSF', '/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF.ini');
define('INI_YSFGW',   '/home/pi/YSFClients/YSFGateway/YSFGateway.ini');
define('INI_TGLIST',  '/home/pi/MMDVM_CM/DMR2YSF/TG-YSFList.txt');
define('TGYSF_NAMES', '/home/pi/MMDVM_CM/DMR2YSF/TG-YSFNames.json');
define('YSF_HOSTS',   '/home/pi/YSFClients/YSFGateway/YSFHosts.json');

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

// ── Funciones auxiliares ──
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
        'EA'=>['ESP','🇪🇸'],'EB'=>['ESP','🇪'],'EC'=>['ESP','🇸'],'ED'=>['ESP','🇪🇸'],'EE'=>['ESP','🇪🇸'],'EF'=>['ESP','🇪🇸'],
        'F'=>['FRA','🇫🇷'],'FB'=>['FRA','🇫🇷'],'FC'=>['FRA','🇫🇷'],'FD'=>['FRA','🇫🇷'],'FE'=>['FRA','🇫🇷'],'FF'=>['FRA','🇫🇷'],
        'I'=>['ITA','🇮🇹'],'IZ'=>['ITA','🇮🇹'],'IW'=>['ITA','🇮🇹'],'IV'=>['ITA','🇮🇹'],'IX'=>['ITA','🇮🇹'],
        'G'=>['GBR','🇬🇧'],'M'=>['GBR','🇬🇧'],'2E'=>['GBR','🇬🇧'],'M6'=>['GBR','🇬🇧'],'M7'=>['GBR','🇬🇧'],
        'DL'=>['DEU','🇩🇪'],'DA'=>['DEU','🇩🇪'],'DB'=>['DEU','🇩🇪'],'DC'=>['DEU','🇩🇪'],'DD'=>['DEU','🇩🇪'],'DF'=>['DEU','🇩🇪'],
        'ON'=>['BEL','🇧🇪'],'OR'=>['BEL','🇧🇪'],'OT'=>['BEL','🇧🇪'],
        'PA'=>['NLD','🇳🇱'],'PB'=>['NLD','🇳🇱'],'PC'=>['NLD','🇳🇱'],'PD'=>['NLD','🇳🇱'],'PE'=>['NLD','🇳🇱'],'PF'=>['NLD','🇳🇱'],
        'OE'=>['AUT','🇦🇹'],
        'HB'=>['CHE','🇨🇭'],'HE'=>['CHE','🇨🇭'],
        'LY'=>['LTU','🇱🇹'],'ES'=>['EST','🇪🇪'],'YL'=>['LVA','🇱🇻'],
        'SP'=>['POL','🇵'],'SQ'=>['POL','🇱'],'SN'=>['POL','🇵'],'SO'=>['POL','🇱'],
        'OK'=>['CZE','🇨'],'OM'=>['SVK','🇸🇰'],'HA'=>['HUN','🇭🇺'],
        'YO'=>['ROU','🇷🇴'],'YR'=>['ROU','🇷'],
        'SV'=>['GRC','🇬🇷'],'SW'=>['GRC','🇬🇷'],'SX'=>['GRC','🇬🇷'],'SY'=>['GRC','🇬🇷'],'SZ'=>['GRC','🇬🇷'],
        'UA'=>['RUS','🇷'],'UB'=>['RUS','🇷'],'UC'=>['RUS','🇷🇺'],'UD'=>['RUS','🇷'],'UE'=>['RUS','🇷🇺'],
        'UW'=>['UKR','🇺🇦'],'UX'=>['UKR','🇺'],'UY'=>['UKR','🇺'],'UZ'=>['UKR','🇺🇦'],
        'K'=>['USA','🇺'],'N'=>['USA','🇸'],'W'=>['USA','🇺'],'AA'=>['USA','🇸'],'AB'=>['USA','🇺'],
        'VE'=>['VEN','🇻'],'YV'=>['VEN','🇻🇪'],
        'PY'=>['BRA','🇧🇷'],'PU'=>['BRA','🇧'],'PP'=>['BRA','🇷'],'PQ'=>['BRA','🇧🇷'],'PR'=>['BRA','🇧'],'PS'=>['BRA','🇷'],'PT'=>['BRA','🇧🇷'],
        'CE'=>['CHL','🇨'],'CA'=>['CHL','🇨🇱'],'CD'=>['CHL','🇨🇱'],
        'CX'=>['URY','🇺'],'CW'=>['URY','🇺'],
        'LV'=>['ARG','🇦'],'LU'=>['ARG','🇷'],'LW'=>['ARG','🇦🇷'],'LX'=>['ARG','🇦🇷'],
        'HC'=>['ECU','🇪'],'HD'=>['ECU','🇪🇨'],
        'HK'=>['COL','🇨🇴'],'HJ'=>['COL','🇨🇴'],'5J'=>['COL','🇨🇴'],'5K'=>['COL','🇨🇴'],
        'TI'=>['CRI','🇨🇷'],'TE'=>['CRI','🇨🇷'],
        'CP'=>['BOL','🇧🇴'],
        'JA'=>['JPN','🇯🇵'],'JB'=>['JPN','🇯🇵'],'JC'=>['JPN','🇯🇵'],'JD'=>['JPN','🇯🇵'],'JE'=>['JPN','🇯'],
        'BV'=>['TWN','🇹🇼'],'BU'=>['TWN','🇹🇼'],
        'VR'=>['HKG','🇭'],'VS'=>['HKG','🇭🇰'],
        'XX'=>['MAC','🇲🇴'],
        'HL'=>['KOR','🇰'],'DS'=>['KOR','🇰🇷'],'DT'=>['KOR','🇰🇷'],'DU'=>['KOR','🇰'],
        'BY'=>['CHN','🇨'],'BA'=>['CHN','🇨🇳'],'BD'=>['CHN','🇨🇳'],
        'VU'=>['IND','🇮🇳'],'AT'=>['IND','🇮🇳'],'AU'=>['IND','🇮🇳'],
        'AP'=>['PAK','🇵🇰'],'A2'=>['BWA','🇧'],'A3'=>['TON','🇹🇴'],'A4'=>['OMN','🇴🇲'],'A5'=>['BTN','🇧🇹'],'A6'=>['ARE','🇦🇪'],'A7'=>['QAT','🇶🇦'],'A9'=>['BHR','🇧'],
        '4X'=>['ISR','🇮'],'4Z'=>['ISR','🇮🇱'],
        'ZS'=>['ZAF','🇿'],'ZT'=>['ZAF','🇿🇦'],'ZU'=>['ZAF','🇿'],
        'VK'=>['AUS','🇦🇺'],'VH'=>['AUS','🇦🇺'],'VI'=>['AUS','🇦🇺'],
        'ZL'=>['NZL','🇳🇿'],'ZM'=>['NZL','🇳'],
        '9A'=>['HRV','🇭🇷'],'S5'=>['SVN','🇸🇮'],'T7'=>['BIH','🇧'],'E7'=>['BIH','🇧'],
        'YT'=>['SRB','🇷🇸'],'YU'=>['SRB','🇷'],'Z3'=>['MKD','🇲'],'ZA'=>['ALB','🇦'],
        'PZ'=>['SUR','🇸🇷'],'8P'=>['BRB','🇧'],'9Y'=>['TTO','🇹'],'9Z'=>['TTO','🇹🇹'],
        'J6'=>['LCA','🇱'],'J7'=>['DMA','🇩'],'J8'=>['GRD','🇬'],
        'VP2'=>['AIA','🇦'],'VP5'=>['TCA','🇹'],'VP8'=>['FLK','🇫'],
        'ZD8'=>['SHN','🇸🇭'],'C6'=>['BHS','🇧🇸'],'C9'=>['MOZ','🇲'],'D4'=>['CPV','🇨'],
        'EA8'=>['ESH','🇪'],'EA9'=>['ESH','🇪🇭'],'ZB2'=>['GIB','🇬'],
        'CT'=>['PRT','🇵'],'CU'=>['PRT','🇵🇹'],'CV'=>['PRT','🇵🇹'],'CW'=>['PRT','🇵🇹'],'CS'=>['PRT','🇵🇹'],'CR'=>['PRT','🇵🇹']
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

// ── TG-YSFList: listar reflectores disponibles ──
if ($action === 'tgysf-hosts') {
    $list = [];
    if (file_exists(YSF_HOSTS)) {
        $json = json_decode(file_get_contents(YSF_HOSTS), true);
        if (isset($json['reflectors']) && is_array($json['reflectors'])) {
            foreach ($json['reflectors'] as $ref) {
                $id = intval($ref['designator'] ?? 0);
                $name = trim($ref['name'] ?? '');
                $desc = trim($ref['sponsor'] ?? '');
                $country = strtoupper(trim($ref['country'] ?? ''));
                if ($id <= 0) continue;
                $list[] = ['id'=>$id,'name'=>$name,'desc'=>$desc,'country'=>$country];
            }
        }
    }
    usort($list, function($a,$b){
        $aES = $a['country']==='ES'?0:1; $bES = $b['country']==='ES'?0:1;
        if ($aES !== $bES) return $aES - $bES;
        return strcmp($a['name'], $b['name']);
    });
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'hosts'=>$list]);
    exit;
}

// ── TG-YSFList: leer entradas actuales ──
if ($action === 'tgysf-read') {
    $entries = [];
    $names = file_exists(TGYSF_NAMES) ? (json_decode(file_get_contents(TGYSF_NAMES), true) ?: []) : [];
    $hostNames = [];
    if (file_exists(YSF_HOSTS)) {
        $hjson = json_decode(file_get_contents(YSF_HOSTS), true);
        if (isset($hjson['reflectors'])) {
            foreach ($hjson['reflectors'] as $ref) {
                $hid = intval($ref['designator'] ?? 0);
                $hnm = trim($ref['name'] ?? '');
                if ($hid > 0 && $hnm !== '') $hostNames[(string)$hid] = $hnm;
            }
        }
    }
    if (file_exists(INI_TGLIST)) {
        foreach (file(INI_TGLIST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $parts = explode(';', $line, 2);
            if (count($parts) === 2 && is_numeric(trim($parts[0]))) {
                $tg = trim($parts[0]); $ysf = trim($parts[1]);
                $nm = $names[$tg] ?? $hostNames[$ysf] ?? '';
                $entries[] = ['tg'=>$tg, 'ysf'=>$ysf, 'name'=>$nm];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'entries'=>$entries]);
    exit;
}

// ── TG-YSFList: guardar entradas ──
if ($action === 'tgysf-save') {
    $raw = json_decode(file_get_contents('php://input'), true);
    $entries = $raw['entries'] ?? [];
    $lines = ["# DMR TG - YSF ID mapping", "# DMR TG ID;YSF reflector ID", "#"];
    $names = [];
    foreach ($entries as $e) {
        $tg = intval($e['tg'] ?? 0); $ysf = intval($e['ysf'] ?? 0); $nm = trim($e['name'] ?? '');
        if ($tg > 0 && $ysf > 0) {
            $lines[] = $tg . ';' . $ysf;
            if ($nm !== '') $names[(string)$tg] = $nm;
        }
    }
    $b1 = file_put_contents(INI_TGLIST, implode("\n", $lines) . "\n");
    $b2 = file_put_contents(TGYSF_NAMES, json_encode($names, JSON_PRETTY_PRINT));
    $ok = ($b1 !== false && $b2 !== false);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>$ok, 'msg'=>$ok?'Guardado correctamente':'Error al escribir']);
    exit;
}

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
    // 1. Guardamos el estado en ON para que systemd sepa que debe arrancar al reiniciar
    saveState('dmr2ysf', 'on');
    
    // 2. Le ordenamos a systemd que inicie el servicio (él ejecutará el .sh)
    $out = shell_exec('sudo systemctl start dmr2ysf.service 2>&1');
    
    // 3. Esperamos a que los procesos se estabilicen
    sleep(4);
    
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'msg'=>'Puente iniciado', 'log'=>trim($out)?:'Sin salida']);
    exit;
}

if ($action === 'stop') {
    // 1. Guardamos el estado en OFF para que systemd NO lo arranque al reiniciar
    saveState('dmr2ysf', 'off');
    
    // 2. Le ordenamos a systemd que detenga el servicio (ejecutará el ExecStop)
    $out = shell_exec('sudo systemctl stop dmr2ysf.service 2>&1');
    
    sleep(2);
    
    // 3. Limpieza de seguridad extrema por si algún proceso se quedó colgado
    shell_exec('sudo pkill -9 -f DMR2YSF 2>/dev/null');
    shell_exec('sudo rm -f /tmp/DMR2YSF.pid /tmp/MMDVMDMR2YSF.pid /tmp/YSFGateway.pid');
    
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
    if (!$path) { 
        header('Content-Type: application/json'); 
        echo json_encode(['ok'=>false, 'msg'=>'Ruta no válida']); 
        exit; 
    }
    $res = file_put_contents($path, $_POST['content'] ?? '');
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => ($res !== false), 
        'msg' => ($res !== false ? 'Guardado correctamente' : 'Error al escribir el fichero')
    ]);
    exit;
}

if ($action === 'restart-svc') {
    // Forzamos estado ON y reinicio limpio vía systemd
    saveState('dmr2ysf', 'on');
    shell_exec('sudo systemctl restart dmr2ysf.service >/dev/null 2>&1');
    usleep(1000000);
    header('Content-Type: application/json'); 
    echo json_encode(['ok'=>true]); 
    exit;
}

// ── Transmisión y Last Heard ──
if ($action === 'transmission') {
    $log = tailLive(LOG_MMDVM, 5000);
    if (empty(trim($log))) {
        header('Content-Type: application/json');
        $cached = _loadLastHeardCache(5, 300, true);
        echo json_encode(['state'=>['active'=>false], 'lastHeard'=>$cached, 'vu'=>['slot1'=>0,'slot2'=>0]]);
        exit;
    }
    
    $lines = explode("\n", $log);
    
    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','slot'=>'','time'=>'','source'=>'','duration'=>'','loss'=>''];
    $namesMap = [];
    
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*FindWithName\s*=\s*([A-Z0-9]+)\s+(.+)/i', $line, $m)) {
            $namesMap[strtoupper(trim($m[2]))] = trim($m[3]);
        }
    }
    
    $getName = function($cs) use ($namesMap) {
        $name = $namesMap[$cs] ?? '';
        if (!$name) {
            $lookup = lookupCall($cs);
            $name = $lookup['name'] ?? '';
        }
        return $name;
    };
    
    $lastHeard = [];
    $maxEntries = 5;
    $cacheFile = '/tmp/dmr2ysf_lastheard.json';
    $cacheTTL = 300;
    
    $cachedEntries = _loadLastHeardCache($maxEntries, $cacheTTL, $cacheFile, false);
    
    $newEntries = [];
    
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d+).*DMR Slot ([12]),\s*received\s+(RF|network)\s+voice header from\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+)/i', $line, $m)) {
            $callsign = strtoupper(trim($m[4]));
            $source = strtoupper($m[3]) === 'RF' ? 'RF' : 'NET';
            $key = $callsign.'-'.$m[2].'-'.$m[5].'-'.$source;
            
            $foundIndex = null;
            foreach ($newEntries as $i => $entry) {
                if (($entry['callsign'].'-'.$entry['slot'].'-'.$entry['tg'].'-'.$entry['source']) === $key) {
                    $foundIndex = $i;
                    break;
                }
            }
            
            $newEntry = [
                'callsign' => $callsign,
                'name' => $getName($callsign),
                'tg' => $m[5],
                'slot' => $m[2],
                'time' => explode('.', $m[1])[0],
                'source' => $source,
                'status' => 'TX',
                'duration' => '',
                'loss' => ''
            ];
            
            if ($foundIndex !== null) {
                unset($newEntries[$foundIndex]);
                $newEntries[] = $newEntry;
            } else {
                $newEntries[] = $newEntry;
            }
        }
        
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d+).*DMR Slot ([12]),\s*received\s+(RF|network)\s+end of voice transmission from\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+),\s*([\d.]+)\s*seconds,\s*(?:BER:\s*([\d.]+)%|([\d.]+)%\s*packet loss)/i', $line, $m)) {
            $callsign = strtoupper(trim($m[4]));
            $source = strtoupper($m[3]) === 'RF' ? 'RF' : 'NET';
            $key = $callsign.'-'.$m[2].'-'.$m[5].'-'.$source;
            
            $foundIndex = null;
            foreach ($newEntries as $i => $entry) {
                $entryKey = $entry['callsign'].'-'.$entry['slot'].'-'.$entry['tg'].'-'.$entry['source'];
                if ($entryKey === $key) {
                    $foundIndex = $i;
                    break;
                }
            }
            
            if ($foundIndex !== null) {
                $newEntries[$foundIndex]['duration'] = $m[6].'s';
                $newEntries[$foundIndex]['loss'] = ($m[7] ?? $m[8] ?? '').'%';
                $updatedEntry = $newEntries[$foundIndex];
                unset($newEntries[$foundIndex]);
                $newEntries[] = $updatedEntry;
            }
        }
    }
    
    $merged = $cachedEntries;
    
    foreach ($newEntries as $new) {
        $key = $new['callsign'].'-'.$new['slot'].'-'.$new['tg'].'-'.$new['source'];
        $foundIndex = null;
        foreach ($merged as $i => $entry) {
            if (($entry['callsign'].'-'.$entry['slot'].'-'.$entry['tg'].'-'.$entry['source']) === $key) {
                $foundIndex = $i;
                break;
            }
        }
        
        if ($foundIndex !== null) {
            if ($new['duration'] && !$merged[$foundIndex]['duration']) {
                $merged[$foundIndex]['duration'] = $new['duration'];
                $merged[$foundIndex]['loss'] = $new['loss'];
            }
            $temp = $merged[$foundIndex];
            unset($merged[$foundIndex]);
            $merged[] = $temp;
        } else {
            $merged[] = $new;
        }
    }
    
    if (count($merged) > $maxEntries) {
        $merged = array_slice($merged, -$maxEntries);
    }
    $lastHeard = array_values($merged);
    
    _saveLastHeardCache($lastHeard, $cacheFile);
    
    $activeBySlot = [1 => null, 2 => null];
    
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d+).*DMR Slot ([12]),\s*received\s+(RF|network)\s+voice header from\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+)/i', $line, $m)) {
            $slot = (int)$m[2];
            $callsign = strtoupper(trim($m[4]));
            $activeBySlot[$slot] = [
                'callsign' => $callsign,
                'tg' => $m[5],
                'time' => explode('.', $m[1])[0],
                'source' => strtoupper($m[3]) === 'RF' ? 'RF' : 'NET'
            ];
        }
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d+).*DMR Slot ([12]),\s*received\s+(RF|network)\s+end of voice transmission from\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+),\s*([\d.]+)\s*seconds/i', $line, $m)) {
            $slot = (int)$m[2];
            $callsign = strtoupper(trim($m[4]));
            $tg = $m[5];
            if ($activeBySlot[$slot] && $activeBySlot[$slot]['callsign'] === $callsign && $activeBySlot[$slot]['tg'] === $tg) {
                $activeBySlot[$slot] = null;
            }
        }
    }
    
    foreach ([1, 2] as $slot) {
        if ($activeBySlot[$slot]) {
            if (!$state['active'] || $activeBySlot[$slot]['time'] > $state['time']) {
                $state = [
                    'active' => true,
                    'callsign' => $activeBySlot[$slot]['callsign'],
                    'name' => $getName($activeBySlot[$slot]['callsign']),
                    'tg' => $activeBySlot[$slot]['tg'],
                    'slot' => $slot,
                    'time' => $activeBySlot[$slot]['time'],
                    'source' => $activeBySlot[$slot]['source'],
                    'duration' => '',
                    'loss' => ''
                ];
            }
        }
    }
    
    $vu = ['slot1'=>0, 'slot2'=>0];
    if ($state['active']) {
        $vu['slot'.$state['slot']] = 30 + rand(0, 70);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['state'=>$state, 'lastHeard'=>$lastHeard, 'vu'=>$vu]);
    exit;
}

// ============================================================================
// FUNCIONES DE PERSISTENCIA
// ============================================================================

function _loadLastHeardCache($maxEntries = 5, $ttlSeconds = 300, $cacheFile = '/tmp/dmr2ysf_lastheard.json', $stableOrder = true) {
    if (!file_exists($cacheFile)) return [];
    
    $data = @json_decode(file_get_contents($cacheFile), true);
    if (!$data || !is_array($data['entries'] ?? null)) return [];
    
    $now = time();
    $valid = array_filter($data['entries'], function($e) use ($now, $ttlSeconds) {
        return ($now - ($e['_ts'] ?? 0)) < $ttlSeconds;
    });
    
    if ($stableOrder) {
        $valid = array_values($valid);
    } else {
        usort($valid, function($a, $b) {
            return ($b['_ts'] ?? 0) - ($a['_ts'] ?? 0);
        });
        $valid = array_values($valid);
    }
    
    return array_slice($valid, 0, $maxEntries);
}

function _saveLastHeardCache($entries, $cacheFile = '/tmp/dmr2ysf_lastheard.json') {
    $now = time();
    $data = [
        'entries' => array_map(function($e) use ($now) {
            $e['_ts'] = $e['_ts'] ?? $now;
            return $e;
        }, $entries),
        'saved_at' => $now
    ];
    
    @file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
    @chmod($cacheFile, 0644);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🔗 DMR ⇄ YSF</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { 
    --bg:#0a0e14; 
    --surface:#111720; 
    --border:#1e2d3d; 
    --green:#00ff9f; 
    --red:#ff4560; 
    --amber:#ffb300; 
    --cyan:#00d4ff; 
    --violet:#b57aff; 
    --text:#a8b9cc; 
    --text-dim:#4a5568; 
    --font-mono:'Share Tech Mono',monospace; 
    --font-ui:'Rajdhani',sans-serif; 
}
* { box-sizing:border-box; margin:0; padding:0; }
body { 
    background:var(--bg); 
    color:var(--text); 
    font-family:var(--font-ui); 
    font-size:1rem; 
    min-height:100vh; 
    line-height:1.5; 
}

.header { 
    border-bottom:2px solid var(--border); 
    padding:1.2rem 2rem; 
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%); 
    display:flex; 
    align-items:center; 
    justify-content:space-between; 
    flex-wrap:wrap; 
    gap:1rem;
    box-shadow:0 4px 20px rgba(0,0,0,0.3);
}
.header h1 { 
    font-family:var(--font-ui); 
    font-weight:700; 
    font-size:1.6rem; 
    letter-spacing:.1em; 
    color:#e2eaf5; 
    text-transform:uppercase; 
    margin:0;
    text-shadow:0 0 20px rgba(0,212,255,0.3);
}
.badge-direct { 
    font-family:var(--font-mono); 
    font-size:.75rem; 
    background:linear-gradient(135deg,rgba(181,122,255,.2),rgba(0,212,255,.2)); 
    color:var(--violet); 
    border:1px solid var(--violet); 
    border-radius:6px; 
    padding:.3rem .7rem;
    box-shadow:0 0 10px rgba(181,122,255,0.2);
}
.nav-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
.btn-nav { 
    font-family:var(--font-mono); 
    font-size:.75rem; 
    letter-spacing:.06em; 
    text-transform:uppercase; 
    background:transparent; 
    border-radius:6px; 
    padding:.5rem 1rem; 
    cursor:pointer; 
    transition:all .3s; 
    text-decoration:none; 
    display:inline-flex; 
    align-items:center; 
    gap:.5rem; 
    border:1px solid var(--border); 
    color:var(--text);
    font-weight:600;
}
.btn-nav:hover { 
    background:rgba(0,212,255,.15); 
    border-color:var(--cyan); 
    color:var(--cyan);
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(0,212,255,0.2);
}
.btn-nav.primary { 
    background:linear-gradient(135deg,rgba(181,122,255,.15),rgba(0,212,255,.15)); 
    border-color:var(--violet); 
    color:var(--violet);
}
.btn-nav.primary:hover { 
    background:linear-gradient(135deg,rgba(181,122,255,.3),rgba(0,212,255,.3));
    box-shadow:0 4px 15px rgba(181,122,255,0.3);
}

.container { max-width:1400px; margin:0 auto; padding:2rem; }

.status-bar { 
    display:flex; 
    gap:2rem; 
    margin-bottom:2rem; 
    flex-wrap:wrap; 
    align-items:center; 
    padding:1rem 1.5rem; 
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%); 
    border:1px solid var(--border); 
    border-radius:10px;
    box-shadow:0 4px 15px rgba(0,0,0,0.2);
}
.s-item { 
    display:flex; 
    align-items:center; 
    gap:.6rem; 
    font-family:var(--font-mono); 
    font-size:.85rem; 
}
.s-dot { 
    width:12px; 
    height:12px; 
    border-radius:50%; 
    background:var(--text-dim); 
    transition:all .3s;
    box-shadow:0 0 8px rgba(0,0,0,0.5);
}
.s-dot.on { 
    background:var(--green); 
    box-shadow:0 0 12px var(--green),0 0 20px rgba(0,255,159,0.4);
    animation:pulse-dot 2s infinite;
}
.s-dot.off { 
    background:var(--red);
    box-shadow:0 0 8px var(--red);
}
@keyframes pulse-dot {
    0%,100% { opacity:1; }
    50% { opacity:0.6; }
}
.s-label { 
    color:var(--text-dim); 
    text-transform:uppercase; 
    letter-spacing:.1em; 
    font-size:.75rem; 
}
.s-val { 
    font-weight:700; 
    color:var(--cyan);
    text-shadow:0 0 10px rgba(0,212,255,0.3);
}
.s-val.on { color:var(--green); } 
.s-val.off { color:var(--red); }

.card { 
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%); 
    border:1px solid var(--border); 
    border-radius:12px; 
    padding:1.8rem; 
    margin-bottom:2rem; 
    position:relative; 
    overflow:hidden;
    box-shadow:0 8px 25px rgba(0,0,0,0.3);
}
.card::before { 
    content:''; 
    position:absolute; 
    top:0; 
    left:0; 
    right:0; 
    height:3px; 
    background:linear-gradient(90deg,transparent,var(--violet),var(--cyan),var(--violet),transparent);
    box-shadow:0 0 10px rgba(181,122,255,0.5);
}
.c-title { 
    font-family:var(--font-mono); 
    font-size:.9rem; 
    letter-spacing:.15em; 
    text-transform:uppercase; 
    color:var(--violet); 
    margin-bottom:1.5rem; 
    display:flex; 
    align-items:center; 
    gap:.6rem;
    font-weight:700;
}
.c-title::before { 
    content:'▸'; 
    color:var(--cyan);
    font-size:1.2rem;
}

.toggle-row { 
    display:flex; 
    align-items:center; 
    gap:1.5rem; 
    padding:.8rem 0; 
    margin-bottom:.8rem; 
}
.t-label { 
    font-family:var(--font-mono); 
    font-size:.95rem; 
    letter-spacing:.08em; 
    color:var(--text); 
    text-transform:uppercase; 
    flex:1;
    font-weight:600;
}
.t-status { 
    font-family:var(--font-mono); 
    font-size:.8rem; 
    letter-spacing:.12em; 
    color:var(--text-dim); 
    min-width:4rem; 
    text-align:right;
    font-weight:700;
}
.t-status.on { color:var(--green); } 
.t-status.off { color:var(--red); }
.sw { 
    position:relative; 
    width:70px; 
    height:36px; 
    flex-shrink:0; 
    cursor:pointer; 
}
.sw input { opacity:0; width:0; height:0; position:absolute; }
.sw-track { 
    position:absolute; 
    inset:0; 
    border-radius:8px; 
    background:#1a2535; 
    border:2px solid var(--red); 
    transition:all .3s;
    box-shadow:inset 0 2px 5px rgba(0,0,0,0.3);
}
.sw-knob { 
    position:absolute; 
    top:4px; 
    left:4px; 
    width:24px; 
    height:24px; 
    background:var(--red); 
    border-radius:5px; 
    transition:all .3s cubic-bezier(.4,0,.2,1); 
    box-shadow:0 3px 8px rgba(0,0,0,0.4);
}
.sw input:checked ~ .sw-track { 
    background:#1a2535; 
    border-color:var(--green);
    box-shadow:0 0 15px rgba(0,255,159,0.3);
}
.sw input:checked ~ .sw-knob { 
    transform:translateX(34px); 
    background:var(--green); 
    box-shadow:0 0 15px rgba(0,255,159,0.6),0 0 25px rgba(0,255,159,0.3);
}
.sw.busy .sw-knob { 
    animation:pulse-knob 1s infinite alternate;
}
@keyframes pulse-knob { 
    from{box-shadow:0 0 10px rgba(0,255,159,.5)} 
    to{box-shadow:0 0 20px rgba(0,255,159,.9),0 0 30px rgba(181,122,255,.6)} 
}

.cfg-row { 
    display:grid; 
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); 
    gap:.8rem; 
    margin-top:1.2rem; 
}
.btn-cfg { 
    font-family:var(--font-mono); 
    font-size:.75rem; 
    text-transform:uppercase; 
    padding:.6rem 1rem; 
    border-radius:8px; 
    border:1px solid var(--border); 
    background:linear-gradient(135deg,rgba(30,45,61,0.5),rgba(17,23,32,0.5)); 
    color:var(--text); 
    cursor:pointer; 
    transition:all .3s; 
    text-align:center;
    font-weight:600;
    letter-spacing:.05em;
}
.btn-cfg:hover:not(.muted) { 
    background:linear-gradient(135deg,rgba(255,179,0,.2),rgba(0,212,255,.2)); 
    border-color:var(--amber); 
    color:var(--amber);
    transform:translateY(-2px);
    box-shadow:0 5px 15px rgba(255,179,0,0.2);
}
.btn-cfg.muted { 
    color:var(--text-dim); 
    border-color:var(--border); 
    cursor:not-allowed;
    opacity:0.5;
}

/* ── VU METERS VERTICALES ── */
.vu-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    min-height: 180px;
}

.vu-meter {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.vu-label {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
}

.vu-track {
    width: 35px;
    height: 160px;
    background: linear-gradient(180deg, #0a0e14, #1a2535);
    border: 2px solid var(--border);
    border-radius: 4px;
    position: relative;
    overflow: hidden;
    box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
}

.vu-track::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: repeating-linear-gradient(
        to bottom,
        transparent 0px,
        transparent 2px,
        rgba(255,255,255,0.1) 2px,
        rgba(255,255,255,0.1) 3px
    );
    z-index: 2;
    pointer-events: none;
}

.vu-track::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: repeating-linear-gradient(
        to bottom,
        transparent 0px,
        transparent 14px,
        rgba(255,255,255,0.3) 14px,
        rgba(255,255,255,0.3) 16px
    );
    z-index: 2;
    pointer-events: none;
}

.vu-fill {
    position: absolute;
    bottom: 0;
    left: 2px;
    right: 2px;
    height: 0%;
    background: linear-gradient(180deg, 
        var(--green) 0%, 
        var(--green) 60%, 
        var(--amber) 80%, 
        var(--red) 100%
    );
    border-radius: 2px;
    transition: height 0.1s ease-out;
    box-shadow: 0 0 15px rgba(0,212,255,0.5);
    z-index: 1;
}

.vu-fill.peak {
    background: linear-gradient(180deg, var(--amber) 0%, var(--red) 100%);
    box-shadow: 0 0 20px rgba(255,179,0,0.8), 0 0 30px rgba(255,69,96,0.5);
}

.vu-peak {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--amber);
    opacity: 0.9;
    transition: bottom 0.2s ease-out;
    box-shadow: 0 0 8px var(--amber);
    z-index: 3;
}

.vu-value {
    font-family: var(--font-mono);
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--cyan);
    min-width: 40px;
    text-align: center;
}

/* PANEL DE TRANSMISIÓN CON VU METERS INTEGRADOS */
.tx-panel {
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(0,0,0,0.3), rgba(13,30,42,0.3));
    border-radius: 10px;
    padding: 1.5rem;
    font-family: var(--font-mono);
    border: 2px solid var(--border);
}

.tx-idle {
    color: var(--text-dim);
    font-size: 1rem;
    letter-spacing: 0.1em;
}

.tx-active {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
}

.tx-callsign {
    font-family: var(--font-ui);
    font-size: 3.5rem;
    font-weight: 900;
    color: var(--green);
    text-shadow: 0 0 20px rgba(0,255,159,0.6), 0 0 40px rgba(0,255,159,0.3);
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    animation: tx-glow 2s ease-in-out infinite;
}

@keyframes tx-glow {
    0%,100% { text-shadow: 0 0 20px rgba(0,255,159,0.6), 0 0 40px rgba(0,255,159,0.3); }
    50% { text-shadow: 0 0 30px rgba(0,255,159,0.9), 0 0 60px rgba(0,255,159,0.5); }
}

.tx-flag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    font-size: 2.5rem;
}

.tx-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.tx-name {
    font-size: 1.2rem;
    color: var(--cyan);
    font-weight: 600;
    text-shadow: 0 0 10px rgba(0,212,255,0.4);
}

.tx-meta {
    display: flex;
    gap: 1.2rem;
    align-items: center;
    font-size: 0.85rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 0.5rem;
}

.tx-src {
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: var(--font-mono);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
}

.tx-src.rf {
    background: rgba(0,255,159,.2);
    color: var(--green);
    border: 1px solid rgba(0,255,159,.4);
    box-shadow: 0 0 10px rgba(0,255,159,0.2);
}

.tx-src.net {
    background: rgba(0,212,255,.2);
    color: var(--cyan);
    border: 1px solid rgba(0,212,255,.4);
    box-shadow: 0 0 10px rgba(0,212,255,0.2);
}

.tx-dest {
    color: var(--amber);
    font-weight: 700;
    text-shadow: 0 0 10px rgba(255,179,0,0.3);
}

.tx-time {
    color: var(--text-dim);
    font-family: var(--font-mono);
}

.tx-slot {
    color: var(--violet);
    font-weight: 700;
    text-shadow: 0 0 10px rgba(181,122,255,0.3);
}

/* LAST HEARD TABLE */
.lh-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-mono);
    font-size: 0.8rem;
}

.lh-table th {
    text-align: left;
    padding: 0.8rem 1rem;
    background: rgba(0,0,0,0.3);
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-size: 0.75rem;
    border-bottom: 2px solid var(--border);
    font-weight: 700;
}

.lh-table td {
    padding: 0.6rem 1rem;
    border-bottom: 1px solid rgba(30,45,61,0.5);
    color: var(--text);
    vertical-align: middle;
}

.lh-table tr:hover td {
    background: rgba(181,122,255,0.08);
}

.tx-row td {
    background: rgba(0,255,159,0.1);
    border-left: 3px solid var(--green);
}

.lh-cs {
    color: var(--violet);
    font-weight: bold;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.lh-flag {
    font-size: 1.3rem;
}

.lh-empty {
    text-align: center;
    color: var(--text-dim);
    padding: 1.5rem !important;
    font-style: italic;
}

.tx-flag img, .lh-flag img {
    width: auto;
    height: 2rem;
    vertical-align: middle;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* LOGS PANEL */
.logs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

@media (max-width: 1000px) {
    .logs { grid-template-columns: 1fr; }
}

.l-panel {
    height: 250px;
    display: flex;
    flex-direction: column;
    border: 2px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    background: linear-gradient(180deg, var(--surface), #0d1e2a);
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

.l-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.8rem 1.2rem;
    background: rgba(0,0,0,0.3);
    border-bottom: 2px solid var(--border);
}

.l-title {
    font-family: var(--font-mono);
    font-size: 0.85rem;
    color: var(--violet);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 700;
}

.l-actions { display: flex; gap: 0.5rem; }

.btn-log {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: var(--text-dim);
    background: rgba(30,45,61,0.5);
    border: 1px solid var(--border);
    cursor: pointer;
    padding: 0.3rem 0.6rem;
    border-radius: 5px;
    transition: all 0.3s;
}

.btn-log:hover {
    color: var(--text);
    background: rgba(0,212,255,0.2);
    border-color: var(--cyan);
    transform: translateY(-1px);
}

.l-out {
    flex: 1;
    font-family: var(--font-mono);
    font-size: 0.75rem;
    line-height: 1.6;
    color: #7a9ab5;
    padding: 1rem;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
    background: rgba(0,0,0,0.2);
}

.l-out::-webkit-scrollbar { width: 6px; }
.l-out::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
.l-out::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}
.l-out::-webkit-scrollbar-thumb:hover { background: var(--cyan); }

.log-info { color: #7a9ab5; }
.log-ok { color: var(--green); }
.log-warn { color: var(--amber); }
.log-err { color: var(--red); }

/* MODAL GENERIC */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.modal.open { display: flex; }

.m-box {
    background: linear-gradient(135deg, var(--surface), #0d1e2a);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 2rem;
    width: 900px;
    max-width: 95vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

.m-title {
    font-family: var(--font-mono);
    font-size: 1rem;
    color: var(--cyan);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    font-weight: 700;
}

.m-path {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    color: var(--amber);
    letter-spacing: 0.05em;
    word-break: break-all;
    background: rgba(0,0,0,0.3);
    padding: 0.5rem;
    border-radius: 5px;
    border: 1px solid var(--border);
}

.m-editor {
    flex: 1;
    font-family: var(--font-mono);
    font-size: 0.85rem;
    color: #c9d1d9;
    background: #060c10;
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    resize: vertical;
    outline: none;
    line-height: 1.6;
    min-height: 300px;
    box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
}

.m-editor:focus {
    border-color: var(--cyan);
    box-shadow: 0 0 15px rgba(0,212,255,0.3), inset 0 2px 10px rgba(0,0,0,0.5);
}

.m-msg {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    display: none;
    border: 1px solid;
    font-weight: 600;
}

.m-msg.ok {
    color: var(--green);
    border-color: var(--green);
    background: rgba(0,255,159,0.1);
    box-shadow: 0 0 10px rgba(0,255,159,0.2);
}

.m-msg.err {
    color: var(--red);
    border-color: var(--red);
    background: rgba(255,69,96,0.1);
    box-shadow: 0 0 10px rgba(255,69,96,0.2);
}

.m-acts {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.btn-act {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.7rem 1.5rem;
    border-radius: 8px;
    border: 2px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
}

.btn-act.stop {
    background: transparent;
    color: var(--red);
    border-color: var(--red);
}

.btn-act.stop:hover {
    background: rgba(255,69,96,.15);
    color: #fff;
    border-color: #ff6b81;
    box-shadow: 0 0 20px rgba(255,69,96,0.4);
    transform: translateY(-2px);
}

.btn-act.start {
    background: transparent;
    color: var(--green);
    border-color: var(--green);
}

.btn-act.start:hover {
    background: rgba(0,255,159,.15);
    color: #fff;
    border-color: #00ff9f;
    box-shadow: 0 0 20px rgba(0,255,159,0.4);
    transform: translateY(-2px);
}

.btn-act:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* ── MODAL TG-YSFList (adaptado a tus colores) ── */
#tgYsfModal .m-box { width: 720px; }
#tgYsfModal .tg-header {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    color: var(--cyan);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}
#tgYsfModal .tg-sub {
    font-family: var(--font-mono);
    font-size: 0.65rem;
    color: var(--text-dim);
    margin-bottom: 0.8rem;
}
#tgYsfModal .tg-table-wrap {
    background: #060c10;
    border: 1px solid rgba(0,212,255,0.2);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.8rem;
}
#tgYsfModal .tg-table-head {
    display: grid;
    grid-template-columns: 90px 110px 1fr 36px;
    padding: 0.4rem 0.8rem;
    background: rgba(0,0,0,0.3);
    font-family: var(--font-mono);
    font-size: 0.65rem;
    color: var(--text-dim);
    letter-spacing: 0.1em;
    text-transform: uppercase;
    gap: 0.5rem;
}
#tgYsfModal .tg-rows {
    max-height: 220px;
    overflow-y: auto;
}
#tgYsfModal .tg-row {
    display: grid;
    grid-template-columns: 90px 110px 1fr 36px;
    padding: 0.38rem 0.8rem;
    border-bottom: 1px solid rgba(0,212,255,0.1);
    align-items: center;
    gap: 0.5rem;
}
#tgYsfModal .tg-val {
    font-family: var(--font-mono);
    font-size: 0.82rem;
    color: var(--cyan);
}
#tgYsfModal .tg-ysf {
    color: #80ffe8;
}
#tgYsfModal .tg-name-input {
    background: transparent;
    border: none;
    border-bottom: 1px solid rgba(0,212,255,0.2);
    color: var(--text);
    font-family: var(--font-mono);
    font-size: 0.78rem;
    padding: 0.15rem 0.2rem;
    outline: none;
    width: 100%;
}
#tgYsfModal .tg-name-input:focus {
    border-bottom-color: var(--cyan);
}
#tgYsfModal .tg-del {
    background: transparent;
    border: 1px solid rgba(255,69,96,0.3);
    border-radius: 3px;
    color: var(--red);
    font-size: 0.7rem;
    cursor: pointer;
    padding: 0.15rem 0.3rem;
}
#tgYsfModal .tg-del:hover {
    background: rgba(255,69,96,0.1);
}
#tgYsfModal .tg-add-row {
    display: grid;
    grid-template-columns: 90px 110px 1fr auto;
    gap: 0.5rem;
    align-items: end;
    margin-bottom: 0.5rem;
}
#tgYsfModal .tg-add-row input {
    width: 100%;
    background: var(--surface);
    border: 1px solid rgba(0,212,255,0.3);
    border-radius: 4px;
    color: var(--cyan);
    font-family: var(--font-mono);
    font-size: 0.82rem;
    padding: 0.42rem 0.5rem;
    outline: none;
}
#tgYsfModal .tg-add-row input:focus {
    border-color: var(--cyan);
}
#tgYsfModal .tg-add-btn {
    background: rgba(0,204,153,0.2);
    color: var(--green);
    border: none;
    border-radius: 4px;
    font-family: var(--font-mono);
    font-size: 0.78rem;
    padding: 0.42rem 0.8rem;
    cursor: pointer;
}
#tgYsfModal .tg-add-btn:hover {
    background: rgba(0,204,153,0.3);
}
#tgYsfModal .tg-search-btn {
    background: rgba(13,37,53,0.5);
    color: var(--cyan);
    border: 1px solid rgba(0,212,255,0.3);
    border-radius: 4px;
    font-family: var(--font-mono);
    font-size: 0.78rem;
    padding: 0.42rem 0.8rem;
    cursor: pointer;
    margin-top: 0.25rem;
}
#tgYsfModal .tg-search-btn:hover {
    background: rgba(0,212,255,0.1);
}
#tgYsfModal .tg-host-panel {
    display: none;
    background: #060c10;
    border: 1px solid rgba(0,212,255,0.2);
    border-radius: 4px;
    padding: 0.8rem;
    margin-bottom: 0.5rem;
}
#tgYsfModal .tg-host-search {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.6rem;
}
#tgYsfModal .tg-host-search input {
    flex: 1;
    background: var(--surface);
    border: 1px solid rgba(0,212,255,0.3);
    border-radius: 4px;
    color: var(--cyan);
    font-family: var(--font-mono);
    font-size: 0.78rem;
    padding: 0.38rem 0.6rem;
    outline: none;
}
#tgYsfModal .tg-host-close {
    background: transparent;
    border: 1px solid rgba(255,69,96,0.3);
    color: var(--red);
    border-radius: 4px;
    font-family: var(--font-mono);
    font-size: 0.7rem;
    padding: 0.35rem 0.6rem;
    cursor: pointer;
}
#tgYsfModal .tg-host-list {
    max-height: 200px;
    overflow-y: auto;
    font-family: var(--font-mono);
    font-size: 0.72rem;
}
#tgYsfModal .tg-host-item {
    padding: 0.35rem 0.6rem;
    cursor: pointer;
    border-bottom: 1px solid rgba(0,212,255,0.1);
    display: flex;
    gap: 0.8rem;
    align-items: center;
}
#tgYsfModal .tg-host-item:hover {
    background: rgba(0,212,255,0.08);
}
#tgYsfModal .tg-host-id {
    color: var(--cyan);
    min-width: 52px;
}
#tgYsfModal .tg-host-name {
    color: #80ffe8;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#tgYsfModal .tg-host-ctry {
    color: var(--text-dim);
    font-size: 0.6rem;
}
#tgYsfModal .tg-hint {
    font-family: var(--font-mono);
    font-size: 0.6rem;
    color: var(--text-dim);
    margin-top: 0.4rem;
}
#tgYsfModal .tg-msg {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    display: none;
    border: 1px solid;
    margin-bottom: 0.5rem;
}
#tgYsfModal .tg-msg.ok {
    color: var(--green);
    border-color: var(--green);
    background: rgba(0,255,159,0.1);
}
#tgYsfModal .tg-msg.err {
    color: var(--red);
    border-color: var(--red);
    background: rgba(255,69,96,0.1);
}

.footer {
    text-align: center;
    padding: 2rem;
    color: var(--text-dim);
    font-family: var(--font-mono);
    font-size: 0.8rem;
    border-top: 2px solid var(--border);
    margin-top: 3rem;
    background: linear-gradient(180deg, transparent, var(--surface));
}

.footer a {
    color: var(--cyan);
    text-decoration: none;
    transition: all 0.3s;
}

.footer a:hover {
    color: var(--violet);
    text-shadow: 0 0 10px rgba(181,122,255,0.5);
}

@media (max-width: 768px) {
    .header { padding: 1rem; }
    .header h1 { font-size: 1.2rem; }
    .container { padding: 1rem; }
    .card { padding: 1.2rem; }
    .tx-callsign { font-size: 2rem; }
    .vu-track { height: 120px; width: 30px; }
    .logs { grid-template-columns: 1fr; }
    .l-panel { height: 200px; }
    .vu-wrapper { gap: 1rem; }
    #tgYsfModal .m-box { width: 95vw; }
    #tgYsfModal .tg-add-row { grid-template-columns: 1fr; }
    #tgYsfModal .tg-table-head,
    #tgYsfModal .tg-row { grid-template-columns: 1fr; gap: 0.3rem; }
}
</style>
</head>
<body>
<header class="header">
    <div style="display:flex;align-items:center;gap:1rem;">
        <h1>🔗 Puente DMR ⇄ YSF</h1>
        <span class="badge-direct">BRIDGE</span>
    </div>
    <div class="nav-actions">
        <a href="mmdvm.php" class="btn-nav primary">🏠 Panel PHPPLUS</a>
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
        <div class="c-title">⚙️ Control Principal del Puente</div>
        <div class="toggle-row">
            <span class="t-label">Activar / Desactivar sistemas</span>
            <label class="sw" id="sw"><input type="checkbox" id="chk" onchange="toggle(this)"><span class="sw-track"></span><span class="sw-knob"></span></label>
            <span class="t-status" id="sts">OFF</span>
        </div>
        <div style="font-family:var(--font-mono);font-size:.85rem;color:var(--text-dim);margin:.8rem 0 1.2rem;padding:.8rem;background:rgba(0,0,0,0.2);border-radius:6px;border-left:3px solid var(--violet);">
            <span style="color:var(--cyan);">ℹ️</span> <strong>Arquitectura:</strong> MMDVMHost ⇄ DMR2YSF ⇄ YSFGateway
        </div>
        <div class="cfg-row">
            <button class="btn-cfg" onclick="openCfg('mmdvm')">📄 MMDVMDMR2YSF.ini</button>
            <button class="btn-cfg" onclick="openCfg('dmr2ysf')">📄 DMR2YSF.ini</button>
            <button class="btn-cfg" onclick="openCfg('ysf')">📄 YSFGateway.ini</button>
            <button class="btn-cfg" onclick="openTgYsfModal()">📋 TG-YSFList.txt</button>
        </div>
    </div>

    <div class="card">
        <div class="c-title">📡 Transmisión Activa</div>
        <div id="txDisplay" class="tx-panel">
            <div class="vu-wrapper">
                <!-- VU METER SLOT 1 -->
                <div class="vu-meter">
                    <div class="vu-label">Slot 1</div>
                    <div class="vu-track">
                        <div class="vu-fill" id="vu1"></div>
                        <div class="vu-peak" id="vu1Peak"></div>
                    </div>
                    <div class="vu-value" id="vu1Val">0%</div>
                </div>
                
                <!-- TRANSMISIÓN CENTRAL -->
                <div class="tx-idle" id="txCenter">⏸ Pausa > Esperando actividad </div>
                
                <!-- VU METER SLOT 2 -->
                <div class="vu-meter">
                    <div class="vu-label">Slot 2</div>
                    <div class="vu-track">
                        <div class="vu-fill" id="vu2"></div>
                        <div class="vu-peak" id="vu2Peak"></div>
                    </div>
                    <div class="vu-value" id="vu2Val">0%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="c-title">📋 Ultimas Estaciones Escuchadas</div>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>Indicativo</th>
                    <th>Nombre</th>
                    <th>TG</th>
                    <th>Slot</th>
                    <th>Hora</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody id="lhBody">
                <tr><td colspan="6" class="lh-empty">Sin actividad reciente</td></tr>
            </tbody>
        </table>
    </div>

    <div class="logs">
        <div class="l-panel">
            <div class="l-head">
                <span class="l-title">📋 MMDVMDMR2YSF</span>
                <div class="l-actions">
                    <button class="btn-log" onclick="refreshLogs()" title="Actualizar logs">🔄</button>
                    <button class="btn-log" onclick="clearLog('lMmd')" title="Limpiar">🗑</button>
                </div>
            </div>
            <div class="l-out" id="lMmd">Esperando…</div>
        </div>
        <div class="l-panel">
            <div class="l-head">
                <span class="l-title" style="color:#c9a0ff">📋 DMR2YSF</span>
                <div class="l-actions">
                    <button class="btn-log" onclick="refreshLogs()" title="Actualizar logs">🔄</button>
                    <button class="btn-log" onclick="clearLog('lD2Y')" title="Limpiar">🗑</button>
                </div>
            </div>
            <div class="l-out" id="lD2Y">Esperando…</div>
        </div>
        <div class="l-panel">
            <div class="l-head">
                <span class="l-title" style="color:var(--green)">📋 YSFGateway</span>
                <div class="l-actions">
                    <button class="btn-log" onclick="refreshLogs()" title="Actualizar logs">🔄</button>
                    <button class="btn-log" onclick="clearLog('lYsf')" title="Limpiar">🗑</button>
                </div>
            </div>
            <div class="l-out" id="lYsf">Esperando…</div>
        </div>
    </div>
</main>

<!-- Modal genérico de edición -->
<div id="cfgModal" class="modal" onclick="if(event.target===this)closeCfg()">
    <div class="m-box">
        <div class="m-title">✏️ Editor de Configuración</div>
        <div class="m-path" id="cfgPath">—</div>
        <textarea class="m-editor" id="cfgArea" spellcheck="false"></textarea>
        <div class="m-msg" id="cfgMsg"></div>
        <div class="m-acts">
            <button class="btn-act stop" onclick="closeCfg()">✖ Cerrar</button>
            <button class="btn-act start" onclick="saveCfg()">💾 Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- Modal TG-YSFList -->
<div id="tgYsfModal" class="modal" onclick="if(event.target===this)closeTgYsfModal()">
    <div class="m-box">
        <div class="tg-header">📋 TG-YSF List · Mapeo TalkGroup → Reflector YSF</div>
        <div class="tg-sub">/home/pi/MMDVM_CM/DMR2YSF/TG-YSFList.txt</div>
        
        <div class="tg-table-wrap">
            <div class="tg-table-head">
                <span>TG DMR</span><span>YSF ID</span><span>Nombre</span><span></span>
            </div>
            <div id="tgYsfRows" class="tg-rows"></div>
        </div>
        
        <div class="tg-add-row">
            <div><input type="text" id="tgYsfNewTG" placeholder="9"></div>
            <div><input type="text" id="tgYsfNewYSF" placeholder="62980"></div>
            <div><input type="text" id="tgYsfNewName" placeholder="ej: ES-EA-DISTRITO-4"></div>
            <div style="display:flex;flex-direction:column;gap:0.25rem;">
                <button onclick="tgYsfAdd()" class="tg-add-btn">➕ Añadir</button>
                <button onclick="tgYsfToggleHosts()" class="tg-search-btn">📡 Buscar Sala</button>
            </div>
        </div>
        
        <div id="tgYsfHostPanel" class="tg-host-panel">
            <div class="tg-host-search">
                <input type="text" id="tgYsfSearch" placeholder="🔍 Buscar reflector…" oninput="tgYsfFilterHosts(this.value)">
                <button onclick="tgYsfToggleHosts()" class="tg-host-close">✖</button>
            </div>
            <div id="tgYsfHostList" class="tg-host-list"></div>
            <div class="tg-hint">↑ Haz clic para rellenar YSF ID y Nombre</div>
        </div>
        
        <div id="tgYsfMsg" class="tg-msg"></div>
        
        <div class="m-acts">
            <button class="btn-act stop" onclick="closeTgYsfModal()">✖ Cerrar</button>
            <button class="btn-act start" onclick="tgYsfSave()">💾 Guardar</button>
        </div>
    </div>
</div>

<footer class="footer">
    Panel Bridge DMR⇄YSF | <a href="mmdvm.php">Volver al panel PHPPLUS</a>
</footer>

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

// 🌍 Sistema de banderas
const _winOS = /Windows/i.test(navigator.userAgent);
const _TBASE = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/';
const _FLAGS = [
    {re:/^E[ABCDEFGH][1-9]/, e:'🇪🇸', t:'1f1ea-1f1f8'},
    {re:/^C[TUQ]/, e:'🇵', t:'1f1f5-1f1f9'},
    {re:/^F[A-Z]/, e:'🇫', t:'1f1eb-1f1f7'},
    {re:/^I[0-9]|^IK|^IW|^IZ/, e:'🇮🇹', t:'1f1ee-1f1f9'},
    {re:/^G[0-9]|^M[0-9]|^2E|^GB|^MJ|^MU/, e:'🇬🇧', t:'1f1ec-1f1e7'},
    {re:/^D[A-R]|^Y[2-9]/, e:'🇩🇪', t:'1f1e9-1f1ea'},
    {re:/^[KWN][0-9]|^AA|^AB|^AC|^AD|^AE|^AF/, e:'🇺', t:'1f1fa-1f1f8'},
    {re:/^VE|^VA|^VO|^VY/, e:'🇨', t:'1f1e8-1f1e6'},
    {re:/^PY|^PU|^PV|^PW|^PX/, e:'🇧', t:'1f1e7-1f1f7'},
    {re:/^LU|^LV|^LW|^LX/, e:'🇦', t:'1f1e6-1f1f7'},
    {re:/^JA|^JE|^JF|^JG|^JH|^JI|^JJ|^JK|^JL|^JR/, e:'🇯🇵', t:'1f1ef-1f1f5'},
    {re:/^VK/, e:'🇦', t:'1f1e6-1f1fa'},
    {re:/^ZS|^ZT|^ZU/, e:'🇿🇦', t:'1f1ff-1f1e6'},
    {re:/^OH|^OG/, e:'🇫🇮', t:'1f1eb-1f1ee'},
    {re:/^PA|^PB|^PC|^PD|^PE|^PF|^PG|^PH/, e:'🇳', t:'1f1f3-1f1f1'},
    {re:/^HB/, e:'🇨', t:'1f1e8-1f1ed'},
    {re:/^OE/, e:'🇦🇹', t:'1f1e6-1f1f9'},
    {re:/^SP|^SQ|^SR|^HF/, e:'🇵', t:'1f1f5-1f1f1'},
    {re:/^UA|^UB|^UC|^UD|^UE|^UF|^RA|^RB|^RC/, e:'🇷', t:'1f1f7-1f1fa'},
    {re:/^SV|^SW|^SX|^SY|^SZ/, e:'🇬', t:'1f1ec-1f1f7'},
    {re:/^LY/, e:'🇱', t:'1f1f1-1f1f9'},
    {re:/^9A/, e:'🇭🇷', t:'1f1ed-1f1f7'},
];

function getFlag(callsign){
    if(!callsign) return '';
    const cs = callsign.toUpperCase().trim();
    for(const p of _FLAGS){
        if(p.re.test(cs)){
            if(_winOS) return '<img class="flag-emoji-img" src="'+_TBASE+p.t+'.png" alt="">';
            return '<span class="flag-emoji">'+p.e+'</span>';
        }
    }
    return '<span class="flag-emoji">🌐</span>';
}



let S = { active:false, poll:null, logT:null, txT:null, last:null, busy:false, cfgId:null };

function setDot(id, v) {
    const e=$(id);
    const vEl=$(id.replace('dot-','val-'));
    if(!e||!vEl)return;
    e.className='s-dot '+(v==='active'?'on':'off');
    vEl.textContent=v==='active'?'ON':'OFF';
    vEl.className='s-val '+(v==='active'?'on':'off');
}

function setToggle(on, busy=false) {
    const chk=$('chk'), sts=$('sts'), sw=$('sw');
    if (!busy) {
        chk.checked = on;
        sts.textContent = on ? 'ON' : 'OFF';
        sts.className = 't-status ' + (on ? 'on' : 'off');
    }
    sw.classList.toggle('busy', busy);
    S.busy = busy;
}

function updateVU(slot, level) {
    const fill = $('vu'+slot), peak = $('vu'+slot+'Peak'), val = $('vu'+slot+'Val');
    if(!fill || !peak || !val) return;
    level = Math.max(0, Math.min(100, level));
    fill.style.height = level + '%';
    val.textContent = level + '%';
    peak.style.bottom = level + '%';
    fill.classList.toggle('peak', level >= 90);
}

async function status() {
    try {
        const d = await api('status');
        setDot('dot-mmd', d.mmdvm);
        setDot('dot-d2y', d.dmr2ysf);
        setDot('dot-ysf', d.ysfgateway);
        const newActive = d.bridge_active;
        if (!S.busy && newActive !== S.active) {
            setToggle(newActive, false);
        }
        S.active = newActive;
        $('ts').textContent=fmtT(d.ts);
        document.querySelectorAll('.btn-cfg').forEach(btn=>{
            const id=btn.getAttribute('onclick').match(/'([^']+)'/)[1];
            if(d.perms[id]) btn.classList.toggle('muted', !d.perms[id].writable);
        });
    } catch(e){ console.warn('status err',e); }
}

async function refreshLogs() {
    try {
        const d = await api('logs', {lines:80});
        const panels = {lMmd: d.mmdvm, lD2Y: d.dmr2ysf, lYsf: d.ysf};
        for(let [id, txt] of Object.entries(panels)) {
            const el = $(id);
            const atBot = el.scrollHeight - el.clientHeight <= el.scrollTop + 20;
            el.innerHTML = txt ? colorizeLog(txt) : '<span class="log-info">Sin salida en vivo</span>';
            if(atBot) el.scrollTop = el.scrollHeight;
        }
    } catch(e) {
        console.warn('logs err', e);
        alert('⚠️ Error al actualizar logs: ' + e.message);
    }
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
        const txCenter = $('txCenter');
        
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
            txCenter.innerHTML = `
                <div class="tx-info">
                    <div class="tx-callsign"><span class="tx-flag">${flag}</span>${esc(d.callsign)} ${nameHtml}</div>
                    ${metaHtml}
                </div>
            `;
        } else {
            txCenter.innerHTML = '<div class="tx-idle">⏸ Pausa > Esperando actividad </div>';
        }
        
        if (r.vu) {
            updateVU(1, r.vu.slot1||0);
            updateVU(2, r.vu.slot2||0);
        }
        
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
    S.cfgId=id;
    const m=$('cfgModal'), a=$('cfgArea'), p=$('cfgPath'), msg=$('cfgMsg');
    msg.style.display='none';
    a.disabled=true;
    m.classList.add('open');
    try {
        const d=await api('cfg-read',{id},'POST');
        if(d.ok){
            p.textContent=d.path;
            a.value=d.content;
            a.disabled=false;
            a.focus();
        } else {
            p.textContent='Error';
            msg.className='m-msg err';
            msg.textContent='✖ '+d.msg;
            msg.style.display='block';
        }
    } catch(e){
        msg.className='m-msg err';
        msg.textContent='✖ '+e.message;
        msg.style.display='block';
    }
}

function closeCfg(){
    $('cfgModal').classList.remove('open');
    S.cfgId=null;
}

async function saveCfg() {
    if(!S.cfgId)return;
    const msg=$('cfgMsg');
    msg.style.display='block';
    msg.className='m-msg';
    msg.textContent='⏳ Guardando…';
    try {
        const d=await api('cfg-save',{id:S.cfgId,content:$('cfgArea').value},'POST');
        msg.className='m-msg '+(d.ok?'ok':'err');
        msg.textContent=(d.ok?'✅ ':'❌ ')+d.msg;
        if(d.ok) setTimeout(closeCfg,1500);
    } catch(e){
        msg.className='m-msg err';
        msg.textContent='✖ '+e.message;
    }
}

const clearLog=id=>{
    if(!confirm('¿Limpiar logs?'))return;
    $(id).innerHTML='<span class="log-info">Limpiado.</span>';
};

const forceRefresh=()=>{
    status();
    fetchLogs();
    fetchTransmission();
};

function colorizeLog(text) {
    return text.split('\n').map(l => {
        const ll = l.toLowerCase();
        if (/error|fail|abort|exception|denied|segfault/i.test(ll)) return `<span class="log-err">${l}</span>`;
        if (/warn|warning|timeout/i.test(ll)) return `<span class="log-warn">${l}</span>`;
        if (/connect|start|open|loaded|success|tx|rx|linked|tg|ysf|slot|mode|sigterm|stopped/i.test(ll)) return `<span class="log-ok">${l}</span>`;
        return `<span class="log-info">${l}</span>`;
    }).join('\n');
}

function startPoll(){
    status();
    fetchLogs();
    fetchTransmission();
    S.poll=setInterval(status,5000);
    S.logT=setInterval(fetchLogs,7000);
    S.txT=setInterval(fetchTransmission,1000);
}

function stopPoll(){
    clearInterval(S.poll);
    clearInterval(S.logT);
    clearInterval(S.txT);
}

document.addEventListener('keydown',e=>{
    if(e.key==='Escape' && $('cfgModal').classList.contains('open')) closeCfg();
    if(e.key==='Escape' && $('tgYsfModal').style.display==='flex') closeTgYsfModal();
});

// ── Funciones TG-YSFList ──────────────────────────────────────────────────────
let _tgYsfEntries=[], _tgYsfHosts=[], _tgYsfHostsLoaded=false;

function openTgYsfModal(){
    $('tgYsfModal').style.display='flex';
    $('tgYsfMsg').style.display='none';
    $('tgYsfHostPanel').style.display='none';
    tgYsfLoad();
}

function closeTgYsfModal(){
    $('tgYsfModal').style.display='none';
}

async function tgYsfLoad(){
    try{
        const r=await api('tgysf-read');
        _tgYsfEntries=r.entries||[];
        tgYsfRender();
    }catch(e){ console.warn('tgysf-read err',e); }
}

function tgYsfRender(){
    const c=$('tgYsfRows');
    if(!_tgYsfEntries.length){
        c.innerHTML='<div style="padding:1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin entradas</div>';
        return;
    }
    c.innerHTML=_tgYsfEntries.map((e,i)=>`<div class="tg-row">
        <span class="tg-val">${esc(e.tg)}</span>
        <span class="tg-val tg-ysf">${esc(e.ysf)}</span>
        <input type="text" class="tg-name-input" value="${esc(e.name||'')}" placeholder="—" onchange="_tgYsfEntries[${i}].name=this.value">
        <button class="tg-del" onclick="tgYsfRemove(${i})">✖</button>
    </div>`).join('');
}

function tgYsfAdd(){
    const tg=$('tgYsfNewTG').value.trim();
    const ysf=$('tgYsfNewYSF').value.trim();
    const name=$('tgYsfNewName').value.trim();
    if(!tg||!ysf||isNaN(tg)||isNaN(ysf)){ tgYsfShowMsg('Introduce valores numéricos válidos',false); return; }
    if(_tgYsfEntries.some(e=>e.tg===tg)){ tgYsfShowMsg('El TG '+tg+' ya existe',false); return; }
    _tgYsfEntries.push({tg,ysf,name});
    $('tgYsfNewTG').value=''; $('tgYsfNewYSF').value=''; $('tgYsfNewName').value='';
    tgYsfRender();
}

function tgYsfRemove(i){
    _tgYsfEntries.splice(i,1);
    tgYsfRender();
}

async function tgYsfSave(){
    try{
        const r=await fetch(location.href+'?action=tgysf-save',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({entries:_tgYsfEntries})
        });
        const d=await r.json();
        tgYsfShowMsg(d.msg,d.ok);
        if(d.ok) setTimeout(closeTgYsfModal,1500);
    }catch(e){ tgYsfShowMsg('Error de red',false); }
}

async function tgYsfToggleHosts(){
    const p=$('tgYsfHostPanel');
    const v=p.style.display!=='none';
    p.style.display=v?'none':'block';
    if(!v&&!_tgYsfHostsLoaded) await tgYsfLoadHosts();
}

async function tgYsfLoadHosts(){
    $('tgYsfHostList').innerHTML='<div style="color:var(--text-dim);text-align:center;padding:.5rem;">Cargando…</div>';
    try{
        const r=await api('tgysf-hosts');
        _tgYsfHosts=r.hosts||[];
        _tgYsfHostsLoaded=true;
        tgYsfRenderHosts(_tgYsfHosts);
    }catch(e){
        $('tgYsfHostList').innerHTML='<div style="color:var(--red);text-align:center;padding:.5rem;">Error</div>';
    }
}

function tgYsfFilterHosts(q){
    const term=q.trim().toLowerCase();
    tgYsfRenderHosts(term===''?_tgYsfHosts:_tgYsfHosts.filter(h=>
        String(h.id).includes(term)||h.name.toLowerCase().includes(term)||h.desc.toLowerCase().includes(term)||h.country.toLowerCase().includes(term)
    ));
}

function tgYsfRenderHosts(list){
    const el=$('tgYsfHostList');
    if(!list.length){ el.innerHTML='<div style="color:var(--text-dim);text-align:center;padding:.5rem;">Sin resultados</div>'; return; }
    el.innerHTML=list.map(h=>{
        const flag=h.country==='ES'?'🇪🇸 ':h.country?h.country+' ':'';
        const nm=h.name||'—';
        const desc=h.desc?' · '+h.desc:'';
        const nmEsc=nm.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
        return `<div class="tg-host-item" onclick="tgYsfSelectHost(${h.id},'${nmEsc}')">
            <span class="tg-host-id">${h.id}</span>
            <span class="tg-host-name">${flag}${esc(nm)}${esc(desc)}</span>
            <span class="tg-host-ctry">${h.country}</span>
        </div>`;
    }).join('');
}

function tgYsfSelectHost(id,name){
    $('tgYsfNewYSF').value=id;
    $('tgYsfNewName').value=name;
    $('tgYsfHostPanel').style.display='none';
    $('tgYsfSearch').value='';
    $('tgYsfNewTG').focus();
}

function tgYsfShowMsg(msg,ok){
    const el=$('tgYsfMsg');
    el.textContent=(ok?'✔ ':'✖ ')+msg;
    el.style.display='block';
    el.className='tg-msg '+(ok?'ok':'err');
    if(ok) setTimeout(()=>el.style.display='none',3000);
}
// ── Fin funciones TG-YSFList ─────────────────────────────────────────────────

window.addEventListener('load', startPoll);
</script>
</body>
</html>

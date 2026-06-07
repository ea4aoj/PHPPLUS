<?php
// =============================================================
// ysf2dmr.php - Control del puente YSF ⇄ DMR
// =============================================================

// 🔧 Permitir acceso sin sesión SOLO desde localhost (systemd/terminal)
if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
    // Bypass auth para control local
} else {
    if (!isset($_GET['action']) || ($_GET['action'] !== 'status' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1')) {
        require_once __DIR__ . '/auth.php';
    }
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Rutas de Scripts ──
define('START_SCRIPT', '/usr/local/bin/ysf2dmr-start.sh');
define('STOP_SCRIPT',  '/usr/local/bin/ysf2dmr-stop.sh');

// ── Archivos de Configuración ──
define('INI_MMDVM',   '/home/pi/MMDVMHost/MMDVMYSF2DMR.ini');
define('INI_Y2D',     '/home/pi/MMDVM_CM/YSF2DMR/YSF2DMR.ini');

// ── Archivos PID ──
define('PID_MMDVM',   '/tmp/MMDVMYSF2DMR.pid');
define('PID_Y2D',     '/tmp/YSF2DMR.pid');

// ── Logs en vivo ──
define('LOG_MMDVM',   '/tmp/MMDVMYSF2DMR.log');
define('LOG_Y2D',     '/tmp/YSF2DMR.log');

$CONFIG_FILES = [
    'mmdvm'   => INI_MMDVM,
    'ysf2dmr' => INI_Y2D
];

// ── Funciones auxiliares ──
function saveState($key, $value) {
    $file = '/var/lib/mmdvm-state';
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    foreach ($lines as &$line) { 
        if (strpos($line, $key . '=') === 0) { 
            $line = $key . '=' . $value; 
            $found = true; 
        } 
    }
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
        'VE'=>['CAN','🇨🇦'],'YV'=>['VEN','🇻🇪'],
        'PY'=>['BRA','🇧🇷'],'PU'=>['BRA','🇧🇷'],'PP'=>['BRA','🇧🇷'],'PQ'=>['BRA','🇧🇷'],'PR'=>['BRA','🇧🇷'],'PS'=>['BRA','🇧🇷'],'PT'=>['BRA','🇧🇷'],
        'CE'=>['CHL','🇨🇱'],'CA'=>['CHL','🇨🇱'],'CD'=>['CHL','🇨🇱'],
        'LV'=>['ARG','🇦🇷'],'LU'=>['ARG','🇦🇷'],'LW'=>['ARG','🇦🇷'],'LX'=>['ARG','🇦🇷'],
        'HC'=>['ECU','🇪🇨'],'HD'=>['ECU','🇪🇨'],
        'HK'=>['COL','🇨🇴'],'HJ'=>['COL','🇨🇴'],
        'TI'=>['CRI','🇨🇷'],'TE'=>['CRI','🇨🇷'],
        'CP'=>['BOL','🇧🇴'],
        'JA'=>['JPN','🇯🇵'],'JB'=>['JPN','🇯🇵'],'JC'=>['JPN','🇯🇵'],'JD'=>['JPN','🇯🇵'],'JE'=>['JPN','🇯🇵'],
        'BV'=>['TWN','🇹🇼'],'BU'=>['TWN','🇹🇼'],
        'VR'=>['HKG','🇭🇰'],'VS'=>['HKG','🇭🇰'],
        'XX'=>['MAC','🇲🇴'],
        'HL'=>['KOR','🇰🇷'],'DS'=>['KOR','🇰🇷'],'DT'=>['KOR','🇰🇷'],'DU'=>['KOR','🇰🇷'],
        'BY'=>['CHN','🇨🇳'],'BA'=>['CHN','🇨🇳'],'BD'=>['CHN','🇨🇳'],
        'VU'=>['IND','🇮🇳'],'AT'=>['IND','🇮🇳'],'AU'=>['IND','🇮🇳'],
        'AP'=>['PAK','🇵🇰'],
        '4X'=>['ISR','🇮🇱'],'4Z'=>['ISR','🇮🇱'],
        'ZS'=>['ZAF','🇿🇦'],'ZT'=>['ZAF','🇿🇦'],'ZU'=>['ZAF','🇿🇦'],
        'VK'=>['AUS','🇦🇺'],'VH'=>['AUS','🇦🇺'],'VI'=>['AUS','🇦🇺'],
        'ZL'=>['NZL','🇳🇿'],'ZM'=>['NZL','🇳🇿'],
        '9A'=>['HRV','🇭🇷'],'S5'=>['SVN','🇸🇮'],
        'YT'=>['SRB','🇷🇸'],'YU'=>['SRB','🇷🇸'],
        'CT'=>['PRT','🇵🇹'],'CU'=>['PRT','🇵🇹'],'CS'=>['PRT','🇵🇹'],'CR'=>['PRT','🇵🇹'],
        'EA8'=>['ESP','🇪🇸'],'EA9'=>['ESP','🇪🇸'],
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
        if (preg_match('/connect|start|open|loaded|success|tx|rx|linked|tg|ysf|slot|mode|sigterm|stopped|received|header|src/i', $ll)) return '<span class="log-ok">'.htmlspecialchars($l).'</span>';
        return '<span class="log-info">'.htmlspecialchars($l).'</span>';
    }, explode("\n", $text)));
}

// ============================================================================
// ROUTER AJAX
// ============================================================================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'status') {
    $mmd = checkPid(PID_MMDVM, 'MMDVMYSF2DMR');
    $y2d = checkPid(PID_Y2D, 'YSF2DMR');
    $bridge_active = ($mmd==='active' && $y2d==='active');

    $state_on = null;
    $state_file = '/var/lib/mmdvm-state';
    if (file_exists($state_file)) {
        foreach (file($state_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            if (strpos($ln, 'ysf2dmr=') === 0) {
                $state_on = (trim(substr($ln, 8)) === 'on');
                break;
            }
        }
    }
    if ($bridge_active && $state_on !== true) {
        saveState('ysf2dmr', 'on');
        $state_on = true;
    }

    $perms = [];
    foreach ($CONFIG_FILES as $k => $p) {
        $perms[$k] = ['exists' => file_exists($p), 'writable' => is_writable($p)];
    }
    header('Content-Type: application/json');
    echo json_encode([
        'mmdvm'         => $mmd,
        'ysf2dmr'       => $y2d,
        'bridge_active' => $bridge_active,
        'state_on'      => $state_on,
        'perms'         => $perms,
        'ts'            => time()
    ]);
    exit;
}

if ($action === 'start') {
    saveState('ysf2dmr', 'on');
    $out = shell_exec('sudo ' . START_SCRIPT . ' 2>&1');
    $ok = false;
    for ($i = 0; $i < 5; $i++) {
        sleep(2);
        $mmd = checkPid(PID_MMDVM, 'MMDVMYSF2DMR');
        $y2d = checkPid(PID_Y2D, 'YSF2DMR');
        if ($mmd === 'active' && $y2d === 'active') {
            $ok = true;
            break;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Puente iniciado' : 'Puente iniciado (procesos aún arrancando)', 'log' => trim($out) ?: 'Sin salida']);
    exit;
}

if ($action === 'stop') {
    saveState('ysf2dmr', 'off');
    $out = shell_exec('sudo ' . STOP_SCRIPT . ' 2>&1');
    sleep(2);
    shell_exec('sudo pkill -9 -f YSF2DMR 2>/dev/null');
    shell_exec('sudo rm -f /tmp/YSF2DMR.pid');
    sleep(1);
    @unlink('/tmp/ysf2dmr_lastheard.json');
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'msg'=>'Puente detenido', 'log'=>trim($out)?:'Sin salida']);
    exit;
}

if ($action === 'logs') {
    $n = intval($_GET['lines'] ?? 80);
    header('Content-Type: application/json');
    echo json_encode([
        'mmdvm'   => htmlspecialchars(tailLive(LOG_MMDVM, $n) ?: ''),
        'ysf2dmr' => htmlspecialchars(tailLive(LOG_Y2D, $n) ?: '')
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
    shell_exec('sudo '.STOP_SCRIPT.' >/dev/null 2>&1'); sleep(1);
    shell_exec('sudo '.START_SCRIPT.' >/dev/null 2>&1');
    usleep(1000000);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

// ── Transmisión y Last Heard ──
if ($action === 'transmission') {
    $log = tailLive(LOG_Y2D, 5000);

    if (empty(trim($log))) {
        header('Content-Type: application/json');
        $cached = _loadLastHeardCache(5, 300, '/tmp/ysf2dmr_lastheard.json', true);
        echo json_encode(['state'=>['active'=>false], 'lastHeard'=>$cached, 'vu'=>['slot1'=>0]]);
        exit;
    }

    $lines = explode("\n", $log);
    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','slot'=>'-','time'=>'','source'=>'YSF','duration'=>'','loss'=>''];

    // Resuelve ID numérico DMR → callsign
    $resolveCallsign = function($idOrCs) {
        if (!ctype_digit($idOrCs)) return strtoupper($idOrCs);
        $datFiles = ['/home/pi/MMDVMHost/DMRIds.dat', '/etc/DMRIds.dat', '/usr/local/etc/DMRIds.dat'];
        foreach ($datFiles as $f) {
            if (!file_exists($f)) continue;
            $row = trim(shell_exec("awk -F'\t' '{if (\$1==\"".$idOrCs."\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' ".escapeshellarg($f)." 2>/dev/null"));
            if ($row !== '') {
                $parts = explode("\t", $row);
                return strtoupper(trim($parts[1] ?? $idOrCs));
            }
        }
        return strtoupper($idOrCs);
    };

    $getName = function($cs) {
        $lookup = lookupCall($cs);
        return $lookup['name'] ?? '';
    };

    $addOrUpdate = function($entry) use (&$newEntries) {
        $key = $entry['callsign'].'-'.$entry['tg'];
        foreach ($newEntries as $i => $e) {
            if (($e['callsign'].'-'.$e['tg']) === $key) { unset($newEntries[$i]); break; }
        }
        $newEntries[] = $entry;
    };

    $closeActiveTx = function($duration) use (&$activeTx, &$newEntries, &$pendingCallsign, &$pendingTime) {
        if ($activeTx !== null) {
            $key = $activeTx['callsign'].'-'.$activeTx['tg'];
            foreach ($newEntries as $i => $e) {
                if (($e['callsign'].'-'.$e['tg']) === $key) {
                    $newEntries[$i]['duration'] = $duration;
                    $newEntries[$i]['status']   = 'END';
                    $updated = $newEntries[$i];
                    unset($newEntries[$i]);
                    $newEntries[] = $updated;
                    break;
                }
            }
            $activeTx = null;
        }
        $pendingCallsign = null;
        $pendingTime     = null;
    };

    $maxEntries    = 5;
    $cacheFile     = '/tmp/ysf2dmr_lastheard.json';
    $cacheTTL      = 300;
    $cachedEntries = _loadLastHeardCache($maxEntries, $cacheTTL, $cacheFile, true);

    $newEntries      = [];
    $activeTx        = null;
    $pendingCallsign = null;
    $pendingTime     = null;

    foreach ($lines as $line) {

        // ── YSF→DMR PASO 1: cabecera YSF con callsign origen ──
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+Received YSF Header:\s+Src:\s+([A-Z0-9]+)\s/i', $line, $m)) {
            $pendingCallsign = strtoupper(trim($m[2]));
            $pendingTime     = $m[1];
        }

        // ── YSF→DMR PASO 2: TG asociado al header anterior ──
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR ID of ([A-Z0-9]+):\s*\d+,\s*DstID:\s*TG\s*(\d+)/i', $line, $m)) {
            $callsign = $pendingCallsign ?? strtoupper(trim($m[2]));
            $tg       = $m[3];
            $time     = $pendingTime ?? $m[1];
            $pendingCallsign = null;
            $pendingTime     = null;

            $activeTx = [
                'callsign' => $callsign,
                'name'     => $getName($callsign),
                'tg'       => $tg,
                'time'     => $time,
                'source'   => 'YSF',
                'status'   => 'TX',
                'duration' => '',
                'loss'     => ''
            ];
            $addOrUpdate($activeTx);
        }

        // ── YSF→DMR PASO 3: fin de transmisión YSF ──
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+YSF received end of voice transmission,\s*([\d.]+)\s*seconds/i', $line, $m)) {
            $closeActiveTx($m[2].'s');
        }

        // ── DMR→YSF INICIO: audio DMR entrante (callsign o ID numérico) ──
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR audio (?:late entry )?received from\s+([A-Z0-9]+)\s+to\s+TG\s*(\d+)/i', $line, $m)) {
            $rawId    = trim($m[2]);
            $callsign = $resolveCallsign($rawId);
            $tg       = $m[3];
            $time     = $m[1];

            $activeTx = [
                'callsign' => $callsign,
                'name'     => $getName($callsign),
                'tg'       => $tg,
                'time'     => $time,
                'source'   => 'DMR',
                'status'   => 'TX',
                'duration' => '',
                'loss'     => ''
            ];
            $addOrUpdate($activeTx);
        }

        // ── DMR→YSF FIN: fin de transmisión DMR ──
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR received end of voice transmission,\s*([\d.]+)\s*seconds/i', $line, $m)) {
            $closeActiveTx($m[2].'s');
        }
    }

    // Fusionar con caché
    $merged = $cachedEntries;
    foreach ($newEntries as $new) {
        $key = $new['callsign'].'-'.$new['tg'];
        $foundIndex = null;
        foreach ($merged as $i => $entry) {
            if (($entry['callsign'].'-'.$entry['tg']) === $key) { $foundIndex = $i; break; }
        }
        if ($foundIndex !== null) {
            if ($new['duration'] && !$merged[$foundIndex]['duration']) {
                $merged[$foundIndex]['duration'] = $new['duration'];
                $merged[$foundIndex]['loss']     = $new['loss'] ?? '';
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

    // Estado activo: si hay una transmisión sin cerrar
    if ($activeTx !== null) {
        $state = [
            'active'   => true,
            'callsign' => $activeTx['callsign'],
            'name'     => $activeTx['name'],
            'tg'       => $activeTx['tg'],
            'slot'     => '-',
            'time'     => $activeTx['time'],
            'source'   => $activeTx['source'],
            'duration' => '',
            'loss'     => ''
        ];
    }

    $vu = ['slot1'=>0];
    if ($state['active']) {
        $vu['slot1'] = 30 + rand(0, 70);
    }

    header('Content-Type: application/json');
    echo json_encode(['state'=>$state, 'lastHeard'=>$lastHeard, 'vu'=>$vu]);
    exit;
}

// ============================================================================
// FUNCIONES DE PERSISTENCIA
// ============================================================================
function _loadLastHeardCache($maxEntries = 5, $ttlSeconds = 300, $cacheFile = '/tmp/ysf2dmr_lastheard.json', $stableOrder = true) {
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
            return ($a['_ts'] ?? 0) - ($b['_ts'] ?? 0);
        });
        $valid = array_values($valid);
    }
    return array_slice($valid, 0, $maxEntries);
}

function _saveLastHeardCache($entries, $cacheFile = '/tmp/ysf2dmr_lastheard.json') {
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
<title>🔗 YSF ⇄ DMR</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#0a0e14; --surface:#111720; --border:#1e2d3d;
    --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --violet:#b57aff;
    --text:#a8b9cc; --text-dim:#4a5568;
    --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:var(--font-ui);font-size:.9rem;}

/* ══ HEADER ══ */
.header{
    padding:.7rem .8rem;
    display:flex;align-items:center;gap:1rem;
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%);
    border:1px solid var(--border);border-radius:8px;
    box-shadow:0 4px 20px rgba(0,0,0,.3);
}
.header h1{font-family:var(--font-ui);font-weight:700;font-size:1.1rem;letter-spacing:.08em;color:#e2eaf5;text-transform:uppercase;margin:0;white-space:nowrap;}
.badge-direct{font-family:var(--font-mono);font-size:.65rem;background:linear-gradient(135deg,rgba(181,122,255,.2),rgba(0,212,255,.2));color:var(--violet);border:1px solid var(--violet);border-radius:5px;padding:.15rem .5rem;white-space:nowrap;}
/* status dots en header */
.h-status{display:flex;align-items:center;gap:1rem;flex:1;margin-left:.3rem;}
.s-item{display:flex;align-items:center;gap:.35rem;font-family:var(--font-mono);font-size:.72rem;}
.s-dot{width:9px;height:9px;border-radius:50%;background:var(--text-dim);transition:all .3s;}
.s-dot.on{background:var(--green);box-shadow:0 0 7px var(--green);animation:pulse-dot 2s infinite;}
.s-dot.off{background:var(--red);box-shadow:0 0 5px var(--red);}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.55}}
.s-label{color:var(--text-dim);text-transform:uppercase;letter-spacing:.07em;font-size:.68rem;}
.s-val{font-weight:700;color:var(--cyan);}
.s-val.on{color:var(--green);} .s-val.off{color:var(--red);}
.h-ts{font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);white-space:nowrap;}
.h-ts span{color:var(--amber);font-weight:700;}
/* toggle en header */
.h-toggle{display:flex;align-items:center;gap:.5rem;flex-shrink:0;}
.h-tlabel{font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;}
.sw{position:relative;width:54px;height:26px;flex-shrink:0;cursor:pointer;}
.sw input{opacity:0;width:0;height:0;position:absolute;}
.sw-track{position:absolute;inset:0;border-radius:6px;background:#1a2535;border:2px solid var(--red);transition:all .3s;}
.sw-knob{position:absolute;top:3px;left:3px;width:16px;height:16px;background:var(--red);border-radius:4px;transition:all .3s cubic-bezier(.4,0,.2,1);}
.sw input:checked~.sw-track{border-color:var(--green);box-shadow:0 0 10px rgba(0,255,159,.3);}
.sw input:checked~.sw-knob{transform:translateX(28px);background:var(--green);box-shadow:0 0 10px rgba(0,255,159,.6);}
.sw.busy .sw-knob{animation:pulse-knob 1s infinite alternate;}
@keyframes pulse-knob{from{box-shadow:0 0 8px rgba(0,255,159,.5)}to{box-shadow:0 0 18px rgba(0,255,159,.9)}}
.t-status{font-family:var(--font-mono);font-size:.75rem;font-weight:700;}
.t-status.on{color:var(--green);} .t-status.off{color:var(--red);}
.btn-nav{font-family:var(--font-mono);font-size:.68rem;letter-spacing:.05em;text-transform:uppercase;background:transparent;border-radius:5px;padding:.3rem .65rem;cursor:pointer;transition:all .3s;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--border);color:var(--text);font-weight:600;white-space:nowrap;}
.btn-nav:hover{background:rgba(0,212,255,.15);border-color:var(--cyan);color:var(--cyan);}
.btn-nav.primary{background:linear-gradient(135deg,rgba(181,122,255,.15),rgba(0,212,255,.15));border-color:var(--violet);color:var(--violet);}

/* ══ WRAPPER ══ */
.page-wrap{
    max-width:1400px;width:100%;
    margin:0 auto;
    padding:1.5rem .8rem .5rem;
    display:flex;flex-direction:column;
    gap:1rem;
}

/* ══ CARD CONFIG ══ */
.card-cfg{
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%);
    border:1px solid var(--border);border-radius:8px;
    padding:.4rem .75rem;position:relative;overflow:hidden;flex-shrink:0;
}
.card-cfg::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,var(--violet),var(--cyan),var(--violet),transparent);}
.c-title{font-family:var(--font-mono);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;
    color:var(--violet);display:flex;align-items:center;gap:.4rem;font-weight:700;white-space:nowrap;}
.c-title::before{content:'▸';color:var(--cyan);}
.arch-info{font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);padding:.25rem .55rem;
    background:rgba(0,0,0,.2);border-radius:4px;border-left:2px solid var(--violet);}
.cfg-row{display:flex;gap:.4rem;flex-wrap:wrap;}
.btn-cfg{font-family:var(--font-mono);font-size:.68rem;text-transform:uppercase;padding:.3rem .65rem;
    border-radius:6px;border:1px solid var(--border);
    background:linear-gradient(135deg,rgba(30,45,61,.5),rgba(17,23,32,.5));
    color:var(--text);cursor:pointer;transition:all .3s;font-weight:600;letter-spacing:.04em;white-space:nowrap;}
.btn-cfg:hover:not(.muted){background:linear-gradient(135deg,rgba(255,179,0,.2),rgba(0,212,255,.2));border-color:var(--amber);color:var(--amber);}
.btn-cfg.muted{color:var(--text-dim);border-color:var(--border);cursor:not-allowed;opacity:.5;}

/* ══ ZONA ACTIVA ══ */
.active-zone{
    flex-shrink:0;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.5rem;
    align-items:stretch;
}

/* ── Columna TX ── */
.tx-col{
    display:flex;flex-direction:column;gap:.4rem;
    height:433px;
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%);
    border:1px solid var(--border);border-radius:8px;
    padding:.5rem .7rem;position:relative;overflow:hidden;
}
.tx-col::before,.lh-col::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,var(--violet),var(--cyan),var(--violet),transparent);}

/* Panel TX */
.tx-display-wrap{
    flex:1;min-height:0;
    display:flex;align-items:stretch;
    border:1px solid var(--border);border-radius:7px;
    background:rgba(0,0,0,.25);overflow:hidden;
}
.vu-side{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:.3rem;padding:.4rem .5rem;
    background:rgba(0,0,0,.15);
    border-right:1px solid var(--border);
    flex-shrink:0;
}
.vu-side.right{border-right:none;border-left:1px solid var(--border);}
.vu-label{font-family:var(--font-mono);font-size:.6rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.07em;}
.vu-track{width:22px;height:120px;background:linear-gradient(180deg,#0a0e14,#1a2535);border:1px solid var(--border);border-radius:3px;position:relative;overflow:hidden;}
.vu-track::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(to bottom,transparent 0,transparent 3px,rgba(255,255,255,.07) 3px,rgba(255,255,255,.07) 4px);z-index:2;pointer-events:none;}
.vu-fill{position:absolute;bottom:0;left:1px;right:1px;height:0%;background:linear-gradient(180deg,var(--green) 0%,var(--green) 60%,var(--amber) 80%,var(--red) 100%);border-radius:2px;transition:height .1s ease-out;z-index:1;}
.vu-fill.peak{background:linear-gradient(180deg,var(--amber) 0%,var(--red) 100%);}
.vu-peak{position:absolute;left:0;right:0;height:2px;background:var(--amber);opacity:.9;transition:bottom .2s;box-shadow:0 0 4px var(--amber);z-index:3;}
.vu-value{font-family:var(--font-mono);font-size:.65rem;font-weight:700;color:var(--cyan);}

/* Centro TX */
.tx-center{
    flex:1;display:flex;align-items:center;justify-content:center;
    padding:.6rem;font-family:var(--font-mono);
}
.tx-idle{color:var(--text-dim);font-size:1.2rem;letter-spacing:.1em;text-align:center;}
.tx-callsign{font-family:var(--font-ui);font-size:5.5rem;font-weight:900;color:var(--green);
    text-shadow:0 0 30px rgba(0,255,159,.6),0 0 60px rgba(0,255,159,.3);
    letter-spacing:.05em;display:flex;align-items:center;justify-content:center;gap:.5rem;
    animation:tx-glow 2s ease-in-out infinite;}
@keyframes tx-glow{0%,100%{text-shadow:0 0 30px rgba(0,255,159,.6),0 0 60px rgba(0,255,159,.3)}50%{text-shadow:0 0 50px rgba(0,255,159,.9),0 0 90px rgba(0,255,159,.4)}}
.tx-flag{font-size:3.8rem;line-height:1;}
.tx-info{display:flex;flex-direction:column;align-items:center;gap:.5rem;}
.tx-name{font-size:1.8rem;color:var(--cyan);font-weight:600;text-shadow:0 0 12px rgba(0,212,255,.4);display:block;text-align:center;margin-top:.3rem;margin-bottom:.6rem;}
.tx-meta{display:flex;gap:.8rem;align-items:center;font-size:.9rem;flex-wrap:wrap;justify-content:center;margin-top:.4rem;}
.tx-src{padding:.2rem .6rem;border-radius:6px;font-weight:700;font-family:var(--font-mono);text-transform:uppercase;font-size:.78rem;}
.tx-src.rf{background:rgba(0,255,159,.2);color:var(--green);border:1px solid rgba(0,255,159,.4);}
.tx-src.net{background:rgba(0,212,255,.2);color:var(--cyan);border:1px solid rgba(0,212,255,.4);}
.tx-dest{color:var(--amber);font-weight:700;}
.tx-time{color:var(--text-dim);font-family:var(--font-mono);font-size:.78rem;}
.flag-emoji{font-size:1em;line-height:1;vertical-align:middle;}
.flag-emoji-img{height:1.1em;width:auto;vertical-align:middle;}

/* Last Heard columna derecha */
.lh-col{
    display:flex;flex-direction:column;
    height:433px;
    background:linear-gradient(135deg,var(--surface) 0%,#0d1e2a 100%);
    border:1px solid var(--border);border-radius:8px;
    padding:.5rem .7rem;position:relative;overflow:hidden;
}
.lh-table{width:100%;border-collapse:collapse;font-family:var(--font-mono);font-size:.88rem;}
.lh-table th{text-align:left;padding:.4rem .6rem;background:rgba(0,0,0,.3);color:var(--text-dim);
    text-transform:uppercase;letter-spacing:.07em;font-size:.78rem;border-bottom:1px solid var(--border);}
.lh-table td{padding:.45rem .6rem;border-bottom:1px solid rgba(30,45,61,.4);color:var(--text);vertical-align:middle;}
.lh-table tr:hover td{background:rgba(181,122,255,.08);}
.tx-row td{background:rgba(0,255,159,.08);border-left:2px solid var(--green);}
.lh-cs{color:var(--violet);font-weight:bold;display:flex;align-items:center;gap:.4rem;font-size:.9rem;}
.lh-flag{font-size:1.2rem;}
.lh-empty{text-align:center;color:var(--text-dim);padding:1rem!important;font-style:italic;}
.lh-scroll{flex:1;overflow-y:auto;}
.lh-table .tx-src{font-size:.75rem;padding:.15rem .5rem;}

/* ── Logs: 2 paneles en fila horizontal ── */
.logs-wrap{
    flex-shrink:0;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:.5rem;
    height:220px;
}
.l-panel{
    flex:1;min-height:0;
    display:flex;flex-direction:column;
    border:1px solid var(--border);border-radius:7px;overflow:hidden;
    background:linear-gradient(180deg,var(--surface),#0d1e2a);
}
.l-head{display:flex;align-items:center;justify-content:space-between;padding:.3rem .65rem;
    background:rgba(0,0,0,.3);border-bottom:1px solid var(--border);flex-shrink:0;}
.l-title{font-family:var(--font-mono);font-size:.7rem;color:var(--violet);text-transform:uppercase;letter-spacing:.08em;font-weight:700;}
.l-actions{display:flex;gap:.3rem;}
.btn-log{font-family:var(--font-mono);font-size:.65rem;color:var(--text-dim);background:rgba(30,45,61,.5);
    border:1px solid var(--border);cursor:pointer;padding:.15rem .38rem;border-radius:4px;transition:all .3s;}
.btn-log:hover{color:var(--text);background:rgba(0,212,255,.2);border-color:var(--cyan);}
.l-out{flex:1;min-height:0;font-family:var(--font-mono);font-size:.82rem;line-height:1.6;
    color:#7a9ab5;padding:.4rem .6rem;overflow-y:auto;white-space:pre-wrap;word-break:break-word;background:rgba(0,0,0,.15);}
.l-out::-webkit-scrollbar{width:4px;} .l-out::-webkit-scrollbar-track{background:rgba(0,0,0,.2);}
.l-out::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px;}
.log-info{color:#7a9ab5;} .log-ok{color:var(--green);} .log-warn{color:var(--amber);} .log-err{color:var(--red);}

/* ══ FOOTER ══ */
.footer{color:var(--text-dim);font-family:var(--font-mono);font-size:.68rem;}
.footer-inner{
    padding:.3rem .8rem;text-align:center;
    background:var(--surface);
    border:1px solid var(--border);border-radius:8px;
}
.footer a{color:var(--cyan);text-decoration:none;} .footer a:hover{color:var(--violet);}

/* ══ MODALES ══ */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:1000;
    align-items:center;justify-content:center;backdrop-filter:blur(5px);}
.modal.open{display:flex;}
.m-box{background:linear-gradient(135deg,var(--surface),#0d1e2a);border:2px solid var(--border);
    border-radius:12px;padding:1.5rem;width:900px;max-width:95vw;max-height:90vh;
    display:flex;flex-direction:column;gap:1rem;box-shadow:0 20px 60px rgba(0,0,0,.5);}
.m-title{font-family:var(--font-mono);font-size:.9rem;color:var(--cyan);letter-spacing:.1em;text-transform:uppercase;font-weight:700;}
.m-path{font-family:var(--font-mono);font-size:.72rem;color:var(--amber);word-break:break-all;
    background:rgba(0,0,0,.3);padding:.4rem;border-radius:4px;border:1px solid var(--border);}
.m-editor{flex:1;font-family:var(--font-mono);font-size:.8rem;color:#c9d1d9;background:#060c10;
    border:2px solid var(--border);border-radius:7px;padding:.8rem;resize:vertical;outline:none;
    line-height:1.6;min-height:280px;}
.m-editor:focus{border-color:var(--cyan);box-shadow:0 0 12px rgba(0,212,255,.3);}
.m-msg{font-family:var(--font-mono);font-size:.72rem;padding:.4rem .75rem;border-radius:5px;display:none;border:1px solid;}
.m-msg.ok{color:var(--green);border-color:var(--green);background:rgba(0,255,159,.1);}
.m-msg.err{color:var(--red);border-color:var(--red);background:rgba(255,69,96,.1);}
.m-acts{display:flex;gap:.7rem;justify-content:flex-end;padding-top:.7rem;border-top:1px solid var(--border);}
.btn-act{font-family:var(--font-mono);font-size:.72rem;letter-spacing:.07em;text-transform:uppercase;
    padding:.55rem 1.1rem;border-radius:7px;border:2px solid var(--border);cursor:pointer;
    transition:all .3s;display:inline-flex;align-items:center;gap:.4rem;font-weight:700;}
.btn-act.stop{background:transparent;color:var(--red);border-color:var(--red);}
.btn-act.stop:hover{background:rgba(255,69,96,.15);}
.btn-act.start{background:transparent;color:var(--green);border-color:var(--green);}
.btn-act.start:hover{background:rgba(0,255,159,.15);}
.btn-act:disabled{opacity:.5;cursor:not-allowed;pointer-events:none;}
</style>
</head>
<body>

<!-- ══ TODO dentro del mismo container centrado ══ -->
<div class="page-wrap">

<!-- HEADER -->
<header class="header">
    <div style="display:flex;align-items:center;gap:.55rem;flex-shrink:0;">
        <h1>🔗 YSF ⇄ DMR</h1>
        <span class="badge-direct">BRIDGE</span>
    </div>
    <div class="h-status">
        <div class="s-item"><span class="s-dot" id="dot-mmd"></span><span class="s-label">MMDVMYSF2DMR:</span><span class="s-val" id="val-mmd">—</span></div>
        <div class="s-item"><span class="s-dot" id="dot-y2d"></span><span class="s-label">YSF2DMR:</span><span class="s-val" id="val-y2d">—</span></div>
        <div class="h-ts">ACT: <span id="ts">—</span></div>
    </div>
    <div class="h-toggle" style="flex-shrink:0;">
        <span class="h-tlabel">PUENTE:</span>
        <label class="sw" id="sw"><input type="checkbox" id="chk" onchange="toggle(this)"><span class="sw-track"></span><span class="sw-knob"></span></label>
        <span class="t-status" id="sts">OFF</span>
    </div>
    <a href="mmdvm.php" class="btn-nav primary" style="flex-shrink:0;">🏠 PHPPLUS</a>
</header>

<!-- CONTENIDO -->

    <!-- CARD CONFIG -->
    <div class="card-cfg">
        <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;">
            <span class="c-title" style="margin-bottom:0;">⚙️ Control del Puente</span>
            <div class="arch-info" style="flex:1;min-width:180px;margin-bottom:0;">
                <span style="color:var(--cyan);">ℹ️</span> <strong>Arquitectura:</strong> MMDVMHost ⇄ YSF2DMR
            </div>
            <div class="cfg-row" style="flex-shrink:0;">
                <button class="btn-cfg" onclick="openCfg('mmdvm')">📄 MMDVMYSF2DMR.ini</button>
                <button class="btn-cfg" onclick="openCfg('ysf2dmr')">📄 YSF2DMR.ini</button>
            </div>
        </div>
    </div>

    <!-- ZONA ACTIVA: TX izquierda + Last Heard derecha -->
    <div class="active-zone">

        <!-- TX -->
        <div class="tx-col">
            <div class="c-title" style="margin-bottom:0;flex-shrink:0;">📡 Transmisión Activa</div>
            <div class="tx-display-wrap">
                <div class="vu-side">
                    <div class="vu-label">AUDIO</div>
                    <div class="vu-track"><div class="vu-fill" id="vu1"></div><div class="vu-peak" id="vu1Peak"></div></div>
                    <div class="vu-value" id="vu1Val">0%</div>
                </div>
                <div class="tx-center">
                    <div class="tx-idle" id="txCenter">⏸ Pausa > Esperando actividad</div>
                </div>
                <div class="vu-side right" style="visibility:hidden;">
                    <div class="vu-label">SL 2</div>
                    <div class="vu-track"><div class="vu-fill" id="vu2"></div><div class="vu-peak" id="vu2Peak"></div></div>
                    <div class="vu-value" id="vu2Val">0%</div>
                </div>
            </div>
        </div>

        <!-- Last Heard -->
        <div class="lh-col">
            <div class="c-title" style="margin-bottom:.3rem;flex-shrink:0;">📋 Últimas Estaciones Escuchadas</div>
            <div class="lh-scroll">
                <table class="lh-table">
                    <thead><tr><th>Indicativo</th><th>Nombre</th><th>TG</th><th>Hora</th><th>Origen</th></tr></thead>
                    <tbody id="lhBody"><tr><td colspan="5" class="lh-empty">Sin actividad reciente</td></tr></tbody>
                </table>
            </div>
        </div>

    </div><!-- /active-zone -->

    <!-- LOGS: 2 paneles en fila horizontal -->
    <div class="logs-wrap">
        <div class="l-panel">
            <div class="l-head">
                <span class="l-title">📋 MMDVMYSF2DMR</span>
                <div class="l-actions">
                    <button class="btn-log" onclick="refreshLogs()" title="Actualizar">🔄</button>
                    <button class="btn-log" onclick="clearLog('lMmd')" title="Limpiar">🗑</button>
                </div>
            </div>
            <div class="l-out" id="lMmd">Esperando…</div>
        </div>
        <div class="l-panel">
            <div class="l-head">
                <span class="l-title" style="color:#c9a0ff;">📋 YSF2DMR</span>
                <div class="l-actions">
                    <button class="btn-log" onclick="refreshLogs()" title="Actualizar">🔄</button>
                    <button class="btn-log" onclick="clearLog('lY2D')" title="Limpiar">🗑</button>
                </div>
            </div>
            <div class="l-out" id="lY2D">Esperando…</div>
        </div>
    </div><!-- /logs-wrap -->

<!-- FOOTER -->
<footer class="footer">
    <div class="footer-inner">
        Panel Bridge YSF⇄DMR | <a href="mmdvm.php">Volver al panel PHPPLUS</a>
    </div>
</footer>

</div><!-- /page-wrap -->

<!-- ══ MODAL CFG ══ -->
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

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const fmtT = ts => new Date(ts*1000).toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
const api = async (a, p={}, m='GET') => {
    const u = new URL(location.href); u.searchParams.set('action',a);
    const o = {method:m,headers:{'Content-Type':'application/x-www-form-urlencoded'}};
    if(m==='POST' && Object.keys(p).length) o.body = new URLSearchParams(p).toString();
    const r = await fetch(u.toString(),o);
    if(!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
};

const _winOS = /Windows/i.test(navigator.userAgent);
const _TBASE = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/';
const _FLAGS = [
    {re:/^E[ABCDEFGH][1-9]/,e:'🇪🇸',t:'1f1ea-1f1f8'},{re:/^C[TUQ]/,e:'🇵🇹',t:'1f1f5-1f1f9'},
    {re:/^F[A-Z]/,e:'🇫🇷',t:'1f1eb-1f1f7'},{re:/^I[0-9]|^IK|^IW|^IZ/,e:'🇮🇹',t:'1f1ee-1f1f9'},
    {re:/^G[0-9]|^M[0-9]|^2E|^GB|^MJ|^MU/,e:'🇬🇧',t:'1f1ec-1f1e7'},{re:/^D[A-R]|^Y[2-9]/,e:'🇩🇪',t:'1f1e9-1f1ea'},
    {re:/^[KWN][0-9]|^AA|^AB|^AC|^AD|^AE|^AF/,e:'🇺🇸',t:'1f1fa-1f1f8'},{re:/^VE|^VA|^VO|^VY/,e:'🇨🇦',t:'1f1e8-1f1e6'},
    {re:/^PY|^PU|^PV|^PW|^PX/,e:'🇧🇷',t:'1f1e7-1f1f7'},{re:/^LU|^LV|^LW|^LX/,e:'🇦🇷',t:'1f1e6-1f1f7'},
    {re:/^JA|^JE|^JF|^JG|^JH|^JI|^JJ|^JK|^JL|^JR/,e:'🇯🇵',t:'1f1ef-1f1f5'},{re:/^VK/,e:'🇦🇺',t:'1f1e6-1f1fa'},
    {re:/^ZS|^ZT|^ZU/,e:'🇿🇦',t:'1f1ff-1f1e6'},{re:/^OH|^OG/,e:'🇫🇮',t:'1f1eb-1f1ee'},
    {re:/^PA|^PB|^PC|^PD|^PE|^PF|^PG|^PH/,e:'🇳🇱',t:'1f1f3-1f1f1'},{re:/^HB/,e:'🇨🇭',t:'1f1e8-1f1ed'},
    {re:/^OE/,e:'🇦🇹',t:'1f1e6-1f1f9'},{re:/^SP|^SQ|^SR|^HF/,e:'🇵🇱',t:'1f1f5-1f1f1'},
    {re:/^UA|^UB|^UC|^UD|^UE|^UF|^RA|^RB|^RC/,e:'🇷🇺',t:'1f1f7-1f1fa'},{re:/^SV|^SW|^SX|^SY|^SZ/,e:'🇬🇷',t:'1f1ec-1f1f7'},
    {re:/^LY/,e:'🇱🇹',t:'1f1f1-1f1f9'},{re:/^9A/,e:'🇭🇷',t:'1f1ed-1f1f7'}
];

function getFlag(callsign){
    if(!callsign) return '';
    const cs=callsign.toUpperCase().trim();
    for(const p of _FLAGS){ if(p.re.test(cs)){
        if(_winOS) return '<img class="flag-emoji-img" src="'+_TBASE+p.t+'.png" alt="">';
        return '<span class="flag-emoji">'+p.e+'</span>';
    }}
    return '<span class="flag-emoji">🌐</span>';
}

let S={active:false,poll:null,logT:null,txT:null,last:null,busy:false,cfgId:null};

function setDot(id,v){
    const e=$(id),vEl=$(id.replace('dot-','val-'));
    if(!e||!vEl)return;
    e.className='s-dot '+(v==='active'?'on':'off');
    vEl.textContent=v==='active'?'ON':'OFF';
    vEl.className='s-val '+(v==='active'?'on':'off');
}
function setToggle(on,busy=false){
    const chk=$('chk'),sts=$('sts'),sw=$('sw');
    if(!busy){chk.checked=on;sts.textContent=on?'ON':'OFF';sts.className='t-status '+(on?'on':'off');}
    sw.classList.toggle('busy',busy);S.busy=busy;
}
function updateVU(slot,level){
    const fill=$('vu'+slot),peak=$('vu'+slot+'Peak'),val=$('vu'+slot+'Val');
    if(!fill||!peak||!val)return;
    level=Math.max(0,Math.min(100,level));
    fill.style.height=level+'%';val.textContent=level+'%';peak.style.bottom=level+'%';
    fill.classList.toggle('peak',level>=90);
}

async function status(){
    try{
        const d=await api('status');
        setDot('dot-mmd',d.mmdvm);setDot('dot-y2d',d.ysf2dmr);
        const desiredOn = (d.state_on !== null && d.state_on !== undefined) ? !!d.state_on : !!d.bridge_active;
        if(!S.busy&&desiredOn!==S.active)setToggle(desiredOn,false);
        S.active=desiredOn;$('ts').textContent=fmtT(d.ts);
        document.querySelectorAll('.btn-cfg').forEach(btn=>{
            const m=btn.getAttribute('onclick').match(/'([^']+)'/);
            if(m&&d.perms[m[1]])btn.classList.toggle('muted',!d.perms[m[1]].writable);
        });
    }catch(e){console.warn('status err',e);}
}

async function refreshLogs(){
    try{
        const d=await api('logs',{lines:80});
        const panels={lMmd:d.mmdvm,lY2D:d.ysf2dmr};
        for(let[id,txt]of Object.entries(panels)){
            const el=$(id);
            const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+20;
            el.innerHTML=txt?colorizeLog(txt):'<span class="log-info">Sin salida en vivo</span>';
            if(atBot)el.scrollTop=el.scrollHeight;
        }
    }catch(e){console.warn('logs err',e);}
}
async function fetchLogs(){
    try{
        const d=await api('logs',{lines:80});
        const panels={lMmd:d.mmdvm,lY2D:d.ysf2dmr};
        for(let[id,txt]of Object.entries(panels)){
            const el=$(id);
            const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+20;
            el.innerHTML=txt?colorizeLog(txt):'<span class="log-info">Sin salida en vivo</span>';
            if(atBot)el.scrollTop=el.scrollHeight;
        }
    }catch(e){console.warn('logs err',e);}
}

async function fetchTransmission(){
    try{
        const r=await api('transmission');
        const d=r.state||{};
        const txCenter=$('txCenter');
        if(d.active&&d.callsign){
            const flag=getFlag(d.callsign);
            const nameHtml=d.name?`<span class="tx-name">(${esc(d.name)})</span>`:'';
            const sourceBadge=`<span class="tx-src ${d.source==='YSF'?'rf':'net'}">${d.source||'YSF'}</span>`;
            const metaHtml=`<div class="tx-meta">${sourceBadge}<span class="tx-dest">→ TG ${d.tg||'—'}</span>${d.duration?`<span class="tx-time">⏱ ${esc(d.duration)}</span>`:''}</div>`;
            txCenter.innerHTML=`<div class="tx-info"><div class="tx-callsign"><span class="tx-flag">${flag}</span>${esc(d.callsign)}</div>${nameHtml}${metaHtml}</div>`;
        }else{
            txCenter.innerHTML='<div class="tx-idle">⏸ Pausa > Esperando actividad</div>';
        }
        if(r.vu){updateVU(1,r.vu.slot1||0);}
        const tbody=$('lhBody');const list=r.lastHeard||[];
        if(list.length===0){
            tbody.innerHTML='<tr><td colspan="5" class="lh-empty">Sin actividad reciente</td></tr>';
        }else{
            tbody.innerHTML=list.map(r=>{
                const flag=getFlag(r.callsign);
                const isTx=d.active&&r.callsign===d.callsign&&r.tg===d.tg;
                const durLoss=r.duration?`<small style="color:var(--text-dim)">(${r.duration})</small>`:'';
                return `<tr class="${isTx?'tx-row':''}">
                    <td><span class="lh-cs"><span class="lh-flag">${flag}</span>${esc(r.callsign)}</span></td>
                    <td>${esc(r.name||'—')}</td><td>TG ${esc(r.tg||'—')}</td>
                    <td>${esc(r.time||'—')}</td>
                    <td><span class="tx-src ${r.source==='YSF'?'rf':'net'}">${r.source||'YSF'}</span> ${durLoss}</td>
                </tr>`;
            }).join('');
        }
    }catch(e){console.warn('tx err',e);}
}

async function toggle(chk){
    const target=chk.checked;setToggle(target,true);
    try{
        const res=await api(target?'start':'stop',{},'POST');
        if(!res.ok){alert('❌ '+res.msg);setToggle(!target,false);return;}
        await new Promise(r=>setTimeout(r,2500));
        S.busy=false;
        await status();fetchLogs();
        setToggle(target,false);
        if(target===false){['lMmd','lY2D'].forEach(id=>$(id).innerHTML='<span class="log-info">Logs limpiados.</span>');$('lhBody').innerHTML='<tr><td colspan="5" class="lh-empty">Sin actividad reciente</td></tr>';$('txCenter').innerHTML='<div class="tx-idle">⏸ Pausa > Esperando actividad</div>';updateVU(1,0);}
    }catch(e){console.error(e);setToggle(!target,false);alert('⚠️ Error: '+e.message);}
}

async function openCfg(id){
    S.cfgId=id;const m=$('cfgModal'),a=$('cfgArea'),p=$('cfgPath'),msg=$('cfgMsg');
    msg.style.display='none';a.disabled=true;m.classList.add('open');
    try{
        const d=await api('cfg-read',{id},'POST');
        if(d.ok){p.textContent=d.path;a.value=d.content;a.disabled=false;a.focus();}
        else{p.textContent='Error';msg.className='m-msg err';msg.textContent='✖ '+d.msg;msg.style.display='block';}
    }catch(e){msg.className='m-msg err';msg.textContent='✖ '+e.message;msg.style.display='block';}
}
function closeCfg(){$('cfgModal').classList.remove('open');S.cfgId=null;}
async function saveCfg(){
    if(!S.cfgId)return;
    const msg=$('cfgMsg');msg.style.display='block';msg.className='m-msg';msg.textContent='⏳ Guardando…';
    try{
        const d=await api('cfg-save',{id:S.cfgId,content:$('cfgArea').value},'POST');
        msg.className='m-msg '+(d.ok?'ok':'err');msg.textContent=(d.ok?'✅ ':'❌ ')+d.msg;
        if(d.ok)setTimeout(closeCfg,1500);
    }catch(e){msg.className='m-msg err';msg.textContent='✖ '+e.message;}
}
const clearLog=id=>{if(!confirm('¿Limpiar logs?'))return;$(id).innerHTML='<span class="log-info">Limpiado.</span>';};
function colorizeLog(text){
    return text.split('\n').map(l=>{
        const ll=l.toLowerCase();
        if(/error|fail|abort|exception|denied|segfault/i.test(ll))return`<span class="log-err">${esc(l)}</span>`;
        if(/warn|warning|timeout/i.test(ll))return`<span class="log-warn">${esc(l)}</span>`;
        if(/connect|start|open|loaded|success|tx|rx|linked|tg|ysf|slot|mode|sigterm|stopped|received|header|src/i.test(ll))return`<span class="log-ok">${esc(l)}</span>`;
        return`<span class="log-info">${esc(l)}</span>`;
    }).join('\n');
}
function startPoll(){
    status();refreshLogs();fetchTransmission();
    S.poll=setInterval(status,5000);
    S.logT=setInterval(refreshLogs,7000);
    S.txT=setInterval(fetchTransmission,1000);
}
document.addEventListener('keydown',e=>{
    if(e.key==='Escape'&&$('cfgModal').classList.contains('open'))closeCfg();
});
window.addEventListener('load',startPoll);
</script>
</body>
</html>

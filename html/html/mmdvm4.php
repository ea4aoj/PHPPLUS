<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=UTF-8');
$action = $_GET['action'] ?? '';

function saveState($key, $value) {
    $file = '/var/lib/mmdvm-state';
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    foreach ($lines as &$line) {
        if (strpos($line, $key . '=') === 0) { $line = $key . '=' . $value; $found = true; }
    }
    unset($line);
    if (!$found) $lines[] = $key . '=' . $value;
    file_put_contents($file, implode("\n", $lines) . "\n");
}

function parseMMDVMIni($path) {
    $result = []; if (!file_exists($path)) return $result;
    $section = '';
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) { $section = trim($m[1]); continue; }
        if (preg_match('/^([^=]+)=(.*)$/', $line, $m)) { $result[$section][trim($m[1])] = trim($m[2]); }
    }
    return $result;
}

function latLonToLocator($lat, $lon) {
    $lat = floatval($lat) + 90; $lon = floatval($lon) + 180; $A = ord('A');
    $f1 = chr($A + intval($lon / 20)); $f2 = chr($A + intval($lat / 10));
    $f3 = strval(intval(fmod($lon, 20) / 2)); $f4 = strval(intval(fmod($lat, 10)));
    $f5 = chr($A + intval(fmod($lon, 2) * 12)); $f6 = chr($A + intval(fmod($lat, 1) * 24));
    return strtoupper($f1 . $f2 . $f3 . $f4) . strtolower($f5 . $f6);
}

function formatFreq($hz) {
    $mhz = floatval($hz) / 1000000;
    return number_format($mhz, 3, '.', '') . ' MHz';
}

if ($action === 'read-file') {
    $path = trim($_POST['path'] ?? '');
    if ($path === '' || !file_exists($path)) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok'=>false,'msg'=>'Fichero no encontrado: '.$path], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $content = file_get_contents($path);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok'=>true,'content'=>$content,'path'=>$path], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save-file') {
    $path    = trim($_POST['path'] ?? '');
    $content = $_POST['content'] ?? '';
    if ($path === '') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok'=>false,'msg'=>'Ruta vacía'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = file_put_contents($path, $content);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result !== false ? ['ok'=>true,'msg'=>'Guardado correctamente'] : ['ok'=>false,'msg'=>'Error al escribir el fichero'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'terminal') {
    $cmd = trim($_POST['cmd'] ?? '');
    if (preg_match('/^\s*(vim|vi|less|more|top|htop|su)\s*/i', $cmd)) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['output' => 'Comando interactivo no soportado. Usa: nano /ruta/fichero'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (preg_match('/(rm\s+-rf|shutdown|reboot|mkfs|dd\s+if=)/i', $cmd)) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['output' => '❌ Comando bloqueado por seguridad'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $out = $cmd !== '' ? (shell_exec('/usr/bin/sudo -n -u pi -H bash -c ' . escapeshellarg($cmd) . ' 2>&1') ?? '') : '';
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => htmlspecialchars($out, ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'station-info') {
    $iniPath = '/home/pi/MMDVMHost/MMDVMHost.ini';
    $ini = parseMMDVMIni($iniPath);
    $callsign = $ini['General']['Callsign'] ?? 'EA1HG';
    $dmrid    = $ini['General']['Id'] ?? '214317526';
    $txfreq   = $ini['General']['TXFrequency'] ?? ($ini['General']['Frequency'] ?? '430000000');
    $lat      = $ini['Info']['Latitude']    ?? '40.9651';
    $lon      = $ini['Info']['Longitude']   ?? '-5.6634';
    $location = $ini['Info']['Location']    ?? 'Salamanca';
    $desc     = $ini['Info']['Description'] ?? '';
    $locator  = (floatval($lat) != 0 || floatval($lon) != 0) ? latLonToLocator($lat, $lon) : 'IN71CK';
    $port     = $ini['Modem']['UARTPort'] ?? ($ini['modem']['UARTPort'] ?? '');
    $rxhz     = $ini['Info']['RXFrequency'] ?? '0';
    $txhz     = $ini['Info']['TXFrequency'] ?? $txfreq;
    $freqRX   = formatFreq($rxhz); $freq = formatFreq($txhz);
    $iniIp    = trim($ini['General']['Address'] ?? '');
    if ($iniIp === '' || $iniIp === '0.0.0.0') $iniIp = trim(shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    $ip = $iniIp ?: '—';
    
    $ysfIniPath = '/home/pi/MMDVMHost/MMDVMYSF.ini'; $ysfIni = parseMMDVMIni($ysfIniPath);
    $ysfPort   = $ysfIni['Modem']['UARTPort'] ?? ($ysfIni['modem']['UARTPort'] ?? '—');
    $ysfRxHz   = $ysfIni['Info']['RXFrequency'] ?? '0'; $ysfTxHz = $ysfIni['Info']['TXFrequency'] ?? '0';
    $ysfFreqRX = formatFreq($ysfRxHz); $ysfFreqTX = formatFreq($ysfTxHz);
    $ysfIpRaw  = trim($ysfIni['General']['Address'] ?? '');
    $ysfIp     = ($ysfIpRaw !== '' && $ysfIpRaw !== '0.0.0.0') ? $ysfIpRaw : $ip;

    $dstarIniPath = '/home/pi/MMDVMHost/MMDVMDSTAR.ini';
    $dstarIni = parseMMDVMIni($dstarIniPath);
    $dstarPort  = $dstarIni['Modem']['UARTPort'] ?? ($dstarIni['modem']['UARTPort'] ?? '—');
    $dstarRxHz  = $dstarIni['Info']['RXFrequency'] ?? '0';
    $dstarTxHz  = $dstarIni['Info']['TXFrequency'] ?? '0';
    $dstarFreqRX = formatFreq($dstarRxHz);
    $dstarFreqTX = formatFreq($dstarTxHz);
    $dstarIpRaw = trim($dstarIni['General']['Address'] ?? '');
    $dstarIp    = ($dstarIpRaw !== '' && $dstarIpRaw !== '0.0.0.0') ? $dstarIpRaw : $ip;

    $nxdnIniPath = '/home/pi/MMDVMHost/MMDVMNXDN.ini';
    $nxdnIni = parseMMDVMIni($nxdnIniPath);
    $nxdnPort   = $nxdnIni['Modem']['UARTPort'] ?? ($nxdnIni['modem']['UARTPort'] ?? '—');
    $nxdnRxHz   = $nxdnIni['Info']['RXFrequency'] ?? '0';
    $nxdnTxHz   = $nxdnIni['Info']['TXFrequency'] ?? '0';
    $nxdnFreqRX = formatFreq($nxdnRxHz);
    $nxdnFreqTX = formatFreq($nxdnTxHz);
    $nxdnIpRaw  = trim($nxdnIni['General']['Address'] ?? '');
    $nxdnIp     = ($nxdnIpRaw !== '' && $nxdnIpRaw !== '0.0.0.0') ? $nxdnIpRaw : $ip;

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'callsign'=>strtoupper(trim($callsign)),'dmrid'=>trim($dmrid),'freq'=>$freq,'freqRX'=>$freqRX,
        'port'=>$port?:'—','ip'=>$ip,'locator'=>$locator,'location'=>trim($location),'desc'=>trim($desc),'lat'=>$lat,'lon'=>$lon,
        'ysfPort'=>$ysfPort?:'—','ysfFreqRX'=>$ysfFreqRX,'ysfFreqTX'=>$ysfFreqTX,'ysfIp'=>$ysfIp?:'—',
        'dstarPort'=>$dstarPort?:'—','dstarFreqRX'=>$dstarFreqRX,'dstarFreqTX'=>$dstarFreqTX,'dstarIp'=>$dstarIp?:'—',
        'nxdnPort'=>$nxdnPort?:'—','nxdnFreqRX'=>$nxdnFreqRX,'nxdnFreqTX'=>$nxdnFreqTX,'nxdnIp'=>$nxdnIp?:'—'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'sysinfo') {
    $s1 = file('/proc/stat'); $cpu1 = preg_split('/\s+/', trim($s1[0])); usleep(300000);
    $s2 = file('/proc/stat'); $cpu2 = preg_split('/\s+/', trim($s2[0]));
    $idle1 = $cpu1[4]; $total1 = array_sum(array_slice($cpu1, 1));
    $idle2 = $cpu2[4]; $total2 = array_sum(array_slice($cpu2, 1));
    $dTotal = $total2 - $total1; $dIdle = $idle2 - $idle1;
    $cpu = $dTotal > 0 ? round(100 * ($dTotal - $dIdle) / $dTotal, 1) : 0;
    $memRaw = file('/proc/meminfo'); $mem = [];
    foreach ($memRaw as $line) { if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) $mem[$m[1]] = intval($m[2]); }
    $ramTotal = round($mem['MemTotal'] / 1048576, 2);
    $ramFree  = round(($mem['MemAvailable'] ?? $mem['MemFree']) / 1048576, 2);
    $ramUsed  = round($ramTotal - $ramFree, 2);
    $diskTotal = round(disk_total_space('/') / 1073741824, 1);
    $diskFree  = round(disk_free_space('/') / 1073741824, 1);
    $diskUsed  = round($diskTotal - $diskFree, 1);
    $temp = '';
    if (file_exists('/sys/class/thermal/thermal_zone0/temp'))
        $temp = round(intval(trim(file_get_contents('/sys/class/thermal/thermal_zone0/temp'))) / 1000, 1) . ' °C';
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['cpu'=>$cpu,'ramTotal'=>$ramTotal,'ramUsed'=>$ramUsed,'ramFree'=>$ramFree,'diskTotal'=>$diskTotal,'diskUsed'=>$diskUsed,'diskFree'=>$diskFree,'temp'=>$temp], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'status') {
    $gw = trim(shell_exec('systemctl is-active dmrgateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmhost 2>/dev/null'));
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['gateway'=>$gw,'mmdvm'=>$mmd], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'start') {
    saveState('dmr','on'); shell_exec('sudo systemctl start dmrgateway 2>/dev/null'); sleep(2);
    shell_exec('sudo systemctl start mmdvmhost 2>/dev/null');
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'stop') {
    saveState('dmr','off'); shell_exec('sudo systemctl stop mmdvmhost 2>/dev/null'); sleep(1);
    shell_exec('sudo systemctl stop dmrgateway 2>/dev/null');
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'update-imagen') { $output = shell_exec('sudo sh /home/pi/A108/actualiza_imagen.sh 2>&1'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'(sin salida)', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'update-ids')    { $output = shell_exec('sudo sh /home/pi/A108/actualizar_ids.sh 2>&1'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'(sin salida)', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'update-ysf')    { $output = shell_exec('sudo sh /home/pi/A108/actualizar_reflectores_ysf.sh 2>&1'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'(sin salida)', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'ysf-status') {
    $st = trim(shell_exec('sudo /usr/local/bin/ysf_status.sh 2>/dev/null'));
    if ($st === 'active') { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ysf'=>'active'], JSON_UNESCAPED_UNICODE); exit; }
    $pid = trim(@file_get_contents('/tmp/ysfgateway.pid'));
    $active = ($pid && is_numeric($pid) && file_exists('/proc/'.$pid)) ? 'active' : 'inactive';
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ysf'=>$active], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'ysf-start')  { saveState('ysf','on'); shell_exec('sudo systemctl start ysfgateway 2>/dev/null'); sleep(1); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'ysf-stop')   { saveState('ysf','off'); shell_exec('sudo systemctl stop ysfgateway 2>/dev/null'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'mmdvmysf-status') { $st = trim(shell_exec('systemctl is-active mmdvmysf 2>/dev/null')); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['mmdvmysf'=>$st], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'mmdvmysf-start')  { saveState('ysf','on'); shell_exec('sudo systemctl start mmdvmysf 2>/dev/null'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'mmdvmysf-stop')   { saveState('ysf','off'); shell_exec('sudo systemctl stop ysfgateway 2>/dev/null'); sleep(1); shell_exec('sudo systemctl stop mmdvmysf 2>/dev/null'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'mmdvmysf-logs')   { $lines = intval($_GET['lines']??15); $log = shell_exec("sudo journalctl -u mmdvmysf -n {$lines} --no-pager --output=short 2>/dev/null"); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['mmdvmysf'=>htmlspecialchars($log??'', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'reboot')          { shell_exec('sudo /usr/bin/systemctl reboot 2>/dev/null'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'display-restart') { shell_exec('sudo systemctl daemon-reload 2>/dev/null'); shell_exec('sudo systemctl enable displaydriver 2>/dev/null'); shell_exec('sudo systemctl restart displaydriver 2>/dev/null'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'install-display') { $output = shell_exec('sudo /home/pi/A108/instalar_displaydriver.sh 2>&1'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'backup-configs') {
    $zipName = 'Copia_PHP2.zip'; $zipPath = '/tmp/'.$zipName;
    $files = ['/home/pi/MMDVMHost/MMDVMHost.ini','/home/pi/MMDVMHost/MMDVMYSF.ini','/home/pi/MMDVMHost/MMDVMDSTAR.ini','/home/pi/MMDVMHost/MMDVMNXDN.ini','/home/pi/Display-Driver/DisplayDriver.ini','/home/pi/YSFClients/YSFGateway/YSFGateway.ini','/home/pi/DMRGateway/DMRGateway.ini','/home/pi/DStarGateway/DStarGateway.ini','/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini'];
    $fileList = implode(' ', array_map('escapeshellarg', $files));
    shell_exec("zip -j ".escapeshellarg($zipPath)." {$fileList} 2>/dev/null");
    if (file_exists($zipPath)) { header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$zipName.'"'); header('Content-Length: '.filesize($zipPath)); header('Pragma: no-cache'); header('Expires: 0'); readfile($zipPath); unlink($zipPath); } else { header('Content-Type: text/plain; charset=UTF-8'); echo 'Error: No se pudo crear el ZIP.'; }
    exit;
}

if ($action === 'restore-configs') {
    ob_start(); error_reporting(0);
    $uploadOk = isset($_FILES['zipfile']) && $_FILES['zipfile']['error'] === UPLOAD_ERR_OK;
    if (!$uploadOk) { $errCode = $_FILES['zipfile']['error']??-1; ob_end_clean(); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'msg'=>'No se recibió el fichero. Error: '.$errCode], JSON_UNESCAPED_UNICODE); exit; }
    $tmpZip = $_FILES['zipfile']['tmp_name'];
    if (!file_exists($tmpZip)||filesize($tmpZip)===0) { ob_end_clean(); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'msg'=>'Fichero vacío.'], JSON_UNESCAPED_UNICODE); exit; }
    $destMap = ['MMDVMHost.ini'=>'/home/pi/MMDVMHost/MMDVMHost.ini','MMDVMYSF.ini'=>'/home/pi/MMDVMHost/MMDVMYSF.ini','DisplayDriver.ini'=>'/home/pi/Display-Driver/DisplayDriver.ini','YSFGateway.ini'=>'/home/pi/YSFClients/YSFGateway/YSFGateway.ini','DMRGateway.ini'=>'/home/pi/DMRGateway/DMRGateway.ini','DStarGateway.ini'=>'/home/pi/DStarGateway/DStarGateway.ini','NXDNGateway.ini'=>'/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini','MMDVMDSTAR.ini'=>'/home/pi/MMDVMHost/MMDVMDSTAR.ini','MMDVMNXDN.ini'=>'/home/pi/MMDVMHost/MMDVMNXDN.ini'];
    $zip = new ZipArchive(); $openResult = $zip->open($tmpZip);
    if ($openResult !== true) { ob_end_clean(); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'msg'=>'No se pudo abrir el ZIP. Código: '.$openResult], JSON_UNESCAPED_UNICODE); exit; }
    $restored = []; $errors = [];
    for ($i=0;$i<$zip->numFiles;$i++) { $name=basename($zip->getNameIndex($i)); if(isset($destMap[$name])){$result=file_put_contents($destMap[$name],$zip->getFromIndex($i));if($result!==false)$restored[]=$name;else $errors[]=$name;} }
    $zip->close(); ob_end_clean();
    if (empty($restored)) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'msg'=>'No se encontraron ficheros compatibles.'], JSON_UNESCAPED_UNICODE); exit; }
    $msg = 'Restaurados: '.implode(', ',$restored); if($errors)$msg.=' | Errores: '.implode(', ',$errors);
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true,'msg'=>$msg], JSON_UNESCAPED_UNICODE); exit;
}

if ($action === 'logs') {
    $lines = intval($_GET['lines']??15);
    $gw  = shell_exec("sudo journalctl -u dmrgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmhost -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['gateway'=>htmlspecialchars($gw??'', ENT_QUOTES, 'UTF-8'),'mmdvm'=>htmlspecialchars($mmd??'', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit;
}

if ($action === 'ysf-logs') {
    $lines = intval($_GET['lines']??15);
    $log = shell_exec("sudo journalctl -u ysfgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) $log = shell_exec("tail -n {$lines} /tmp/ysfgateway.log 2>/dev/null");
    if (empty(trim($log))) { $logFile = glob('/home/pi/YSFClients/YSFGateway/YSFGateway-*.log'); if($logFile){$latest=end($logFile);$log=shell_exec("tail -n {$lines} ".escapeshellarg($latest)." 2>/dev/null");} }
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ysf'=>htmlspecialchars($log??'', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit;
}

function lookupCall($callsign) {
    $datFiles=['/home/pi/MMDVMHost/DMRIds.dat','/etc/DMRIds.dat','/usr/local/etc/DMRIds.dat'];
    $cs=strtoupper(trim($callsign));
    foreach ($datFiles as $f) {
        if(!file_exists($f))continue;
        $cmd="awk -F'\t' '{if (toupper(\$2)==\"" . $cs . "\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' ".escapeshellarg($f)." 2>/dev/null";
        $row=trim(shell_exec($cmd));
        if($row!==''){$parts=explode("\t",$row);return['dmrid'=>trim($parts[0]??''),'name'=>trim($parts[2]??'')];}
    }
    return ['dmrid'=>'','name'=>''];
}

if ($action === 'transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmhost -n 200 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n",$log));
    $active=false;$callsign='';$dmrid='';$name='';$tg='';$slot='';$source='';
    foreach ($lines as $line) {
        if(preg_match('/DMR Slot \d.*(end of voice|lost RF|watchdog)/i',$line)){$active=false;break;}
        if(preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i',$line,$m)){$active=true;$slot=$m[1];$source=strtoupper($m[2]);$callsign=strtoupper(rtrim($m[3],','));$tg=$m[4];break;}
    }
    if($callsign){$info=lookupCall($callsign);$dmrid=$info['dmrid'];$name=$info['name'];}
    $lastHeard=[];$seen=[];
    foreach ($lines as $line) {
        if(preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i',$line,$m)){
            $cs=strtoupper(rtrim($m[4],','));
            if(!in_array($cs,$seen)){$inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'dmrid'=>$inf['dmrid'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]];$seen[]=$cs;if(count($lastHeard)>=5)break;}
        }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dmrid'=>$dmrid,'tg'=>$tg,'slot'=>$slot,'source'=>$source,'lastHeard'=>$lastHeard], JSON_UNESCAPED_UNICODE); exit;
}

if ($action === 'dstar-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmdstar -n 300 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log ?? ''));
    $active = false; $callsign = ''; $source = ''; $name = '';
    foreach ($lines as $line) {
        if (preg_match('/D-Star.*(end of|lost RF|watchdog|finished|timeout)/i', $line)) { $active = false; break; }
        if (preg_match('/D-Star.*received (RF|network).*from\s+([A-Z0-9\/]+)/i', $line, $m)) { $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); break; }
        if (preg_match('/received (RF|network) header from\s+([A-Z0-9\/]+)/i', $line, $m)) { $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); break; }
    }
    if ($callsign) { $info = lookupCall(preg_replace('/\/.*$/', '', $callsign)); $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs = ''; $src = ''; $time = '';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*D-Star.*received (RF|network).*from\s+([A-Z0-9\/]+)/i', $line, $m)) { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*received (RF|network) header from\s+([A-Z0-9\/]+)/i', $line, $m)) { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        if ($cs && !in_array($cs, $seen)) { $inf = lookupCall(preg_replace('/\/.*$/','',$cs)); $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'source'=>$src,'time'=>$time]; $seen[] = $cs; if (count($lastHeard) >= 5) break; }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'source'=>$source,'lastHeard'=>$lastHeard], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'dstar-status') {
    $gw=trim(shell_exec('systemctl is-active dstargateway 2>/dev/null'));
    $mmd=trim(shell_exec('systemctl is-active mmdvmdstar 2>/dev/null'));
    $stopped=file_exists('/var/lib/dstar-stopped');
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['gateway'=>$gw,'mmdvm'=>$mmd,'stopped'=>$stopped], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'dstar-start') { shell_exec('sudo /usr/local/bin/dstar-start.sh 2>/dev/null &'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'dstar-stop')  { shell_exec('sudo /usr/local/bin/dstar-stop.sh 2>/dev/null &'); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'dstar-logs')  {
    $lines=intval($_GET['lines']??15);
    $gw  = shell_exec("sudo journalctl -u dstargateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmdstar  -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['gateway'=>htmlspecialchars($gw??'', ENT_QUOTES, 'UTF-8'),'mmdvm'=>htmlspecialchars($mmd??'', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE); exit;
}

if ($action === 'ysf-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmysf -n 300 --no-pager --output=short 2>/dev/null");
    if(empty(trim($log)))$log=shell_exec("sudo journalctl -u ysfgateway -n 300 --no-pager --output=short 2>/dev/null");
    $lines=array_reverse(explode("\n",$log));
    $active=false;$callsign='';$name='';$dest='';$source='';
    foreach ($lines as $line) {
        if(preg_match('/YSF.*(end of|lost RF|lost net|watchdog|timeout|no reply|voice end|fin)/i',$line)){$active=false;break;}
        if(preg_match('/YSF.*voice (end|fin|stop)/i',$line)){$active=false;break;}
        if(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i',$line,$m)){$active=true;$source=strtoupper($m[2]);$callsign=strtoupper(trim($m[3]));break;}
        if(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i',$line,$m)){$active=true;$source='RF';$callsign=strtoupper(trim($m[2]));$dest=trim($m[3]);break;}
    }
    if($callsign){$info=lookupCall($callsign);$name=$info['name'];}
    $lastHeard=[];$seen=[];
    foreach ($lines as $line) {
        $cs='';$src='';$time='';$dst='';
        if(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i',$line,$m)){$time=$m[1];$src=strtoupper($m[2]);$cs=strtoupper(trim($m[3]));}
        elseif(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i',$line,$m)){$time=$m[1];$src='RF';$cs=strtoupper(trim($m[2]));$dst=trim($m[3]);}
        if($cs&&!in_array($cs,$seen)){$inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'dest'=>$dst,'source'=>$src,'time'=>$time];$seen[]=$cs;if(count($lastHeard)>=5)break;}
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dest'=>$dest,'source'=>$source,'lastHeard'=>$lastHeard], JSON_UNESCAPED_UNICODE); exit;
}

if ($action === 'nxdn-status') {
    $gw  = trim(shell_exec('systemctl is-active nxdngateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmnxdn 2>/dev/null'));
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['gateway'=>$gw,'mmdvm'=>$mmd], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'nxdn-start') {
  saveState('nxdn','on');
    shell_exec('sudo systemctl start mmdvmnxdn 2>/dev/null'); sleep(2);
    shell_exec('sudo systemctl start nxdngateway 2>/dev/null');
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'nxdn-stop') {
  saveState('nxdn','off');
    shell_exec('sudo systemctl stop nxdngateway 2>/dev/null'); sleep(1);
    shell_exec('sudo systemctl stop mmdvmnxdn 2>/dev/null');
    header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'nxdn-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $gw  = shell_exec("sudo journalctl -u nxdngateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmnxdn  -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['gateway'=>htmlspecialchars($gw??'', ENT_QUOTES, 'UTF-8'),'mmdvm'=>htmlspecialchars($mmd??'', ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'nxdn-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmnxdn -n 300 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log ?? ''));
    $active = false; $callsign = ''; $source = ''; $name = ''; $tg = '';
    foreach ($lines as $line) {
        if (preg_match('/NXDN.*(end of|lost RF|watchdog|finished|timeout)/i', $line)) { $active = false; break; }
        if (preg_match('/NXDN.*received (RF|network).*from\s+([A-Z0-9]+).*to\s+(\d+)/i', $line, $m)) {
            $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); $tg = $m[3]; break;
        }
        if (preg_match('/NXDN.*received (RF|network).*from\s+([A-Z0-9]+)/i', $line, $m)) {
            $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); break;
        }
    }
    if ($callsign) { $info = lookupCall($callsign); $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs = ''; $src = ''; $time = ''; $tgr = '';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*received (RF|network).*from\s+([A-Z0-9]+).*to\s+(\d+)/i', $line, $m)) {
            $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); $tgr=$m[4];
        } elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*received (RF|network).*from\s+([A-Z0-9]+)/i', $line, $m)) {
            $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3]));
        }
        if ($cs && !in_array($cs, $seen)) {
            $inf = lookupCall($cs);
            $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$tgr,'source'=>$src,'time'=>$time];
            $seen[] = $cs;
            if (count($lastHeard) >= 5) break;
        }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'tg'=>$tg,'source'=>$source,'lastHeard'=>$lastHeard], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel PHPPLUS - EA1HG</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg: #0a0e14; --surface: #111720; --border: #1e2d3d; --green: #00ff9f; --green-dim: #00cc7a; --red: #ff4560; --amber: #ffb300; --cyan: #00d4ff; --violet: #b57aff; --text: #a8b9cc; --text-dim: #4a5568; --font-mono: 'Share Tech Mono', monospace; --font-ui: 'Rajdhani', sans-serif; --font-orb: 'Orbitron', monospace; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); font-size: 1rem; min-height: 100vh; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }

/* ✅ BANDERAS ESTILO LOLLIPOP */
@-webkit-keyframes flagFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-2px); } }
@keyframes flagFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-2px); } }
.flag-wrap {
    display: inline-block; width: 38px; height: 24px; vertical-align: middle; margin-right: 6px;
    -webkit-animation: flagFloat 3s ease-in-out infinite; animation: flagFloat 3s ease-in-out infinite;
}
.flag-lollipop {
    width: 100%; height: 100%; border-radius: 3px; overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3), 0 1px 2px rgba(0,0,0,0.2);
    position: relative; background: #2a2d34;
    transition: transform 0.2s cubic-bezier(0.4,0,0.2,1), box-shadow 0.2s cubic-bezier(0.4,0,0.2,1);
}
.flag-lollipop:hover {
    transform: scale(1.15) translateY(-2px);
    box-shadow: 0 6px 10px rgba(0,0,0,0.4), 0 2px 4px rgba(0,0,0,0.3);
}
.flag-lollipop svg { width: 100%; height: 100%; display: block; }

/* ✅ INFOBAR COMPACTO */
.nx-infobar { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0 0.5rem; width: 100%; flex-wrap: nowrap; }
.nx-info-item { display: flex; flex-direction: column; align-items: center; gap: 1px; flex: 1; min-width: 0; }
.nx-info-lbl { font-family: var(--font-mono); font-size: 0.45rem; color: var(--text-dim); letter-spacing: 0.1em; text-transform: uppercase; white-space: nowrap; }
.nx-info-val { font-family: var(--font-mono); font-size: 0.6rem; font-weight: bold; text-align: center; line-height: 1.1; display: block; width: 100%; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
@media (max-width: 600px) {
  .nx-infobar { flex-wrap: wrap; justify-content: space-around; gap: 0.3rem; }
  .nx-info-item { flex: 0 0 20%; min-width: 60px; }
  .nx-info-val { font-size: 0.55rem; }
  .flag-wrap { width: 30px; height: 19px; margin-right: 4px; }
}

/* ✅ TIEMPO TX */
.nx-tx-timer { display: none; font-family: var(--font-mono); font-size: 0.85rem; margin-top: 4px; letter-spacing: 0.1em; font-weight: 700; }
.dmr-tx-timer { color: var(--green); text-shadow: 0 0 10px rgba(0,255,159,.4); }
.ysf-tx-timer { color: var(--violet); text-shadow: 0 0 10px rgba(181,122,255,.4); }
.dstar-tx-timer { color: #00e5ff; text-shadow: 0 0 10px rgba(0,229,255,.4); }
.nxdn-tx-timer { color: #ffd700; text-shadow: 0 0 10px rgba(255,215,0,.4); }

.station-meta-item, .toggle-row, .service-card-btns, .ctrl-header-top, .lh-row, .nx-topbar, .nx-botbar { min-width: 0; }
.display-row > div, .log-grid > div, .controls-section > div { min-width: 0; flex: 1 1 auto; }
.toggle-label { min-width: 0; overflow: hidden; text-overflow: ellipsis; flex: 1; }

.lh-body::-webkit-scrollbar, .log-output::-webkit-scrollbar, .fedit-area::-webkit-scrollbar, .update-console::-webkit-scrollbar, .xterm-out::-webkit-scrollbar { width: 6px; height: 6px; }
.lh-body::-webkit-scrollbar-thumb, .log-output::-webkit-scrollbar-thumb, .fedit-area::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
.lh-body, .log-output, .fedit-area, .update-console, .xterm-out { scrollbar-width: thin; scrollbar-color: var(--border) transparent; }

@-webkit-keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
@-webkit-keyframes scan { from{background-position:200% 0} to{background-position:-200% 0} }
@keyframes scan { from{background-position:200% 0} to{background-position:-200% 0} }

.ctrl-header { border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; flex-direction: column; align-items: center; gap: .6rem; background: var(--surface); }
.ctrl-header-top { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; justify-content: center; }
.ctrl-header-top h1 { font-family: var(--font-ui); font-weight: 700; font-size: 1.5rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
.ctrl-header-btns { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; justify-content: center; }
.btn-header { font-family: var(--font-mono); font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; background: transparent; border-radius: 4px; padding: .28rem .75rem; cursor: pointer; transition: background .2s; text-decoration: none; display: inline-block; }
.btn-header.cyan { color: var(--cyan); border: 1px solid var(--cyan); }
.btn-header.cyan:hover { background: rgba(0,212,255,.1); }
.btn-header.amber { color: var(--amber); border: 1px solid var(--amber); }
.btn-header.amber:hover { background: rgba(255,179,0,.1); }
.btn-header.red { color: var(--red); border: 1px solid var(--red); }
.btn-header.red:hover { background: rgba(255,69,96,.15); }
button.btn-header { font-family: var(--font-mono); }
.ctrl-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.station-card { background: linear-gradient(135deg,#111720 60%,#0d1e2a 100%); border: 1px solid var(--border); border-radius: 10px; padding: 1.2rem 2rem; display: flex; align-items: center; gap: 2.5rem; margin-bottom: 1.8rem; flex-wrap: wrap; position: relative; overflow: hidden; }
.station-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,transparent,var(--cyan),var(--violet),transparent); }
.station-card-main { display: flex; flex-direction: column; align-items: flex-start; gap: .3rem; }
.station-callsign { font-family: var(--font-orb); font-size: 2.4rem; font-weight: 900; color: var(--cyan); letter-spacing: .08em; line-height: 1; text-shadow: 0 0 20px rgba(0,212,255,.4); }
.station-divider { width: 1px; height: 70px; background: var(--border); flex-shrink: 0; }
.station-meta-item { display: flex; flex-direction: column; gap: .15rem; }
.station-meta-label { font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .15em; text-transform: uppercase; }
.station-meta-value { font-family: var(--font-mono); font-size: .95rem; color: var(--amber); letter-spacing: .06em; font-weight: bold; }
@media (max-width:700px) { .station-card { gap: 1.2rem; padding: 1rem; } .station-divider { display: none; } }
.status-bar { display: flex; gap: 2rem; margin-bottom: 1.8rem; flex-wrap: wrap; align-items: center; }
.status-item { display: flex; align-items: center; gap: .5rem; font-family: var(--font-mono); font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); transition: background .4s, box-shadow .4s; }
.dot.active { background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 2s infinite; }
.dot.error { background: var(--red); box-shadow: 0 0 8px var(--red); }
.section-divider { width: 1px; height: 20px; background: var(--border); margin: 0 .5rem; }
.controls-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 2rem; }
@media (max-width:800px) { .controls-section { grid-template-columns: 1fr; } }
.service-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.2rem 1.6rem; }
.service-card-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 1rem; }
.service-card-label.dmr { color: var(--white); }
.service-card-label.ysf { color: var(--violet); }
.toggle-row { display: flex; align-items: center; gap: 1rem; padding: .5rem 0; }
.toggle-label { font-family: var(--font-mono); font-size: .85rem; letter-spacing: .06em; color: var(--text-dim); text-transform: uppercase; flex: 1; transition: color .3s; }
.toggle-label.on-dmr { color: var(--white); }
.toggle-label.on-ysf { color: var(--violet); }
.toggle-status { font-family: var(--font-mono); font-size: .72rem; letter-spacing: .1em; color: var(--text-dim); min-width: 3rem; text-align: right; transition: color .3s; }
.toggle-status.on { color: var(--green); }
.sw { position: relative; width: 56px; height: 28px; flex-shrink: 0; cursor: pointer; }
.sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid #666; transition: background .3s, border-color .3s; }
.sw-knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #e95c04; border-radius: 2px; box-shadow: 0 1px 4px rgba(0,0,0,.5); transition: transform .3s cubic-bezier(.4,0,.2,1); }
.sw input:checked ~ .sw-knob { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(0,255,159,.6); }
.auto-badge { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); display: flex; align-items: center; gap: .4rem; margin-top: .4rem; }
.auto-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
.auto-badge.ysf .dot-sm { background: var(--violet); }
.service-card-btns { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1rem; }
.ini-btn { font-family: var(--font-mono); font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; padding: .3rem .7rem; border-radius: 3px; border: 1px solid var(--border); background: transparent; cursor: pointer; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: .3rem; flex: 1; justify-content: center; }
.ini-btn.edit { color: var(--cyan); border-color: rgba(0,212,255,.3); }
.ini-btn.edit:hover { border-color: var(--cyan); background: rgba(0,212,255,.08); }
.ini-btn.edit.ysf { color: var(--violet); border-color: rgba(181,122,255,.3); }
.ini-btn.edit.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.08); }
.ini-btn.view { color: #fff; border-color: rgba(255,179,0,.3); }
.ini-btn.view:hover { border-color: var(--amber); background: rgba(255,179,0,.08); }
.display-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin: 2rem 0; align-items: start; }
@media (max-width:900px) { .display-row { grid-template-columns: 1fr; } }
.panel-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; color: var(--amber); text-transform: uppercase; margin-bottom: .5rem; }
.panel-label.ysf-label { color: var(--violet); }
.nextion { background: #060c10; border: 2px solid #1a3a4a; border-radius: 6px; box-shadow: 0 0 0 1px #0d2030, inset 0 0 40px rgba(0,212,255,.04), 0 0 30px rgba(0,212,255,.08); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-ysf { background: #08060e; border: 2px solid #2d1a4a; border-radius: 6px; box-shadow: 0 0 0 1px #1a0d30, inset 0 0 40px rgba(181,122,255,.04), 0 0 30px rgba(181,122,255,.1); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-dstar { background: #06100e; border: 2px solid #004a4a; border-radius: 6px; box-shadow: 0 0 0 1px #002030, inset 0 0 40px rgba(0,229,255,.04), 0 0 30px rgba(0,229,255,.12); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-nxdn { background: #0e0e06; border: 2px solid #4a4a00; border-radius: 6px; box-shadow: 0 0 0 1px #303000, inset 0 0 40px rgba(255,215,0,.04), 0 0 30px rgba(255,215,0,.12); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }

.nx-topbar { position: absolute; top: 0; left: 0; right: 0; height: 30px; background: #1c1c24; border-bottom: 1px solid #1a3a4a; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .1em; }
.nx-topbar.ysf-bar { background: #1a1424; border-bottom: 1px solid #2d1a4a; color: #4a2a7a; }
.nx-topbar.dstar-bar { background: #0a1a1a; border-bottom: 1px solid #004a4a; color: #006070; }
.nx-topbar.nxdn-bar { background: #1a1a0a; border-bottom: 1px solid #4a4a00; color: #707000; }
.nx-topbar .nx-mode { color: var(--cyan); opacity: .7; }
.nx-topbar.ysf-bar .nx-mode { color: var(--violet); opacity: .8; }
.nx-topbar.dstar-bar .nx-mode { color: #00e5ff; opacity: .8; }
.nx-topbar.nxdn-bar .nx-mode { color: #ffd700; opacity: .8; }
.nx-topbar .nx-tg { color: var(--amber); opacity: .85; min-width: 5rem; text-align: right; }

.nx-infobar { position: absolute; top: 30px; left: 0; right: 0; height: 26px; background: rgba(0,0,0,.35); border-bottom: 1px solid #0d2030; display: flex; align-items: center; justify-content: space-around; padding: 0 3rem; gap: 1rem; z-index: 2; }
.nx-infobar-ysf { background: rgba(0,0,0,.4); border-bottom: 1px solid #1a0d30; }
.nx-infobar-dstar { background: rgba(0,0,0,.4); border-bottom: 1px solid #003040; }
.nx-infobar-nxdn { background: rgba(0,0,0,.4); border-bottom: 1px solid #303000; }

.nx-vu { position: absolute; left: 1rem; top: 56px; bottom: 32px; width: 6px; display: flex; flex-direction: column-reverse; gap: 2px; }
.nx-vu.right { left: auto; right: 1rem; }
.nx-vu-bar { height: 5px; border-radius: 1px; background: #0d2030; transition: background .08s; }
.nx-vu-bar.lit-g { background: var(--green); box-shadow: 0 0 4px var(--green); }
.nx-vu-bar.lit-a { background: var(--amber); box-shadow: 0 0 4px var(--amber); }
.nx-vu-bar.lit-r { background: var(--red); box-shadow: 0 0 4px var(--red); }
.nx-vu-bar.lit-v { background: var(--violet); box-shadow: 0 0 4px var(--violet); }
.nx-vu-bar.lit-vd { background: #d4a8ff; box-shadow: 0 0 4px #d4a8ff; }
.nx-vu-bar.lit-y { background: #ffd700; box-shadow: 0 0 4px #ffd700; }
.nx-vu-bar.lit-ya { background: #ffc400; box-shadow: 0 0 4px #ffc400; }

.nx-txbar { position: absolute; bottom: 28px; left: 0; right: 0; height: 3px; }
.nx-txbar.active { background: linear-gradient(90deg,transparent,var(--green),transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.active-ysf { background: linear-gradient(90deg,transparent,var(--violet),transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.active-nxdn { background: linear-gradient(90deg,transparent,#ffd700,transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.dstar-tx { background: linear-gradient(90deg,transparent,#00e5ff,transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }

.nx-center { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; z-index: 1; }
.nx-clock { font-family: var(--font-orb); font-size: 4rem; font-weight: 700; color: #fff; letter-spacing: .06em; line-height: 1; }
.nx-date { font-family: var(--font-mono); font-size: .7rem; color: #ff0; letter-spacing: .12em; text-transform: uppercase; margin-top: .2rem; }
.nx-callsign { font-family: var(--font-orb); font-size: 3.4rem; font-weight: 900; letter-spacing: .04em; line-height: 1; color: var(--green); text-shadow: 0 0 20px rgba(0,255,159,.55); display: flex; align-items: center; justify-content: center; gap: 6px; }
.nx-callsign.ysf { color: var(--violet); text-shadow: 0 0 20px rgba(181,122,255,.6); }
.nx-callsign.dstar { color: #00e5ff; text-shadow: 0 0 20px rgba(0,229,255,.6); }
.nx-callsign.nxdn { color: #ffd700; text-shadow: 0 0 20px rgba(255,215,0,.6); }
.nx-name { font-family: var(--font-ui); font-weight: 500; font-size: 1.2rem; color: var(--cyan); letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-top: .15rem; }
.nx-name.ysf { color: #d4a8ff; }
.nx-name.dstar { color: #80f0ff; }
.nx-name.nxdn { color: #ffc400; }

.nx-botbar { position: absolute; bottom: 0; left: 0; right: 0; height: 28px; background: #0d1e2a; border-top: 1px solid #1a3a4a; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .08em; }
.nx-botbar.ysf-bar { background: #110d1e; border-top: 1px solid #2d1a4a; color: #4a2a7a; }
.nx-botbar.dstar-bar { background: #06100e; border-top: 1px solid #004a4a; color: #006070; }
.nx-botbar.nxdn-bar { background: #0e0e06; border-top: 1px solid #4a4a00; color: #707000; }
.nx-botbar .nx-dmrid { color: #3a6a8a; min-width: 6rem; }
.nx-botbar .nx-source { padding: .1rem .45rem; border-radius: 2px; font-size: .6rem; letter-spacing: .1em; }
.nx-botbar .nx-source.rf { background: rgba(0,255,159,.15); color: var(--green); border: 1px solid rgba(0,255,159,.3); }
.nx-botbar .nx-source.net { background: rgba(0,212,255,.15); color: var(--cyan); border: 1px solid rgba(0,212,255,.3); }

/* ✅ PANELS LAST HEARD (CORREGIDOS Y FIJOS) */
.lh-panel { background: var(--surface); border: 3px solid #1a3a4a; border-radius: 6px; display: flex; flex-direction: column; width: 100%; }
.lh-header { background: #1c1c24; border-bottom: 1px solid var(--border); padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.6fr .7fr .7fr .5fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase; width: 100%; }
.lh-body { flex: 1; overflow-y: auto; width: 100%; }
.lh-row { display: grid; grid-template-columns: 1.2fr 1.6fr .7fr .7fr .5fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(30,45,61,.6); align-items: center; transition: background .2s; width: 100%; }
.lh-row:last-child { border-bottom: none; }
.lh-row:hover { background: rgba(0,212,255,.04); }
.lh-row.lh-active { background: rgba(0,255,159,.06); }
.lh-call-wrap { display: flex; align-items: center; gap: .35rem; }
.lh-tx-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call { font-family: var(--font-mono); font-size: .82rem; color: var(--green); letter-spacing: .05em; font-weight: bold; display: flex; align-items: center; gap: 4px; }
.lh-name { font-family: var(--font-ui); font-size: .82rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lh-tg { font-family: var(--font-mono); font-size: .72rem; color: var(--amber); }
.lh-time { font-family: var(--font-mono); font-size: .68rem; color: var(--text-dim); }
.lh-src { font-family: var(--font-mono); font-size: .6rem; }
.lh-src.rf { color: var(--green); }
.lh-src.net { color: var(--cyan); }
.lh-empty { padding: 1.5rem 1rem; font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); text-align: center; width: 100%; display: block; }

/* ✅ YSF PANEL (CORREGIDO ESPECÍFICAMENTE) */
.lh-panel-ysf { background: var(--surface); border: 3px solid #2d1a4a; border-radius: 6px; display: flex; flex-direction: column; width: 100%; }
.lh-header-ysf { background: #1a1424; border-bottom: 1px solid #2d1a4a; padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.8fr 1fr .6fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: #4a2a7a; letter-spacing: .1em; text-transform: uppercase; width: 100%; box-sizing: border-box; }
.lh-row-ysf { display: grid; grid-template-columns: 1.2fr 1.8fr 1fr .6fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(45,26,74,.5); align-items: center; transition: background .2s; width: 100%; box-sizing: border-box; }
.lh-row-ysf:last-child { border-bottom: none; }
.lh-row-ysf:hover { background: rgba(181,122,255,.04); }
.lh-row-ysf.lh-active { background: rgba(181,122,255,.08); }
.lh-tx-dot-ysf { width: 6px; height: 6px; border-radius: 50%; background: var(--violet); box-shadow: 0 0 6px var(--violet); animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call-ysf { font-family: var(--font-mono); font-size: .82rem; color: var(--violet); letter-spacing: .05em; font-weight: bold; }
.lh-row-ysf .lh-call-wrap { display: flex; align-items: center; gap: .35rem; }

/* ✅ NXDN PANEL */
.lh-panel-nxdn { background: var(--surface); border: 3px solid #4a4a00; border-radius: 6px; display: flex; flex-direction: column; width: 100%; }
.lh-header-nxdn { background: #1a1a0a; border-bottom: 1px solid #4a4a00; padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.8fr .8fr 1fr .6fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: #707000; letter-spacing: .1em; text-transform: uppercase; width: 100%; box-sizing: border-box; }
.lh-row-nxdn { display: grid; grid-template-columns: 1.2fr 1.8fr .8fr 1fr .6fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(74,74,0,.5); align-items: center; transition: background .2s; width: 100%; box-sizing: border-box; }
.lh-row-nxdn:last-child { border-bottom: none; }
.lh-row-nxdn:hover { background: rgba(255,215,0,.04); }
.lh-row-nxdn.lh-active { background: rgba(255,215,0,.08); }
.lh-tx-dot-nxdn { width: 6px; height: 6px; border-radius: 50%; background: #ffd700; box-shadow: 0 0 6px #ffd700; animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call-nxdn { font-family: var(--font-mono); font-size: .82rem; color: #ffd700; letter-spacing: .05em; font-weight: bold; }
.lh-row-nxdn .lh-call-wrap { display: flex; align-items: center; gap: .35rem; }

.log-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
@media (max-width:900px) { .log-grid { grid-template-columns: 1fr; } }
.log-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
.log-panel-header { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; border-bottom: 1px solid var(--border); background: rgba(0,0,0,.3); }
.log-panel-header .svc-name { font-family: var(--font-mono); font-size: .8rem; letter-spacing: .1em; color: var(--green); text-transform: uppercase; }
.log-panel-header .svc-name.gw { color: var(--amber); }
.log-panel-header .svc-name.ysf { color: var(--violet); }
.log-panel-header .btn-clear { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); background: none; border: none; cursor: pointer; transition: color .2s; }
.log-panel-header .btn-clear:hover { color: var(--text); }
.log-output { font-family: var(--font-mono); font-size: .72rem; line-height: 1.55; color: #7a9ab5; padding: .8rem 1rem; height: 190px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
.ln-info { color: #7a9ab5; }
.ln-warn { color: var(--amber); }
.ln-err { color: var(--red); }
.ln-ok { color: var(--green-dim,#00cc7a); }

.restore-modal,.install-modal,.update-modal,.xterm-modal,.fedit-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 9000; align-items: center; justify-content: center; }
.restore-modal.open,.install-modal.open,.update-modal.open,.xterm-modal.open,.fedit-modal.open { display: flex; }
.restore-box,.install-box,.update-box,.xterm-box,.fedit-box { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; max-width: 95vw; }
.restore-box { min-width: 380px; }
.install-box, .update-box { min-width: 480px; width: 680px; }
.xterm-box { width: 780px; }
.fedit-box { width: 900px; display: flex; flex-direction: column; gap: .8rem; }

.fedit-title, .xterm-title, .update-title, .install-title, .restore-title { font-family: var(--font-mono); font-size: .8rem; color: var(--cyan); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1rem; }
.install-title { color: var(--green); }
.restore-title { color: var(--amber); }
.update-title { color: var(--cyan); }

.fedit-area { font-family: var(--font-mono); font-size: .78rem; color: #c9d1d9; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 420px; resize: vertical; outline: none; line-height: 1.5; width: 100%; }
.fedit-area:focus { border-color: var(--cyan); }

.fedit-msg, .restore-msg, .install-msg { font-family: var(--font-mono); font-size: .75rem; display: none; padding: .5rem .8rem; border-radius: 4px; border: 1px solid; margin-top: .5rem; }
.fedit-msg.ok, .restore-msg.ok { color: var(--green); border-color: var(--green); background: rgba(0,255,159,.06); }
.fedit-msg.err, .restore-msg.err { color: var(--red); border-color: var(--red); background: rgba(255,69,96,.06); }
.fedit-msg.loading, .restore-msg.loading { color: var(--amber); border-color: var(--amber); background: rgba(255,179,0,.06); }

.xterm-out { font-family: var(--font-mono); font-size: .75rem; color: #7a9ab5; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 340px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: .6rem; }
.update-console { font-family: var(--font-mono); font-size: .75rem; color: #7a9ab5; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 280px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: 1rem; }
.install-output { font-family: var(--font-mono); font-size: .72rem; color: #7a9ab5; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 200px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: 1rem; display: none; }
.install-output.visible { display: block; }
.restore-btns { display: flex; gap: .8rem; margin-top: .8rem; }
.restore-btn-ok { flex: 1; background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .6rem; cursor: pointer; transition: background .2s; }
.restore-btn-ok:hover { background: #218838; }
.restore-btn-cancel { flex: 1; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .6rem; cursor: pointer; transition: all .2s; }
.restore-btn-cancel:hover { border-color: var(--text); color: var(--text); }
.restore-file { width: 100%; background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; color: var(--green); font-family: var(--font-mono); font-size: .8rem; padding: .5rem; margin-bottom: 1rem; }
.restore-label { font-family: var(--font-mono); font-size: .72rem; color: var(--text); display: block; margin-bottom: .5rem; }

.dropdown-wrap { position: relative; display: inline-block; }
.dropdown-menu-custom { display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background: var(--surface); border: 1px solid var(--border); border-radius: 6px; min-width: 270px; z-index: 1000; box-shadow: 0 8px 24px rgba(0,0,0,.5); overflow: hidden; padding-top: .4rem; }
.dropdown-wrap:hover .dropdown-menu-custom { display: block; }
.dropdown-item-custom { display: block; width: 100%; padding: .55rem 1rem; font-family: var(--font-mono); font-size: .75rem; letter-spacing: .07em; text-transform: uppercase; color: var(--text); background: none; border: none; cursor: pointer; text-align: left; transition: background .15s, color .15s; border-bottom: 1px solid var(--border); }
.dropdown-item-custom:last-child { border-bottom: none; }
.dropdown-item-custom:hover { background: rgba(0,212,255,.08); color: var(--cyan); }

#xtTtydModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 9800; align-items: center; justify-content: center; }
#xtTtydModal.open { display: flex; }
.xterm-row { display: flex; align-items: center; gap: .5rem; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .5rem .8rem; margin-bottom: 1rem; }
.xterm-pr { font-family: var(--font-mono); font-size: .78rem; color: #00ff9f; white-space: nowrap; }
.xterm-inp { flex: 1; background: transparent; border: none; outline: none; font-family: var(--font-mono); font-size: .78rem; color: #c9d1d9; caret-color: #00ff9f; }
</style>
</head>
<body>
<header class="ctrl-header">
<div class="ctrl-header-top">
<img src="Logo_ea1hg.png" alt="EA1HG" style="height:40px;width:auto;">
<h1>PANEL PHPPLUS - EA1HG</h1>
</div>
<div class="ctrl-header-btns">
<a href="edit_ini.php?file=displaydriver" class="btn-header cyan">📄 Display-Driver</a>
<a href="?action=backup-configs" class="btn-header amber">💾 Backup</a>
<button onclick="openRestore()" class="btn-header cyan">📂 Restaurar</button>
<div class="dropdown-wrap" id="dropActualizaciones">
  <button class="btn-header cyan">⬇ Actualizaciones ▾</button>
  <div class="dropdown-menu-custom">
    <button class="dropdown-item-custom" onclick="runUpdate('imagen')">🖼 Imagen</button>
    <button class="dropdown-item-custom" onclick="runUpdate('ids')">📋 IDs</button>
    <button class="dropdown-item-custom" onclick="runUpdate('ysf')">📡 YSF</button>
  </div>
</div>
<button class="btn-header cyan" onclick="xtTtydOpen()">⌨ Terminal</button>
<button id="btnReboot" class="btn-header red" onclick="rebootPi()">⏻ Reiniciar</button>
</div>
</header>
<main class="ctrl-body">
<div class="station-card">
    <div class="station-card-main"><div class="station-callsign" id="scCallsign">📡 —</div></div>
    <div class="station-divider" style="height:50px;"></div>
    <div class="station-meta-item"><span class="station-meta-label">🖥️ CPU</span><span class="station-meta-value" id="siCpu" style="color:var(--green);">—</span></div>
    <div class="station-meta-item"><span class="station-meta-label">🌡️ Temp</span><span class="station-meta-value" id="siTemp" style="color:var(--amber);">—</span></div>
    <div class="station-meta-item"><span class="station-meta-label">💾 RAM</span><span class="station-meta-value" id="siRam" style="color:var(--cyan);">—</span></div>
    <div class="station-meta-item"><span class="station-meta-label">💿 Disco</span><span class="station-meta-value" id="siDisk" style="color:var(--amber);">—</span></div>
</div>

<div class="status-bar">
<div class="status-item"><div class="dot" id="dot-mosquitto"></div><span>Mosquitto</span></div>
<div class="status-item"><div class="dot" id="dot-mmdvm"></div><span>MMDVMHost Dmr</span></div>
<div class="status-item"><div class="dot" id="dot-gateway"></div><span>DMRGateway</span></div>
<div class="status-item"><div class="dot" id="dot-mmdvmysf"></div><span style="color:#26c6da">MMDVMHost YSF</span></div>
<div class="status-item"><div class="dot" id="dot-ysf"></div><span style="color:var(--violet)">YSFGateway</span></div>
<div class="status-item"><div class="dot" id="dot-dstarmmd"></div><span style="color:#00e5ff">MMDVMhost DStar</span></div>
<div class="status-item"><div class="dot" id="dot-dstargw"></div><span style="color:#00e5ff">DStarGateway</span></div>
<div class="status-item"><div class="dot" id="dot-nxdnmmd"></div><span style="color:#ffd700">MMDVMHost NXDN</span></div>
<div class="status-item"><div class="dot" id="dot-nxdngw"></div><span style="color:#ffd700">NXDNGateway</span></div>
</div>

<div class="controls-section">
  <div class="service-card">
    <div class="service-card-label dmr">▸ DMR</div>
    <div class="toggle-row"><span class="toggle-label" id="dmrToggleLabel">DMR</span><label class="sw dmr" id="swDMR"><input type="checkbox" id="chkDMR" onchange="toggleServices(this)"><span class="sw-track"></span><span class="sw-knob"></span></label><span class="toggle-status" id="dmrToggleStatus">OFF</span></div>
    <div class="auto-badge" id="autoRefreshBadge" style="display:none"><div class="dot-sm"></div> DMR activo</div>
    <div class="service-card-btns"><a href="mmdvm_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:var(--cyan);border-color:rgba(0,212,255,.3);">⚙ MMDVMHOST Config</a><a href="dmrgateway_config.php" class="ini-btn edit" style="flex:1;justify-content:center;">⚙ DMRGateway Config</a></div>
    <div class="service-card-btns" style="margin-top:.4rem;"><a href="edit_ini.php?file=mmdvm" class="ini-btn view" style="flex:1;justify-content:center;">📄 EDITAR MMDVMHOST.ini</a><a href="edit_ini.php?file=dmrgateway" class="ini-btn view" style="flex:1;justify-content:center;color:var(--white);border-color:rgba(255,179,0,.3);">📄 EDITAR DMRGateway.ini</a></div>
  </div>
  <div class="service-card">
    <div class="service-card-label ysf">▸ C4FM</div>
    <div class="toggle-row"><span class="toggle-label" id="ysfToggleLabel">C4FM</span><label class="sw ysf" id="swYSF"><input type="checkbox" id="chkYSF" onchange="toggleYSF(this)"><span class="sw-track"></span><span class="sw-knob"></span></label><span class="toggle-status" id="ysfToggleStatus">OFF</span></div>
    <div class="auto-badge ysf" id="ysfRefreshBadge" style="display:none"><div class="dot-sm"></div> C4FM activo</div>
    <div class="service-card-btns" style="margin-top:.4rem;"><a href="mmdvmysf_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(38,198,218,.3);">⚙ MMDVMYSF CONFIG</a><a href="ysfgateway_config.php" class="ini-btn edit ysf" style="flex:1;justify-content:center;">⚙ YSFGATEWAY CONFIG</a></div>
    <div class="service-card-btns"><a href="edit_ini.php?file=mmdvmysf" class="ini-btn view" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(38,198,218,.2);">📄 editar MMDVMYSF.ini</a><a href="edit_ini.php?file=ysfgateway" class="ini-btn view ysf" style="flex:1;justify-content:center;">📄 editar YSFGateway.ini</a></div>
  </div>
  <div class="service-card" style="border-color:rgba(0,229,255,.25);">
    <div class="service-card-label" style="color:#00ff9f;">▸ DSTAR</div>
    <div class="toggle-row"><span class="toggle-label" id="dstarToggleLabel">DSTAR</span><label class="sw dstar" id="swDSTAR"><input type="checkbox" id="chkDSTAR" onchange="toggleDStar(this)"><span class="sw-track"></span><span class="sw-knob"></span></label><span class="toggle-status" id="dstarToggleStatus">OFF</span></div>
    <div class="auto-badge" id="dstarRefreshBadge" style="display:none;color:#00e5ff;"><div class="dot-sm" style="background:#00e5ff;"></div> DSTAR activo</div>
    <div class="service-card-btns" style="margin-top:.6rem;"><a href="mmdvmdstar_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(0,229,255,.3);">⚙ MMDVMDSTAR CONFIG</a><a href="dstargateway_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00ff9f;border-color:rgba(0,255,159,.3);">⚙ DSTARGATEWAY CONFIG</a></div>
    <div class="service-card-btns" style="margin-top:.4rem;"><a href="edit_ini.php?file=mmdvmdstar" class="ini-btn view" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(0,229,255,.3);">📄 editar MMDVMDSTAR.ini</a><a href="edit_ini.php?file=dstargateway" class="ini-btn view" style="flex:1;justify-content:center;color:#00ff9f;border-color:rgba(0,255,159,.3);">📄 editar DStarGateway.ini</a></div>
  </div>
  <div class="service-card" style="border-color:rgba(255,215,0,.25);">
    <div class="service-card-label" style="color:#ffd700;">▸ NXDN</div>
    <div class="toggle-row"><span class="toggle-label" id="nxdnToggleLabel">NXDN</span><label class="sw" id="swNXDN"><input type="checkbox" id="chkNXDN" onchange="toggleNXDN(this)"><span class="sw-track"></span><span class="sw-knob"></span></label><span class="toggle-status" id="nxdnToggleStatus">OFF</span></div>
    <div class="auto-badge" id="nxdnRefreshBadge" style="display:none;color:#ffd700;"><div class="dot-sm" style="background:#ffd700;"></div> NXDN activo</div>
    <div class="service-card-btns" style="margin-top:.6rem;"><a href="mmdvmnxdn_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(255,215,0,.3);">⚙ MMDVMNXDN CONFIG</a><a href="nxdngateway_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#ffc400;border-color:rgba(255,196,0,.3);">⚙ NXDNGATEWAY CONFIG</a></div>
    <div class="service-card-btns" style="margin-top:.4rem;"><a href="edit_ini.php?file=mmdvmnxdn" class="ini-btn view" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(255,215,0,.3);">📄 editar MMDVMNXDN.ini</a><a href="edit_ini.php?file=nxdngateway" class="ini-btn view" style="flex:1;justify-content:center;color:#ffc400;border-color:rgba(255,196,0,.3);">📄 editar NXDNGateway.ini</a></div>
  </div>
</div>

<div class="display-row">
  <div id="dmrDisplayPanel">
    <div class="panel-label">▸ DMR Display</div>
    <div class="nextion">
      <div class="nx-topbar"><span class="nx-mode">DMR</span><span id="nxStationLabel">EA1HG</span><span class="nx-tg" id="nxTG">—</span></div>
      <div class="nx-infobar"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="nxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val cyan" id="nxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val amber" id="nxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val green" id="nxIp">—</span></span></div>
      <div class="nx-vu" id="vuLeft"></div><div class="nx-vu right" id="vuRight"></div>
      <div class="nx-center" id="nxCenter"><div class="nx-clock" id="nxClock">00:00:00</div><div class="nx-date" id="nxDate">—</div><span id="dmrTxTimer" class="nx-tx-timer dmr-tx-timer">00:00</span></div>
      <div class="nx-txbar" id="nxTxBar"></div>
      <div class="nx-botbar"><span class="nx-dmrid" id="nxDmrid">—</span><span>SLOT <span id="nxSlot">—</span></span><span class="nx-source" id="nxSource"></span></div>
    </div>
  </div>
  <div id="ysfDisplayPanel">
    <div class="panel-label ysf-label">▸ C4FM Display</div>
    <div class="nextion-ysf">
      <div class="nx-topbar ysf-bar"><span class="nx-mode">C4FM</span><span style="color:#6a3a9a" id="ysfStationLabel">EA1HG</span><span class="nx-dest" id="ysfDest">—</span></div>
      <div class="nx-infobar nx-infobar-ysf"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="ysfNxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val" style="color:#d4a8ff" id="ysfNxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val" style="color:#c084ff" id="ysfNxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val" style="color:#9b6dff" id="ysfNxIp">—</span></span></div>
      <div class="nx-vu" id="ysfVuLeft"></div><div class="nx-vu right" id="ysfVuRight"></div>
      <div class="nx-center" id="ysfNxCenter"><div class="nx-clock" id="ysfNxClock" style="color:#c084ff;">00:00:00</div><div class="nx-date" id="ysfNxDate" style="color:#9b59d4;">—</div><span id="ysfTxTimer" class="nx-tx-timer ysf-tx-timer">00:00</span></div>
      <div class="nx-txbar" id="ysfTxBar"></div>
      <div class="nx-botbar ysf-bar"><span>YSF · C4FM</span><span class="nx-source" id="ysfSource"></span></div>
    </div>
  </div>
</div>

<div class="display-row" style="margin-top:1.2rem;">
  <div id="dstarDisplayPanel" style="display:none;">
    <div class="panel-label" style="color:#00e5ff;">▸ DSTAR Display</div>
    <div class="nextion-dstar">
      <div class="nx-topbar dstar-bar"><span class="nx-mode">DSTAR</span><span style="color:#006070" id="dstarStationLabel">EA1HG</span><span style="color:#00b0c0;opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="dstarDest">CQCQCQ</span></div>
      <div class="nx-infobar nx-infobar-dstar"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="dstarNxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val" style="color:#00e5ff" id="dstarNxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val" style="color:#00b0c0" id="dstarNxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val" style="color:#80f0ff" id="dstarNxIp">—</span></span></div>
      <div class="nx-vu" id="dstarVuLeft"></div><div class="nx-vu right" id="dstarVuRight"></div>
      <div class="nx-center" id="dstarNxCenter"><div class="nx-clock" id="dstarNxClock" style="color:#00e5ff;">00:00:00</div><div class="nx-date" id="dstarNxDate" style="color:#009090;">—</div><span id="dstarTxTimer" class="nx-tx-timer dstar-tx-timer">00:00</span></div>
      <div class="nx-txbar" id="dstarTxBar"></div>
      <div class="nx-botbar dstar-bar"><span>D-STAR</span><span class="nx-source" id="dstarSource"></span></div>
    </div>
  </div>
  <div id="nxdnDisplayPanel" style="display:none;">
    <div class="panel-label" style="color:#ffd700;">▸ NXDN Display</div>
    <div class="nextion-nxdn">
      <div class="nx-topbar nxdn-bar"><span class="nx-mode">NXDN</span><span style="color:#707000" id="nxdnStationLabel">EA1HG</span><span style="color:#ffd700;opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="nxdnTGLabel">—</span></div>
      <div class="nx-infobar nx-infobar-nxdn"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="nxdnNxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val" style="color:#ffd700" id="nxdnNxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val" style="color:#ffc400" id="nxdnNxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val" style="color:#ffe066" id="nxdnNxIp">—</span></span></div>
      <div class="nx-vu" id="nxdnVuLeft"></div><div class="nx-vu right" id="nxdnVuRight"></div>
      <div class="nx-center" id="nxdnNxCenter"><div class="nx-clock" id="nxdnNxClock" style="color:#ffd700;">00:00:00</div><div class="nx-date" id="nxdnNxDate" style="color:#b8a000;">—</div><span id="nxdnTxTimer" class="nx-tx-timer nxdn-tx-timer">00:00</span></div>
      <div class="nx-txbar" id="nxdnTxBar"></div>
      <div class="nx-botbar nxdn-bar"><span>NXDN</span><span class="nx-source" id="nxdnSource"></span></div>
    </div>
  </div>
</div>

<div class="display-row" style="margin-top:1rem;">
  <div id="dmrLastHeardPanel">
    <div class="panel-label">▸ Últimos DMR</div>
    <div class="lh-panel">
      <div class="lh-header"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="lhBody"><div class="lh-empty">Sin actividad</div></div>
    </div>
  </div>
  <div id="ysfLastHeardPanel">
    <div class="panel-label ysf-label">▸ Últimos C4FM</div>
    <div class="lh-panel-ysf">
      <div class="lh-header-ysf"><span>Indicativo</span><span>Nombre</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="ysfLhBody"><div class="lh-empty">Sin actividad</div></div>
    </div>
  </div>
</div>

<div class="display-row" style="margin-top:1rem;">
  <div id="dstarLastHeardPanel" style="display:none;">
    <div class="panel-label" style="color:#00e5ff;">▸ Últimos DSTAR</div>
    <div class="lh-panel" style="border-color:#004a4a;">
      <div class="lh-header" style="background:#0a1a1a;border-bottom-color:#004a4a;color:#006070;"><span>Indicativo</span><span>Nombre</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="dstarLhBody"><div class="lh-empty">Sin actividad</div></div>
    </div>
  </div>
  <div id="nxdnLastHeardPanel" style="display:none;">
    <div class="panel-label" style="color:#ffd700;">▸ Últimos NXDN</div>
    <div class="lh-panel-nxdn">
      <div class="lh-header-nxdn"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="nxdnLhBody"><div class="lh-empty">Sin actividad</div></div>
    </div>
  </div>
</div>

<div class="log-grid" style="margin-top:2rem;">
<div id="dmrLogPanels" style="display:contents;">
<div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ MMDVMHost</span><button class="btn-clear" onclick="clearLog('logMmd')">limpiar</button></div><div class="log-output" id="logMmd">Esperando…</div></div>
<div class="log-panel"><div class="log-panel-header"><span class="svc-name gw">▸ DMRGateway</span><button class="btn-clear" onclick="clearLog('logGw')">limpiar</button></div><div class="log-output" id="logGw">Esperando…</div></div>
</div>
<div id="ysfLogPanels" style="display:contents;">
<div class="log-panel"><div class="log-panel-header"><span class="svc-name" style="color:#26c6da">▸ MMDVMHost YSF</span><button class="btn-clear" onclick="clearLog('logMmdvmYsf')">limpiar</button></div><div class="log-output" id="logMmdvmYsf">Esperando…</div></div>
<div class="log-panel"><div class="log-panel-header"><span class="svc-name ysf">▸ YSFGateway</span><button class="btn-clear" onclick="clearLog('logYsf')">limpiar</button></div><div class="log-output" id="logYsf">Esperando…</div></div>
</div>
<div id="dstarPanelMmd" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#80f0ff;">▸ MMDVMHost DStar</span><button class="btn-clear" onclick="clearLog('logDstarMmd')">limpiar</button></div><div class="log-output" id="logDstarMmd">Esperando…</div></div>
<div id="dstarPanelGw" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#00e5ff;">▸ DStarGateway</span><button class="btn-clear" onclick="clearLog('logDstarGw')">limpiar</button></div><div class="log-output" id="logDstarGw">Esperando…</div></div>
<div id="nxdnPanelMmd" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#ffd700;">▸ MMDVMHost NXDN</span><button class="btn-clear" onclick="clearLog('logNxdnMmd')">limpiar</button></div><div class="log-output" id="logNxdnMmd">Esperando…</div></div>
<div id="nxdnPanelGw" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#ffc400;">▸ NXDNGateway</span><button class="btn-clear" onclick="clearLog('logNxdnGw')">limpiar</button></div><div class="log-output" id="logNxdnGw">Esperando…</div></div>
</div>
</main>

<div id="feditModal" class="fedit-modal" onclick="if(event.target===this)feditClose()">
<div class="fedit-box">
  <div class="fedit-title">📝 Editor</div>
  <div class="fedit-path" id="feditPath">—</div>
  <textarea class="fedit-area" id="feditArea" spellcheck="false"></textarea>
  <div class="fedit-msg" id="feditMsg"></div>
  <div class="restore-btns">
    <button class="restore-btn-ok" onclick="feditSave()">💾 Guardar</button>
    <button class="restore-btn-cancel" onclick="feditClose()">✖ Cerrar</button>
  </div>
</div>
</div>

<div id="xtTtydModal">
  <div style="background:#0a0e14;border:1px solid #1e2d3d;border-radius:8px;width:960px;max-width:96vw;height:620px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.2rem;background:#111720;border-bottom:1px solid #1e2d3d;flex-shrink:0;">
      <span style="font-family:var(--font-mono);font-size:.8rem;color:var(--cyan);letter-spacing:.12em;text-transform:uppercase;">⌨ Terminal</span>
      <button onclick="xtTtydClose()" style="background:transparent;border:1px solid var(--red);color:var(--red);font-family:var(--font-mono);font-size:.7rem;border-radius:4px;padding:.25rem .8rem;cursor:pointer;">✖ Cerrar</button>
    </div>
    <iframe id="xtTtydFrame" src="" style="flex:1;border:none;width:100%;background:#000;" allow="clipboard-read; clipboard-write"></iframe>
  </div>
</div>

<div id="xtModal" class="xterm-modal" onclick="if(event.target===this)xtClose()">
<div class="xterm-box">
  <div class="xterm-title">⌨ Terminal</div>
  <div class="xterm-out" id="xtOut">pi@raspberry:~$ 
</div>
  <div class="xterm-row">
    <span class="xterm-pr" id="xtPr">pi@raspberry:~$</span>
    <input id="xtInp" class="xterm-inp" autocomplete="off" spellcheck="false" placeholder="comando…">
  </div>
  <div class="restore-btns">
    <button class="restore-btn-cancel" onclick="xtClose()">✖ Cerrar</button>
  </div>
</div>
</div>

<div id="updateModal" class="update-modal">
<div class="update-box">
<div class="update-title" id="updateTitle">⬇ Actualizando…</div>
<div class="update-console" id="updateConsole">Iniciando…</div>
<div class="restore-btns"><button class="restore-btn-cancel" id="updateCloseBtn" onclick="closeUpdate()">✖ Cerrar</button></div>
</div>
</div>

<div id="restoreModal" class="restore-modal">
<div class="restore-box">
<div class="restore-title">📂 Restaurar</div>
<label class="restore-label" for="restoreFile">Selecciona Copia_PHP2.zip</label>
<input type="file" id="restoreFile" accept=".zip" class="restore-file">
<div class="restore-btns">
<button class="restore-btn-ok" onclick="doRestore()">▶ Restaurar</button>
<button class="restore-btn-cancel" onclick="closeRestore()">✖ Cancelar</button>
</div>
<div id="restoreMsg" class="restore-msg"></div>
</div>
</div>

<div id="installModal" class="install-modal">
<div class="install-box">
<div class="install-title">⚙ Instalar Display Driver</div>
<div id="installOutput" class="install-output"></div>
<div class="restore-btns">
<button class="restore-btn-ok" id="btnInstalarOk" onclick="confirmarInstalacion()">▶ Instalar</button>
<button class="restore-btn-cancel" onclick="closeInstalar()">✖ Cancelar</button>
</div>
<div id="installMsg" class="restore-msg"></div>
</div>
</div>

<script>
// ✅ BANDERAS SVG - PREFIJOS RADIOAFICIONADOS ITU
const FLAGS = {
  ES: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 750 500"><rect width="750" height="500" fill="#c60b1e"/><rect y="125" width="750" height="250" fill="#ffc400"/></svg>',
  PT: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 660 400"><rect width="660" height="400" fill="#006600"/><rect x="330" width="330" height="400" fill="#ff0000"/><circle cx="330" cy="200" r="70" fill="#ffcd00"/><circle cx="330" cy="200" r="52" fill="#006600"/></svg>',
  FR: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/><rect x="600" width="300" height="600" fill="#ed2939"/></svg>',
  IT: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1500 1000"><rect width="500" height="1000" fill="#009246"/><rect x="500" width="500" height="1000" fill="#fff"/><rect x="1000" width="500" height="1000" fill="#ce2b37"/></svg>',
  GB: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30"><rect width="60" height="30" fill="#00247d"/><path d="M0 0L60 30M60 0L0 30" stroke="#fff" stroke-width="3.5"/><path d="M0 0L60 30M60 0L0 30" stroke="#cf142b" stroke-width="1.8"/><path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="5"/><path d="M30 0v30M0 15h60" stroke="#cf142b" stroke-width="2.5"/></svg>',
  DE: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 600"><rect width="1000" height="600"/><rect y="200" width="1000" height="200" fill="#dd0000"/><rect y="400" width="1000" height="200" fill="#ffce00"/></svg>',
  US: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1235 650"><rect width="1235" height="650" fill="#b22234"/><rect width="1235" height="50" fill="#fff" y="50"/><rect width="1235" height="50" fill="#fff" y="150"/><rect width="1235" height="50" fill="#fff" y="250"/><rect width="1235" height="50" fill="#fff" y="350"/><rect width="1235" height="50" fill="#fff" y="450"/><rect width="1235" height="50" fill="#fff" y="550"/><rect width="494" height="350" fill="#3c3b6e"/></svg>',
  CA: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 250"><rect width="500" height="250" fill="#fff"/><rect width="125" height="250" fill="#ff0000"/><rect x="375" width="125" height="250" fill="#ff0000"/></svg>',
  BR: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1100 700"><rect width="1100" height="700" fill="#009739"/><path d="M550 80L1020 350L550 620L80 350Z" fill="#fedd00"/><circle cx="550" cy="350" r="140" fill="#002776"/></svg>',
  AR: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#fff"/><rect width="900" height="200" fill="#75aadb"/><rect y="400" width="900" height="200" fill="#75aadb"/></svg>',
  JP: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#fff"/><circle cx="450" cy="300" r="160" fill="#bc002d"/></svg>',
  AU: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect width="1200" height="600" fill="#00008b"/><rect width="600" height="300" fill="#fff"/><path d="M0 0L600 300M600 0L0 300" stroke="#e4002b" stroke-width="4"/><path d="M300 0v300M0 150h600" stroke="#e4002b" stroke-width="6"/></svg>',
  ZA: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#007749"/><path d="M0 0L300 300L0 600Z" fill="#000"/><path d="M0 0L300 300L0 600Z" stroke="#ffb81c" stroke-width="25" fill="none"/><path d="M300 300H900" stroke="#fff" stroke-width="60"/><path d="M300 300H900" stroke="#000" stroke-width="30"/></svg>',
  FI: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1800 1100"><rect width="1800" height="1100" fill="#fff"/><rect y="400" width="1800" height="300" fill="#003580"/><rect x="500" width="300" height="1100" fill="#003580"/></svg>',
  NL: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ae1c28"/><rect y="200" width="900" height="200" fill="#fff"/><rect y="400" width="900" height="200" fill="#21468b"/></svg>',
  CH: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 480"><rect width="480" height="480" fill="#ff0000"/><rect x="200" y="120" width="80" height="240" fill="#fff"/><rect x="120" y="200" width="240" height="80" fill="#fff"/></svg>',
  AT: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ed2939"/><rect y="200" width="900" height="200" fill="#fff"/><rect y="400" width="900" height="200" fill="#ed2939"/></svg>',
  PL: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 800"><rect width="1280" height="800" fill="#dc143c"/><rect width="1280" height="400" fill="#fff"/></svg>',
  RU: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#fff"/><rect y="200" width="900" height="200" fill="#0039a6"/><rect y="400" width="900" height="200" fill="#d52b1e"/></svg>',
  GR: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 27 18"><rect width="27" height="18" fill="#0d5eaf"/><path d="M9 0v9h9M0 9h27M0 13.5h27M0 4.5h27" stroke="#fff" stroke-width="2"/></svg>',
  LT: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400"><rect width="600" height="400" fill="#fdb913"/><rect y="133" width="600" height="134" fill="#006a44"/><rect y="267" width="600" height="133" fill="#c1272d"/></svg>',
  HR: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect width="1200" height="600" fill="#ff0000"/><rect y="200" width="1200" height="200" fill="#fff"/><rect y="400" width="1200" height="200" fill="#171796"/></svg>'
};

// ✅ FUNCIÓN GENERADORA DE BANDERAS - PREFIJOS ITU RADIOAFICIONADOS
function getFlagByCall(callsign) {
    if (!callsign) return '<span class="flag-wrap"><div class="flag-lollipop">'+FLAGS.ES+'</div></span>';
    const cs = callsign.toUpperCase().trim();
    // Orden específico para evitar falsos positivos (de más específico a más general)
    const prefixes = [
        // España: EA1-9, EB1-9, EC1-9, ED1-9, EE1-9, EF1-9, EG1-9, EH1-9
        {re:/^E[ABCDEFGH][1-9]/, code:'ES'},
        // Portugal: CT1-9, CU1-9, CQ, CR, CS
        {re:/^C(TU|[TQ]|R|S)/, code:'PT'},
        // Francia: F, FM, FO, FH, FJ, FK, FL, FP, FR, FS, FT, FU, FV, FW, FX
        {re:/^F([A-Z]|M|O|H|J|K|L|P|R|S|T|U|V|W|X)/, code:'FR'},
        // Italia: I, IM, IN, IO, IP, IQ, IR, IS, IT, IU, IV, IW, IX, IY, IZ
        {re:/^I([M-P]|R-Z|[2-9]|M[NOPT])/, code:'IT'},
        {re:/^I$/, code:'IT'},
        // UK: G, M, 2E, 2M, 2I, 2D, 2W, 2S, 2C, GB, MJ, MU, GQ
        {re:/^(G|M|2[EIMDWS]|GB|MJ|MU|GQ)/, code:'GB'},
        // Alemania: DA-DR, DL, DM, DO, DP, DQ, Y2-Y9, DD-DR, DF-DL, DM-DQ
        {re:/^D([A-RT]|L|M|O|P|Q)|^Y[2-9]/, code:'DE'},
        // USA: K, W, N, AA-AL, WA-WZ, NA-NZ, KA-KZ
        {re:/^[KWN]|^A[ALM]|^N[A-Z]|^W[A-Z]|^K[A-Z]/, code:'US'},
        // Canadá: VA, VE, VO, VY
        {re:/^V(A|E|O|Y)/, code:'CA'},
        // Brasil: PY, PU, PV, PW, PX
        {re:/^P(Y|U|V|W|X)/, code:'BR'},
        // Argentina: LU, LV, LW, LX, L4-L9, LO-LR
        {re:/^L(U|V|W|X|[4-9]|O|P|Q|R)/, code:'AR'},
        // Japón: JA-JS, 7J-7N, 8J-8N
        {re:/^J([A-S])|^7([J-N])|^8([J-N])/, code:'JP'},
        // Australia: VK, AX, VH-VN, VX-VZ
        {re:/^V(K|X|H|I|J|K|L|M|N|X|Y|Z)/, code:'AU'},
        // Sudáfrica: ZS, ZT, ZU, ZV-ZZ, ZR
        {re:/^Z(S|T|U|[V-Z]|R)/, code:'ZA'},
        // Finlandia: OH, OF, OI, OJ
        {re:/^O(H|F|I|J)/, code:'FI'},
        // Países Bajos: PA-PI
        {re:/^P([A-I])/, code:'NL'},
        // Suiza: HB, HB9, HE
        {re:/^(HB9?|HE)/, code:'CH'},
        // Austria: OE
        {re:/^OE/, code:'AT'},
        // Polonia: SP, SO, SQ, SR, HF
        {re:/^S(P|O|Q|R)|^HF/, code:'PL'},
        // Rusia: UA-UI, RA-RC, UA1-0, R1-0
        {re:/^(U[A-I]|R[A-C])/, code:'RU'},
        // Grecia: SV, SY, SZ, J4, SX-SZ
        {re:/^S(V|Y|Z)|^J4|^S([X-Z])/, code:'GR'},
        // Lituania: LY
        {re:/^LY/, code:'LT'},
        // Croacia: 9A, YU
        {re:/^(9A|YU)/, code:'HR'}
    ];
    for(const p of prefixes){ if(p.re.test(cs)) return '<span class="flag-wrap"><div class="flag-lollipop">'+FLAGS[p.code]+'</div></span>'; }
    return '<span class="flag-wrap"><div class="flag-lollipop">'+FLAGS.ES+'</div></span>';
}

// ✅ TEMPORIZADORES & ESTADO (LÓGICA INMEDIATA)
const txTimers = { dmr: {}, ysf: {}, dstar: {}, nxdn: {} };

function startTxTimer(mode) {
  const el = document.getElementById(mode+'TxTimer'); 
  if(!el) return;
  const t = txTimers[mode];
  t.el = el;
  if (!t.start) {
    t.start = Date.now();
    clearInterval(t.interval);
    t.interval = setInterval(() => {
      const sec = Math.floor((Date.now()-t.start)/1000);
      const mm = String(Math.floor(sec/60)).padStart(2,'0');
      const ss = String(sec%60).padStart(2,'0');
      const curr = document.getElementById(mode+'TxTimer');
      if(curr) curr.textContent = mm+':'+ss;
    }, 1000);
  }
  t.el.style.display = 'inline-block';
}

function stopTxTimer(mode) {
  const t = txTimers[mode];
  clearInterval(t.interval);
  t.interval = null;
  t.start = null;
  if(t.el) {
      t.el.style.display = 'none';
      t.el.textContent = '00:00';
  }
}

let refreshTimer=null,txTimer=null,vuTimer=null,ysfTimer=null,mmdvmYsfTimer=null,ysfTxTimer=null,ysfVuTimer=null,dstarTimer=null;
let running=false,ysfRunning=false,mmdvmYsfRunning=false,dstarRunning=false,currentlyActive=false,ysfCurrentlyActive=false;
let dmrLastActiveTs=0,ysfLastActiveTs=0,nxdnCurrentlyActive=false,nxdnLastActiveTs=0,nxdnVuTimerAnim=null,nxdnRunning=false,nxdnTimer=null,nxdnTxTimer=null,dstarVuTimer=null,dstarCurrentlyActive=false,dstarTxTimer2=null;

async function fetchStationInfo(){try{const r=await fetch('?action=station-info');const d=await r.json();document.getElementById('scCallsign').textContent='📡 '+d.callsign;const nxPort=document.getElementById('nxPort');if(nxPort)nxPort.textContent=d.port||'—';const nxFrx=document.getElementById('nxFrx');if(nxFrx)nxFrx.textContent=d.freqRX||'—';const nxFtx=document.getElementById('nxFtx');if(nxFtx)nxFtx.textContent=d.freq||'—';const nxIp=document.getElementById('nxIp');if(nxIp)nxIp.textContent=d.ip||'—';const yNxPort=document.getElementById('ysfNxPort');if(yNxPort)yNxPort.textContent=d.ysfPort||'—';const yNxFrx=document.getElementById('ysfNxFrx');if(yNxFrx)yNxFrx.textContent=d.ysfFreqRX||'—';const yNxFtx=document.getElementById('ysfNxFtx');if(yNxFtx)yNxFtx.textContent=d.ysfFreqTX||'—';const yNxIp=document.getElementById('ysfNxIp');if(yNxIp)yNxIp.textContent=d.ysfIp||'—';const label=d.callsign;const nx=document.getElementById('nxStationLabel');if(nx)nx.textContent=label;const yx=document.getElementById('ysfStationLabel');if(yx)yx.textContent=label;const dx=document.getElementById('dstarStationLabel');if(dx)dx.textContent=label;const nxdnLbl=document.getElementById('nxdnStationLabel');if(nxdnLbl)nxdnLbl.textContent=label;const dNxPort=document.getElementById('dstarNxPort');if(dNxPort)dNxPort.textContent=d.dstarPort||'—';const dNxFrx=document.getElementById('dstarNxFrx');if(dNxFrx)dNxFrx.textContent=d.dstarFreqRX||'—';const dNxFtx=document.getElementById('dstarNxFtx');if(dNxFtx)dNxFtx.textContent=d.dstarFreqTX||'—';const dNxIp=document.getElementById('dstarNxIp');if(dNxIp)dNxIp.textContent=d.dstarIp||'—';const nNxPort=document.getElementById('nxdnNxPort');if(nNxPort)nNxPort.textContent=d.nxdnPort||'—';const nNxFrx=document.getElementById('nxdnNxFrx');if(nNxFrx)nNxFrx.textContent=d.nxdnFreqRX||'—';const nNxFtx=document.getElementById('nxdnNxFtx');if(nNxFtx)nNxFtx.textContent=d.nxdnFreqTX||'—';const nNxIp=document.getElementById('nxdnNxIp');if(nNxIp)nNxIp.textContent=d.nxdnIp||'—';}catch(e){console.warn('station-info error:',e);}}

function buildVU(id){const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}}
buildVU('vuLeft');buildVU('vuRight');buildVU('ysfVuLeft');buildVU('ysfVuRight');buildVU('nxdnVuLeft');buildVU('nxdnVuRight');buildVU('dstarVuLeft');buildVU('dstarVuRight');

function animateVU(on,prefix){clearInterval(prefix==='ysf'?ysfVuTimer:vuTimer);const ids=prefix==='ysf'?['ysfVuLeft','ysfVuRight']:['vuLeft','vuRight'];ids.forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;const timer=setInterval(()=>{ids.forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=prefix==='ysf'?(i<10?' lit-v':i<14?' lit-vd':' lit-r'):(i<10?' lit-g':i<14?' lit-a':' lit-r');document.getElementById(`${id}-${i}`).className=cls;}});},80);if(prefix==='ysf')ysfVuTimer=timer;else vuTimer=timer;}

function animateNXDNVU(on){clearInterval(nxdnVuTimerAnim);['nxdnVuLeft','nxdnVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;nxdnVuTimerAnim=setInterval(()=>{['nxdnVuLeft','nxdnVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-y':i<14?' lit-ya':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}

function updateClock(){const now=new Date();const hms=now.toLocaleTimeString('es-ES');const date=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();if(!currentlyActive){const clk=document.getElementById('nxClock');if(clk){clk.textContent=hms;document.getElementById('nxDate').textContent=date;}}if(!ysfCurrentlyActive){const yClk=document.getElementById('ysfNxClock');if(yClk){yClk.textContent=hms;document.getElementById('ysfNxDate').textContent=date;}}}
setInterval(updateClock,1000);updateClock();

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function setDMRToggle(on){const chk=document.getElementById('chkDMR'),lbl=document.getElementById('dmrToggleLabel'),sta=document.getElementById('dmrToggleStatus');chk.checked=on;lbl.className='toggle-label'+(on?' on-dmr':'');sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('autoRefreshBadge').style.display=on?'flex':'none';document.getElementById('dmrLogPanels').style.display=on?'contents':'none';document.getElementById('dmrLastHeardPanel').style.display=on?'':'none';document.getElementById('dmrDisplayPanel').style.display=on?'':'none';}
function setYSFToggle(on){const chk=document.getElementById('chkYSF'),lbl=document.getElementById('ysfToggleLabel'),sta=document.getElementById('ysfToggleStatus');chk.checked=on;lbl.className='toggle-label'+(on?' on-ysf':'');sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('ysfRefreshBadge').style.display=on?'flex':'none';document.getElementById('ysfLogPanels').style.display=on?'contents':'none';document.getElementById('ysfLastHeardPanel').style.display=on?'':'none';document.getElementById('ysfDisplayPanel').style.display=on?'':'none';}

// ✅ LÓGICA INMEDIATA DMR
function showIdle(){
    currentlyActive=false;animateVU(false,'dmr');stopTxTimer('dmr');
    document.getElementById('nxCenter').innerHTML = '<div class="nx-clock" id="nxClock">00:00:00</div><div class="nx-date" id="nxDate">—</div><span id="dmrTxTimer" class="nx-tx-timer dmr-tx-timer">00:00</span>';
    document.getElementById('nxTxBar').classList.remove('active');
    document.getElementById('nxTG').textContent='—';document.getElementById('nxSlot').textContent='—';document.getElementById('nxDmrid').textContent='—';
    const src=document.getElementById('nxSource');src.textContent='';src.className='nx-source';updateClock();
}
function showActive(d){
    currentlyActive=true;animateVU(true,'dmr');
    const flag=getFlagByCall(d.callsign);startTxTimer('dmr');
    document.getElementById('nxCenter').innerHTML = `<div class="nx-callsign">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name">${esc(d.name)}</div>`:'')+'<span id="dmrTxTimer" class="nx-tx-timer dmr-tx-timer">00:00</span>';
    txTimers['dmr'].el = document.getElementById('dmrTxTimer');
    document.getElementById('nxTxBar').classList.add('active');document.getElementById('nxTG').textContent=d.tg?'TG '+d.tg:'—';document.getElementById('nxSlot').textContent=d.slot||'—';document.getElementById('nxDmrid').textContent=d.dmrid||'—';
    const src=document.getElementById('nxSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else if(d.source==='NETWORK'){src.textContent='NET';src.className='nx-source net';}else{src.textContent='';src.className='nx-source';}
}

// ✅ LÓGICA INMEDIATA YSF
function showYSFIdle(){
    ysfCurrentlyActive=false;animateVU(false,'ysf');stopTxTimer('ysf');
    document.getElementById('ysfNxCenter').innerHTML='<div class="nx-clock" id="ysfNxClock" style="color:#c084ff;">00:00:00</div><div class="nx-date" id="ysfNxDate" style="color:#9b59d4;">—</div><span id="ysfTxTimer" class="nx-tx-timer ysf-tx-timer">00:00</span>';
    document.getElementById('ysfTxBar').className='nx-txbar';document.getElementById('ysfDest').textContent='—';
    const src=document.getElementById('ysfSource');src.textContent='';src.className='nx-source';updateClock();
}
function showYSFActive(d){
    ysfCurrentlyActive=true;animateVU(true,'ysf');
    const flag=getFlagByCall(d.callsign);startTxTimer('ysf');
    document.getElementById('ysfNxCenter').innerHTML=`<div class="nx-callsign ysf">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name ysf">${esc(d.name)}</div>`:'')+'<span id="ysfTxTimer" class="nx-tx-timer ysf-tx-timer">00:00</span>';
    txTimers['ysf'].el = document.getElementById('ysfTxTimer');
    document.getElementById('ysfTxBar').className='nx-txbar active-ysf';document.getElementById('ysfDest').textContent=d.dest?d.dest:'ALL';
    const src=document.getElementById('ysfSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else if(d.source==='NETWORK'){src.textContent='NET';src.className='nx-source net';}else{src.textContent='';src.className='nx-source';}
}

function renderLastHeard(list,activeCall){const body=document.getElementById('lhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-tg">${esc(r.tg||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}
function renderYSFLastHeard(list,activeCall){const body=document.getElementById('ysfLhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot-ysf"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row-ysf${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call-ysf">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}

// ✅ FETCH DMR: SIN RETRASO
async function fetchTransmission(){
  try{const r=await fetch('?action=transmission');const d=await r.json();
    if(d.active){showActive(d);}else{showIdle();}
    renderLastHeard(d.lastHeard||[],d.active?d.callsign:null);
  }catch(e){}
}
// ✅ FETCH YSF: SIN RETRASO
async function fetchYSFTransmission(){
  try{const r=await fetch('?action=ysf-transmission');const d=await r.json();
    if(d.active){showYSFActive(d);}else{showYSFIdle();}
    renderYSFLastHeard(d.lastHeard||[],d.active?d.callsign:null);
  }catch(e){}
}

function setDSTARToggle(on){const chk=document.getElementById('chkDSTAR'),lbl=document.getElementById('dstarToggleLabel'),sta=document.getElementById('dstarToggleStatus');chk.checked=on;lbl.style.color=on?'#00e5ff':'';sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('dstarRefreshBadge').style.display=on?'flex':'none';document.getElementById('dstarPanelMmd').style.display=on?'':'none';document.getElementById('dstarPanelGw').style.display=on?'':'none';document.getElementById('dstarDisplayPanel').style.display=on?'':'none';document.getElementById('dstarLastHeardPanel').style.display=on?'':'none';}

function buildDStarVU(){['dstarVuLeft','dstarVuRight'].forEach(id=>{const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}});}
buildDStarVU();
function animateDStarVU(on){clearInterval(dstarVuTimer);['dstarVuLeft','dstarVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;dstarVuTimer=setInterval(()=>{['dstarVuLeft','dstarVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-g':i<14?' lit-a':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}
function updateDStarClock(){if(!dstarCurrentlyActive){const now=new Date();const clk=document.getElementById('dstarNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('dstarNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateDStarClock,1000);updateDStarClock();

// ✅ LÓGICA INMEDIATA DSTAR
function showDStarIdle(){
  dstarCurrentlyActive=false;animateDStarVU(false);stopTxTimer('dstar');
  document.getElementById('dstarNxCenter').innerHTML = '<div class="nx-clock" id="dstarNxClock" style="color:#00e5ff;">00:00:00</div><div class="nx-date" id="dstarNxDate" style="color:#009090;">—</div><span id="dstarTxTimer" class="nx-tx-timer dstar-tx-timer">00:00</span>';
  document.getElementById('dstarTxBar').className='nx-txbar';const src=document.getElementById('dstarSource');src.textContent='';src.className='nx-source';updateDStarClock();
}
function showDStarActive(d){
  dstarCurrentlyActive=true;animateDStarVU(true);
  const flag=getFlagByCall(d.callsign.replace(/\/.*$/,''));startTxTimer('dstar');
  document.getElementById('dstarNxCenter').innerHTML = `<div class="nx-callsign dstar">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name dstar">${esc(d.name)}</div>`:'')+'<span id="dstarTxTimer" class="nx-tx-timer dstar-tx-timer">00:00</span>';
  txTimers['dstar'].el = document.getElementById('dstarTxTimer');
  document.getElementById('dstarTxBar').className='nx-txbar';document.getElementById('dstarTxBar').style.background='linear-gradient(90deg,transparent,#00e5ff,transparent)';document.getElementById('dstarTxBar').style.backgroundSize='200% 100%';document.getElementById('dstarTxBar').style.animation='scan 1.4s linear infinite';
  const src=document.getElementById('dstarSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else{src.textContent='NET';src.className='nx-source net';}
}

function setNXDNToggle(on){const chk=document.getElementById('chkNXDN'),lbl=document.getElementById('nxdnToggleLabel'),sta=document.getElementById('nxdnToggleStatus');chk.checked=on;lbl.style.color=on?'#ffd700':'';sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('nxdnRefreshBadge').style.display=on?'flex':'none';document.getElementById('nxdnPanelMmd').style.display=on?'':'none';document.getElementById('nxdnPanelGw').style.display=on?'':'none';document.getElementById('nxdnDisplayPanel').style.display=on?'':'none';document.getElementById('nxdnLastHeardPanel').style.display=on?'':'none';}

function updateNXDNClock(){if(!nxdnCurrentlyActive){const now=new Date();const clk=document.getElementById('nxdnNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('nxdnNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateNXDNClock,1000);updateNXDNClock();

// ✅ LÓGICA INMEDIATA NXDN
function showNXDNIdle(){
  nxdnCurrentlyActive=false;animateNXDNVU(false);stopTxTimer('nxdn');
  document.getElementById('nxdnNxCenter').innerHTML = '<div class="nx-clock" id="nxdnNxClock" style="color:#ffd700;">00:00:00</div><div class="nx-date" id="nxdnNxDate" style="color:#b8a000;">—</div><span id="nxdnTxTimer" class="nx-tx-timer nxdn-tx-timer">00:00</span>';
  document.getElementById('nxdnTxBar').className='nx-txbar';document.getElementById('nxdnTGLabel').textContent='—';
  const src=document.getElementById('nxdnSource');src.textContent='';src.className='nx-source';updateNXDNClock();
}
function showNXDNActive(d){
  nxdnCurrentlyActive=true;animateNXDNVU(true);
  const flag=getFlagByCall(d.callsign);startTxTimer('nxdn');
  document.getElementById('nxdnNxCenter').innerHTML = `<div class="nx-callsign nxdn">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name nxdn">${esc(d.name)}</div>`:'')+'<span id="nxdnTxTimer" class="nx-tx-timer nxdn-tx-timer">00:00</span>';
  txTimers['nxdn'].el = document.getElementById('nxdnTxTimer');
  document.getElementById('nxdnTxBar').className='nx-txbar active-nxdn';document.getElementById('nxdnTGLabel').textContent=d.tg?'TG '+d.tg:'—';
  const src=document.getElementById('nxdnSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else{src.textContent='NET';src.className='nx-source net';}
}

function renderDStarLastHeard(list,activeCall){const body=document.getElementById('dstarLhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot" style="background:#00e5ff;box-shadow:0 0 6px #00e5ff;"></span>':'';const flag=getFlagByCall(r.callsign.replace(/\/.*$/,''));return`<div class="lh-row${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call" style="color:#00e5ff;">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}
function renderNXDNLastHeard(list,activeCall){const body=document.getElementById('nxdnLhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot-nxdn"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row-nxdn${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call-nxdn">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-tg">${esc(r.tg||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}

// ✅ FETCH DSTAR: SIN RETRASO
async function fetchDStarTransmission(){
  try{const r=await fetch('?action=dstar-transmission');const d=await r.json();
    if(d.active){showDStarActive(d);}else{showDStarIdle();}
    renderDStarLastHeard(d.lastHeard||[],d.active?d.callsign:null);
  }catch(e){}
}
function startDStarTransmissionPoll(){fetchDStarTransmission();dstarTxTimer2=setInterval(fetchDStarTransmission,4000);}
function stopDStarTransmissionPoll(){clearInterval(dstarTxTimer2);dstarTxTimer2=null;}

// ✅ FETCH NXDN: SIN RETRASO
async function fetchNXDNTransmission(){
  try{const r=await fetch('?action=nxdn-transmission');const d=await r.json();
    if(d.active){showNXDNActive(d);}else{showNXDNIdle();}
    renderNXDNLastHeard(d.lastHeard||[],d.active?d.callsign:null);
  }catch(e){}
}

async function checkStatus(){try{const r=await fetch('?action=status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';setDot('dot-gateway',gw?'active':'off');setDot('dot-mmdvm',mmd?'active':'off');setDot('dot-mosquitto',gw?'active':'off');running=gw||mmd;setDMRToggle(running);if(running)startRefresh();}catch(e){}}
async function checkYSFStatus(){try{const r=await fetch('?action=ysf-status');const d=await r.json();ysfRunning=d.ysf==='active';setDot('dot-ysf',ysfRunning?'active':'off');setYSFToggle(ysfRunning||mmdvmYsfRunning);}catch(e){}}
async function checkMMDVMYSFStatus(){try{const r=await fetch('?action=mmdvmysf-status');const d=await r.json();mmdvmYsfRunning=d.mmdvmysf==='active';setDot('dot-mmdvmysf',mmdvmYsfRunning?'active':'off');setYSFToggle(ysfRunning||mmdvmYsfRunning);}catch(e){}}
function setDot(id,state){document.getElementById(id).className='dot'+(state==='active'?' active':state==='error'?' error':'');}

async function checkDStarStatus(){try{const r=await fetch('?action=dstar-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';setDot('dot-dstargw',gw?'active':'off');setDot('dot-dstarmmd',mmd?'active':'off');dstarRunning=(gw||mmd)&&!d.stopped;setDSTARToggle(dstarRunning);if(dstarRunning){startDStarLogs();startDStarTransmissionPoll();}}catch(e){}}
async function toggleDStar(chk){const wasOn=!chk.checked;const sw=document.getElementById('swDSTAR');chk.checked=wasOn;sw.classList.add('busy');try{await fetch(wasOn?'?action=dstar-stop':'?action=dstar-start');let ok=false;for(let i=0;i<15;i++){await new Promise(r=>setTimeout(r,1000));const r=await fetch('?action=dstar-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';const isOn=(gw||mmd)&&!d.stopped;if(wasOn&&!isOn){ok=true;setDot('dot-dstargw','off');setDot('dot-dstarmmd','off');dstarRunning=false;setDSTARToggle(false);stopDStarLogs();stopDStarTransmissionPoll();showDStarIdle();clearLog('logDstarGw');clearLog('logDstarMmd');break;}if(!wasOn&&isOn){ok=true;setDot('dot-dstargw',gw?'active':'off');setDot('dot-dstarmmd',mmd?'active':'off');dstarRunning=true;setDSTARToggle(true);startDStarLogs();startDStarTransmissionPoll();break;}}if(!ok){const r=await fetch('?action=dstar-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';dstarRunning=(gw||mmd)&&!d.stopped;setDot('dot-dstargw',gw?'active':'off');setDot('dot-dstarmmd',mmd?'active':'off');setDSTARToggle(dstarRunning);}}catch(e){console.warn('toggleDStar error:',e);}finally{sw.classList.remove('busy');}}
async function fetchDStarLogs(){try{const r=await fetch('?action=dstar-logs&lines=15');const d=await r.json();['logDstarGw:gateway','logDstarMmd:mmdvm'].forEach(pair=>{const[id,key]=pair.split(':');const el=document.getElementById(id);const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d[key]);if(atBot)el.scrollTop=el.scrollHeight;});}catch(e){}}
function startDStarLogs(){fetchDStarLogs();dstarTimer=setInterval(fetchDStarLogs,5000);}
function stopDStarLogs(){clearInterval(dstarTimer);dstarTimer=null;}

async function checkNXDNStatus(){try{const r=await fetch('?action=nxdn-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';setDot('dot-nxdngw',gw?'active':'off');setDot('dot-nxdnmmd',mmd?'active':'off');nxdnRunning=gw||mmd;setNXDNToggle(nxdnRunning);if(nxdnRunning){startNXDNLogs();startNXDNTxPoll();}}catch(e){}}
async function toggleNXDN(chk){const wasOn=!chk.checked;const sw=document.getElementById('swNXDN');chk.checked=wasOn;sw.classList.add('busy');try{await fetch(wasOn?'?action=nxdn-stop':'?action=nxdn-start');let ok=false;for(let i=0;i<15;i++){await new Promise(r=>setTimeout(r,1000));const r=await fetch('?action=nxdn-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';const isOn=gw||mmd;if(wasOn&&!isOn){ok=true;setDot('dot-nxdngw','off');setDot('dot-nxdnmmd','off');nxdnRunning=false;setNXDNToggle(false);stopNXDNLogs();stopNXDNTxPoll();showNXDNIdle();clearLog('logNxdnGw');clearLog('logNxdnMmd');break;}if(!wasOn&&isOn){ok=true;setDot('dot-nxdngw',gw?'active':'off');setDot('dot-nxdnmmd',mmd?'active':'off');nxdnRunning=true;setNXDNToggle(true);startNXDNLogs();startNXDNTxPoll();break;}}if(!ok)await checkNXDNStatus();}catch(e){console.warn('toggleNXDN error:',e);}finally{sw.classList.remove('busy');}}
async function fetchNXDNLogs(){try{const r=await fetch('?action=nxdn-logs&lines=15');const d=await r.json();[['logNxdnGw','gateway'],['logNxdnMmd','mmdvm']].forEach(([id,key])=>{const el=document.getElementById(id);const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d[key]);if(atBot)el.scrollTop=el.scrollHeight;});}catch(e){}}
function startNXDNLogs(){fetchNXDNLogs();nxdnTimer=setInterval(fetchNXDNLogs,5000);}
function stopNXDNLogs(){clearInterval(nxdnTimer);nxdnTimer=null;}
function startNXDNTxPoll(){fetchNXDNTransmission();nxdnTxTimer=setInterval(fetchNXDNTransmission,4000);}
function stopNXDNTxPoll(){clearInterval(nxdnTxTimer);nxdnTxTimer=null;}

async function toggleServices(chk){const wasOn=!chk.checked;const sw=document.getElementById('swDMR');chk.checked=wasOn;sw.classList.add('busy');try{await fetch(wasOn?'?action=stop':'?action=start');await new Promise(r=>setTimeout(r,2200));const r=await fetch('?action=status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';running=gw||mmd;setDot('dot-gateway',gw?'active':'off');setDot('dot-mmdvm',mmd?'active':'off');setDot('dot-mosquitto',gw?'active':'off');setDMRToggle(running);if(wasOn){stopRefresh();showIdle();clearLog('logGw');clearLog('logMmd');document.getElementById('lhBody').innerHTML='<div class="lh-empty">Sin actividad reciente</div>';}else startRefresh();}finally{sw.classList.remove('busy');}}
async function toggleYSF(chk){const wasOn=!chk.checked;const sw=document.getElementById('swYSF');chk.checked=wasOn;sw.classList.add('busy');try{if(wasOn){await fetch('?action=ysf-stop');await new Promise(r=>setTimeout(r,1000));await fetch('?action=mmdvmysf-stop');await new Promise(r=>setTimeout(r,2000));stopTxTimer('ysf');clearLog('logYsf');clearLog('logMmdvmYsf');stopYSFLogs();stopMMDVMYSFLogs();showYSFIdle();document.getElementById('ysfLhBody').innerHTML='<div class="lh-empty">Sin actividad C4FM</div>';}else{await fetch('?action=mmdvmysf-start');await new Promise(r=>setTimeout(r,2000));await fetch('?action=ysf-start');await new Promise(r=>setTimeout(r,1500));startYSFLogs();startMMDVMYSFLogs();}await checkYSFStatus();await checkMMDVMYSFStatus();}finally{sw.classList.remove('busy');}}

function toggleDropdown(e){e.stopPropagation();document.getElementById('dropActualizaciones').classList.toggle('open');}
document.addEventListener('click',()=>document.getElementById('dropActualizaciones').classList.remove('open'));
function closeUpdate(){document.getElementById('updateModal').classList.remove('open');}
const UPDATE_TITLES={imagen:'🖼 Actualizar Imagen',ids:'📋 Actualizar IDs',ysf:'📡 Actualizar Reflectores YSF'};
const UPDATE_ACTIONS={imagen:'?action=update-imagen',ids:'?action=update-ids',ysf:'?action=update-ysf'};
async function runUpdate(type){document.getElementById('dropActualizaciones').classList.remove('open');document.getElementById('updateTitle').textContent=UPDATE_TITLES[type];const con=document.getElementById('updateConsole');con.textContent='⏳ Ejecutando, espera…';document.getElementById('updateCloseBtn').disabled=true;document.getElementById('updateModal').classList.add('open');try{const r=await fetch(UPDATE_ACTIONS[type]);const d=await r.json();con.textContent=d.output||'(sin salida)';con.scrollTop=con.scrollHeight;}catch(e){con.textContent='✖ Error de red: '+e.message;}finally{document.getElementById('updateCloseBtn').disabled=false;}}
async function rebootPi(){if(!confirm('¿Seguro que quieres reiniciar la Raspberry Pi?'))return;const btn=document.getElementById('btnReboot');btn.textContent='⏻ Reiniciando…';btn.disabled=true;await fetch('?action=reboot');}
function closeInstalar(){document.getElementById('installModal').classList.remove('open');}
async function confirmarInstalacion(){const btn=document.getElementById('btnInstalarOk');const msg=document.getElementById('installMsg');const out=document.getElementById('installOutput');btn.disabled=true;btn.textContent='⏳ Instalando…';msg.className='restore-msg loading';msg.style.display='block';msg.textContent='⏳ Ejecutando instalador, espera…';out.className='install-output visible';out.textContent='';try{const r=await fetch('?action=install-display');const d=await r.json();out.textContent=d.output||'(sin salida)';out.scrollTop=out.scrollHeight;msg.className='restore-msg ok';msg.textContent='✔ Instalación completada.';btn.textContent='✔ Cerrar';btn.disabled=false;btn.onclick=function(){closeInstalar();};}catch(e){msg.className='restore-msg err';msg.textContent='✖ Error durante la instalación.';btn.textContent='▶ Confirmar instalación';btn.disabled=false;}}
function openRestore(){document.getElementById('restoreModal').classList.add('open');document.getElementById('restoreFile').value='';const msg=document.getElementById('restoreMsg');msg.style.display='none';msg.className='restore-msg';}
function closeRestore(){document.getElementById('restoreModal').classList.remove('open');}
async function doRestore(){const file=document.getElementById('restoreFile').files[0];if(!file){alert('Selecciona un fichero ZIP primero.');return;}const msg=document.getElementById('restoreMsg');if(!file.name.startsWith('Copia_PHP')){msg.className='restore-msg err';msg.style.display='block';msg.textContent='✖ Fichero no válido. El nombre debe empezar por "Copia_PHP".';return;}msg.className='restore-msg loading';msg.style.display='block';msg.textContent='⏳ Restaurando…';try{const form=new FormData();form.append('zipfile',file);const r=await fetch('?action=restore-configs',{method:'POST',body:form});const text=await r.text();let d;try{d=JSON.parse(text);}catch(parseErr){msg.className='restore-msg err';msg.textContent='✖ Respuesta inesperada: '+text.substring(0,200);return;}msg.className='restore-msg '+(d.ok?'ok':'err');msg.textContent=(d.ok?'✔ ':'✖ ')+d.msg;if(d.ok)setTimeout(closeRestore,2500);}catch(e){msg.className='restore-msg err';msg.textContent='✖ Error de red: '+e.message;}}

function colorize(text){return text.split('\n').map(l=>{const ll=l.toLowerCase();if(/error|fail|abort|assert/.test(ll))return`<span class="ln-err">${l}</span>`;if(/warn/.test(ll))return`<span class="ln-warn">${l}</span>`;if(/connect|start|open|loaded|success/.test(ll))return`<span class="ln-ok">${l}</span>`;return`<span class="ln-info">${l}</span>`;}).join('\n');}
function clearLog(id){document.getElementById(id).innerHTML='';}
async function fetchLogs(){try{const r=await fetch('?action=logs&lines=15');const d=await r.json();['logGw:gateway','logMmd:mmdvm'].forEach(pair=>{const[id,key]=pair.split(':');const el=document.getElementById(id);const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d[key]);if(atBot)el.scrollTop=el.scrollHeight;});}catch(e){}}
async function fetchYSFLogs(){try{const r=await fetch('?action=ysf-logs&lines=15');const d=await r.json();const el=document.getElementById('logYsf');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.ysf);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}}
async function fetchMMDVMYSFLogs(){try{const r=await fetch('?action=mmdvmysf-logs&lines=15');const d=await r.json();const el=document.getElementById('logMmdvmYsf');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.mmdvmysf);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}}
function startRefresh(){fetchLogs();fetchTransmission();refreshTimer=setInterval(fetchLogs,5000);txTimer=setInterval(fetchTransmission,3000);}
function stopRefresh(){clearInterval(refreshTimer);clearInterval(txTimer);refreshTimer=txTimer=null;}
function startYSFLogs(){fetchYSFLogs();ysfTimer=setInterval(fetchYSFLogs,4000);}
function stopYSFLogs(){clearInterval(ysfTimer);ysfTimer=null;}
function startMMDVMYSFLogs(){fetchMMDVMYSFLogs();mmdvmYsfTimer=setInterval(fetchMMDVMYSFLogs,4000);}
function stopMMDVMYSFLogs(){clearInterval(mmdvmYsfTimer);mmdvmYsfTimer=null;}
function startYSFTransmissionPoll(){fetchYSFTransmission();ysfTxTimer=setInterval(fetchYSFTransmission,4000);}

async function fetchSysInfo(){
    try{const r=await fetch('?action=sysinfo');const d=await r.json();
        const cpuEl=document.getElementById('siCpu');if(cpuEl){cpuEl.textContent=d.cpu+' %';cpuEl.style.color=d.cpu>80?'var(--red)':d.cpu>50?'var(--amber)':'var(--green)';}
        const tempEl=document.getElementById('siTemp');if(tempEl){tempEl.textContent=d.temp||'—';const t=parseFloat(d.temp);tempEl.style.color=t>75?'var(--red)':t>60?'var(--amber)':'var(--green)';}
        const ramEl=document.getElementById('siRam');if(ramEl) ramEl.textContent=d.ramUsed+' GB / '+d.ramTotal+' GB';
        const diskEl=document.getElementById('siDisk');if(diskEl) diskEl.textContent=d.diskUsed+' GB / '+d.diskTotal+' GB';
    }catch(e){console.warn('Error al cargar info del sistema:', e);}
}
fetchSysInfo();setInterval(fetchSysInfo, 5000);

async function feditOpen(path){
    const msg=document.getElementById('feditMsg');msg.style.display='none';document.getElementById('feditPath').textContent=path;document.getElementById('feditArea').value='Cargando…';document.getElementById('feditModal').classList.add('open');
    try{const r=await fetch('?action=read-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)});const d=await r.json();if(d.ok){document.getElementById('feditArea').value=d.content;document.getElementById('feditArea').focus();}else{document.getElementById('feditArea').value='';msg.className='fedit-msg err';msg.textContent='✖ '+d.msg;msg.style.display='block';}}catch(e){document.getElementById('feditArea').value='';msg.className='fedit-msg err';msg.textContent='✖ Error: '+e.message;msg.style.display='block';}
}
async function feditSave(){
    const path=document.getElementById('feditPath').textContent;const content=document.getElementById('feditArea').value;const msg=document.getElementById('feditMsg');
    try{const r=await fetch('?action=save-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)+'&content='+encodeURIComponent(content)});const d=await r.json();msg.className='fedit-msg '+(d.ok?'ok':'err');msg.textContent=(d.ok?'✔ ':'✖ ')+d.msg;msg.style.display='block';if(d.ok)setTimeout(()=>{msg.style.display='none';},3000);}catch(e){msg.className='fedit-msg err';msg.textContent='✖ Error: '+e.message;msg.style.display='block';}
}
function feditClose(){document.getElementById('feditModal').classList.remove('open');}

function xtTtydOpen(){var url='http://'+window.location.hostname+':7681';document.getElementById('xtTtydFrame').src=url;document.getElementById('xtTtydModal').style.display='flex';}
function xtTtydClose(){document.getElementById('xtTtydModal').style.display='none';document.getElementById('xtTtydFrame').src='';}

(function(){
var xtHist=[],xtHidx=-1,xtCwd='/home/pi';
function xtPr(){return 'pi@raspberry:'+xtCwd.replace('/home/pi','~')+'$';}
function xtApp(html){var o=document.getElementById('xtOut');o.innerHTML+=html+'\n';o.scrollTop=o.scrollHeight;}
function xtEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
window.xtOpen=function(){document.getElementById('xtModal').classList.add('open');setTimeout(function(){document.getElementById('xtInp').focus();},80);};
window.xtClose=function(){document.getElementById('xtModal').classList.remove('open');};
document.getElementById('xtInp').addEventListener('keydown',async function(e){
    if(e.key==='Escape'){xtClose();return;}
    if(e.key==='ArrowUp'){e.preventDefault();if(xtHidx<xtHist.length-1)this.value=xtHist[++xtHidx]||'';return;}
    if(e.key==='ArrowDown'){e.preventDefault();xtHidx>0?this.value=xtHist[--xtHidx]:(xtHidx=-1,this.value='');return;}
    if(e.key!=='Enter')return;
    var cmd=this.value.trim();if(!cmd)return;
    xtHist.unshift(cmd);xtHidx=-1;this.value='';
    xtApp('<span class="xt-cmd">'+xtEsc(xtPr())+' '+xtEsc(cmd)+'</span>');
    if(/^\s*clear\s*$/.test(cmd)){document.getElementById('xtOut').innerHTML='';return;}
    if(/^\s*(edit|nano)(\s+\S+)?\s*$/.test(cmd)){
        var fpath=cmd.replace(/^\s*(edit|nano)\s*/,'').trim();
        if(!fpath)fpath=xtCwd.replace(/\/$/,'')+'/nuevo.txt';
        if(!fpath.startsWith('/'))fpath=xtCwd.replace(/\/$/,'')+'/'+fpath;
        xtApp('<span class="xt-out">Abriendo editor: '+xtEsc(fpath)+'</span>');
        feditOpen(fpath);return;
    }
    if(/^\s*(sudo\s+su|su\s*$|top|htop|vim|vi|less|more)\s*/.test(cmd)){xtApp('<span class="xt-err">Comando interactivo no soportado. Usa: nano /ruta/fichero</span>');return;}
    if(/^\s*cd(\s|$)/.test(cmd)){
        var t=cmd.replace(/^\s*cd\s*/,'').trim()||'~';
        if(t==='~'||t===''){xtCwd='/home/pi';}
        else if(t.startsWith('/')){xtCwd=t;}
        else if(t==='..'){var parts=xtCwd.split('/').filter(Boolean);parts.pop();xtCwd='/'+parts.join('/')||'/';}
        else{xtCwd=xtCwd.replace(/\/$/,'')+'/'+t;}
        document.getElementById('xtPr').textContent=xtPr();return;
    }
    try{
        var resp=await fetch('?action=terminal',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'cmd='+encodeURIComponent('cd '+xtCwd+' && '+cmd)});
        var dat=await resp.json();
        if(dat.output){xtApp('<span class="xt-out">'+dat.output+'</span>');}
    }catch(err){xtApp('<span class="xt-err">Error: '+xtEsc(err.message)+'</span>');}
});
})();

(async()=>{
    await fetchStationInfo();
    setInterval(fetchStationInfo,60000);
    await checkStatus();await checkYSFStatus();await checkMMDVMYSFStatus();await checkDStarStatus();await checkNXDNStatus();
    setInterval(checkStatus,10000);setInterval(checkYSFStatus,8000);setInterval(checkMMDVMYSFStatus,8000);setInterval(checkDStarStatus,10000);setInterval(checkNXDNStatus,10000);
    if(!running){showIdle();fetchTransmission();}
    showYSFIdle();showNXDNIdle();startYSFLogs();startMMDVMYSFLogs();startYSFTransmissionPoll();
})();
</script>
</body>
</html>
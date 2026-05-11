<?php
require_once __DIR__ . '/auth.php';

$INI_PATH = '/home/pi/MMDVMHost/MMDVMHost.ini';

$SECTIONS = [
    'General' => [
        ['key'=>'Callsign',      'label'=>'Indicativo',           'type'=>'str'],
        ['key'=>'Id',            'label'=>'DMR ID',                'type'=>'int'],
        ['key'=>'Timeout',       'label'=>'Timeout (s)',           'type'=>'int'],
        ['key'=>'Duplex',        'label'=>'Duplex (0=simplex)',    'type'=>'int'],
        ['key'=>'ModeHang',      'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'RFModeHang',    'label'=>'RF Mode Hang (s)',      'type'=>'int'],
        ['key'=>'NetModeHang',   'label'=>'Net Mode Hang (s)',     'type'=>'int'],
        ['key'=>'Display',       'label'=>'Display',               'type'=>'str'],
        ['key'=>'Daemon',        'label'=>'Daemon (0/1)',          'type'=>'int'],
    ],
    'Info' => [
        ['key'=>'RXFrequency',  'label'=>'Frecuencia RX (Hz)',    'type'=>'int'],
        ['key'=>'TXFrequency',  'label'=>'Frecuencia TX (Hz)',    'type'=>'int'],
        ['key'=>'Power',        'label'=>'Potencia (W)',           'type'=>'int'],
        ['key'=>'Latitude',     'label'=>'Latitud',               'type'=>'str'],
        ['key'=>'Longitude',    'label'=>'Longitud',              'type'=>'str'],
        ['key'=>'Height',       'label'=>'Altura (m)',            'type'=>'int'],
        ['key'=>'Location',     'label'=>'Localización',          'type'=>'str'],
        ['key'=>'Description',  'label'=>'Descripción',           'type'=>'str'],
        ['key'=>'URL',          'label'=>'URL',                   'type'=>'str'],
    ],
    'Modem' => [
        ['key'=>'Protocol',     'label'=>'Protocolo',             'type'=>'str'],
        ['key'=>'UARTPort',     'label'=>'Puerto UART',           'type'=>'select','options'=>[
            ['label'=>'/dev/ttyAMA0','value'=>'/dev/ttyAMA0'],
            ['label'=>'/dev/ttyACM0','value'=>'/dev/ttyACM0'],
            ['label'=>'/dev/ttyACM1','value'=>'/dev/ttyACM1'],
            ['label'=>'/dev/ttyACM2','value'=>'/dev/ttyACM2'],
            ['label'=>'/dev/ttyUSB0','value'=>'/dev/ttyUSB0'],
            ['label'=>'/dev/ttyUSB1','value'=>'/dev/ttyUSB1'],
            ['label'=>'/dev/ttyUSB2','value'=>'/dev/ttyUSB2'],
        ]],
        ['key'=>'UARTSpeed',    'label'=>'Velocidad UART',        'type'=>'select','options'=>[
            ['label'=>'115200','value'=>'115200'],
            ['label'=>'460800','value'=>'460800'],
        ]],
        ['key'=>'TXInvert',     'label'=>'TX Invertido (0/1)',    'type'=>'int'],
        ['key'=>'RXInvert',     'label'=>'RX Invertido (0/1)',    'type'=>'int'],
        ['key'=>'PTTInvert',    'label'=>'PTT Invertido (0/1)',   'type'=>'int'],
        ['key'=>'TXDelay',      'label'=>'TX Delay (ms)',         'type'=>'int'],
        ['key'=>'RXOffset',     'label'=>'RX Offset',             'type'=>'int','signed'=>true],
        ['key'=>'TXOffset',     'label'=>'TX Offset',             'type'=>'int','signed'=>true],
        ['key'=>'DMRDelay',     'label'=>'DMR Delay (ms)',        'type'=>'int'],
        ['key'=>'RXLevel',      'label'=>'Nivel RX (%)',          'type'=>'int'],
        ['key'=>'TXLevel',      'label'=>'Nivel TX (%)',          'type'=>'int'],
        ['key'=>'RFLevel',      'label'=>'Nivel RF (%)',          'type'=>'int'],
        ['key'=>'Trace',        'label'=>'Trace (0/1)',           'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'Transparent Data' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'RemoteAddress','label'=>'IP Remota',             'type'=>'str'],
        ['key'=>'RemotePort',   'label'=>'Puerto Remoto',         'type'=>'int'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'SendFrameType','label'=>'Send Frame Type (0/1)', 'type'=>'int'],
    ],
    'D-Star' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Module',       'label'=>'Módulo (A/B/C)',        'type'=>'str'],
        ['key'=>'SelfOnly',     'label'=>'Self Only (0/1)',       'type'=>'int'],
        ['key'=>'AckReply',     'label'=>'ACK Reply (0/1)',       'type'=>'int'],
        ['key'=>'AckTime',      'label'=>'ACK Time (ms)',         'type'=>'int'],
        ['key'=>'AckMessage',   'label'=>'ACK Message (0/1)',     'type'=>'int'],
        ['key'=>'ErrorReply',   'label'=>'Error Reply (0/1)',     'type'=>'int'],
        ['key'=>'RemoteGateway','label'=>'Remote Gateway (0/1)', 'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'DMR' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'BeaconInterval','label'=>'Beacon Interval (s)',  'type'=>'int'],
        ['key'=>'BeaconDuration','label'=>'Beacon Duration (s)',  'type'=>'int'],
        ['key'=>'Id',           'label'=>'ID (ColorCode)',        'type'=>'int'],
        ['key'=>'ColorCode',    'label'=>'Color Code',            'type'=>'int'],
        ['key'=>'SelfOnly',     'label'=>'Self Only (0/1)',       'type'=>'int'],
        ['key'=>'EmbeddedLCOnly','label'=>'Embedded LC Only (0/1)','type'=>'int'],
        ['key'=>'DumpTAData',   'label'=>'Dump TA Data (0/1)',    'type'=>'int'],
        ['key'=>'Slot1',        'label'=>'Slot 1 (0/1)',          'type'=>'int'],
        ['key'=>'Slot2',        'label'=>'Slot 2 (0/1)',          'type'=>'int'],
        ['key'=>'OVCM',         'label'=>'OVCM (0/1)',            'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'System Fusion' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LowDeviation', 'label'=>'Low Deviation (0/1)',   'type'=>'int'],
        ['key'=>'RemoteGateway','label'=>'Remote Gateway (0/1)', 'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'P25' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'NAC',          'label'=>'NAC',                   'type'=>'int'],
        ['key'=>'SelfOnly',     'label'=>'Self Only (0/1)',       'type'=>'int'],
        ['key'=>'OverrideUIDCheck','label'=>'Override UID Check (0/1)','type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'NXDN' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'RAN',          'label'=>'RAN',                   'type'=>'int'],
        ['key'=>'SelfOnly',     'label'=>'Self Only (0/1)',       'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'M17' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'CAN',          'label'=>'CAN',                   'type'=>'int'],
        ['key'=>'SelfOnly',     'label'=>'Self Only (0/1)',       'type'=>'int'],
        ['key'=>'AllowEncryption','label'=>'Allow Encryption (0/1)','type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'POCSAG' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Frequency',    'label'=>'Frecuencia (Hz)',       'type'=>'int'],
    ],
    'FM' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Callsign',     'label'=>'Indicativo',            'type'=>'str'],
        ['key'=>'CallsignSpeed','label'=>'Callsign Speed (WPM)', 'type'=>'int'],
        ['key'=>'CallsignFrequency','label'=>'Callsign Freq (Hz)','type'=>'int'],
        ['key'=>'CallsignTime', 'label'=>'Callsign Time (min)',   'type'=>'int'],
        ['key'=>'CallsignHoldoff','label'=>'Callsign Holdoff',   'type'=>'int'],
        ['key'=>'CallsignHighLevel','label'=>'Callsign High Level','type'=>'str'],
        ['key'=>'CallsignLowLevel','label'=>'Callsign Low Level', 'type'=>'str'],
        ['key'=>'CallsignAtStart','label'=>'Callsign At Start (0/1)','type'=>'int'],
        ['key'=>'CallsignAtEnd','label'=>'Callsign At End (0/1)', 'type'=>'int'],
        ['key'=>'CallsignAtLatch','label'=>'Callsign At Latch (0/1)','type'=>'int'],
        ['key'=>'RFAck',        'label'=>'RF Ack',                'type'=>'str'],
        ['key'=>'ExtAck',       'label'=>'Ext Ack',               'type'=>'str'],
        ['key'=>'AckSpeed',     'label'=>'Ack Speed (WPM)',       'type'=>'int'],
        ['key'=>'AckFrequency', 'label'=>'Ack Frequency (Hz)',    'type'=>'int'],
        ['key'=>'AckMinTime',   'label'=>'Ack Min Time (ms)',     'type'=>'int'],
        ['key'=>'AckDelay',     'label'=>'Ack Delay (ms)',        'type'=>'int'],
        ['key'=>'AckLevel',     'label'=>'Ack Level',             'type'=>'str'],
        ['key'=>'Timeout',      'label'=>'Timeout (s)',           'type'=>'int'],
        ['key'=>'TimeoutLevel', 'label'=>'Timeout Level',         'type'=>'str'],
        ['key'=>'CTCSSFrequency','label'=>'CTCSS Freq (Hz)',      'type'=>'str'],
        ['key'=>'CTCSSHighThreshold','label'=>'CTCSS High Thresh','type'=>'int'],
        ['key'=>'CTCSSLowThreshold','label'=>'CTCSS Low Thresh',  'type'=>'int'],
        ['key'=>'CTCSSLevel',   'label'=>'CTCSS Level',           'type'=>'str'],
        ['key'=>'KerchunkTime', 'label'=>'Kerchunk Time (ms)',    'type'=>'int'],
        ['key'=>'HangTime',     'label'=>'Hang Time (ms)',        'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'AX.25' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'TXDelay',      'label'=>'TX Delay (ms)',         'type'=>'int'],
        ['key'=>'RXTwist',      'label'=>'RX Twist',              'type'=>'int','signed'=>true],
        ['key'=>'SlotTime',     'label'=>'Slot Time (ms)',        'type'=>'int'],
        ['key'=>'PPersist',     'label'=>'P-Persist',             'type'=>'int'],
        ['key'=>'Trace',        'label'=>'Trace (0/1)',           'type'=>'int'],
    ],
    'D-Star Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'DMR Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Type',         'label'=>'Tipo (DMR+/MMDVM)',     'type'=>'str'],
        ['key'=>'Address',      'label'=>'IP Servidor',           'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto',                'type'=>'int'],
        ['key'=>'Local',        'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'Password',     'label'=>'Password',              'type'=>'str'],
        ['key'=>'Options',      'label'=>'Options',               'type'=>'str'],
        ['key'=>'Slot1',        'label'=>'Slot 1 (0/1)',          'type'=>'int'],
        ['key'=>'Slot2',        'label'=>'Slot 2 (0/1)',          'type'=>'int'],
        ['key'=>'APRS',         'label'=>'APRS (0/1)',            'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'System Fusion Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'P25 Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'NXDN Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'M17 Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'POCSAG Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'FM Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'AX.25 Network' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LocalAddress', 'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'GatewayAddress','label'=>'IP Gateway',           'type'=>'str'],
        ['key'=>'GatewayPort', 'label'=>'Puerto Gateway',         'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',           'type'=>'int'],
    ],
    'GPSD' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Address',      'label'=>'IP GPSD',               'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto GPSD',           'type'=>'int'],
    ],
    'Remote Control' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Address',      'label'=>'IP Local',              'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto',                'type'=>'int'],
    ],
    'Log' => [
        ['key'=>'DisplayLevel', 'label'=>'Nivel Display',         'type'=>'int'],
        ['key'=>'FileLevel',    'label'=>'Nivel Fichero',         'type'=>'int'],
        ['key'=>'FilePath',     'label'=>'Ruta Log',              'type'=>'str'],
        ['key'=>'FileRoot',     'label'=>'Nombre Log',            'type'=>'str'],
        ['key'=>'FileRotate',   'label'=>'Rotación (0/1)',        'type'=>'int'],
    ],
    'Nextion' => [
        ['key'=>'Port',         'label'=>'Puerto',                'type'=>'str'],
        ['key'=>'Brightness',   'label'=>'Brillo',                'type'=>'int'],
        ['key'=>'DisplayClock', 'label'=>'Mostrar Reloj (0/1)',   'type'=>'int'],
        ['key'=>'UTC',          'label'=>'UTC (0/1)',             'type'=>'int'],
        ['key'=>'IdleBrightness','label'=>'Brillo Idle',         'type'=>'int'],
        ['key'=>'ScreenLayout', 'label'=>'Layout Pantalla',       'type'=>'int'],
        ['key'=>'TEMPDisplayTime','label'=>'Temp Display Time (s)','type'=>'int'],
        ['key'=>'ScrollCharTime','label'=>'Scroll Char Time (ms)','type'=>'int'],
        ['key'=>'ScrollDelay',  'label'=>'Scroll Delay (ms)',     'type'=>'int'],
    ],
    'OLED' => [
        ['key'=>'Type',         'label'=>'Tipo (3/6)',            'type'=>'int'],
        ['key'=>'Brightness',   'label'=>'Brillo (0-255)',        'type'=>'int'],
        ['key'=>'Invert',       'label'=>'Invertir (0/1)',        'type'=>'int'],
        ['key'=>'Scroll',       'label'=>'Scroll (0/1)',          'type'=>'int'],
        ['key'=>'Rotate',       'label'=>'Rotar (0/1)',           'type'=>'int'],
        ['key'=>'Cast',         'label'=>'Cast (0/1)',            'type'=>'int'],
        ['key'=>'LogoScreensaver','label'=>'Logo Screensaver (0/1)','type'=>'int'],
    ],
    'LCDproc' => [
        ['key'=>'Address',      'label'=>'IP LCDproc',            'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto LCDproc',        'type'=>'int'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'DisplayClock', 'label'=>'Mostrar Reloj (0/1)',   'type'=>'int'],
        ['key'=>'UTC',          'label'=>'UTC (0/1)',             'type'=>'int'],
        ['key'=>'DimOnIdle',    'label'=>'Dim On Idle (0/1)',     'type'=>'int'],
    ],
    'HD44780' => [
        ['key'=>'Rows',         'label'=>'Filas',                 'type'=>'int'],
        ['key'=>'Columns',      'label'=>'Columnas',              'type'=>'int'],
        ['key'=>'I2CAddress',   'label'=>'I2C Address',           'type'=>'str'],
        ['key'=>'PWMBrightness','label'=>'PWM Brillo',            'type'=>'int'],
        ['key'=>'DisplayClock', 'label'=>'Mostrar Reloj (0/1)',   'type'=>'int'],
        ['key'=>'UTC',          'label'=>'UTC (0/1)',             'type'=>'int'],
    ],
    'CW Id' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Time',         'label'=>'Tiempo (min)',          'type'=>'int'],
        ['key'=>'Message',      'label'=>'Mensaje',               'type'=>'str'],
    ],
    'Mobile GPS' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'Port',         'label'=>'Puerto GPS',            'type'=>'str'],
        ['key'=>'Speed',        'label'=>'Velocidad',             'type'=>'int'],
        ['key'=>'MinimumDistance','label'=>'Distancia Mínima (m)','type'=>'int'],
        ['key'=>'Callsign',     'label'=>'Indicativo APRS',       'type'=>'str'],
        ['key'=>'Symbol',       'label'=>'Símbolo APRS',          'type'=>'str'],
        ['key'=>'SymbolTable',  'label'=>'Tabla Símbolos',        'type'=>'str'],
    ],
];

function readIniValues($path, $sections) {
    $values = [];
    if (!file_exists($path)) return $values;
    $lines = file($path);
    $currentSec = '';
    foreach ($lines as $line) {
        $stripped = trim($line);
        if (preg_match('/^\[(.+)\]/', $stripped, $m)) { $currentSec = $m[1]; continue; }
        if ($stripped === '' || $stripped[0] === '#' || $stripped[0] === ';') continue;
        if (strpos($stripped, '=') !== false) {
            [$k, $v] = explode('=', $stripped, 2);
            $k = trim($k); $v = trim($v);
            if (isset($sections[$currentSec])) {
                foreach ($sections[$currentSec] as $field) {
                    if (strtolower($field['key']) === strtolower($k)) {
                        $values[$currentSec][$field['key']] = $v;
                    }
                }
            }
        }
    }
    return $values;
}

function writeIniValues($path, $sections, $newValues) {
    if (!file_exists($path)) return ['ok'=>false,'msg'=>'Fichero no encontrado'];
    $lines = file($path);
    $currentSec = '';
    $result = [];
    foreach ($lines as $line) {
        $stripped = trim($line);
        if (preg_match('/^\[(.+)\]/', $stripped, $m)) { $currentSec = $m[1]; $result[] = $line; continue; }
        if ($stripped === '' || $stripped[0] === '#' || $stripped[0] === ';') { $result[] = $line; continue; }
        if (strpos($stripped, '=') !== false && isset($sections[$currentSec])) {
            [$k] = explode('=', $stripped, 2);
            $k = trim($k);
            $matched = false;
            foreach ($sections[$currentSec] as $field) {
                if (strtolower($field['key']) === strtolower($k)) {
                    if (isset($newValues[$currentSec][$field['key']])) {
                        $result[] = $k . '=' . $newValues[$currentSec][$field['key']] . "\n";
                        $matched = true;
                    }
                    break;
                }
            }
            if (!$matched) $result[] = $line;
        } else {
            $result[] = $line;
        }
    }
    file_put_contents($path, implode('', $result));
    return ['ok'=>true,'msg'=>'Configuración guardada correctamente'];
}

$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $newValues = [];
    foreach ($SECTIONS as $sec => $fields) {
        foreach ($fields as $field) {
            $val = trim($_POST[$sec . '_' . $field['key']] ?? '');
            if ($field['type'] === 'int' && $val !== '' && !is_numeric($val)) {
                $errors[] = "'{$field['label']}' debe ser un número entero.";
            } else {
                $newValues[$sec][$field['key']] = $val;
            }
        }
    }
    if ($errors) { $message = implode('<br>', $errors); $msgType = 'error'; }
    else {
        $res = writeIniValues($INI_PATH, $SECTIONS, $newValues);
        $message = $res['msg']; $msgType = $res['ok'] ? 'success' : 'error';
    }
}

$values = readIniValues($INI_PATH, $SECTIONS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVM Config Editor · EA3EIZ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg:       #0a0e14;
    --surface:  #111720;
    --border:   #1e2d3d;
    --green:    #00ff9f;
    --red:      #ff4560;
    --amber:    #ffb300;
    --cyan:     #00d4ff;
    --text:     #a8b9cc;
    --text-dim: #4a5568;
    --font-mono:'Share Tech Mono', monospace;
    --font-ui:  'Rajdhani', sans-serif;
}
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }

.ctrl-header {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem;
}
.ctrl-header img { height: 40px; width: auto; }
.ctrl-header h1 {
    font-family: var(--font-ui); font-weight: 700; font-size: 1.4rem;
    letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase;
}

.page-body { padding: 2rem; max-width: 1200px; margin: 0 auto; }

.sec-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden;
}
.sec-card-header {
    background: #0d1e2a; border-bottom: 1px solid var(--border);
    padding: .6rem 1.2rem; font-family: var(--font-mono); font-size: .8rem;
    color: var(--amber); letter-spacing: .12em; text-transform: uppercase;
}
.sec-card-body { padding: 1.2rem 1.5rem; }

.field-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .field-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 750px)  { .field-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px)  { .field-grid { grid-template-columns: 1fr; } }

.field-item { display: flex; flex-direction: column; }

label {
    font-family: var(--font-ui); font-size: .9rem;
    color: var(--text); display: block; margin-bottom: .3rem;
}

input[type=text], input[type=number] {
    width: 100%; background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 4px; color: var(--green); font-family: var(--font-mono);
    font-size: .9rem; padding: .45rem .7rem; outline: none; transition: border-color .2s;
}
input[type=text]:focus, input[type=number]:focus { border-color: var(--green); }

select.field-select {
    width: 100%; background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 4px; color: var(--green); font-family: var(--font-mono);
    font-size: .9rem; padding: .45rem .7rem; outline: none; transition: border-color .2s; cursor: pointer;
}
select.field-select:focus { border-color: var(--green); }
select.field-select option { background: var(--surface); color: var(--green); }

.field-hint { font-family: var(--font-mono); font-size: .65rem; color: var(--text-dim); margin-top: .2rem; }

.alert-custom {
    border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem;
    font-family: var(--font-mono); font-size: .85rem; border: 1px solid;
}
.alert-success { background: rgba(0,255,159,.08); border-color: var(--green); color: var(--green); }
.alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }

.btn-save {
    background: #28a745; color: #fff; border: none; border-radius: 6px;
    font-family: var(--font-ui); font-weight: 700; font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .75rem 2.5rem; cursor: pointer; transition: background .2s;
}
.btn-save:hover { background: #218838; }
.btn-reload {
    background: var(--surface); color: var(--text-dim); border: 1px solid var(--border);
    border-radius: 6px; font-family: var(--font-ui); font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .75rem 2rem; cursor: pointer; transition: background .2s, color .2s;
    text-decoration: none; display: inline-block;
}
.btn-reload:hover { background: #1e2d3d; color: var(--text); }

.ini-path {
    font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim);
    background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px;
    padding: .4rem .8rem; margin-bottom: 1.5rem; word-break: break-all;
}
.ini-path span { color: var(--cyan); }

.btn-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
.note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); }
.btn-back {
    margin-left: auto; background: #28a745; color: #fff; border: none; border-radius: 6px;
    font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em;
    text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none; transition: background .2s;
}
.btn-back:hover { background: #218838; color: #fff; }
</style>
</head>
<body>

<header class="ctrl-header">
    <img src="Logo_ea3eiz.png" alt="EA3EIZ">
    <h1>MMDVM Config Editor</h1>
    <a href="mmdvm.php" class="btn-back">← Volver</a>
</header>

<div class="page-body">

    <div class="ini-path">📄 Fichero: <span><?= htmlspecialchars($INI_PATH) ?></span></div>

    <?php if ($message): ?>
    <div class="alert-custom alert-<?= $msgType ?>">
        <?= $msgType === 'success' ? '✔ ' : '✖ ' ?><?= $message ?>
        <?php if ($msgType === 'success'): ?>
            <br><small>Reinicia MMDVMHost para aplicar los cambios.</small>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!file_exists($INI_PATH)): ?>
    <div class="alert-custom alert-error">✖ Fichero no encontrado: <?= htmlspecialchars($INI_PATH) ?></div>
    <?php else: ?>

    <form method="POST">
    <?php foreach ($SECTIONS as $sec => $fields): ?>
    <div class="sec-card">
        <div class="sec-card-header">[<?= htmlspecialchars($sec) ?>]</div>
        <div class="sec-card-body">
            <div class="field-grid">
            <?php foreach ($fields as $field):
                $val     = $values[$sec][$field['key']] ?? '';
                $inpType = $field['type'] === 'int' ? 'number' : 'text';
                $fieldId = $sec . '_' . $field['key'];
                $signed  = !empty($field['signed']);
            ?>
            <div class="field-item">
                <label for="<?= $fieldId ?>"><?= htmlspecialchars($field['label']) ?></label>
                <?php if ($field['type'] === 'select'): ?>
                    <select id="<?= $fieldId ?>" name="<?= $fieldId ?>" class="field-select">
                        <?php foreach ($field['options'] as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['value']) ?>"
                            <?= $val == $opt['value'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input
                        type="<?= $inpType ?>"
                        id="<?= $fieldId ?>"
                        name="<?= $fieldId ?>"
                        value="<?= htmlspecialchars($val) ?>"
                        <?= $inpType === 'number' ? ($signed ? 'min="-9999"' : 'min="0"') : '' ?>
                    >
                <?php endif; ?>
                <div class="field-hint"><?= htmlspecialchars($field['key']) ?></div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="btn-row" style="margin-top:1rem;">
        <button type="submit" class="btn-save">💾 Guardar</button>
        <a href="mmdvm_config.php" class="btn-reload">🔄 Recargar</a>
        <span class="note">Los cambios requieren reiniciar MMDVMHost</span>
    </div>
    </form>

    <?php endif; ?>
</div>

</body>
</html>

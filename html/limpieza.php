<?php
// =========================================
//           LIMPIEZA DEL SISTEMA
// =========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$paths = [
    'mmdvm' => '/home/pi/MMDVMHost/*.log',
    'tmp'   => '/tmp/*',
    'oldlogs' => '/var/log/*.gz',
    'oldlogs2' => '/var/log/*.1'
];

function consola($msg) {
    return "<div class='linea'>" . htmlspecialchars($msg) . "</div>";
}

function borrar($pattern) {
    $files = glob($pattern);
    $deleted = 0;
    $out = "";

    $out .= consola("Escaneando: $pattern");

    if (!$files) {
        $out .= consola("Sin archivos");
        return [$deleted, $out];
    }

    foreach ($files as $f) {
        if (is_file($f)) {
            if (@unlink($f)) {
                $out .= consola("✔ Eliminado: $f");
                $deleted++;
            } else {
                $out .= consola("✖ Error permisos: $f");
            }
        }
    }

    return [$deleted, $out];
}

function limpiar_historial() {

    $out = "";

    $files = [
        '/home/pi/.bash_history',
        '/root/.bash_history'
    ];

    foreach ($files as $f) {
        if (file_exists($f)) {
            if (@unlink($f)) {
                $out .= consola("✔ Historial eliminado");
            } else {
                $out .= consola("✖ Error historial");
            }
        }
    }

    return $out;
}

function info_sistema() {

    $df = shell_exec("df -h / | awk 'NR==2'");
    $uptime = shell_exec("uptime -p");
    $mem = shell_exec("free -m | awk 'NR==2{printf \"Usado: %sMB / Total: %sMB\", $3,$2}'");

    return [
        'disco' => trim($df),
        'uptime' => trim($uptime),
        'ram' => trim($mem)
    ];
}

function ejecutar($opt) {

    global $paths;

    $report = [];
    $console = "";

    if (!empty($opt['mmdvm'])) {
        list($c, $log) = borrar($paths['mmdvm']);
        $report['MMDVMHost'] = $c;
        $console .= $log;
    }

    if (!empty($opt['tmp'])) {
        list($c, $log) = borrar($paths['tmp']);
        $report['/tmp'] = $c;
        $console .= $log;
    }

    if (!empty($opt['oldlogs'])) {
        list($c1, $l1) = borrar($paths['oldlogs']);
        list($c2, $l2) = borrar($paths['oldlogs2']);
        $report['logs antiguos'] = $c1 + $c2;
        $console .= $l1 . $l2;
    }

    if (!empty($opt['journal'])) {
        exec("journalctl --vacuum-time=3d --vacuum-size=100M 2>&1");
        $report['journalctl'] = "OK";
        $console .= consola("✔ Journal optimizado");
    }

    if (!empty($opt['apt'])) {
        exec("apt clean 2>&1");
        $report['APT'] = "OK";
        $console .= consola("✔ Cache APT limpia");
    }

    if (!empty($opt['history'])) {
        $console .= limpiar_historial();
        $report['historial'] = "OK";
    }

    if (!empty($opt['extra'])) {
        exec("rm -rf /home/pi/.cache/* 2>&1");
        exec("rm -rf /var/tmp/* 2>&1");
        $report['cache'] = "OK";
        $console .= consola("✔ Cache sistema limpiada");
    }

    return [$report, $console];
}

$report = null;
$console = "";
$sys = info_sistema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $opt = [
        'mmdvm' => isset($_POST['mmdvm']),
        'tmp' => isset($_POST['tmp']),
        'oldlogs' => isset($_POST['oldlogs']),
        'journal' => isset($_POST['journal']),
        'apt' => isset($_POST['apt']),
        'history' => isset($_POST['history']),
        'extra' => isset($_POST['extra'])
    ];

    list($report, $console) = ejecutar($opt);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Limpieza del sistema</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#0d1117;
    color:#e6edf3;
}

.contenedor{
    max-width:900px;
    margin:30px auto;
    background:#161b22;
    padding:20px;
    border-radius:12px;
}

/* HEADER */
.top{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

h1{
    color:#c9d1d9;
}

.home{
    background:#21262d;
    color:#fff;
    padding:8px 12px;
    border-radius:8px;
    text-decoration:none;
}
.home:hover{background:#2a313c;}

/* =========================
   CHECKBOX HORIZONTAL PRO
========================= */

.grid-opciones{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:15px;
}

.opcion{
    background:#1c212a;
    border:1px solid #2a313c;
    padding:8px 12px;
    border-radius:8px;
    display:flex;
    align-items:center;
    gap:8px;
    cursor:pointer;
    transition:0.2s;
}

.opcion:hover{
    background:#222a36;
}

.opcion input{
    accent-color:#4c8bf5;
    transform:scale(1.1);
}

.opcion span{
    font-size:13px;
    color:#c9d1d9;
}

/* BOTÓN */
button{
    width:100%;
    margin-top:15px;
    padding:14px;
    border:none;
    border-radius:8px;
    background:#1f6feb;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#2f81f7;
}

/* CONSOLA */
.consola{
    margin-top:15px;
    background:#0b0f14;
    padding:10px;
    height:110px;
    overflow:auto;
    font-family:monospace;
    font-size:12px;
    border-radius:8px;
    border:1px solid #222;
}

.linea{
    color:#8ab4f8;
}

/* RESULTADOS */
.card{
    display:flex;
    justify-content:space-between;
    background:#1c212a;
    padding:10px;
    margin:6px 0;
    border-radius:8px;
    border:1px solid #2a313c;
}

.badge{
    background:#30363d;
    color:#fff;
    padding:3px 8px;
    border-radius:6px;
}

/* SISTEMA */
.sysgrid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:10px;
    margin-top:20px;
}

.syscard{
    background:#161b22;
    border:1px solid #2a313c;
    padding:15px;
    border-radius:10px;
}

.syscard h3{
    margin:0 0 10px 0;
    font-size:14px;
    color:#8ab4f8;
}

.sysvalue{
    font-size:13px;
    white-space:pre-wrap;
}

</style>
</head>

<body>

<div class="contenedor">

<div class="top">
<h1>🧹 Limpieza del sistema</h1>
<a class="home" href="mmdvm.php">🏠 Panel PHPPLUS</a>
</div>

<form method="post">

<div class="grid-opciones">

<label class="opcion">
<input type="checkbox" name="mmdvm" checked>
<span>MMDVM logs</span>
</label>

<label class="opcion">
<input type="checkbox" name="tmp">
<span>/tmp</span>
</label>

<label class="opcion">
<input type="checkbox" name="oldlogs">
<span>Logs antiguos</span>
</label>

<label class="opcion">
<input type="checkbox" name="journal">
<span>Journal</span>
</label>

<label class="opcion">
<input type="checkbox" name="apt">
<span>APT cache</span>
</label>

<label class="opcion">
<input type="checkbox" name="history">
<span>Historial</span>
</label>

<label class="opcion">
<input type="checkbox" name="extra">
<span>Cache sistema</span>
</label>

</div>

<button type="submit">🚀 Ejecutar limpieza</button>

</form>

<?php if ($report): ?>
<div class="mt-3">
<?php foreach ($report as $k => $v): ?>
<div class="card">
<span><?= htmlspecialchars($k) ?></span>
<span class="badge"><?= htmlspecialchars($v) ?></span>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($console): ?>
<div class="consola"><?= $console ?></div>
<?php endif; ?>

<div class="sysgrid">

<div class="syscard">
<h3>💾 Disco</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['disco']) ?></div>
</div>

<div class="syscard">
<h3>🧠 RAM</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['ram']) ?></div>
</div>

<div class="syscard">
<h3>⏱️ Uptime</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['uptime']) ?></div>
</div>

</div>

</div>

</body>
</html>

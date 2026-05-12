<?php
// =========================================
// LIMPIADOR DEL SISTEMA - ORANGE PI (DASHBOARD PRO)
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
        $out .= consola("Sin archivos encontrados");
        return [$deleted, $out];
    }

    foreach ($files as $f) {
        $out .= consola("Encontrado: $f");

        if (is_file($f)) {
            if (@unlink($f)) {
                $out .= consola("Eliminado ✔");
                $deleted++;
            } else {
                $out .= consola("ERROR permisos");
            }
        }
    }

    return [$deleted, $out];
}

/**
 * 🔥 INFORMACIÓN DEL SISTEMA MEJORADA
 */
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
        exec("journalctl --vacuum-time=3d 2>&1");
        $report['journalctl'] = "OK";
        $console .= consola("Journalctl limpiado");
    }

    if (!empty($opt['apt'])) {
        exec("apt clean 2>&1");
        $report['APT'] = "OK";
        $console .= consola("Cache APT limpiada");
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
        'apt' => isset($_POST['apt'])
    ];

    list($report, $console) = ejecutar($opt);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Limpiador del sistema</title>

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
    color:#00d4ff;
}

.home{
    background:#21262d;
    color:#fff;
    padding:8px 12px;
    border-radius:8px;
    text-decoration:none;
}
.home:hover{background:#30363d;}

/* OPCIONES */
.opcion{
    background:#21262d;
    padding:12px;
    margin:10px 0;
    border-radius:8px;
}

/* BOTON */
button{
    width:100%;
    padding:14px;
    background:linear-gradient(90deg,#00d4ff,#007bff);
    border:none;
    color:#fff;
    font-weight:bold;
    border-radius:8px;
}

/* CONSOLA */
.consola{
    margin-top:15px;
    background:#000;
    padding:10px;
    height:140px;
    overflow:auto;
    font-family:monospace;
    font-size:12px;
    border-radius:8px;
}
.linea{color:#58a6ff; margin-bottom:2px;}

/* RESULTADOS */
.resultado{
    margin-top:15px;
}

.card{
    display:flex;
    justify-content:space-between;
    background:#21262d;
    padding:12px;
    margin:8px 0;
    border-radius:8px;
}

.badge{
    background:#00d4ff;
    color:#000;
    padding:3px 8px;
    border-radius:6px;
}

/* ===== DASHBOARD SISTEMA ===== */
.sysgrid{
    display:grid;
    grid-template-columns: repeat(auto-fit,minmax(250px,1fr));
    gap:10px;
    margin-top:20px;
}

.syscard{
    padding:15px;
    border-radius:10px;
    background:#111827;
    border:1px solid #222;
}

.syscard h3{
    margin:0 0 10px 0;
    font-size:14px;
    color:#00d4ff;
}

.sysvalue{
    font-size:13px;
    color:#e6edf3;
    white-space:pre-wrap;
}

/* colores por tipo */
.disco{border-left:4px solid #00d4ff;}
.ram{border-left:4px solid #00ff9d;}
.uptime{border-left:4px solid #ffcc00;}
</style>
</head>

<body>

<div class="contenedor">

<div class="top">
<h1>🧹 Limpiador del sistema</h1>
<a class="home" href="mmdvm.php">🏠 Inicio</a>
</div>

<form method="post">

<div class="opcion"><label><input type="checkbox" name="mmdvm" checked> Logs MMDVMHost</label></div>
<div class="opcion"><label><input type="checkbox" name="tmp"> Archivos temporales (/tmp)</label></div>
<div class="opcion"><label><input type="checkbox" name="oldlogs"> Logs antiguos</label></div>
<div class="opcion"><label><input type="checkbox" name="journal"> Journal del sistema</label></div>
<div class="opcion"><label><input type="checkbox" name="apt"> Caché APT</label></div>

<button type="submit">🚀 Ejecutar limpieza segura</button>

</form>

<?php if ($report): ?>
<div class="resultado">
<h3>Resultado</h3>

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

<!-- =========================
     DASHBOARD SISTEMA MEJORADO
========================= -->
<div class="sysgrid">

<div class="syscard disco">
<h3>💾 Disco</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['disco']) ?></div>
</div>

<div class="syscard ram">
<h3>🧠 Memoria RAM</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['ram']) ?></div>
</div>

<div class="syscard uptime">
<h3>⏱️ Tiempo activo</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['uptime']) ?></div>
</div>

</div>

</div>

</body>
</html>

<?php
// =========================================
// ORANGE PI CLEANER - DARK UI (PHP)
// =========================================
// Ejecutar en navegador o CLI (recomendado web con apache/nginx)
// IMPORTANTE: requiere permisos adecuados para borrar logs del sistema
// =========================================

// CONFIGURACIÓN
$paths = [
    'mmdvm' => '/home/pi/MMDVMHosts/*.log',
    'tmp'   => '/tmp/*',
    'varlog' => '/var/log/*.log',
    'varlog1' => '/var/log/*.1',
    'gziplogs' => '/var/log/*.gz'
];

function borrar_archivos($pattern) {
    $files = glob($pattern);
    $deleted = 0;

    if (!$files) return 0;

    foreach ($files as $file) {
        if (is_file($file)) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }

    return $deleted;
}

function ejecutar_limpieza($options) {
    global $paths;
    $report = [];

    if (!empty($options['mmdvm'])) {
        $report['MMDVMHosts logs'] = borrar_archivos($paths['mmdvm']);
    }

    if (!empty($options['tmp'])) {
        $report['/tmp'] = borrar_archivos($paths['tmp']);
    }

    if (!empty($options['system_logs'])) {
        $report['System logs'] = borrar_archivos($paths['varlog'])
            + borrar_archivos($paths['varlog1'])
            + borrar_archivos($paths['gziplogs']);
    }

    if (!empty($options['journal'])) {
        exec("journalctl --vacuum-time=3d");
        $report['Journalctl'] = 'limpiado (3 días)';
    }

    return $report;
}

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $options = [
        'mmdvm' => isset($_POST['mmdvm']),
        'tmp' => isset($_POST['tmp']),
        'system_logs' => isset($_POST['system_logs']),
        'journal' => isset($_POST['journal'])
    ];

    $report = ejecutar_limpieza($options);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orange Pi Cleaner</title>
<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #0f1115;
        color: #e6e6e6;
    }

    .container {
        max-width: 700px;
        margin: 50px auto;
        background: #1a1d23;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 20px rgba(0,0,0,0.6);
    }

    h1 {
        text-align: center;
        color: #00d4ff;
    }

    .option {
        background: #262a33;
        padding: 12px;
        margin: 10px 0;
        border-radius: 8px;
    }

    label {
        cursor: pointer;
    }

    input[type=checkbox] {
        transform: scale(1.3);
        margin-right: 10px;
    }

    button {
        width: 100%;
        padding: 15px;
        background: linear-gradient(90deg, #00d4ff, #007bff);
        border: none;
        color: white;
        font-size: 16px;
        border-radius: 8px;
        cursor: pointer;
        margin-top: 15px;
    }

    button:hover {
        opacity: 0.9;
    }

    .report {
        margin-top: 20px;
        background: #11141a;
        padding: 15px;
        border-radius: 8px;
    }

    .ok {
        color: #00ff9d;
    }
</style>
</head>
<body>

<div class="container">
    <h1>🧹 Orange Pi Cleaner</h1>

    <form method="post">

        <div class="option">
            <label><input type="checkbox" name="mmdvm" checked> Limpiar logs MMDVMHosts</label>
        </div>

        <div class="option">
            <label><input type="checkbox" name="tmp"> Limpiar /tmp</label>
        </div>

        <div class="option">
            <label><input type="checkbox" name="system_logs"> Limpiar logs del sistema (/var/log)</label>
        </div>

        <div class="option">
            <label><input type="checkbox" name="journal"> Limpiar journalctl (3 días)</label>
        </div>

        <button type="submit">🚀 Ejecutar limpieza</button>
    </form>

<?php if ($report): ?>
    <div class="report">
        <h3>Resultado:</h3>
        <?php foreach ($report as $k => $v): ?>
            <p class="ok"><?= htmlspecialchars($k) ?> → <?= htmlspecialchars($v) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

</body>
</html>


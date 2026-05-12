<?php
// =========================================
// ORANGE PI CLEANER - DARK UI (PHP)
// FIXED + DEBUG MODE + ROBUST FILE DELETION
// =========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$paths = [
    // FIX: corregido nombre probable (MMDVMHost vs MMDVMHosts)
    'mmdvm' => '/home/pi/MMDVMHost/*.log',
    'tmp'   => '/tmp/*',
    'varlog' => '/var/log/*.log',
    'varlog1' => '/var/log/*.1',
    'gziplogs' => '/var/log/*.gz'
];

function borrar_archivos($pattern) {
    $files = glob($pattern);
    $deleted = 0;

    echo "<pre>DEBUG pattern: $pattern\n";

    if (!$files) {
        echo "No files found\n</pre>";
        return 0;
    }

    foreach ($files as $file) {
        echo "Found: $file\n";

        if (is_file($file)) {
            // más robusto que unlink en algunos casos
            if (@unlink($file)) {
                echo "Deleted: $file\n";
                $deleted++;
            } else {
                echo "FAILED deleting: $file (permission?)\n";
            }
        }
    }

    echo "</pre>";

    return $deleted;
}

function ejecutar_limpieza($options) {
    global $paths;
    $report = [];

    if (!empty($options['mmdvm'])) {
        $report['MMDVMHost logs'] = borrar_archivos($paths['mmdvm']);
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
        exec("journalctl --vacuum-time=3d 2>&1", $out);
        $report['Journalctl'] = implode("\n", $out);
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
    body { margin:0; font-family:Arial; background:#0f1115; color:#e6e6e6; }
    .container { max-width:700px; margin:50px auto; background:#1a1d23; padding:20px; border-radius:12px; }
    h1 { text-align:center; color:#00d4ff; }
    .option { background:#262a33; padding:12px; margin:10px 0; border-radius:8px; }
    input { transform:scale(1.2); }
    button { width:100%; padding:15px; background:#00d4ff; border:none; color:#000; font-weight:bold; border-radius:8px; }
    pre { background:#000; padding:10px; color:#00ff9d; overflow:auto; }
</style>
</head>
<body>

<div class="container">
<h1>🧹 Orange Pi Cleaner (FIXED)</h1>

<form method="post">

<div class="option">
<label><input type="checkbox" name="mmdvm" checked> MMDVMHost logs</label>
</div>

<div class="option">
<label><input type="checkbox" name="tmp"> /tmp</label>
</div>

<div class="option">
<label><input type="checkbox" name="system_logs"> /var/log</label>
</div>

<div class="option">
<label><input type="checkbox" name="journal"> journalctl</label>
</div>

<button type="submit">🚀 Ejecutar limpieza</button>
</form>

<?php if ($report): ?>
    <h3>Resultado:</h3>
    <pre><?php print_r($report); ?></pre>
<?php endif; ?>

</div>

</body>
</html>


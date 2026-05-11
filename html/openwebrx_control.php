<?php

function runCmd($cmd) {
    return shell_exec($cmd . " 2>&1");
}

$output = "";

if (isset($_GET['action'])) {

    $action = $_GET['action'];
    $output .= "Ejecutando: $action\n\n";

    if ($action == "start") {
        $output .= runCmd("docker start openwebrx");
    }

    if ($action == "stop") {
        $output .= runCmd("docker stop openwebrx");
    }

    if ($action == "restart") {
        $output .= runCmd("docker restart openwebrx");
    }

    $output .= "\n--- FIN ---\n";
}

$status = trim(runCmd("docker ps -q -f name=openwebrx"));
$isRunning = ($status != "");

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>OpenWebRX Control</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#121212;
    color:#fff;
}

.panel {
    background:#1e1e1e;
    padding:15px;
    border-radius:12px;
}

.topbar {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.btn {
    border-radius:8px;
}

.terminal {
    background:#000;
    color:#00ff66;
    padding:15px;
    height:320px;
    overflow-y:auto;
    font-family: monospace;
    font-size: 13px;
    border-radius:12px;
    white-space: pre-wrap;
}

.status-ok { color: lime; font-weight:bold; }
.status-bad { color: red; font-weight:bold; }

.spacer {
    flex-grow:1;
}
</style>
</head>

<body>

<div class="container py-4">

    <h3 class="mb-3">📡 OpenWebRX Control Panel</h3>

    <!-- PANEL SUPERIOR -->
    <div class="panel mb-3">

        <div class="topbar">

            <span>
                Estado:
                <?php if ($isRunning): ?>
                    <span class="status-ok">🟢 RUNNING</span>
                <?php else: ?>
                    <span class="status-bad">🔴 STOPPED</span>
                <?php endif; ?>
            </span>

            <div class="ms-3"></div>

            <a href="?action=start" class="btn btn-success btn-sm">▶ START</a>
            <a href="?action=stop" class="btn btn-danger btn-sm">⏹ STOP</a>
            <a href="?action=restart" class="btn btn-warning btn-sm">🔄 RESTART</a>

            <a href="http://localhost:8073" target="_blank" class="btn btn-info btn-sm">
                🌐 OPEN WEB
            </a>

            <div class="spacer"></div>

            <a href="mmdvm.php" class="btn btn-secondary btn-sm">
                🔙 PANEL PHPPLUS
            </a>

        </div>

    </div>

    <!-- TERMINAL -->
    <div class="panel">
        <h5>📟 Consola Docker</h5>

        <div class="terminal">
<?php

if ($output == "") {

    echo "Sin acción ejecutada...\n\n";

    echo "Docker containers:\n";
    echo runCmd("docker ps -a --filter name=openwebrx");

    echo "\n\nÚltimos logs:\n";
    echo runCmd("docker logs --tail 20 openwebrx 2>&1");

} else {
    echo $output;
}

?>
        </div>

    </div>

</div>

</body>
</html>

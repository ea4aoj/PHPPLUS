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

    if ($action == "enable") {
        $output .= runCmd("sudo systemctl enable openwebrx");
    }

    if ($action == "disable") {
        $output .= runCmd("sudo systemctl disable openwebrx");
    }

    $output .= "\n--- FIN ACCIÓN ---\n\n";
}

$status = trim(runCmd("docker ps -q -f name=openwebrx"));
$isRunning = ($status != "");

$autostart = trim(runCmd("systemctl is-enabled openwebrx 2>/dev/null"));
$isEnabled = ($autostart == "enabled");

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>OpenWebRX Control</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#0f1115;
    color:#fff;
}

.panel {
    background:#1b1f2a;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
}

.topbar {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.btn {
    border-radius:8px;
    font-size: 0.85rem;
}

.terminal {
    background:#000;
    color:#00ff66;
    padding:15px;
    height:70vh;
    overflow-y:auto;
    font-family: monospace;
    font-size: 13px;
    border-radius:12px;
    white-space: pre-wrap;
    border:1px solid #333;
}

.status-ok { color: lime; font-weight:bold; }
.status-bad { color: red; font-weight:bold; }

.spacer {
    flex-grow:1;
}

.title {
    font-weight: bold;
    font-size: 18px;
}
</style>
</head>

<body>

<div class="container py-4">

    <div class="panel">
        <div class="topbar">

            <div class="title">📡 OpenWebRX Control Panel</div>

            <div class="ms-3"></div>

            <span>
                Docker:
                <?php if ($isRunning): ?>
                    <span class="status-ok">🟢 RUNNING</span>
                <?php else: ?>
                    <span class="status-bad">🔴 STOPPED</span>
                <?php endif; ?>
            </span>

            <span>
                Autostart:
                <?php if ($isEnabled): ?>
                    <span class="status-ok">🟢 ENABLED</span>
                <?php else: ?>
                    <span class="status-bad">🔴 DISABLED</span>
                <?php endif; ?>
            </span>

            <div class="spacer"></div>

            <a href="?action=start" class="btn btn-success btn-sm">▶ START</a>
            <a href="?action=stop" class="btn btn-danger btn-sm">⏹ STOP</a>
            <a href="?action=restart" class="btn btn-warning btn-sm">🔄 RESTART</a>

            <a href="?action=enable" class="btn btn-primary btn-sm">⚡ ENABLE</a>
            <a href="?action=disable" class="btn btn-secondary btn-sm">🛑 DISABLE</a>

            <a href="http://localhost:8073" target="_blank" class="btn btn-info btn-sm">
                🌐 OPEN WEB
            </a>

            <a href="mmdvm.php" class="btn btn-outline-light btn-sm">
                🔙 PHPPLUS
            </a>

        </div>
    </div>

    <div class="panel">
        <h5>📟 Consola / Logs OpenWebRX</h5>

        <div class="terminal">

<?php

echo "===== ESTADO DOCKER =====\n";
echo runCmd("docker ps -a --filter name=openwebrx");

echo "\n===== LOGS (últimos 80 líneas) =====\n";
echo runCmd("docker logs --tail 80 openwebrx 2>&1");

if ($output != "") {
    echo "\n===== ACCIONES EJECUTADAS =====\n";
    echo $output;
}

?>

        </div>
    </div>

</div>

</body>
</html>

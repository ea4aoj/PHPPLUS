<?php

function runCmd($cmd) {
    return shell_exec($cmd . " 2>&1");
}

$output = "";

/* LIMPIAR TERMINAL + ACCIONES */
if (isset($_GET['action'])) {

    $action = $_GET['action'];

    // 🔥 LIMPIEZA SIEMPRE AL INICIO DE ACCIÓN
    $output = "🧹 Limpiando terminal...\n\n";

    if ($action == "start") {
        $output .= "▶ STARTING OpenWebRX...\n\n";
        $output .= runCmd("docker start openwebrx");
    }

    if ($action == "stop") {
        $output .= "⏹ STOPPING OpenWebRX...\n\n";
        $output .= runCmd("docker stop openwebrx");
    }

    if ($action == "restart") {
        $output .= "🔄 RESTARTING OpenWebRX...\n\n";
        $output .= runCmd("docker restart openwebrx");
    }

    if ($action == "toggle") {

        $output .= "⚙ TOGGLING AUTOSTART...\n\n";

        $state = trim(runCmd("systemctl is-enabled openwebrx 2>/dev/null"));

        if ($state == "enabled") {
            $output .= runCmd("sudo systemctl disable openwebrx");
            $output .= "\nAUTOSTART → DISABLED\n";
        } else {
            $output .= runCmd("sudo systemctl enable openwebrx");
            $output .= "\nAUTOSTART → ENABLED\n";
        }
    }

    $output .= "\n--- FIN ACCIÓN ---\n\n";
}

/* STATUS */
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
body {
    background:#0e1117;
    color:#fff;
}

.panel {
    background:#161b22;
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

.title {
    font-weight:bold;
    font-size:18px;
}

.btn {
    border-radius:8px;
    font-size:0.85rem;
}

.terminal {
    background:#000;
    color:#00ff66;
    padding:15px;
    height:75vh;
    overflow-y:auto;
    font-family: monospace;
    font-size: 13px;
    border-radius:12px;
    white-space: pre-wrap;
    border:1px solid #333;
}

.status-ok { color: lime; font-weight:bold; }
.status-bad { color: red; font-weight:bold; }

.spacer { flex-grow:1; }
</style>
</head>

<body>

<div class="container py-4">

    <!-- HEADER -->
    <div class="panel">
        <div class="topbar">

            <div class="title">📡 OpenWebRX Control Panel</div>

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

            <div class="ms-3"></div>

            <a href="?action=start" class="btn btn-success btn-sm">▶ START</a>
            <a href="?action=stop" class="btn btn-danger btn-sm">⏹ STOP</a>
            <a href="?action=restart" class="btn btn-warning btn-sm">🔄 RESTART</a>
            <a href="?action=toggle" class="btn btn-primary btn-sm">⚙ AUTOSTART</a>

            <a href="http://localhost:8073" target="_blank" class="btn btn-info btn-sm">
                🌐 OPEN WEB
            </a>

            <div class="spacer"></div>

            <!-- 🏠 PHPPLUS -->
            <a href="mmdvm.php" class="btn btn-outline-light btn-sm" title="Panel PHPPLUS">
            <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
            </a>

        </div>
    </div>

    <!-- TERMINAL -->
    <div class="panel">
        <h5>📟 OpenWebRX Console</h5>

        <div class="terminal">
<?php

if ($output != "") {
    echo $output;
} else {

    echo "===== DOCKER STATUS =====\n";
    echo runCmd("docker ps -a --filter name=openwebrx");

    echo "\n===== DOCKER INSPECT =====\n";
    echo runCmd("docker inspect openwebrx --format '{{.State.Status}} | {{.State.Health.Status}}' 2>/dev/null");

    echo "\n===== LIVE LOGS =====\n";
    echo runCmd("docker logs --tail 200 openwebrx 2>&1");

    echo "\n===== SYSTEM STATUS =====\n";
    echo runCmd("systemctl status openwebrx --no-pager 2>/dev/null");
}

?>
        </div>
    </div>

</div>

</body>
</html>

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
    background:#0e1117;
    color:#fff;
}

/* PANEL */
.panel {
    background:#161b22;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
}

/* TOP BAR */
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

/* BOTONES */
.btn {
    border-radius:8px;
    font-size:0.85rem;
}

/* TERMINAL */
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
    box-shadow: inset 0 0 10px rgba(0,255,100,0.1);
}

/* ESTADOS */
.status-ok { color: lime; font-weight:bold; }
.status-bad { color: red; font-weight:bold; }

.spacer {
    flex-grow:1;
}
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

    <!-- TERMINAL -->
    <div class="panel">
        <h5>📟 Logs OpenWebRX (auto-scroll)</h5>

        <div id="terminal" class="terminal">
<?php

echo "===== ESTADO DOCKER =====\n";
echo runCmd("docker ps -a --filter name=openwebrx");

echo "\n===== LOGS OPENWEBRX =====\n";
echo runCmd("docker logs --tail 120 openwebrx 2>&1");

if ($output != "") {
    echo "\n===== ACCIONES =====\n";
    echo $output;
}

?>
        </div>
    </div>

</div>

<!-- AUTO SCROLL SCRIPT -->
<script>
const terminal = document.getElementById("terminal");

// baja automáticamente al cargar
terminal.scrollTop = terminal.scrollHeight;

// si el contenido cambia, sigue bajando
setInterval(() => {
    terminal.scrollTop = terminal.scrollHeight;
}, 1000);
</script>

</body>
</html>

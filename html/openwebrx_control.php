<?php

function runCmd($cmd) {
    return shell_exec($cmd . " 2>&1");
}

$output = "";

/* =========================
   ACCIONES
========================= */
if (isset($_GET['action'])) {

    $action = $_GET['action'];

    $output .= "==================================================\n";
    $output .= "🧹 ACCIÓN: $action\n";
    $output .= "==================================================\n\n";

    if ($action == "start") {

        $output .= "▶ START OpenWebRX (SAFE MODE)\n\n";

        // aseguramos que docker no lo reinicie solo
        $output .= runCmd("docker update --restart=no openwebrx");

        // arrancamos contenedor
        $output .= runCmd("docker start openwebrx");
    }

    if ($action == "stop") {

        $output .= "⏹ STOP OpenWebRX (NO AUTO RESTART)\n\n";

        // quitamos cualquier auto-restart
        $output .= runCmd("docker update --restart=no openwebrx");

        // paramos contenedor
        $output .= runCmd("docker stop -t 10 openwebrx");
    }

    if ($action == "restart") {

        $output .= "🔄 RESTART OpenWebRX (SAFE)\n\n";

        $output .= runCmd("docker update --restart=no openwebrx");
        $output .= runCmd("docker restart openwebrx");
    }

    if ($action == "lock") {

        $output .= "🔒 FULL LOCK (NO AUTO START EVER)\n\n";

        // asegura Docker sin auto restart
        $output .= runCmd("docker update --restart=no openwebrx");

        // stop duro
        $output .= runCmd("docker stop -t 10 openwebrx");

        $output .= "\n✔ LOCK APPLIED (systemd already disabled)\n";
    }

    $output .= "\n==================================================\n";
    $output .= "✔ ACCIÓN TERMINADA\n";
    $output .= "==================================================\n\n";
}

/* =========================
   ESTADO
========================= */
$status = trim(runCmd("docker ps -q -f name=openwebrx"));
$isRunning = ($status != "");

/* restart policy */
$autostart = trim(runCmd("docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' openwebrx"));
$isEnabled = ($autostart == "unless-stopped");

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

.panel {
    background:#161b22;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
}

.topbar {
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.title {
    font-size:18px;
    font-weight:bold;
}

.btn {
    font-size:0.8rem;
}

.terminal {
    background:#000;
    color:#00ff66;
    padding:15px;
    height:70vh;
    overflow-y:auto;
    font-family: monospace;
    font-size: 13px;
    border-radius:10px;
    white-space: pre-wrap;
}

.status-ok { color: lime; font-weight:bold; }
.status-bad { color: red; font-weight:bold; }
</style>
</head>

<body>

<div class="container py-4">

    <div class="panel">
        <div class="topbar">

            <div class="title">📡 OpenWebRX CONTROL (SAFE MODE)</div>

            <span>
                Docker:
                <?php if ($isRunning): ?>
                    <span class="status-ok">🟢 RUNNING</span>
                <?php else: ?>
                    <span class="status-bad">🔴 STOPPED</span>
                <?php endif; ?>
            </span>

            <span>
                Restart:
                <b><?= htmlspecialchars($autostart) ?></b>
            </span>

            <a class="btn btn-success" href="?action=start">▶ START</a>
            <a class="btn btn-danger" href="?action=stop">⏹ STOP</a>
            <a class="btn btn-warning" href="?action=restart">🔄 RESTART</a>
            <a class="btn btn-dark" href="?action=lock">🔒 LOCK</a>

        </div>
    </div>

    <div class="panel">
        <h5>📟 STATUS</h5>

        <div class="terminal">
<?php

if ($output != "") {
    echo $output;
}

echo "\n================ DOCKER STATUS ================\n";
echo runCmd("docker ps -a --filter name=openwebrx");

echo "\n================ CONTAINER INFO ================\n";
echo runCmd("docker inspect openwebrx --format 'Estado: {{.State.Status}} | Restart: {{.HostConfig.RestartPolicy.Name}}' 2>/dev/null");

echo "\n================ LOGS ================\n";
echo runCmd("docker logs --tail 80 openwebrx 2>&1");

?>
        </div>
    </div>

</div>

</body>
</html>

<?php

function runCmd($cmd) {
    return shell_exec($cmd . " 2>&1");
}

// ACCIONES
if (isset($_GET['action'])) {

    if ($_GET['action'] == "start") {
        runCmd("docker start openwebrx");
    }

    if ($_GET['action'] == "stop") {
        runCmd("docker stop openwebrx");
    }

    if ($_GET['action'] == "restart") {
        runCmd("docker restart openwebrx");
    }
}

// ESTADO
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
    background:#1a1a1a;
    color:white;
}

.card {
    background:#2c2c2c;
    border:none;
}

.status-running { color: lime; font-weight:bold; }
.status-stopped { color: red; font-weight:bold; }
</style>

</head>

<body>

<div class="container py-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>📡 OpenWebRX Control</h2>

        <a href="mmdvm.php" class="btn btn-outline-light btn-sm">
            🔙 Panel PHPPlus
        </a>

    </div>

    <!-- STATUS -->
    <div class="card p-3 mb-3">
        <h5>Estado del servicio:</h5>

        <?php if ($isRunning): ?>
            <span class="status-running">RUNNING</span>
        <?php else: ?>
            <span class="status-stopped">STOPPED</span>
        <?php endif; ?>

    </div>

    <!-- BOTONES -->
    <div class="card p-3">

        <a href="?action=start" class="btn btn-success m-1">▶ START</a>
        <a href="?action=stop" class="btn btn-danger m-1">⏹ STOP</a>
        <a href="?action=restart" class="btn btn-warning m-1">🔄 RESTART</a>

        <a href="http://localhost:8073" target="_blank" class="btn btn-info m-1">
            🌐 Abrir OpenWebRX
        </a>

    </div>

</div>

</body>
</html>

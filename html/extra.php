<?php
// ── Acción AJAX: restaurar imagen de fábrica ─────────────────────────────────
if (isset($_POST['accion']) && $_POST['accion'] === 'restaurar_fabrica') {
    header('Content-Type: application/json');
    $scripts = [
        '/home/pi/A108/crear_fabrica.sh',
        '/home/pi/A108/restaurar_de_fabrica.sh',
    ];
    $salida  = [];
    $ok      = true;
    foreach ($scripts as $script) {
        if (!file_exists($script)) {
            $salida[] = "ERROR: No existe $script";
            $ok = false;
            break;
        }
        $cmd    = "bash " . escapeshellarg($script) . " 2>&1";
        $output = shell_exec($cmd);
        $salida[] = "▶ " . basename($script) . "\n" . trim($output);
        if (strpos($output, 'ERROR:') !== false) {
            $ok = false;
            break;
        }
    }
    echo json_encode([
        'ok'     => $ok,
        'output' => implode("\n\n", $salida),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENU EXTRA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background: #1a1a1f;
            background-image: 
                radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.03) 1px, transparent 0);
            background-size: 40px 40px;
        }

        .navbar-granate {
            background-color: #6b0f1a;
            min-height: 60px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .navbar-granate .nav-link {
            color: #fff;
            font-size: 0.85rem;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .navbar-granate .nav-link:hover {
            color: #ffcccc;
        }

        .navbar-granate .navbar-brand {
            padding-top: 0;
            padding-bottom: 0;
        }

        .navbar-granate .navbar-brand img {
            height: 45px;
        }

        /* ═══ TARJETAS ELEGANTES ═══ */
        .card-link {
            text-decoration: none;
            display: block;
            height: 100%;
        }

        .card {
            background: rgba(45, 45, 55, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-left: 4px solid var(--card-color, #6c757d);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            height: 162px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Efecto de brillo en el borde */
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(
                180deg,
                transparent,
                var(--card-color, #6c757d),
                transparent
            );
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .card:hover::before {
            opacity: 1;
            animation: borderGlow 2s ease-in-out infinite;
        }

        @keyframes borderGlow {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .card:hover {
            transform: translateY(-6px);
            background: rgba(55, 55, 65, 0.7);
            border-left-color: var(--card-color, #6c757d);
            box-shadow: 
                0 10px 30px rgba(0, 0, 0, 0.5),
                0 0 30px var(--card-color, transparent);
        }

        .card-body {
            padding: 1.25rem 1.25rem 1rem 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0;
            flex-shrink: 0;
            letter-spacing: 0.02em;
            color: #fff;
        }

        .card-title i {
            font-size: 1.4rem;
            filter: drop-shadow(0 0 8px var(--card-color, #6c757d));
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            animation: iconPulse 3s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { 
                filter: drop-shadow(0 0 8px var(--card-color, #6c757d));
                transform: scale(1);
            }
            50% { 
                filter: drop-shadow(0 0 15px var(--card-color, #6c757d));
                transform: scale(1.05);
            }
        }

        .card:hover .card-title i {
            animation: iconHover 0.6s ease-in-out;
        }

        @keyframes iconHover {
            0% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1.25) rotate(5deg); }
        }

        .card-divider {
            height: 1px;
            background: linear-gradient(90deg, var(--card-color, #6c757d), transparent);
            opacity: 0.3;
            margin: 0.6rem 0;
            flex-shrink: 0;
            transition: opacity 0.4s ease;
        }

        .card:hover .card-divider {
            opacity: 0.6;
        }

        .card-text {
            font-size: 0.9rem;
            line-height: 1.45;
            margin-bottom: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            flex-grow: 1;
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.4s ease;
        }

        .card:hover .card-text {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Colores por tarjeta */
        .card-dump1090 { --card-color: #00ff15; }
        .card-ambe { --card-color: #ff4dff; }
        .card-radarbox { --card-color: #ff6600; }
        .card-fr24 { --card-color: #ffcc00; }
        .card-radiosonde { --card-color: #66ffcc; }
        .card-ais { --card-color: #00d4ff; }
        .card-svxlink { --card-color: #00d4ff; }
        .card-bluetooth { --card-color: #00d4ff; }
        .card-esp32 { --card-color: #00ffff; }
        .card-fusion { --card-color: #ff3b3b; }
        .card-openwebrx { --card-color: #00ff99; }
        .card-limpieza { --card-color: #ff6666; }
        .card-analisis { --card-color: #00e5ff; }
        .card-seguridad { --card-color: #ff6600; }
        .card-fabrica { --card-color: #ffaa00; }

        /* Animación de entrada escalonada */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .col-12 {
            animation: fadeInScale 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
        }

        .col-12:nth-child(1) { animation-delay: 0.05s; }
        .col-12:nth-child(2) { animation-delay: 0.1s; }
        .col-12:nth-child(3) { animation-delay: 0.15s; }
        .col-12:nth-child(4) { animation-delay: 0.2s; }
        .col-12:nth-child(5) { animation-delay: 0.25s; }
        .col-12:nth-child(6) { animation-delay: 0.3s; }
        .col-12:nth-child(7) { animation-delay: 0.35s; }
        .col-12:nth-child(8) { animation-delay: 0.4s; }
        .col-12:nth-child(9) { animation-delay: 0.45s; }
        .col-12:nth-child(10) { animation-delay: 0.5s; }
        .col-12:nth-child(11) { animation-delay: 0.55s; }
        .col-12:nth-child(12) { animation-delay: 0.6s; }
        .col-12:nth-child(13) { animation-delay: 0.65s; }
        .col-12:nth-child(14) { animation-delay: 0.7s; }
        .col-12:nth-child(15) { animation-delay: 0.75s; }
        .col-12:nth-child(16) { animation-delay: 0.8s; }
        .col-12:nth-child(17) { animation-delay: 0.85s; }

        #fabrica-output {
            background: #111;
            color: #00ff99;
            font-family: monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
            border-radius: 6px;
            padding: 10px;
        }
    </style>
</head>

<body class="bg-dark text-white">

<!-- HEADER -->
<nav class="navbar navbar-expand-md navbar-granate">
    <div class="container">
        <a class="navbar-brand" target="_blank" href="http://rem-esp.es">
          <img src="Logo_REM-ESP_EA4RCR.png" alt="Logo">
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>

        <a href="mmdvm.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
        </a>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="container py-4">

    <h1 class="mb-4 text-center">
        <i class="bi bi-grid-3x3-gap-fill me-2" style="color: #ff6600;"></i>
        🍊&nbsp;MENU EXTRA
    </h1>

    <div class="row g-3 justify-content-start">

        <!-- DUMP1090 CONTROL -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/dump1090.php" class="card-link">
                <div class="card card-dump1090">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-airplane-fill me-2" style="color: #00ff15;"></i>Dump1090 Control
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Lanzador configurador Dump1090
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- DUMP1090 MONITOR -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/dump1090monitor.php" class="card-link">
                <div class="card card-dump1090">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-airplane-fill me-2" style="color: #00ff15;"></i>Dump1090 Monitor
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Seguimiento de aeronaves en tiempo real
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- AMBE SERVER -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/ambeserver.php" class="card-link">
                <div class="card card-ambe">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-cpu-fill me-2" style="color:#ff4dff;"></i>AMBE SERVER
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Servidor AMBE · Control de voz digital DMR
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- RADARBOX -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/radarbox.php" class="card-link">
                <div class="card card-radarbox">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-airplane-engines-fill me-2" style="color:#ff6600;"></i>RADARBOX
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Feeder Radarbox · Tracking ADS-B global.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- FLIGHTRADAR24 -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/flightradar.php" class="card-link">
                <div class="card card-fr24">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-airplane-engines-fill me-2" style="color:#ffcc00;"></i>FLIGHTRADAR24
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Feeder FR24 · Seguimiento de vuelos en tiempo real.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- RADIOSONDE (AUTO_RX) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/auto_rx.php" class="card-link">
                <div class="card card-radiosonde">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-balloon-fill me-2" style="color:#66ffcc;"></i>RADIOSONDE
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Seguimiento de sondas meteorológicas en tiempo real.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- AIS / SHIP EXPLORER -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/sxfeeder.php" class="card-link">
                <div class="card card-ais">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-water me-2" style="color:#00d4ff;"></i>
                            AIS / Ship Explorer
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Monitorización AIS · Tráfico marítimo en tiempo real · barcos y rutas
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- SVXLINK -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/svxlink.php" class="card-link">
                <div class="card card-svxlink">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-broadcast me-2" style="color:#00d4ff;"></i>SVXLINK
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Control de repetidor · EchoLink · Configuración y logs
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- BLUETOOTH -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/bluetooth.php" class="card-link">
                <div class="card card-bluetooth">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-bluetooth me-2" style="color:#00d4ff;"></i>Bluetooth
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Gestión de dispositivos Bluetooth
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- PROGRAMADOR ESP32 -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/esp32.php" class="card-link">
                <div class="card card-esp32">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-cpu me-2" style="color:#00ffff;"></i>Programador ESP32
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Grabador de Firmware para módulos ESP32 vía WebSerial
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- FUSION 2X -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/fusion2x.php" class="card-link">
                <div class="card card-fusion">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-broadcast-pin me-2" style="color:#ff3b3b;"></i>Fusion 2X
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Servidor Fusion 2X · Interfaz web en tiempo real para equipos Yaesu
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- OPENWEBRX -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/openwebrx_control.php" class="card-link">
                <div class="card card-openwebrx">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-broadcast me-2" style="color:#00ff99;"></i>OpenWebRX
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Receptor SDR en tiempo real · Web interface para RTL-SDR y decodificación digital.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- LIMPIEZA DEL SISTEMA -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/limpieza.php" class="card-link">
                <div class="card card-limpieza">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-trash3-fill me-2" style="color:#ff6666;"></i>
                            Limpieza del sistema
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Limpieza de logs, temporales y mantenimiento básico del sistema para liberar espacio.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- ANALISIS.PHP -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/analisis.php" class="card-link">
                <div class="card card-analisis">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-speedometer2 me-2" style="color:#00e5ff;"></i>Análisis Servicios
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Panel de monitoreo · CPU/RAM/Disco · Control de servicios con interruptores
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- SEGURIDAD / CAMBIO DE CONTRASEÑAS -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="/changepassword.php" class="card-link">
                <div class="card card-seguridad">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-shield-lock-fill me-2" style="color:#ff6600;"></i>Seguridad
                        </h5>
                        <div class="card-divider"></div>
                        <p class="card-text">
                            Cambio de contraseñas · Gestión segura de usuarios pi y root
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- RESTAURAR IMAGEN DE FÁBRICA -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card card-fabrica" onclick="confirmarFabrica()">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-arrow-counterclockwise me-2" style="color:#ffaa00;"></i>
                        Restaurar de fábrica
                    </h5>
                    <div class="card-divider"></div>
                    <p class="card-text">
                        Restaura la imagen y borra todos los parámetros de usuario dejándola como cuando la descargas.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ─ MODAL CONFIRMACIÓN ─────────────────────────────────────────────────── -->
<div class="modal fade" id="modalConfirm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-warning">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>¿Restaurar imagen de fábrica?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-white-50 small">
                Se ejecutarán en orden:<br>
                <code class="text-warning">1. crear_fabrica.sh</code><br>
                <code class="text-warning">2. restaurar_de_fabrica.sh</code><br><br>
                Los ficheros de configuración actuales serán sobreescritos. ¿Continuar?
            </div>
            <div class="modal-footer border-warning">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning btn-sm fw-bold text-dark" onclick="ejecutarFabrica()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Sí, restaurar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL RESULTADO ────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalResultado" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border" id="modalResultadoBorder">
            <div class="modal-header">
                <h5 class="modal-title" id="modalResultadoTitulo"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fabrica-output"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmarFabrica() {
    new bootstrap.Modal(document.getElementById('modalConfirm')).show();
}

function ejecutarFabrica() {
    bootstrap.Modal.getInstance(document.getElementById('modalConfirm')).hide();

    const output   = document.getElementById('fabrica-output');
    const titulo   = document.getElementById('modalResultadoTitulo');
    const border   = document.getElementById('modalResultadoBorder');

    output.textContent = '⏳ Ejecutando scripts, espera...';
    titulo.innerHTML   = '<i class="bi bi-hourglass-split me-2"></i>Procesando...';
    titulo.className   = 'modal-title text-warning';
    border.className   = 'modal-content bg-dark border border-warning';

    new bootstrap.Modal(document.getElementById('modalResultado')).show();

    const fd = new FormData();
    fd.append('accion', 'restaurar_fabrica');

    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            output.textContent = data.output;
            if (data.ok) {
                titulo.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Restauración completada';
                titulo.className = 'modal-title text-success';
                border.className = 'modal-content bg-dark border border-success';
            } else {
                titulo.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Error en la restauración';
                titulo.className = 'modal-title text-danger';
                border.className = 'modal-content bg-dark border border-danger';
            }
        })
        .catch(err => {
            output.textContent = 'Error de comunicación: ' + err;
            titulo.innerHTML   = '<i class="bi bi-x-circle-fill me-2"></i>Error';
            titulo.className   = 'modal-title text-danger';
            border.className   = 'modal-content bg-dark border border-danger';
        });
}
</script>
</body>
</html>

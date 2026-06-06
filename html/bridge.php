<?php
// ═══ VERIFICACIÓN DE ESTADO DE BRIDGES (Método robusto por PID) ═══
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    
    function isProcessRunningByPid($pidFile, $expectedString) {
        if (!file_exists($pidFile)) return false;
        $pid = trim(file_get_contents($pidFile));
        if (!is_numeric($pid) || $pid <= 0) return false;
        
        // Leemos la línea de comandos real del proceso
        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline === false) return false;
        
        // Los argumentos en cmdline están separados por bytes nulos, los cambiamos a espacios
        $cmdline = str_replace("\0", " ", $cmdline);
        
        // Verificamos si el nombre del ejecutable o su configuración está en la línea de comandos
        return strpos($cmdline, $expectedString) !== false;
    }
    
    $status = [
        'dmr2ysf' => (
            isProcessRunningByPid('/tmp/MMDVMDMR2YSF.pid', 'MMDVMDMR2YSF') &&
            isProcessRunningByPid('/tmp/DMR2YSF.pid', 'DMR2YSF') &&
            isProcessRunningByPid('/tmp/YSFGateway.pid', 'YSFGateway')
        ),
        'ysf2dmr' => (
            isProcessRunningByPid('/tmp/MMDVMYSF2DMR.pid', 'MMDVMYSF2DMR') &&
            isProcessRunningByPid('/tmp/YSF2DMR.pid', 'YSF2DMR')
        ),
        'dmr2nxdn' => (
            isProcessRunningByPid('/tmp/MMDVMDMR2NXDN.pid', 'MMDVMDMR2NXDN') &&
            isProcessRunningByPid('/tmp/DMR2NXDN.pid', 'DMR2NXDN') &&
            isProcessRunningByPid('/tmp/NXDNGateway.pid', 'NXDNGateway')
        )
    ];
    
    echo json_encode($status);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRIDGES</title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23C0C0C0' viewBox='0 0 16 16'%3E%3Cpath d='M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z'/%3E%3Cpath d='M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z'/%3E%3C/svg%3E">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark: #0a0e14;
            --bg-surface: #111720;
            --border: #1e2d3d;
            --cyan: #00d4ff;
            --red: #ff3b3b;
            --orange: #ffa500;
            --granate: #6b0f1a;
            --granate-light: #8b1a2a;
            --text: #a8b9cc;
            --text-dim: #4a5568;
            --green: #00ff9f;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
            margin: 0;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(ellipse at top, rgba(107, 15, 26, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at bottom, rgba(0, 212, 255, 0.08) 0%, transparent 50%),
                var(--bg-dark);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 50%; left: 50%;
            width: 800px; height: 800px;
            transform: translate(-50%, -50%);
            background-image: 
                radial-gradient(circle at center, transparent 0%, transparent 40px, rgba(0, 212, 255, 0.03) 40px, rgba(0, 212, 255, 0.03) 41px, transparent 41px, transparent 80px, rgba(0, 212, 255, 0.03) 80px, rgba(0, 212, 255, 0.03) 81px, transparent 81px, transparent 120px, rgba(0, 212, 255, 0.03) 120px, rgba(0, 212, 255, 0.03) 121px, transparent 121px, transparent 160px, rgba(0, 212, 255, 0.03) 160px, rgba(0, 212, 255, 0.03) 161px, transparent 161px, transparent 200px, rgba(0, 212, 255, 0.03) 200px, rgba(0, 212, 255, 0.03) 201px, transparent 201px);
            animation: pulseWaves 8s ease-in-out infinite;
            z-index: -1;
            pointer-events: none;
        }

        @keyframes pulseWaves {
            0%, 100% { opacity: 0.4; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.8; transform: translate(-50%, -50%) scale(1.1); }
        }

        .navbar-granate {
            background: linear-gradient(135deg, var(--granate) 0%, var(--granate-light) 100%);
            min-height: 60px;
            padding-top: 0; padding-bottom: 0;
            box-shadow: 0 2px 20px rgba(107, 15, 26, 0.5);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .navbar-granate::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 204, 204, 0.5), transparent);
        }

        .navbar-granate .navbar-brand { padding-top: 0; padding-bottom: 0; }
        .navbar-granate .navbar-brand img { height: 45px; transition: transform 0.3s; }
        .navbar-granate .navbar-brand img:hover { transform: scale(1.05); }

        .btn-panel {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
            letter-spacing: 0.05em;
            transition: all 0.3s;
        }

        .btn-panel:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .title-wrapper {
            text-align: center;
            margin: 4rem 0 3.5rem; /* ⬅️ antes 3rem, ahora 2rem para subir el conjunto */
            position: relative;
        }

        .title-antenna {
            position: relative;
            display: inline-block;
            margin-bottom: 2.0rem; /* ️ antes 1rem, ahora casi pegado al título */
        }

        @keyframes antennaPulse {
            0%, 100% { filter: drop-shadow(0 0 20px rgba(0, 212, 255, 0.6)); }
            50% { filter: drop-shadow(0 0 35px rgba(0, 212, 255, 0.9)); }
        }

        .title-antenna::before, .title-antenna::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            border: 2px solid var(--cyan);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
        }

        .title-antenna::before { width: 80px; height: 80px; animation: waveExpand 2s ease-out infinite; }
        .title-antenna::after { width: 80px; height: 80px; animation: waveExpand 2s ease-out infinite 1s; }

        @keyframes waveExpand {
            0% { width: 60px; height: 60px; opacity: 0.8; }
            100% { width: 160px; height: 160px; opacity: 0; }
        }

        .title-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem;
            font-weight: 900;
            letter-spacing: 0.3em;
            background: linear-gradient(135deg, #fff 0%, var(--cyan) 50%, #fff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 40px rgba(0, 212, 255, 0.3);
            margin: 0;
            margin-top: 3rem; /* ️ NUEVO: baja el título para que no lo toquen las ondas */
        }

        .title-sub {
            font-family: 'Rajdhani', sans-serif;
            font-size: 1rem;
            color: var(--text-dim);
            letter-spacing: 0.4em;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        .bridge-card {
            background: linear-gradient(135deg, var(--bg-surface) 0%, rgba(17, 23, 32, 0.8) 100%);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
        }

        .bridge-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--card-color, var(--cyan)), transparent);
            opacity: 0.7;
        }

        .bridge-card::after {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle at center, var(--card-color, var(--cyan)) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
            pointer-events: none;
        }

        .bridge-card:hover {
            transform: translateY(-8px);
            border-color: var(--card-color, var(--cyan));
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5), 0 0 30px color-mix(in srgb, var(--card-color, var(--cyan)) 30%, transparent);
        }

        .bridge-card:hover::after { opacity: 0.05; }

        .bridge-card.dmr2ysf { --card-color: var(--cyan); }
        .bridge-card.ysf2dmr { --card-color: var(--red); }
        .bridge-card.dmr2nxdn { --card-color: var(--orange); }

        .bridge-icon {
            width: 80px; height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border: 1px solid var(--card-color, var(--cyan));
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.5rem;
            position: relative;
            transition: all 0.4s;
        }

        .bridge-icon i {
            font-size: 2.5rem;
            color: var(--card-color, var(--cyan));
            filter: drop-shadow(0 0 10px var(--card-color, var(--cyan)));
            transition: all 0.4s;
        }

        .bridge-card:hover .bridge-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 0 25px color-mix(in srgb, var(--card-color, var(--cyan)) 40%, transparent);
        }

        .bridge-card:hover .bridge-icon i {
            filter: drop-shadow(0 0 20px var(--card-color, var(--cyan)));
        }

        .bridge-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
            letter-spacing: 0.1em;
        }

        .bridge-subtitle {
            font-family: 'Rajdhani', sans-serif;
            font-size: 0.9rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .bridge-desc {
            color: var(--text);
            font-size: 1rem;
            line-height: 1.6;
            flex-grow: 1;
            margin-bottom: 1.5rem;
        }

        .bridge-desc strong { color: var(--card-color, var(--cyan)); font-weight: 700; }

        .btn-bridge {
            background: transparent;
            border: 2px solid var(--card-color, var(--cyan));
            color: var(--card-color, var(--cyan));
            font-family: 'Orbitron', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-transform: uppercase;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-bridge::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: var(--card-color, var(--cyan));
            transition: left 0.3s;
            z-index: -1;
        }

        .btn-bridge:hover {
            color: var(--bg-dark);
            border-color: var(--card-color, var(--cyan));
            box-shadow: 0 0 25px color-mix(in srgb, var(--card-color, var(--cyan)) 50%, transparent);
        }

        .btn-bridge:hover::before { left: 0; }

        .status-badge {
            position: absolute;
            top: 1.5rem; right: 1.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s ease;
        }

        .status-badge::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
        }

        .status-badge.active {
            background: rgba(0, 255, 159, 0.15);
            color: var(--green);
            border: 1px solid rgba(0, 255, 159, 0.3);
        }

        .status-badge.active::before {
            background: var(--green);
            animation: statusPulse 2s infinite;
        }

        @keyframes statusPulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 5px var(--green); }
            50% { opacity: 0.5; box-shadow: 0 0 10px var(--green); }
        }

        .status-badge.inactive {
            background: rgba(255, 59, 59, 0.1);
            color: var(--red);
            border: 1px solid rgba(255, 59, 59, 0.2);
        }

        .status-badge.inactive::before {
            background: var(--red);
            opacity: 0.6;
        }

        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-dim);
            font-family: 'Rajdhani', sans-serif;
            font-size: 0.85rem;
            letter-spacing: 0.1em;
            border-top: 1px solid var(--border);
            margin-top: 4rem;
        }

        .footer a { color: var(--cyan); text-decoration: none; transition: all 0.3s; }
        .footer a:hover { text-shadow: 0 0 10px var(--cyan); }

        @media (max-width: 768px) {
            .title-text { font-size: 2rem; letter-spacing: 0.2em; }
            .title-antenna i { font-size: 2.5rem; }
            .bridge-card { padding: 1.5rem; }
            .bridge-title { font-size: 1.2rem; }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-md navbar-granate">
    <div class="container">
        <a class="navbar-brand" target="_blank" href="http://rem-esp.es">
          <img src="Logo_REM-ESP_EA4RCR.png" alt="Logo">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        <a href="mmdvm.php" class="btn btn-panel btn-sm">
            <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
        </a>
    </div>
</nav>

<div class="container py-4">
    <div class="title-wrapper">
        <div class="title-antenna">
            <i class="bi bi-broadcast"></i>
        </div>
        <h1 class="title-text">BRIDGES</h1>
        <div class="title-sub">Transcoding · Digital Voice · Multi-Protocol</div>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="bridge-card dmr2ysf">
                <span class="status-badge inactive" id="badge-dmr2ysf">INACTIVO</span>
                <div class="bridge-icon"><i class="bi bi-broadcast-pin"></i></div>
                <h3 class="bridge-title">DMR2YSF</h3>
                <div class="bridge-subtitle">DMR ↔ System Fusion</div>
                <p class="bridge-desc">Puente bidireccional entre la red <strong>DMR</strong> y <strong>YSF (Yaesu System Fusion)</strong>. Permite la comunicación entre ambos modos digitales.</p>
                <a href="/dmr2ysf.php" class="btn-bridge"><i class="bi bi-box-arrow-up-right"></i> Acceder</a>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="bridge-card ysf2dmr">
                <span class="status-badge inactive" id="badge-ysf2dmr">INACTIVO</span>
                <div class="bridge-icon"><i class="bi bi-broadcast-pin"></i></div>
                <h3 class="bridge-title">YSF2DMR</h3>
                <div class="bridge-subtitle">System Fusion ↔ DMR</div>
                <p class="bridge-desc">Puente bidireccional entre <strong>YSF (Yaesu System Fusion)</strong> y la red <strong>DMR</strong>. Conecta reflectores Fusion con talkgroups DMR.</p>
                <a href="/ysf2dmr.php" class="btn-bridge"><i class="bi bi-box-arrow-up-right"></i> Acceder</a>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="bridge-card dmr2nxdn">
                <span class="status-badge inactive" id="badge-dmr2nxdn">INACTIVO</span>
                <div class="bridge-icon"><i class="bi bi-broadcast-pin"></i></div>
                <h3 class="bridge-title">DMR2NXDN</h3>
                <div class="bridge-subtitle">DMR ↔ NXDN</div>
                <p class="bridge-desc">Conversor directo entre <strong>DMR</strong> y <strong>NXDN</strong>. Permite enlazar con reflectores NXDN de todo el mundo de forma sencilla.</p>
                <a href="/dmr2nxdn.php" class="btn-bridge"><i class="bi bi-box-arrow-up-right"></i> Acceder</a>
            </div>
        </div>
    </div>
</div>

<div class="footer">
    <i class="bi bi-radio me-2"></i>
    Bridge Cards REM 2026 | <a href="http://rem-esp.es" target="_blank">rem-esp.es</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    async function checkStatus() {
        try {
            const response = await fetch('?action=check_status');
            const data = await response.json();
            
            const bridges = ['dmr2ysf', 'ysf2dmr', 'dmr2nxdn'];
            bridges.forEach(bridge => {
                const badge = document.getElementById(`badge-${bridge}`);
                if (data[bridge]) {
                    badge.className = 'status-badge active';
                    badge.textContent = 'ACTIVO';
                } else {
                    badge.className = 'status-badge inactive';
                    badge.textContent = 'INACTIVO';
                }
            });
        } catch (error) {
            console.error('Error al comprobar estado:', error);
        }
    }

    // Comprobar al cargar y luego cada 3 segundos (3000 ms)
    checkStatus();
    setInterval(checkStatus, 3000);
</script>
</body>
</html>

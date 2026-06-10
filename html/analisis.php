<?php
date_default_timezone_set('Europe/Madrid');
$systemdPath = '/etc/systemd/system/';

$ignoredServices = [
    'dbus', 'systemd', 'getty', 'apt', 'cron', 'rsyslog', 'ssh',
    'apache2', 'nginx', 'mysql', 'mariadb', 'redis', 'network',
    'wpa', 'cups', 'snap', 'ufw', 'polkit'
];

$services = [];
$files = glob($systemdPath . '*.service');

foreach ($files as $file) {
    $service = basename($file, '.service');
    $skip = false;
    foreach ($ignoredServices as $ignore) {
        if (stripos($service, $ignore) !== false) { $skip = true; break; }
    }
    if (!$skip) $services[] = $service;
}
sort($services);

function serviceStatus($service) {
    $status = trim(shell_exec("sudo systemctl is-active " . escapeshellarg($service) . " 2>&1"));
    return ($status === 'active');
}

function serviceEnabled($service) {
    $status = trim(shell_exec("sudo systemctl is-enabled " . escapeshellarg($service) . " 2>&1"));
    return ($status === 'enabled');
}

function serviceAction($service, $action) {
    $allowed = ['start', 'stop', 'restart', 'enable', 'disable'];
    if (!in_array($action, $allowed)) return false;
    shell_exec("sudo systemctl $action " . escapeshellarg($service));
    return true;
}

function getLogs($service) {
    return shell_exec("sudo journalctl -u " . escapeshellarg($service) . " -n 5 --no-pager 2>&1");
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    header('Content-Type: application/json');
    $service = $_GET['service'] ?? '';
    if (in_array($service, $services)) {
        echo json_encode([
            'service' => $service,
            'running' => serviceStatus($service),
            'enabled' => serviceEnabled($service),
            'logs' => getLogs($service)
        ]);
    } else {
        echo json_encode(['error' => 'Servicio no encontrado']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service = $_POST['service'] ?? '';
    $action = $_POST['action'] ?? '';
    if (in_array($service, $services)) {
        serviceAction($service, $action);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$cpu = round(sys_getloadavg()[0], 2);
$data = file_get_contents('/proc/meminfo');
preg_match('/MemTotal:\s+(\d+)/', $data, $total);
preg_match('/MemAvailable:\s+(\d+)/', $data, $available);
$ram = round((($total[1] - $available[1]) / $total[1]) * 100, 1);
$disk = round(((disk_total_space("/") - disk_free_space("/")) / disk_total_space("/")) * 100, 1);
$tempFile = '/sys/class/thermal/thermal_zone0/temp';
$temp = file_exists($tempFile) ? round(file_get_contents($tempFile) / 1000, 1) : 'N/A';
$uptime = trim(shell_exec("uptime -p"));
$ip = trim(shell_exec("hostname -I | awk '{print \$1}'"));
$host = gethostname();

$rx = $tx = 0;
foreach (file('/proc/net/dev') as $line) {
    if (strpos($line, 'lo:') === false && strpos($line, ':') !== false) {
        $d = preg_split('/\s+/', trim($line));
        $rx += $d[1]; $tx += $d[9];
    }
}
$network = ['rx' => round($rx / 1024 / 1024, 2), 'tx' => round($tx / 1024 / 1024, 2)];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios del Sistema</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container { max-width: 1400px; margin: 0 auto; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #60a5fa, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .header p {
            color: #94a3b8;
            margin-top: 4px;
            font-size: 13px;
        }

        .btn-home {
            background: linear-gradient(135deg, #3b82f6, #10b981);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        /* Stats en una sola fila */
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            gap: 12px;
            margin-bottom: 30px;
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 16px;
            padding: 16px 20px;
        }

        .stat-item {
            flex: 1;
            min-width: 0;
            text-align: center;
            padding: 8px 12px;
            border-right: 1px solid rgba(148, 163, 184, 0.08);
        }

        .stat-item:last-child { border-right: none; }

        .stat-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #f1f5f9;
            letter-spacing: -0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }

        .service-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 20px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #60a5fa, #34d399, #60a5fa);
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .service-card.loaded::before { opacity: 1; }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .service-card:hover {
            transform: translateY(-4px);
            border-color: rgba(96, 165, 250, 0.3);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .service-name {
            font-size: 18px;
            font-weight: 600;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .service-name::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #60a5fa;
            box-shadow: 0 0 10px rgba(96, 165, 250, 0.5);
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge.active {
            background: rgba(52, 211, 153, 0.15);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }

        .status-badge.inactive {
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .control-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        .control-row:last-child { border-bottom: none; }

        .control-label {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
        }

        .toggle {
            position: relative;
            width: 56px;
            height: 28px;
        }

        .toggle input { opacity: 0; width: 0; height: 0; }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #334155;
            border-radius: 28px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(148, 163, 184, 0.2);
        }

        .toggle-slider::before {
            position: absolute;
            content: "";
            width: 20px;
            height: 20px;
            left: 2px;
            bottom: 2px;
            background: linear-gradient(145deg, #94a3b8, #64748b);
            border-radius: 50%;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .toggle input:checked + .toggle-slider {
            background: linear-gradient(135deg, #10b981, #059669);
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .toggle input:checked + .toggle-slider::before {
            transform: translateX(28px);
            background: linear-gradient(145deg, #ffffff, #f1f5f9);
        }

        .toggle-mini {
            width: 44px;
            height: 24px;
        }

        .toggle-mini .toggle-slider {
            background: #334155;
            border-color: rgba(139, 92, 246, 0.2);
        }

        .toggle-mini .toggle-slider::before {
            width: 16px;
            height: 16px;
            background: linear-gradient(145deg, #8b5cf6, #7c3aed);
            box-shadow: 0 2px 6px rgba(139, 92, 246, 0.4);
        }

        .toggle-mini input:checked + .toggle-slider {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
        }

        .toggle-mini input:checked + .toggle-slider::before {
            transform: translateX(20px);
            background: #ffffff;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 11px 18px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-restart {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-logs {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .logs-panel {
            margin-top: 16px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 12px;
            padding: 16px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 11px;
            color: #94a3b8;
            max-height: 180px;
            overflow-y: auto;
            display: none;
            border: 1px solid rgba(148, 163, 184, 0.1);
            line-height: 1.6;
        }

        .logs-panel::-webkit-scrollbar { width: 6px; }
        .logs-panel::-webkit-scrollbar-track { background: rgba(148, 163, 184, 0.1); border-radius: 3px; }
        .logs-panel::-webkit-scrollbar-thumb { background: rgba(96, 165, 250, 0.5); border-radius: 3px; }

        .footer {
            margin-top: 60px;
            text-align: center;
            padding: 30px;
            color: #64748b;
            font-size: 13px;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
        }

        @media (max-width: 1200px) {
            .stats-bar { flex-wrap: wrap; }
            .stat-item { flex: 0 0 calc(25% - 12px); border-right: none; }
        }

        @media (max-width: 768px) {
            .services-grid { grid-template-columns: 1fr; }
            .stat-item { flex: 0 0 calc(33.33% - 12px); }
            .header h1 { font-size: 24px; }
        }

        @media (max-width: 480px) {
            .stat-item { flex: 0 0 calc(50% - 12px); }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Servicios del Sistema</h1>
            <p>Panel de control y monitorización</p>
        </div>
        <a href="mmdvm.php" class="btn-home">🏠 Panel PHPPLUS</a>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-label">CPU</div>
            <div class="stat-value"><?php echo $cpu; ?>%</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">RAM</div>
            <div class="stat-value"><?php echo $ram; ?>%</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Disco</div>
            <div class="stat-value"><?php echo $disk; ?>%</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Temp</div>
            <div class="stat-value"><?php echo $temp; ?>°C</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">RX</div>
            <div class="stat-value"><?php echo $network['rx']; ?> MB</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">TX</div>
            <div class="stat-value"><?php echo $network['tx']; ?> MB</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Host</div>
            <div class="stat-value"><?php echo htmlspecialchars($host); ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-label">IP</div>
            <div class="stat-value"><?php echo htmlspecialchars($ip); ?></div>
        </div>
    </div>

    <div class="services-grid">
        <?php foreach($services as $idx => $service): ?>
        <div class="service-card" id="card-<?php echo $idx; ?>">
            <div class="service-header">
                <div class="service-name"><?php echo htmlspecialchars($service); ?></div>
                <div class="status-badge inactive" id="status-<?php echo $idx; ?>">
                    <div class="status-dot"></div>
                    <span id="status-text-<?php echo $idx; ?>">Cargando</span>
                </div>
            </div>

            <div class="control-row">
                <span class="control-label">Estado del servicio</span>
                <label class="toggle">
                    <input type="checkbox" id="toggle-<?php echo $idx; ?>" 
                           onchange="toggleService(<?php echo $idx; ?>, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="control-row">
                <span class="control-label">Inicio automático</span>
                <label class="toggle toggle-mini">
                    <input type="checkbox" id="enable-<?php echo $idx; ?>" 
                           onchange="toggleEnable(<?php echo $idx; ?>, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="button-group">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="service" value="<?php echo htmlspecialchars($service, ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" class="btn btn-restart">↻ Reiniciar</button>
                </form>
                <button class="btn btn-logs" onclick="toggleLogs('<?php echo htmlspecialchars($service, ENT_QUOTES); ?>')">
                    📋 Logs
                </button>
            </div>

            <div class="logs-panel" id="logs-<?php echo htmlspecialchars($service, ENT_QUOTES); ?>">
                Cargando logs...
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="footer">
        <strong>PHPHPLUS</strong> © <?php echo date('Y'); ?> | Uptime: <?php echo htmlspecialchars($uptime); ?>
    </div>
</div>

<script>
const services = <?php echo json_encode($services); ?>;

async function loadServices() {
    await Promise.all(services.map((svc, idx) => loadService(svc, idx)));
}

async function loadService(service, idx) {
    try {
        const res = await fetch(`?ajax=status&service=${encodeURIComponent(service)}`);
        const data = await res.json();
        
        const badge = document.getElementById(`status-${idx}`);
        const text = document.getElementById(`status-text-${idx}`);
        const toggle = document.getElementById(`toggle-${idx}`);
        const enable = document.getElementById(`enable-${idx}`);
        const logs = document.getElementById(`logs-${service}`);
        
        if (data.running) {
            badge.className = 'status-badge active';
            text.textContent = 'Activo';
        } else {
            badge.className = 'status-badge inactive';
            text.textContent = 'Detenido';
        }
        
        toggle.checked = data.running;
        enable.checked = data.enabled;
        if (logs) logs.textContent = data.logs || 'Sin logs disponibles';
        
        document.getElementById(`card-${idx}`).classList.add('loaded');
    } catch (e) { console.error(e); }
}

function toggleService(idx, enabled) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="service" value="${services[idx]}">
                      <input type="hidden" name="action" value="${enabled ? 'start' : 'stop'}">`;
    document.body.appendChild(form);
    form.submit();
}

function toggleEnable(idx, enabled) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="service" value="${services[idx]}">
                      <input type="hidden" name="action" value="${enabled ? 'enable' : 'disable'}">`;
    document.body.appendChild(form);
    form.submit();
}

function toggleLogs(service) {
    const el = document.getElementById(`logs-${service}`);
    el.style.display = el.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('DOMContentLoaded', loadServices);
setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>

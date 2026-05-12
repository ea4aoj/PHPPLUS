<?php
header('X-Content-Type-Options: nosniff');

$SERVICE = "fusion2x-web.service";

/* ───────────────────────── STATUS ───────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'status') {
    $st  = trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));
    $en  = trim(shell_exec("systemctl is-enabled $SERVICE 2>/dev/null"));
    $pid = trim(shell_exec("systemctl show $SERVICE --property=MainPID --value 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode([
        "status"  => $st,
        "active"  => ($st === "active"),
        "enabled" => ($en === "enabled"),
        "pid"     => $pid
    ]);
    exit;
}

/* ───────────────────────── TOGGLE ───────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $st = trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));

    if ($st === "active") {

        shell_exec("sudo systemctl stop $SERVICE 2>/dev/null");
        shell_exec("sudo systemctl disable $SERVICE 2>/dev/null");

        $msg = "Servicio detenido y autostart desactivado";

    } else {

        shell_exec("sudo systemctl enable $SERVICE 2>/dev/null");
        shell_exec("sudo systemctl start $SERVICE 2>/dev/null");

        $msg = "Servicio iniciado y autostart activado";
    }

    header('Content-Type: application/json');
    echo json_encode(["ok" => true, "msg" => $msg]);
    exit;
}

$ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>⚙ Fusion2X WEB · Control</title>

<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">

<!-- ✅ SOLO AÑADIDO: Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

<style>
:root{
    --bg:#0a0e14;
    --surface:#111720;
    --border:#1e2d3d;
    --cyan:#00d4ff;
    --green:#00ff9f;
    --red:#ff4560;
    --text:#a8b9cc;
    --text-dim:#4a5568;

    --font-mono:'Share Tech Mono', monospace;
    --font-ui:'Rajdhani', sans-serif;
    --font-orb:'Orbitron', monospace;
}

*{box-sizing:border-box;margin:0;padding:0}

body{
    background:var(--bg);
    color:var(--text);
    font-family:var(--font-ui);
}

/* HEADER */
.ex-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:.8rem 1.4rem;
    background:var(--surface);
    border-bottom:1px solid var(--border);
}

.ex-title{
    font-family:var(--font-orb);
    color:var(--cyan);
    font-size:1rem;
    letter-spacing:.08em;
}

.ex-btns{display:flex;gap:.6rem;align-items:center}

/* BOTONES */
.btn-ex{
    font-family:var(--font-mono);
    font-size:.7rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    padding:.3rem .8rem;
    border-radius:4px;
    border:1px solid;
    background:transparent;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
}

.btn-cyan{color:var(--cyan);border-color:var(--cyan)}
.btn-green{color:var(--green);border-color:var(--green)}
.btn-red{color:var(--red);border-color:var(--red)}

.btn-cyan:hover{background:rgba(0,212,255,.1)}
.btn-green:hover{background:rgba(0,255,159,.1)}
.btn-red:hover{background:rgba(255,69,96,.1)}

/* SWITCH */
.sw{
    position:relative;
    width:56px;height:28px;
    cursor:pointer;
}
.sw input{display:none}

.sw-track{
    position:absolute;inset:0;
    border:2px solid var(--red);
    border-radius:2px;
    background:#1a2535;
    transition:.3s;
}

.sw-knob{
    position:absolute;
    top:3px;left:3px;
    width:20px;height:20px;
    background:var(--red);
    transition:.3s;
}

.sw input:checked ~ .sw-track{
    border-color:var(--green);
}

.sw input:checked ~ .sw-knob{
    transform:translateX(28px);
    background:var(--green);
}

/* CONTENEDOR */
.container{
    max-width:520px;
    margin:60px auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:10px;
    padding:25px;
    text-align:center;
}

.status{
    margin:15px 0;
    font-family:var(--font-mono);
}

.on{color:var(--green)}
.off{color:var(--red)}

/* ───────── BOTÓN FUSION ───────── */
.fusion-btn{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;

    width:100%;
    margin-top:14px;
    padding:14px 16px;

    border:1px solid var(--cyan);
    border-radius:8px;

    background:linear-gradient(135deg, rgba(0,212,255,.08), rgba(0,0,0,0));
    text-decoration:none;

    transition:.25s;
}

.fusion-btn:hover{
    background:rgba(0,212,255,.12);
    transform:translateY(-1px);
    box-shadow:0 0 12px rgba(0,212,255,.15);
}

/* ICONOS MATERIAL */
.material-symbols-outlined{
    font-variation-settings:
    'FILL' 0,
    'wght' 400,
    'GRAD' 0,
    'opsz' 24;

    font-size:24px;
    color:var(--cyan);
}

.fusion-icon, .fusion-signal{
    display:flex;
    align-items:center;
}

.fusion-text{
    flex:1;
    text-align:left;
    font-family:var(--font-mono);
}

.fusion-title{
    color:var(--cyan);
    font-size:.75rem;
    letter-spacing:.12em;
}

.fusion-sub{
    font-size:.65rem;
    color:var(--text-dim);
    margin-top:2px;
}
</style>
</head>

<body>

<header class="ex-header">
    <div class="ex-title">⚙ Fusion2X WEB · Control</div>

    <div class="ex-btns">

        <label class="sw">
            <input type="checkbox" id="sw" onchange="toggleService(this)">
            <span class="sw-track"></span>
            <span class="sw-knob"></span>
        </label>

        <a class="btn-ex btn-green" href="mmdvm.php">
            🏠 Panel PHPPLUS
        </a>

    </div>
</header>

<div class="container">

    <h3 style="font-family:var(--font-orb);color:var(--cyan);margin-bottom:10px;">
        Fusion 2X WEB SERVICE
    </h3>

    <div class="status">
        Estado:
        <span id="st" class="off">DESCONOCIDO</span>
    </div>

    <!-- BOTÓN MODIFICADO -->
    <a class="fusion-btn" target="_blank"
       href="http://<?php echo $ip; ?>:8080">

        <div class="fusion-icon">
            <span class="material-symbols-outlined">radio</span>
        </div>

        <div class="fusion-text">
            <div class="fusion-title">FUSION 2X WEB</div>
            <div class="fusion-sub">Emisión / radio en tiempo real</div>
        </div>

        <div class="fusion-signal">
            <span class="material-symbols-outlined">signal_cellular_alt</span>
        </div>

    </a>

</div>

<script>

async function loadStatus(){
    const r = await fetch('?action=status');
    const d = await r.json();

    const st = document.getElementById('st');
    const sw = document.getElementById('sw');

    if(d.active){
        st.textContent = "ACTIVO";
        st.className = "on";
        sw.checked = true;
    } else {
        st.textContent = "DETENIDO";
        st.className = "off";
        sw.checked = false;
    }
}

async function toggleService(){
    await fetch('?action=toggle');
    loadStatus();
}

loadStatus();
setInterval(loadStatus, 5000);

</script>

</body>
</html>

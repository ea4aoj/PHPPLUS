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

/* ───────────────────────── TOGGLE (FIXED) ───────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $st = trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));

    if ($st === "active") {

        // 🔴 STOP + DISABLE
        shell_exec("sudo systemctl stop $SERVICE 2>/dev/null");
        shell_exec("sudo systemctl disable $SERVICE 2>/dev/null");

        $msg = "Servicio detenido y autostart desactivado";

    } else {

        // 🟢 ENABLE + START (IMPORTANTE: persistente tras reboot)
        shell_exec("sudo systemctl enable $SERVICE 2>/dev/null");
        shell_exec("sudo systemctl start $SERVICE 2>/dev/null");

        $msg = "Servicio iniciado y autostart activado";
    }

    header('Content-Type: application/json');
    echo json_encode(["ok" => true, "msg" => $msg]);
    exit;
}

/* ───────────────────────── IP ───────────────────────── */
$ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>⚙ Fusion2X WEB · Control</title>

<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">

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

.big-btn{width:100%;margin-top:12px}

</style>
</head>

<body>

<header class="ex-header">
    <div class="ex-title">⚙ Fusion2X WEB · Control</div>

    <div class="ex-btns">

        <!-- SWITCH -->
        <label class="sw">
            <input type="checkbox" id="sw" onchange="toggleService(this)">
            <span class="sw-track"></span>
            <span class="sw-knob"></span>
        </label>

        <!-- 🏠 PHPPLUS -->
        <a class="btn-ex btn-green" href="mmdvm.php">🏠 Panel PHPPLUS</a>

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

    <!-- WEB -->
    <a class="btn-ex btn-cyan big-btn" target="_blank"
       href="http://<?php echo $ip; ?>:8080">
        Fusion 2x WEB
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

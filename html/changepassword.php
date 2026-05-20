<?php
/**
 * Orange Pi 3 LTS - Cambio de contraseña
 * Usuarios: pi y root
 * Diseño oscuro moderno
 */

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $pi_pass   = trim($_POST["pi_pass"] ?? "");
    $root_pass = trim($_POST["root_pass"] ?? "");

    if (strlen($pi_pass) < 4 || strlen($root_pass) < 4) {

        $message = "<div class='error'>
                        Las contraseñas deben tener mínimo 4 caracteres.
                    </div>";

    } else {

        function changePassword($user, $password) {

            $password = escapeshellarg($password);

            $cmd = "echo {$user}:{$password} | sudo chpasswd 2>&1";

            return shell_exec($cmd);
        }

        changePassword("pi", $pi_pass);
        changePassword("root", $root_pass);

        $message = "<div class='success'>
                        Contraseñas actualizadas correctamente.
                    </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>OrangePi Security</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    background:#0f1117;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    color:white;
    padding:20px;
}

.card{
    width:420px;
    background:#1a1d26;
    border-radius:22px;
    padding:35px;
    box-shadow:
        0 0 30px rgba(0,0,0,0.5),
        0 0 12px rgba(255,140,0,0.12);
}

.topbar{
    display:flex;
    justify-content:flex-start;
    margin-bottom:18px;
}

.back-btn{
    text-decoration:none;
    background:#10131a;
    color:#ff8c00;
    padding:10px 16px;
    border-radius:12px;
    border:1px solid #2d3340;
    transition:0.2s;
    font-size:14px;
    font-weight:bold;
}

.back-btn:hover{
    background:#ff8c00;
    color:white;
    box-shadow:0 0 12px rgba(255,140,0,0.4);
}

h1{
    text-align:center;
    margin-bottom:25px;
    color:#ff8c00;
    font-size:30px;
}

label{
    display:block;
    margin-bottom:8px;
    margin-top:18px;
    color:#cccccc;
    font-size:14px;
}

input{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    background:#10131a;
    color:white;
    font-size:15px;
    outline:none;
    border:1px solid #2d3340;
    transition:0.2s;
}

input:focus{
    border-color:#ff8c00;
    box-shadow:0 0 12px rgba(255,140,0,0.25);
}

button{
    width:100%;
    margin-top:30px;
    padding:15px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg,#ff8c00,#ff5e00);
    color:white;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
    transition:0.2s;
}

button:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 20px rgba(255,140,0,0.4);
}

.success{
    background:#12351f;
    border:1px solid #1fa34a;
    color:#7dffab;
    padding:14px;
    border-radius:14px;
    margin-bottom:20px;
    text-align:center;
}

.error{
    background:#351212;
    border:1px solid #ff4f4f;
    color:#ff9d9d;
    padding:14px;
    border-radius:14px;
    margin-bottom:20px;
    text-align:center;
}

.footer{
    margin-top:22px;
    text-align:center;
    color:#666;
    font-size:12px;
}

</style>

</head>
<body>

<div class="card">

    <div class="topbar">
        <a href="mmdvm.php" class="back-btn">
            ← Volver
        </a>
    </div>

    <h1>OrangePi Security</h1>

    <?= $message ?>

    <form method="POST">

        <label>
            Nueva contraseña para usuario pi
        </label>

        <input
            type="password"
            name="pi_pass"
            required
        >

        <label>
            Nueva contraseña para root
        </label>

        <input
            type="password"
            name="root_pass"
            required
        >

        <button type="submit">
            Actualizar Contraseñas
        </button>

    </form>

    <div class="footer">
        Orange Pi 3 LTS · Panel Seguro
    </div>

</div>

</body>
</html>

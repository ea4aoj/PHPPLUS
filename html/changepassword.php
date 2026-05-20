<?php
$message = "";

function changePasswordExpect($user, $old, $new) {

    $old = escapeshellarg($old);
    $new = escapeshellarg($new);

    $script = <<<EOF
spawn passwd $user
expect "Current*password"
send $old\r
expect "New*password"
send $new\r
expect "Retype*new*password"
send $new\r
expect eof
EOF;

    $file = tempnam(sys_get_temp_dir(), "exp");
    file_put_contents($file, $script);

    $cmd = "expect $file 2>&1";
    $out = shell_exec($cmd);

    unlink($file);

    return $out;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old_pi   = $_POST["old_pi"] ?? "";
    $new_pi   = $_POST["new_pi"] ?? "";
    $rep_pi   = $_POST["rep_pi"] ?? "";

    $old_root = $_POST["old_root"] ?? "";
    $new_root = $_POST["new_root"] ?? "";
    $rep_root = $_POST["rep_root"] ?? "";

    if ($new_pi !== $rep_pi || $new_root !== $rep_root) {
        $message = "<div class='error'>Las nuevas contraseñas no coinciden.</div>";
    } else {

        $out1 = changePasswordExpect("pi", $old_pi, $new_pi);
        $out2 = changePasswordExpect("root", $old_root, $new_root);

        $message = "<div class='success'>Proceso ejecutado. Revisa si hubo errores.</div>";
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
    font-family:Arial;
}

body{
    background:#0f1117;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    color:white;
    padding:20px;
}

.card{
    width:450px;
    background:#1a1d26;
    padding:30px;
    border-radius:20px;
    box-shadow:0 0 30px rgba(0,0,0,0.5);
}

.topbar{
    margin-bottom:15px;
}

.back-btn{
    background:#10131a;
    color:#ff8c00;
    padding:10px 14px;
    border-radius:10px;
    text-decoration:none;
    border:1px solid #2d3340;
}

h1{
    text-align:center;
    color:#ff8c00;
    margin:15px 0 20px;
}

label{
    font-size:13px;
    color:#ccc;
    display:block;
    margin-top:12px;
}

input{
    width:100%;
    padding:12px;
    margin-top:6px;
    border-radius:10px;
    border:1px solid #2d3340;
    background:#10131a;
    color:white;
}

button{
    width:100%;
    margin-top:20px;
    padding:14px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#ff8c00,#ff5e00);
    color:white;
    font-weight:bold;
}

.error{
    background:#351212;
    color:#ff9d9d;
    padding:10px;
    border-radius:10px;
    margin-bottom:10px;
}

.success{
    background:#12351f;
    color:#7dffab;
    padding:10px;
    border-radius:10px;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="card">

<div class="topbar">
    <a class="back-btn" href="mmdvm.php">← Volver</a>
</div>

<h1>OrangePi Security</h1>

<?= $message ?>

<form method="POST">

<h3>Usuario PI</h3>

<label>Contraseña actual</label>
<input type="password" name="old_pi" required>

<label>Nueva contraseña</label>
<input type="password" name="new_pi" required>

<label>Repetir nueva contraseña</label>
<input type="password" name="rep_pi" required>

<hr style="margin:15px 0; border:1px solid #2d3340">

<h3>ROOT</h3>

<label>Contraseña actual</label>
<input type="password" name="old_root" required>

<label>Nueva contraseña</label>
<input type="password" name="new_root" required>

<label>Repetir nueva contraseña</label>
<input type="password" name="rep_root" required>

<button type="submit">Actualizar contraseñas</button>

</form>

</div>

</body>
</html>

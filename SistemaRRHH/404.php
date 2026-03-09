<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Error 404 - SistemaRRHH</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{
    margin:0;
    height:100vh;
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg,#141e30,#243b55);
    overflow:hidden;
    display:flex;
    justify-content:center;
    align-items:center;
    color:white;
}

/* Fondo animado */
.background{
    position:absolute;
    width:100%;
    height:100%;
    background: radial-gradient(circle at 20% 30%, rgba(0,195,255,0.3), transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(140,0,255,0.3), transparent 40%);
    animation: floatBg 10s infinite alternate ease-in-out;
}

@keyframes floatBg{
    from { transform: scale(1); }
    to { transform: scale(1.1); }
}

/* Tarjeta central */
.card{
    position:relative;
    z-index:2;
    background: rgba(255,255,255,0.08);
    padding:50px;
    border-radius:20px;
    backdrop-filter: blur(15px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    text-align:center;
    max-width:500px;
    width:90%;
}

h1{
    font-size:90px;
    margin:0;
    letter-spacing:5px;
}

p{
    margin:20px 0 30px;
    font-size:18px;
    opacity:0.9;
}

/* Botones */
a, button{
    text-decoration:none;
    padding:12px 22px;
    border-radius:30px;
    border:none;
    font-weight:bold;
    cursor:pointer;
    margin:8px;
    transition:0.3s;
}

.btn-primary{
    background:#00c6ff;
    color:white;
}

.btn-primary:hover{
    background:#0072ff;
}

.btn-secondary{
    background:white;
    color:#243b55;
}

.btn-secondary:hover{
    transform:scale(1.05);
}
</style>
</head>

<body>

<div class="background"></div>

<div class="card">
    <h1>404</h1>
    <p>Oops... La página que buscas no existe o fue movida.</p>

    <a href="/SistemaRRHH/dashboard.php" class="btn-primary">Inicio</a>
    <a href="/SistemaRRHH/Seguridad/Login.php" class="btn-secondary">Login</a>
    <button onclick="history.back()" class="btn-secondary">↩ Volver</button>

    <p style="margin-top:25px;font-size:12px;opacity:0.7;">
        <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>
    </p>
</div>

</body>
</html>
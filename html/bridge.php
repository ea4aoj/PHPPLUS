<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRIDGES</title>

    <!-- FAVICON: eslabones plateados -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23C0C0C0' viewBox='0 0 16 16'%3E%3Cpath d='M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z'/%3E%3Cpath d='M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z'/%3E%3C/svg%3E">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
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

        .card {
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: scale(1.02);
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
        <i class="bi bi-link-45deg me-2" style="color: #C0C0C0;"></i>
        BRIDGES
    </h1>

    <div class="row g-3 justify-content-center">

        <!-- DMR2YSF -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-broadcast-pin me-2" style="color:#00d4ff;"></i>DMR2YSF
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Bridge DMR ↔ YSF · Puente entre redes DMR y Fusion
                    </p>
                    <a href="/dmr2ysf.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- YSF2DMR -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-broadcast-pin me-2" style="color:#ff3b3b;"></i>YSF2DMR
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Bridge YSF ↔ DMR · Puente entre redes Fusion y DMR
                    </p>
                    
                    <a href="/ysf2dmr.php"  class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- DMR2NXDN -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-broadcast-pin me-2" style="color:#ffa500;"></i>DMR2NXDN
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Bridge DMR ↔ NXDN · Puente entre redes DMR y NXDN
                    </p>
                    <button type="button" class="btn btn-warning btn-sm mt-2 text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#proximamenteModal">
                        <i class="bi bi-hourglass-split me-1"></i>Próximamente
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Próximamente -->
<div class="modal fade" id="proximamenteModal" tabindex="-1" aria-labelledby="proximamenteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-warning">
            <div class="modal-header border-warning">
                <h5 class="modal-title" id="proximamenteModalLabel">
                    <i class="bi bi-rocket-takeoff me-2" style="color:#ffa500;"></i>
                    DMR2NXDN
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-tools" style="font-size: 3rem; color:#ffa500;"></i>
                <p class="mt-3 mb-0">
                    <strong>¡En construcción!</strong><br>
                    Este bridge está en desarrollo.<br>
                    Vuelve pronto para ver la novedad. 🚀
                </p>
            </div>
            <div class="modal-footer border-warning">
                <button type="button" class="btn btn-warning text-dark fw-bold" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<?php require_once 'conexao.php'; ?>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="epi_dashboard.css">
    <link rel="stylesheet" href="index.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="dashboard_docs.css">
    <link rel="stylesheet" href="gerenciar_docs.css">
    <link rel="stylesheet" href="gerenciar_epi.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="epi_dashboard.css">
    <link rel="stylesheet" href="index.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
     <link rel="stylesheet" href="style_dash_colab.css">
<body>
<div class="menu-spacer"></div>
<main class="container">
<nav class="main-menu-fixed">
    <div class="menu-content-container">
        <div class="logo">
            <a href="index.php">
                <i class="fas fa-shield-alt"></i> SESMT - LIMPEBRAS
            </a>
        </div>
        <ul class="nav-links">
            
            <li><a href="index.php"><i class="fas fa-home"></i> Início</a></li>

            <li class="dropdown">
                <a href="#" class="dropbtn"><i class="fas fa-chart-line"></i> Dashboards <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="epi_dashboard.php">EPIs</a>
                    <a href="dashboard_colaboradores.php">Colaboradores</a>
                    <a href="dashboard_treinamento.php">Treinamentos</a>
                    <a href="index_atestados.php">Atestados</a> 
                </div>
            </li>

            <li class="dropdown">
                <a href="#" class="dropbtn"><i class="fas fa-folder-plus"></i> Cadastros <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="cadastro_epi.php">EPIs</a>
                    <a href="novo_colaborador.php">Colaboradores</a>
                    <a href="cadastro_lote.php">Lote (Estoque)</a>
                    <a href="create_treinamento.php">Treinamento</a>
                    <a href="novo_atestado.php">Atestado</a>
                </div>
            </li>
            <li class="dropdown">
                <a href="#" class="dropbtn"><i class="fas fa-exchange-alt"></i> Movimentações <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="entregar_epi.php">Registrar Entrega</a>
                    <a href="gerenciar_entregas.php">Gerenciar Entregas</a>
                    <a href="index_atestados.php">Gerenciar Atestados</a>
                </div>
            </li>  
            <li class="dropdown">
                <a href="#" class="dropbtn"><i class="fas fa-file-export"></i> Relatórios <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="exportar_csv.php">Exportar Geral (CSV)</a>
                    <a href="exportar_treinamentos_csv.php">Treinamento (CSV)</a>
                    <a href="exportar_treinamentos_pdf.php">Treinamento (PDF)</a>
                    <a href="download_atestado.php">Download Atestado</a>
                </div>
            </li> 
            <li class="dropdown">
                <a href="#" class="dropbtn"><i class="fas fa-cogs"></i> Utilitários/Config <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="index_cep.php">Consulta CEP/API</a>
                    <a href="config_cep.php">Config CEP</a>
                    <a href="buscar_fichas.php">Buscar Fichas</a>
                    <a href="buscar_colaboradores.php">Buscar Colaboradores</a>
                    <a href="buscar_cids.php">Buscar CIDs</a>
                </div>
            </li>
        </ul>
        
    </div>
</nav>
<div class="menu-spacer"></div>
<main class="container">
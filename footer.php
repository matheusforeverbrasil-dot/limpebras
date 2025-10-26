<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    /* Estilo customizado para o rodapé */
    .custom-footer {
        height: 40px; /* Altura fixa de 40px */
        /* Azul moderno com 90% de opacidade */
        background-color: rgba(0, 123, 255, 0.9); 
        border-top: 1px solid rgba(255, 255, 255, 0.3); /* Linha branca sutil e transparente no topo */
        width: 100%;
        position: fixed;
        bottom: 0;
        left: 0;
        z-index: 1030;
    }

    /* Estilo para o logo com as dimensões exatas */
    .custom-footer .footer-logo {
        height: 39px; /* Altura exata solicitada */
        width: 140px; /* Largura exata solicitada */
        object-fit: contain; /* Garante que a imagem se ajuste */
        vertical-align: middle;
    }

    /* Estilo para o texto centralizado */
    .custom-footer .footer-info {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.9); /* Texto branco com leve transparência */
    }
</style>

<footer class="custom-footer container-fluid">
    <div class="row h-100 align-items-center">
        <div class="col-auto me-auto d-flex align-items-center h-100 ps-3">
            <img src="imagens/LIMPEBRAS_0001.png" alt="Logo da Empresa" class="footer-logo">
        </div>

        <div class="col-auto d-flex align-items-center h-100">
            <span class="footer-info">
                &copy; 2025 SESMT LIMPERBRAS ENG. AMBIENTAL | Versão 1.0
            </span>
        </div>

        <div class="col-auto ms-auto d-flex align-items-center h-100 pe-3">
            <button class="btn btn-sm btn-light" onclick="window.location.href='logout.php'">
                <i class="bi bi-box-arrow-right"></i> Sair
            </button>
        </div>
    </div>
</footer>
<?php
$conn->close();
?>

<?php
include 'conexao.php';

// Lógica PHP para a parte de EPIs
$sql_epis = "SELECT nome_epi, estoque, validade FROM epis ORDER BY validade ASC";
$epis = $conn->query($sql_epis);

$sql_epis_colaborador = "SELECT c.nome, e.nome_epi, ec.data_retirada FROM epis_colaborador ec JOIN colaboradores c ON ec.id_colaborador = c.id_colaborador JOIN epis e ON ec.id_epi = e.id_epi ORDER BY ec.data_retirada DESC";
$epis_colaborador = $conn->query($sql_epis_colaborador);

// Lógica PHP para a parte de Acidentes por Equipe
$sql_acidentes_equipe = "
    SELECT e.nome_equipe, COUNT(a.id_acidente) AS total 
    FROM acidentes a
    JOIN colaboradores c ON a.id_colaborador = c.id_colaborador
    JOIN equipes e ON c.equipe_id = e.id_equipe
    GROUP BY e.nome_equipe
    ORDER BY total DESC";
$acidentes_equipe = $conn->query($sql_acidentes_equipe);

$sql_acidentes_partes_corpo = "SELECT parte_corpo, COUNT(*) AS total FROM acidentes GROUP BY parte_corpo";
$acidentes_partes_corpo = $conn->query($sql_acidentes_partes_corpo);
$contagem_partes_corpo = [];
while ($row = $acidentes_partes_corpo->fetch_assoc()) {
    $contagem_partes_corpo[$row['parte_corpo']] = $row['total'];
}

$sql_acidentes_locais = "SELECT local_acidente, COUNT(*) AS total FROM acidentes GROUP BY local_acidente";
$acidentes_locais = $conn->query($sql_acidentes_locais);

$sql_acidentes_boletim = "SELECT boletim_ocorrencia, COUNT(*) AS total FROM acidentes GROUP BY boletim_ocorrencia";
$acidentes_boletim = $conn->query($sql_acidentes_boletim);

// Lógica PHP para a parte de Treinamentos
$hoje = date('Y-m-d');
$um_mes_frente = date('Y-m-d', strtotime('+1 month'));

$sql_treinamentos_em_dia = "SELECT COUNT(*) AS total_em_dia FROM treinamentos WHERE data_vencimento >= '$hoje'";
$treinamentos_em_dia = $conn->query($sql_treinamentos_em_dia)->fetch_assoc()['total_em_dia'];

$sql_treinamentos_vencidos = "SELECT COUNT(*) AS total_vencidos FROM treinamentos WHERE data_vencimento < '$hoje'";
$treinamentos_vencidos = $conn->query($sql_treinamentos_vencidos)->fetch_assoc()['total_vencidos'];

$sql_treinamentos_a_vencer = "SELECT COUNT(*) AS total_a_vencer FROM treinamentos WHERE data_vencimento BETWEEN '$hoje' AND '$um_mes_frente'";
$treinamentos_a_vencer = $conn->query($sql_treinamentos_a_vencer)->fetch_assoc()['total_a_vencer'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SST</title>
    <style>
	/* ... dentro da tag <style> ... */

/* Centralização do título do Dashboard (opcional, dependendo do seu layout) */
/* h1 { text-align: center; margin-bottom: 30px; } */ /* Mantenha se quiser o título geral centrado */

/* Estilo para o container de cards no grid (para o layout geral do dashboard) */
.container { max-width: 1200px; margin: 0 auto; display: grid; gap: 20px; grid-template-columns: 2fr 1fr; } 

/* Estilo para os cards de treinamento no grid */
.card-treinamentos { 
    /* Esta linha define a posição do card principal, deve se manter */
    grid-column: 1 / 2; 
    
    /* REMOVA o display: flex daqui */
    /* display: flex; justify-content: space-around; gap: 15px; */ 
    
    /* Vamos colocar o display flex em um container novo */
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    background-color: #fff;
}

/* Novo estilo para o link/card (remove sublinhado e muda cursor) */
a.card {
    text-decoration: none;
    color: inherit;
    display: block; /* Para o link ocupar todo o espaço do card */
    transition: transform 0.2s;
}

a.card:hover {
    transform: translateY(-3px); /* Efeito sutil ao passar o mouse */
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
}

/* ... restante do seu CSS ... */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; display: grid; gap: 20px; grid-template-columns: 2fr 1fr; }
        h1, h2 { color: #444; }
        h1 { text-align: center; margin-bottom: 30px; }
        .card { background-color: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); }

        .card-epis, .card-treinamentos { grid-column: 1 / 2; }
        .card-acidentes { grid-column: 2 / 3; grid-row: 1 / span 3; }
        .card-treinamentos { display: flex; justify-content: space-around; gap: 15px; }
        .treinamento-status { text-align: center; flex: 1; padding: 15px; border-radius: 8px; font-weight: bold; color: #fff; }
        .em-dia { background-color: #28a745; }
        .a-vencer { background-color: #ffc107; }
        .vencidos { background-color: #dc3545; }

        .tabela-epis, .tabela-acidentes { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .tabela-epis th, .tabela-epis td, .tabela-acidentes th, .tabela-acidentes td { border: 1px solid #eee; padding: 12px; text-align: left; }
        .tabela-epis th, .tabela-acidentes th { background-color: #f8f9fa; color: #555; }

        .corpo-humano-container { position: relative; width: 300px; margin: 0 auto; }
        .corpo-humano { width: 100%; }
        .lesao { position: absolute; font-size: 1.2em; font-weight: bold; color: white; background-color: #dc3545; border-radius: 50%; width: 30px; height: 30px; display: flex; justify-content: center; align-items: center; cursor: pointer; border: 2px solid #fff; }
        .lesao::after { content: attr(data-parte); position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 0.8em; white-space: nowrap; color: #333; background-color: rgba(255,255,255,0.9); padding: 5px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); visibility: hidden; opacity: 0; transition: opacity 0.3s; }
        .lesao:hover::after { visibility: visible; opacity: 1; }
        .cabeça { top: 5%; left: 50%; transform: translateX(-50%); }
        .olhos { top: 12%; left: 50%; transform: translateX(-50%); }
        .pescoço { top: 18%; left: 50%; transform: translateX(-50%); }
        .braços { top: 30%; left: 20%; }
        .tronco { top: 35%; left: 50%; transform: translateX(-50%); }
        .estomago { top: 45%; left: 50%; transform: translateX(-50%); }
        .pernas { top: 65%; left: 35%; }
        .pes { top: 90%; left: 45%; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard de SST</h1>

        <!-- Card de Acidentes -->
        <div class="card card-acidentes">
            <h2>Registros de Acidentes</h2>
            <div class="corpo-humano-container">
                <!-- Alteração aqui: usa a URL da imagem -->
                <img src="./imagens/corpoAcidentes.jpg" alt="Corpo Humano" class="corpo-humano">
                <?php foreach($contagem_partes_corpo as $parte => $total): ?>
                    <?php if ($total > 0): ?>
                        <div class="lesao <?php echo strtolower($parte); ?>" data-parte="<?php echo htmlspecialchars($parte); ?>">
                            <?php echo htmlspecialchars($total); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <table class="tabela-acidentes">
                <thead>
                    <tr>
                        <th>Equipe</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $acidentes_equipe->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['nome_equipe']); ?></td>
                            <td><?php echo htmlspecialchars($row['total']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <table class="tabela-acidentes">
                <thead>
                    <tr>
                        <th>Boletim de Ocorrência</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $acidentes_boletim->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['boletim_ocorrencia'] ? 'Sim' : 'Não'; ?></td>
                            <td><?php echo htmlspecialchars($row['total']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Card de EPIs -->
        <div class="card card-epis">
            <h2>Controle de EPIs</h2>
            <table class="tabela-epis">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>EPI</th>
                        <th>Data de Retirada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $epis_colaborador->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['nome']); ?></td>
                            <td><?php echo htmlspecialchars($row['nome_epi']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['data_retirada']))); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Card de Treinamentos -->
       <div class="card card-treinamentos">
            
            <div style="text-align: center; margin-bottom: 20px;">
                <h2>Treinamentos</h2>
            </div>

            <div style="display: flex; justify-content: space-around; gap: 15px;">

                <?php $base_url = 'dashboard_treinamento.php'; // <-- AJUSTE ESTA URL! ?>

                <a href="<?php echo $base_url; ?>?status=em_dia" class="card" style="flex: 1; min-width: 150px;">
                    <div class="treinamento-status em-dia">
                        <h3>Em dia</h3>
                        <p><?php echo $treinamentos_em_dia; ?></p>
                    </div>
                </a>
                
                <a href="<?php echo $base_url; ?>?status=vencendo" class="card" style="flex: 1; min-width: 150px;">
                    <div class="treinamento-status a-vencer">
                        <h3>A vencer (30 dias)</h3>
                        <p><?php echo $treinamentos_a_vencer; ?></p>
                    </div>
                </a>
                
                <a href="<?php echo $base_url; ?>?status=vencido" class="card" style="flex: 1; min-width: 150px;">
                    <div class="treinamento-status vencidos">
                        <h3>Vencidos</h3>
                        <p><?php echo $treinamentos_vencidos; ?></p>
                    </div>
                </a>

            </div>
        </div>

    </div>
    <?php $conn->close(); ?>
</body>
</html>

<?php
// Arquivo: dashboard_atestado.php
// Inclui o arquivo de conexão
include 'conexao.php'; 

// Array para armazenar todos os dados do dashboard
$dashboard_data = [
    'kpis' => [
        'total_atestados' => 0,
        'dias_afastamento_total' => 0,
        'media_dias_por_atestado' => 0.0,
        'top_cid' => 'N/A'
    ],
    'grafico_cids' => [], 
    'grafico_unidades' => [], 
    'grafico_afastamento_mensal' => [],
    'grafico_top_colaboradores' => [], 
    // 'grafico_setores' removido
];

// ------------------------------------------
// 1. CÁLCULO DE KPIs
// ------------------------------------------

// Total de Atestados
$sql = "SELECT COUNT(*) AS total FROM atestados";
$result = $conn->query($sql);
if ($result) {
    if ($row = $result->fetch_assoc()) {
        $dashboard_data['kpis']['total_atestados'] = (int)$row['total'];
    }
} else {
    // Tratamento de erro silencioso
}

// Total de Dias de Afastamento e Média
$sql = "SELECT SUM(dias_afastamento) AS total_dias FROM atestados";
$result = $conn->query($sql);
if ($result) {
    if ($row = $result->fetch_assoc()) {
        $total_dias = (int)$row['total_dias'];
        $dashboard_data['kpis']['dias_afastamento_total'] = $total_dias;
        if ($dashboard_data['kpis']['total_atestados'] > 0) {
            $media = $total_dias / $dashboard_data['kpis']['total_atestados'];
            $dashboard_data['kpis']['media_dias_por_atestado'] = round($media, 1);
        }
    }
}

// CID Mais Frequente (Top CID)
$sql = "SELECT cid, COUNT(cid) AS contagem 
        FROM atestados 
        GROUP BY cid 
        ORDER BY contagem DESC 
        LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    $cid_sigla = $row['cid'];
    
    // Busca a descrição do CID
    $stmt_desc = $conn->prepare("SELECT descricao FROM CID WHERE sigla = ?");
    if ($stmt_desc) {
        $stmt_desc->bind_param("s", $cid_sigla);
        if ($stmt_desc->execute()) {
            $result_desc = $stmt_desc->get_result();
            if ($desc_row = $result_desc->fetch_assoc()) {
                 $dashboard_data['kpis']['top_cid'] = "{$cid_sigla} - {$desc_row['descricao']}";
            } else {
                 $dashboard_data['kpis']['top_cid'] = $cid_sigla;
            }
        }
        $stmt_desc->close();
    } else {
        $dashboard_data['kpis']['top_cid'] = $cid_sigla . " (Descrição não encontrada)";
    }
}


// ------------------------------------------
// 2. DADOS PARA GRÁFICOS INICIAIS
// ------------------------------------------

// Gráfico 1: Top 5 CIDs (Motivos de Afastamento)
$sql = "SELECT t.sigla, t.descricao, COUNT(a.cid) AS contagem 
        FROM atestados a
        JOIN CID t ON a.cid = t.sigla COLLATE utf8_general_ci  
        GROUP BY a.cid, t.sigla, t.descricao 
        ORDER BY contagem DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dashboard_data['grafico_cids'][] = $row;
    }
}


// Gráfico 2: Top 5 Unidades de Saúde (Local de Emissão)
$sql = "SELECT u.nome_u_saude, COUNT(a.unidade_saude) AS contagem 
        FROM atestados a
        JOIN unidade_saude u ON a.unidade_saude = u.id
        GROUP BY u.nome_u_saude 
        ORDER BY contagem DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dashboard_data['grafico_unidades'][] = $row;
    }
}


// Gráfico 3: Afastamentos por Mês (Tendência)
$sql = "SELECT DATE_FORMAT(data_atestado, '%Y-%m') AS mes_ano, 
               SUM(dias_afastamento) AS total_dias 
        FROM atestados 
        GROUP BY mes_ano 
        ORDER BY mes_ano DESC 
        LIMIT 6"; 
$result = $conn->query($sql);
if ($result) {
    $temp_mensal = [];
    while ($row = $result->fetch_assoc()) {
        $temp_mensal[] = $row;
    }
    $dashboard_data['grafico_afastamento_mensal'] = array_reverse($temp_mensal);
}


// ------------------------------------------
// 3. NOVOS DADOS PARA GRÁFICOS E TABELAS
// ------------------------------------------

// Gráfico 4: Top 5 Colaboradores com mais Atestados
$sql = "SELECT c.nome, c.matricula, COUNT(a.id_atestado) AS contagem
        FROM atestados a
        JOIN colaboradores c ON a.colaborador_id = c.id_colaborador
        GROUP BY c.nome, c.matricula
        ORDER BY contagem DESC
        LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dashboard_data['grafico_top_colaboradores'][] = $row;
    }
}

// Gráfico 5: Atestados por Setor (REMOVIDO PARA NÃO GERAR ERRO E POUPAR PROCESSAMENTO)
/*
$sql_setores = "SELECT s.nome_setor, COUNT(a.id_atestado) AS contagem
        FROM (atestados a 
        JOIN colaboradores c ON a.colaborador_id = c.id_colaborador)
        JOIN setores s ON c.id_setor = s.id_setor 
        GROUP BY s.nome_setor
        ORDER BY contagem DESC";
$result_setores = $conn->query($sql_setores);
if ($result_setores) {
    while ($row = $result_setores->fetch_assoc()) {
        $dashboard_data['grafico_setores'][] = $row;
    }
}
*/

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Atestados</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .dashboard-container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #007bff; text-align: center; margin-bottom: 30px; }
        
        /* Layout dos KPIs */
        .kpi-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border-left: 5px solid #007bff; }
        .kpi-card h3 { margin: 0 0 10px 0; font-size: 1.1em; color: #6c757d; }
        .kpi-card p { font-size: 2em; font-weight: bold; margin: 0; color: #007bff; }
        .kpi-card.destaque { border-left-color: #dc3545; }
        .kpi-card.destaque p { color: #dc3545; }

        /* Layout dos Gráficos */
        /* Ajuste o grid para 3 colunas, já que o último gráfico foi removido */
        .charts-grid { 
            display: grid; 
            grid-template-columns: repeat(3, minmax(350px, 1fr)); /* 3 colunas fixas */
            gap: 30px; 
        }
        
        /* Se a tela for pequena, volte para 2 colunas e depois 1 */
        @media (max-width: 1200px) {
            .charts-grid {
                 grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            }
        }
        
        .chart-card { 
            background-color: #fff; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
        }
        .chart-card h2 { 
            margin-top: 0; 
            font-size: 1.5em; 
            color: #007bff; 
            border-bottom: 2px solid #f0f0f0; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }

        /* Estilos para a lista de Top Colaboradores */
        #topColaboradoresList div {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            font-size: 1em;
        }
        #topColaboradoresList div:last-child {
            border-bottom: none;
        }
        #topColaboradoresList .count {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <h1>Dashboard de Absenteísmo por Atestados</h1>

    <div class="kpi-cards">
        <div class="kpi-card">
            <h3>Total de Atestados</h3>
            <p><?php echo number_format($dashboard_data['kpis']['total_atestados'], 0, ',', '.'); ?></p>
        </div>
        <div class="kpi-card destaque">
            <h3>Total de Dias Afastados</h3>
            <p><?php echo number_format($dashboard_data['kpis']['dias_afastamento_total'], 0, ',', '.'); ?></p>
        </div>
        <div class="kpi-card">
            <h3>Média de Dias por Atestado</h3>
            <p><?php echo number_format($dashboard_data['kpis']['media_dias_por_atestado'], 1, ',', '.') . ' dias'; ?></p>
        </div>
        <div class="kpi-card">
            <h3>Top CID</h3>
            <p style="font-size: 1.2em;"><?php echo htmlspecialchars($dashboard_data['kpis']['top_cid']); ?></p>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h2>Top 5 Motivos de Afastamento (CID)</h2>
            <canvas id="chartCids"></canvas>
        </div>
        <div class="chart-card">
            <h2>Afastamento nos Últimos 6 Meses</h2>
            <canvas id="chartMensal"></canvas>
        </div>
        <div class="chart-card">
            <h2>Unidades de Saúde Mais Frequentes</h2>
            <canvas id="chartUnidades"></canvas>
        </div>
        
        <div class="chart-card">
            <h2>Top 5 Colaboradores com Mais Atestados</h2>
            <div id="topColaboradoresList">
                </div>
        </div>
        
        </div>
</div>

<script>
    // ------------------------------------------
    // JS PARA RENDERIZAR GRÁFICOS (CHART.JS)
    // ------------------------------------------

    const dataDashboard = <?php echo json_encode($dashboard_data); ?>;
    
    // Gráfico 1: Top 5 CIDs (Barra Horizontal)
    const cidsLabels = dataDashboard.grafico_cids.map(item => `${item.sigla} - ${item.descricao}`);
    const cidsData = dataDashboard.grafico_cids.map(item => item.contagem);
    
    if (cidsData.length > 0) {
        new Chart(document.getElementById('chartCids'), {
            type: 'bar', 
            data: {
                labels: cidsLabels,
                datasets: [{
                    label: 'Número de Atestados',
                    data: cidsData,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y', 
                responsive: true,
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
    }

    // Gráfico 2: Afastamento nos Últimos 6 Meses (Linha)
    const mensalLabels = dataDashboard.grafico_afastamento_mensal.map(item => item.mes_ano);
    const mensalData = dataDashboard.grafico_afastamento_mensal.map(item => item.total_dias);

    if (mensalData.length > 0) {
        new Chart(document.getElementById('chartMensal'), {
            type: 'line', 
            data: {
                labels: mensalLabels,
                datasets: [{
                    label: 'Total de Dias Afastados',
                    data: mensalData,
                    fill: true,
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    // Gráfico 3: Unidades de Saúde (Rosca)
    const unidadesLabels = dataDashboard.grafico_unidades.map(item => item.nome_u_saude);
    const unidadesData = dataDashboard.grafico_unidades.map(item => item.contagem);

    if (unidadesData.length > 0) {
        new Chart(document.getElementById('chartUnidades'), {
            type: 'doughnut', 
            data: {
                labels: unidadesLabels,
                datasets: [{
                    label: 'Contagem',
                    data: unidadesData,
                    backgroundColor: [
                        '#007bff',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#6c757d'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
    
    // Top 5 Colaboradores (Lista/Tabela Simples) - MANTIDO
    const colaboradoresList = document.getElementById('topColaboradoresList');
    if (dataDashboard.grafico_top_colaboradores.length > 0) {
        dataDashboard.grafico_top_colaboradores.forEach(item => {
            const div = document.createElement('div');
            div.innerHTML = `
                <span>${item.matricula} - ${item.nome}</span>
                <span class="count">${item.contagem}</span>
            `;
            colaboradoresList.appendChild(div);
        });
    } else {
        colaboradoresList.innerHTML = '<div>Nenhum dado de colaborador encontrado.</div>';
    }


    // Atestados por Setor (Pizza) - REMOVIDO
    /* const setoresLabels = dataDashboard.grafico_setores.map(item => item.nome_setor);
    const setoresData = dataDashboard.grafico_setores.map(item => item.contagem);
    
    if (setoresData.length > 0) {
        new Chart(document.getElementById('chartSetores'), {
            // ... código do chart ...
        });
    }
    */

</script>
</body>
</html>
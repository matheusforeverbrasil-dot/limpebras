<?php
$pageTitle = "Dashboard de Estoque e Controle de EPIs - SST"; 
require_once 'header.php'; 

if (!$conexao_status || !isset($conn) || !$conn->ping()) {
    echo '<div class="alert-error"><strong>Falha Crítica no Sistema:</strong> Não foi possível conectar ao banco de dados.</div>';
    include 'footer.php';
    exit;
}

// =======================================================
// 1. CONSULTAS DE KPIS E GRÁFICOS DE ESTOQUE
// =======================================================

// A. KPIs de Estoque (Total de Itens / Necessidade de Compra)
$sql_kpis = "
    SELECT 
        SUM(estoque) AS total_estoque,
        COUNT(CASE WHEN estoque <= qnt_minima AND estoque > 0 THEN id_epi END) AS itens_baixo_minimo,
        COUNT(CASE WHEN estoque <= 0 THEN id_epi END) AS itens_estoque_zero,
        COUNT(CASE WHEN validade < CURDATE() THEN id_epi END) AS itens_ca_vencido,
        COUNT(CASE WHEN validade <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND validade > CURDATE() THEN id_epi END) AS ca_proximo_vencimento
    FROM epis;
";
$result_kpis = $conn->query($sql_kpis);
$kpis = $result_kpis ? $result_kpis->fetch_assoc() : [];

// B. Gráfico Donut: Distribuição de Itens por Status de Estoque
$sql_status_estoque = "
    SELECT 
        CASE 
            WHEN validade < CURDATE() THEN 'CA VENCIDO'
            WHEN estoque <= 0 THEN 'Estoque Zero'
            WHEN estoque <= qnt_minima THEN 'Abaixo do Mínimo'
            ELSE 'Estoque OK'
        END AS status_estoque,
        COUNT(id_epi) AS total
    FROM epis
    GROUP BY status_estoque
";
$result_status = $conn->query($sql_status_estoque);

$dados_status_php = [];
while ($row = $result_status->fetch_assoc()) {
    $dados_status_php[$row['status_estoque']] = (int)$row['total'];
}

// C. Gráfico de Barras: Top 5 EPIs com Maior Necessidade de Compra
$sql_necessidade = "
    SELECT 
        nome_epi, 
        (qnt_minima - estoque) AS necessidade
    FROM epis
    WHERE estoque < qnt_minima AND estoque > 0
    ORDER BY necessidade DESC
    LIMIT 5
";
$result_necessidade = $conn->query($sql_necessidade);

$labels_necessidade = [];
$series_necessidade = [];
while ($row = $result_necessidade->fetch_assoc()) {
    $labels_necessidade[] = $row['nome_epi'];
    $series_necessidade[] = (int)$row['necessidade'];
}

// =======================================================
// 2. NOVAS CONSULTAS DE GRÁFICOS DE CONSUMO (SAÍDAS)
// =======================================================

// D. Gráfico: Top 10 EPIs que Mais Saem (Mais Distribuídos)
$sql_top_saidas = "
    SELECT 
        e.nome_epi,
        SUM(ee.quantidade_saida) AS total_saidas
    FROM entregas_epi ee
    JOIN epis e ON ee.id_epi = e.id_epi
    GROUP BY e.nome_epi
    ORDER BY total_saidas DESC
    LIMIT 10
";
$result_top_saidas = $conn->query($sql_top_saidas);

$labels_top_saidas = [];
$series_top_saidas = [];
if ($result_top_saidas) {
    while ($row = $result_top_saidas->fetch_assoc()) {
        $labels_top_saidas[] = $row['nome_epi'];
        $series_top_saidas[] = (int)$row['total_saidas'];
    }
}


// E. Gráfico: 5 EPIs que Menos Saem (Com saídas > 0)
$sql_bottom_saidas = "
    SELECT 
        e.nome_epi,
        SUM(ee.quantidade_saida) AS total_saidas
    FROM entregas_epi ee
    JOIN epis e ON ee.id_epi = e.id_epi
    GROUP BY e.nome_epi
    HAVING total_saidas > 0
    ORDER BY total_saidas ASC
    LIMIT 5
";
$result_bottom_saidas = $conn->query($sql_bottom_saidas);

$labels_bottom_saidas = [];
$series_bottom_saidas = [];
if ($result_bottom_saidas) {
    while ($row = $result_bottom_saidas->fetch_assoc()) {
        $labels_bottom_saidas[] = $row['nome_epi'];
        $series_bottom_saidas[] = (int)$row['total_saidas'];
    }
}


// F. Gráfico: Top 5 Colaboradores que Mais Solicitam EPIs
$sql_top_colaboradores = "
    SELECT 
        c.nome AS nome_colaborador,
        SUM(ee.quantidade_saida) AS total_solicitado
    FROM entregas_epi ee
    JOIN colaboradores c ON ee.id_colaborador = c.id_colaborador
    GROUP BY c.nome
    ORDER BY total_solicitado DESC
    LIMIT 5
";
$result_top_colaboradores = $conn->query($sql_top_colaboradores);

$labels_top_colab = [];
$series_top_colab = [];
if ($result_top_colaboradores) {
    while ($row = $result_top_colaboradores->fetch_assoc()) {
        $labels_top_colab[] = $row['nome_colaborador'];
        $series_top_colab[] = (int)$row['total_solicitado'];
    }
}


// G. Gráfico: Distribuição de EPIs por Setor
$sql_setor_distribuicao = "
    SELECT 
        s.nome_setor,
        SUM(ee.quantidade_saida) AS total_saidas
    FROM entregas_epi ee
    JOIN colaboradores c ON ee.id_colaborador = c.id_colaborador
    JOIN setores s ON c.id_setor = s.id_setor
    GROUP BY s.nome_setor
    ORDER BY total_saidas DESC
";
$result_setor_distribuicao = $conn->query($sql_setor_distribuicao);

$labels_setores = [];
$series_setores = [];
if ($result_setor_distribuicao) {
    while ($row = $result_setor_distribuicao->fetch_assoc()) {
        $labels_setores[] = $row['nome_setor'];
        $series_setores[] = (int)$row['total_saidas'];
    }
}


// =======================================================
// 3. LAYOUT HTML - DASHBOARD
// =======================================================
?>

<div class="actions-bar">
    <a href="gerenciar_epi.php" class="btn btn-secondary">
        <i class="fas fa-list"></i> Gerenciar Itens
    </a>
    <a href="registro_requisicoes.php" class="btn btn-warning">
        <i class="fas fa-file-upload"></i> Registrar Requisições (PDF)
    </a>
</div>

<h2>Indicadores Chave (KPIs) de Estoque</h2>

<div class="card-grid">
    <div class="card card-total">
        <h2>Total de Itens Cadastrados</h2>
        <p class="value"><?= number_format($kpis['itens_baixo_minimo'] + $kpis['itens_estoque_zero'] + ($kpis['ca_proximo_vencimento'] ?? 0) + $kpis['total_estoque'], 0, ',', '.') ?></p>
    </div>
    <div class="card card-alerta">
        <h2>Itens Abaixo do Mínimo</h2>
        <p class="value"><?= number_format($kpis['itens_baixo_minimo'] ?? 0, 0, ',', '.') ?></p>
    </div>
    <div class="card card-critico">
        <h2>Estoque Zero ou CA Vencido</h2>
        <p class="value"><?= number_format(($kpis['itens_estoque_zero'] ?? 0) + ($kpis['itens_ca_vencido'] ?? 0), 0, ',', '.') ?></p>
    </div>
    <div class="card card-proximo-venc">
        <h2>CA Vence em 90 dias</h2>
        <p class="value"><?= number_format($kpis['ca_proximo_vencimento'] ?? 0, 0, ',', '.') ?></p>
    </div>
</div>

<h3>Análise de Consumo e Distribuição</h3>

<div class="chart-grid-full">
    <div class="chart-area-full">
        <h3>Top 10 EPIs com Maior Volume de Saída</h3>
        <div id="chartBarTopSaidas"></div>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-area">
        <h3>Distribuição de EPIs por Setor (Quantidade Total)</h3>
        <div id="chartPieSetor"></div>
    </div>
    <div class="chart-area">
        <h3>Top 5 Colaboradores que Mais Retiraram EPIs</h3>
        <div id="chartBarTopColab"></div>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-area">
        <h3>Distribuição de Itens por Status de Alerta (Estoque)</h3>
        <div id="chartDonutStatus"></div>
    </div>
    <div class="chart-area">
        <h3>Top 5 EPIs com Urgência de Compra (Quantidade Faltante)</h3>
        <div id="chartBarNecessidade"></div>
    </div>
</div>

<div class="chart-grid-half">
    <div class="chart-area">
        <h3>5 EPIs com Menor Saída (Acima de Zero)</h3>
        <div id="chartBarBottomSaidas"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // =======================================================
    // DADOS GRÁFICOS (Gerados por PHP)
    // =======================================================
    const dadosStatus = <?= json_encode($dados_status_php) ?>;
    const labelsDonut = Object.keys(dadosStatus);
    const seriesDonut = Object.values(dadosStatus);
    
    const labelsBarNecessidade = <?= json_encode($labels_necessidade) ?>;
    const seriesBarNecessidade = <?= json_encode($series_necessidade) ?>;

    const labelsTopSaidas = <?= json_encode($labels_top_saidas) ?>;
    const seriesTopSaidas = <?= json_encode($series_top_saidas) ?>;
    
    const labelsBottomSaidas = <?= json_encode($labels_bottom_saidas) ?>;
    const seriesBottomSaidas = <?= json_encode($series_bottom_saidas) ?>;

    const labelsTopColab = <?= json_encode($labels_top_colab) ?>;
    const seriesTopColab = <?= json_encode($series_top_colab) ?>;

    const labelsSetores = <?= json_encode($labels_setores) ?>;
    const seriesSetores = <?= json_encode($series_setores) ?>;

    
    // Funções Auxiliares
    function getColorForStatus(status) {
        switch (status) {
            case 'CA VENCIDO':
            case 'Estoque Zero':
                return '#ac5f59ff'; // Vermelho (Crítico)
            case 'Abaixo do Mínimo':
                return '#585147ff'; // Laranja (Alerta)
            case 'Estoque OK':
            default:
                return '#037707ff'; // Verde (OK)
        }
    }
    const colorsDonut = labelsDonut.map(getColorForStatus);

    // =======================================================
    // GRÁFICO 1: Status do Estoque (Donut)
    // =======================================================
    const optionsDonut = {
        chart: { type: 'donut', height: 350 },
        series: seriesDonut,
        labels: labelsDonut,
        colors: colorsDonut, 
        responsive: [{ breakpoint: 480, options: { chart: { width: 250 }, legend: { position: 'bottom' } } }],
        plotOptions: {
            pie: {
                donut: {
                    labels: { show: true, total: { show: true, label: 'Total Itens', formatter: (w) => w.globals.series.reduce((a, b) => a + b, 0) } }
                }
            }
        },
    };
    if (document.querySelector("#chartDonutStatus")) {
        new ApexCharts(document.querySelector("#chartDonutStatus"), optionsDonut).render();
    }

    // =======================================================
    // GRÁFICO 2: Necessidade de Compra (Barra)
    // =======================================================
    const optionsBarNecessidade = {
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        series: [{ name: 'Unidades Faltantes', data: seriesBarNecessidade }],
        xaxis: { categories: labelsBarNecessidade, title: { text: 'Quantidade' } },
        colors: ['#43c1d1ff'],
        plotOptions: {
            bar: { horizontal: true }
        }
    };
    if (document.querySelector("#chartBarNecessidade")) {
        new ApexCharts(document.querySelector("#chartBarNecessidade"), optionsBarNecessidade).render();
    }

    // =======================================================
    // GRÁFICO 3: Top 10 Mais Saem (Barra Horizontal - Maior)
    // =======================================================
    const optionsBarTopSaidas = {
        chart: { type: 'bar', height: 400, toolbar: { show: false } },
        series: [{ name: 'Total Saídas', data: seriesTopSaidas }],
        xaxis: { categories: labelsTopSaidas, title: { text: 'Quantidade Total Distribuída' } },
        colors: ['#2196F3'], // Azul
        plotOptions: { bar: { horizontal: true } }
    };
    if (document.querySelector("#chartBarTopSaidas")) {
        new ApexCharts(document.querySelector("#chartBarTopSaidas"), optionsBarTopSaidas).render();
    }
    
    // =======================================================
    // GRÁFICO 4: Top 5 Colaboradores (Barra Horizontal)
    // =======================================================
    const optionsBarTopColab = {
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        series: [{ name: 'Total EPIs Retirados', data: seriesTopColab }],
        xaxis: { categories: labelsTopColab, title: { text: 'Quantidade' } },
        colors: ['#FFC107'], // Amarelo/Laranja
        plotOptions: { bar: { horizontal: true } }
    };
    if (document.querySelector("#chartBarTopColab")) {
        new ApexCharts(document.querySelector("#chartBarTopColab"), optionsBarTopColab).render();
    }
    
    // =======================================================
    // GRÁFICO 5: Distribuição por Setor (Donut)
    // =======================================================
    const optionsPieSetor = {
        chart: { type: 'donut', height: 350 },
        series: seriesSetores,
        labels: labelsSetores,
        responsive: [{ breakpoint: 480, options: { chart: { width: 250 }, legend: { position: 'bottom' } } }],
        plotOptions: {
            pie: { donut: { labels: { show: true, total: { show: true, label: 'Total Saídas' } } } }
        },
    };
    if (document.querySelector("#chartPieSetor")) {
        new ApexCharts(document.querySelector("#chartPieSetor"), optionsPieSetor).render();
    }
    
    // =======================================================
    // GRÁFICO 6: Bottom 5 Menos Saem (Barra Vertical)
    // =======================================================
    const optionsBarBottomSaidas = {
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        series: [{ name: 'Total Saídas', data: seriesBottomSaidas }],
        xaxis: { categories: labelsBottomSaidas, title: { text: 'EPI' } },
        yaxis: { title: { text: 'Quantidade Distribuída' } },
        colors: ['#4CAF50'], // Verde
    };
    if (document.querySelector("#chartBarBottomSaidas")) {
        new ApexCharts(document.querySelector("#chartBarBottomSaidas"), optionsBarBottomSaidas).render();
    }
});
</script>

<?php 
require_once 'footer.php'; 
?>
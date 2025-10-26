<?php
// =======================================================
// dashboard_docs.php - Dashboard de Documentos com Dados Reais
// =======================================================

// 1. INCLUSÃO DE CONEXÃO E CABEÇALHO
include 'conexao.php';
include 'header.php';

// Inicializa variáveis de pesquisa
$termo_pesquisa = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$extensao_pesquisa = isset($_GET['ext']) ? $conn->real_escape_string($_GET['ext']) : '';

// --------------------------------------------------------------------------------
// 2. BUSCA DE DADOS PARA CARDS (KPIs)
// --------------------------------------------------------------------------------

// Consulta: Total de Documentos
$sql_total = "SELECT COUNT(*) AS total FROM documentos WHERE ativo = TRUE";
$result_total = $conn->query($sql_total);
$total_documentos = $result_total->fetch_assoc()['total'];

// Consulta: Documentos Protegidos (status_protecao = 'Protegido' OU protegido_por_senha = TRUE)
$sql_protegidos = "SELECT COUNT(*) AS total FROM documentos WHERE ativo = TRUE AND (status_protecao = 'Protegido' OR protegido_por_senha = TRUE)";
$result_protegidos = $conn->query($sql_protegidos);
$docs_protegidos = $result_protegidos->fetch_assoc()['total'];

// Consulta: Documentos Restritos (simulando "sem permissão definida" como 'Restrito')
$sql_restrito = "SELECT COUNT(*) AS total FROM documentos WHERE ativo = TRUE AND status_protecao = 'Restrito'";
$result_restrito = $conn->query($sql_restrito);
$docs_restritos = $result_restrito->fetch_assoc()['total'];

// Consulta: Tamanho total (em GB para o card)
$sql_tamanho = "SELECT SUM(tamanho_bytes) AS total_bytes FROM documentos WHERE ativo = TRUE";
$result_tamanho = $conn->query($sql_tamanho);
$total_bytes = $result_tamanho->fetch_assoc()['total_bytes'];
$total_gb = $total_bytes / (1024 * 1024 * 1024); // Conversão para Gigabytes

// Cálculo do percentual
$percentual_protecao = ($total_documentos > 0) ? ($docs_protegidos / $total_documentos) * 100 : 0;

// --------------------------------------------------------------------------------
// 3. BUSCA DE DADOS PARA GRÁFICOS
// --------------------------------------------------------------------------------

// A) Distribuição por Tipo de Arquivo (Gráfico Donut)
$sql_tipos = "SELECT extensao, COUNT(*) AS count FROM documentos WHERE ativo = TRUE GROUP BY extensao ORDER BY count DESC LIMIT 8";
$result_tipos = $conn->query($sql_tipos);
$dados_tipos_php = [];
while ($row = $result_tipos->fetch_assoc()) {
    $dados_tipos_php[$row['extensao']] = (int)$row['count'];
}

// B) Tendência de Uploads (Gráfico de Linha - Últimos 6 meses)
// Adaptação para MySQL
$sql_uploads = "
    SELECT 
        DATE_FORMAT(data_upload, '%Y-%m') AS ano_mes, 
        COUNT(*) AS total_uploads
    FROM 
        documentos
    WHERE 
        data_upload >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND ativo = TRUE
    GROUP BY 
        ano_mes
    ORDER BY 
        ano_mes ASC";

$result_uploads = $conn->query($sql_uploads);
$dados_uploads_map = [];
while ($row = $result_uploads->fetch_assoc()) {
    $dados_uploads_map[$row['ano_mes']] = (int)$row['total_uploads'];
}

// Prepara os dados de 6 meses (garantindo 6 pontos)
$labels_linha = [];
$series_linha = [];
for ($i = 5; $i >= 0; $i--) {
    $mes_referencia = date('Y-m', strtotime("-$i months"));
    $mes_label = date('M', strtotime("-$i months")); // Ex: Out
    
    $labels_linha[] = $mes_label;
    $series_linha[] = $dados_uploads_map[$mes_referencia] ?? 0;
}

// --------------------------------------------------------------------------------
// 4. ESTRUTURA HTML (Barra de Pesquisa e Cards)
// --------------------------------------------------------------------------------
?>

<div class="search-bar">
    <h2>Pesquisa Rápida</h2>
    <form action="dashboard_docs.php" method="get">
        <input type="text" name="search" placeholder="Buscar por nome do arquivo..." value="<?= htmlspecialchars($termo_pesquisa) ?>">
        
        <select name="ext">
            <option value="">Todas as Extensões</option>
            <?php
            $extensoes_comuns = array_keys($dados_tipos_php); // Usa as extensões encontradas no BD
            foreach ($extensoes_comuns as $ext) {
                $selected = (strtolower($extensao_pesquisa) == strtolower($ext)) ? 'selected' : '';
                echo "<option value=\"$ext\" $selected>." . strtoupper($ext) . "</option>";
            }
            ?>
        </select>
        
        <button type="submit">Pesquisar</button>
        <?php if (!empty($termo_pesquisa) || !empty($extensao_pesquisa)): ?>
            <a href="dashboard_docs.php">Limpar Filtros</a>
        <?php endif; ?>
    </form>
</div>

<div class="card-grid">
    <div class="card total">
        <h2>Total de Documentos</h2>
        <p class="value"><?= number_format($total_documentos, 0, ',', '.') ?></p>
    </div>
    <div class="card protegidos">
        <h2>Documentos Protegidos (EPIs, PGR, etc.)</h2>
        <p class="value"><?= number_format($docs_protegidos, 0, ',', '.') ?></p>
    </div>
    <div class="card permissao">
        <h2>Documentos Restritos (Acesso Controlado)</h2>
        <p class="value"><?= number_format($docs_restritos, 0, ',', '.') ?></p>
    </div>
    <div class="card tamanho">
        <h2>Espaço Ocupado</h2>
        <p class="value"><?= number_format($total_gb, 2, ',', '.') ?> GB</p>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-area">
        <h2>Tendência de Uploads (Últimos 6 Meses)</h2>
        <div id="chartLinha"></div>
    </div>
    <div class="chart-area">
        <h2>Distribuição por Tipo de Arquivo</h2>
        <div id="chartDonut"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // =======================================================
    // DADOS PARA GRÁFICOS (Gerados por PHP)
    // =======================================================
    const dadosTipos = <?= json_encode($dados_tipos_php) ?>;
    const labelsDonut = Object.keys(dadosTipos);
    const seriesDonut = Object.values(dadosTipos);
    
    const labelsLinha = <?= json_encode($labels_linha) ?>;
    const seriesLinha = <?= json_encode($series_linha) ?>;

    // =======================================================
    // GRÁFICO 1: Distribuição por Tipo de Arquivo (Donut)
    // =======================================================
    const optionsDonut = {
        chart: { type: 'donut', height: 350 },
        series: seriesDonut,
        labels: labelsDonut,
        responsive: [{
            breakpoint: 480,
            options: { chart: { width: 250 }, legend: { position: 'bottom' } }
        }],
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function (w) {
                                return w.globals.series.reduce((a, b) => a + b, 0);
                            }
                        }
                    }
                }
            }
        },
        dataLabels: {
             enabled: true,
             formatter: function (val, opts) {
                return opts.w.config.series[opts.seriesIndex] + " (" + val.toFixed(1) + "%)"
             }
        }
    };
    const chartDonut = new ApexCharts(document.querySelector("#chartDonut"), optionsDonut);
    chartDonut.render();

    // =======================================================
    // GRÁFICO 2: Tendência de Uploads (Linha)
    // =======================================================
    const optionsLinha = {
        chart: {
            type: 'line',
            height: 350,
            toolbar: { show: false }
        },
        series: [{
            name: 'Novos Documentos',
            data: seriesLinha
        }],
        xaxis: {
            categories: labelsLinha, 
            title: { text: 'Mês de Referência' }
        },
        yaxis: {
            title: { text: 'Quantidade de Documentos' }
        },
        stroke: {
            curve: 'smooth'
        },
        markers: {
            size: 4
        },
        colors: ['#00E396']
    };
    const chartLinha = new ApexCharts(document.querySelector("#chartLinha"), optionsLinha);
    chartLinha.render();

});
</script>

<?php
// 5. INCLUSÃO DO FOOTER

include 'footer.php';
// Fecha a conexão com o banco de dados
$conn->close();
?>
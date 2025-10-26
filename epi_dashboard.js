
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
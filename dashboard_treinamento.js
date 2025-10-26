
    document.addEventListener('DOMContentLoaded', () => {
        const filterForm = document.getElementById('filter-form');
        const tableBody = document.querySelector('#treinamentos-table tbody');
        const totalVencidosElem = document.getElementById('total-vencidos');
        const totalProximoElem = document.getElementById('total-proximo');
        const totalPrazoElem = document.getElementById('total-prazo');
        const graficoTreinamentosCanvas = document.getElementById('grafico-resumo').getContext('2d');
        
        const prevPageBtn = document.getElementById('prev-page');
        const nextPageBtn = document.getElementById('next-page');
        const pageInfoElem = document.getElementById('page-info');

        const itemsPerPage = 10;
        let currentPage = 1;
        let allTreinamentos = [];
        let graficoTreinamentos = null;

        // Função para buscar os dados via AJAX
        const fetchData = async (filters = {}) => {
            try {
                const urlParams = new URLSearchParams(filters).toString();
                const response = await fetch(`dashboard.php?${urlParams}`);
                const data = await response.json();

                if (data.error) {
                    console.error("Erro na API:", data.error);
                    return;
                }

                allTreinamentos = data.treinamentos;
                currentPage = 1; // Reseta para a primeira página após uma nova busca
                renderTable();
                updatePaginationControls(data.total_pages);
                updateSummary(data.dados_graficos);
                renderChart(data.dados_graficos);

            } catch (error) {
                console.error("Erro ao buscar dados:", error);
            }
        };

        // Função para renderizar a tabela com os dados da página atual
        const renderTable = () => {
            tableBody.innerHTML = '';
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const treinamentosPaginados = allTreinamentos.slice(startIndex, endIndex);

            const hoje = new Date();
            const proximoMes = new Date();
            proximoMes.setMonth(hoje.getMonth() + 1);

            treinamentosPaginados.forEach(treinamento => {
                const row = document.createElement('tr');
                const dataVencimento = new Date(treinamento.data_vencimento);

                let statusText = '';
                let statusClass = '';

                if (dataVencimento < hoje) {
                    statusText = 'Vencido';
                    statusClass = 'vencido';
                } else if (dataVencimento >= hoje && dataVencimento <= proximoMes) {
                    statusText = 'Próximo a Vencer';
                    statusClass = 'proximo';
                } else {
                    statusText = 'Dentro do Prazo';
                    statusClass = 'dentro-prazo';
                }

                row.innerHTML = `
                    <td>${treinamento.id_treinamento}</td>
                    <td>${treinamento.nome_tipo_treinamento}</td>
                    <td>${treinamento.nome_colaborador}</td>
                    <td>${treinamento.data_vencimento}</td>
                    <td><span class="status ${statusClass}">${statusText}</span></td>
                `;
                tableBody.appendChild(row);
            });
        };

        // Função para atualizar o resumo de totais
        const updateSummary = (dadosGraficos) => {
            totalVencidosElem.textContent = dadosGraficos.vencidos;
            totalProximoElem.textContent = dadosGraficos.proximo_a_vencer;
            totalPrazoElem.textContent = dadosGraficos.dentro_do_prazo;
        };

        // Função para renderizar o gráfico
        const renderChart = (dadosGraficos) => {
            if (graficoTreinamentos) {
                graficoTreinamentos.destroy(); // Destroi o gráfico anterior
            }

            graficoTreinamentos = new Chart(graficoTreinamentosCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Vencidos', 'Próximo a Vencer', 'Dentro do Prazo'],
                    datasets: [{
                        data: [dadosGraficos.vencidos, dadosGraficos.proximo_a_vencer, dadosGraficos.dentro_do_prazo],
                        backgroundColor: ['#dc3545', '#ffc107', '#28a745'],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = Object.values(dadosGraficos).reduce((acc, val) => acc + (Number.isInteger(Number(val)) ? Number(val) : 0), 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(2) + '%' : '0%';
                                    return `${label}: ${value} (${percentage})`;
                                }
                            }
                        }
                    }
                }
            });
        };

        // Lógica de Paginação
        const updatePaginationControls = (totalPages) => {
            pageInfoElem.textContent = `Página ${currentPage} de ${totalPages}`;
            prevPageBtn.disabled = currentPage === 1;
            nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
        };

        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
                updatePaginationControls();
            }
        });

        nextPageBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(allTreinamentos.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
                updatePaginationControls(totalPages);
            }
        });

        // Evento de envio do formulário de filtro
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            const filters = Object.fromEntries(formData.entries());
            fetchData(filters);
        });

        // Inicia o carregamento dos dados ao carregar a página
        fetchData();
    });

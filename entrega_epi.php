<?php
// =================================================================
// ENTREGA_EPI.PHP - Registro de Múltiplos EPIs em uma Transação
// =================================================================

// 🚨 ATENÇÃO: Altere estas credenciais conforme seu ambiente!
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst'); 
define('DB_PORT', 3306); 

$mensagem = '';
$mensagem_tipo = ''; // 'success' ou 'error'

// Conexão com o MySQL usando a extensão mysqli
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die("Erro Crítico de Conexão: " . htmlspecialchars($conn->connect_error));
}
$conn->set_charset("utf8mb4");

// ------------------------------------------------
// FUNÇÃO DE CARREGAMENTO DE DADOS PARA O FORMULÁRIO
// ------------------------------------------------

// Carrega Colaboradores
$colaboradores = [];
$result_colab = $conn->query("SELECT id_colaborador, nome, matricula FROM colaboradores ORDER BY nome");
while ($row = $result_colab->fetch_assoc()) {
    $colaboradores[] = $row;
}

// Carrega EPIs
$epis_disponiveis = [];
$result_epis = $conn->query("SELECT id_epi, nome_epi, estoque, qnt_minima, ca FROM epis ORDER BY nome_epi");
while ($row = $result_epis->fetch_assoc()) {
    // Adiciona o estoque para validação via JS/PHP
    $epis_disponiveis[] = $row;
}
$epis_json = json_encode($epis_disponiveis); // Passa os dados de estoque para o JS

// ------------------------------------------------
// 1. LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (AUTO-SUBMIT)
// ------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_entrega'])) {
    
    $id_colaborador = intval($_POST['id_colaborador']);
    $observacao = $conn->real_escape_string($_POST['observacao']);
    $itens_entrega = $_POST['epis'] ?? [];
    
    if (empty($itens_entrega) || $id_colaborador <= 0) {
        $mensagem = "Selecione um colaborador e adicione pelo menos um EPI.";
        $mensagem_tipo = 'error';
    } else {
        // INICIA A TRANSAÇÃO SQL
        $conn->begin_transaction();
        $sucesso = true;

        try {
            // A. Insere o Header da Entrega (Tabela entregas)
            $stmt_header = $conn->prepare("INSERT INTO entregas (id_colaborador, data_entrega, tipo_movimentacao, observacao) VALUES (?, NOW(), 'ENTREGA', ?)");
            $stmt_header->bind_param("is", $id_colaborador, $observacao);
            $stmt_header->execute();
            $id_entrega = $conn->insert_id;
            $stmt_header->close();

            // B. Processa cada item e atualiza o estoque
            foreach ($itens_entrega as $item) {
                $id_epi = intval($item['id_epi']);
                $quantidade = intval($item['quantidade']);
                
                if ($quantidade <= 0) continue; 
                
                // 1. Verifica se há estoque suficiente antes de prosseguir
                $stmt_check = $conn->prepare("SELECT estoque, nome_epi FROM epis WHERE id_epi = ?");
                $stmt_check->bind_param("i", $id_epi);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                if ($result_check && $result_check['estoque'] >= $quantidade) {
                    
                    // 2. Insere o Detalhe da Entrega (Tabela detalhes_entrega)
                    $stmt_detalhe = $conn->prepare("INSERT INTO detalhes_entrega (id_entrega, id_epi, quantidade_movimentada) VALUES (?, ?, ?)");
                    $stmt_detalhe->bind_param("iii", $id_entrega, $id_epi, $quantidade);
                    $stmt_detalhe->execute();
                    $stmt_detalhe->close();
                    
                    // 3. Atualiza o Estoque (Tabela epis)
                    $stmt_update = $conn->prepare("UPDATE epis SET estoque = estoque - ? WHERE id_epi = ?");
                    $stmt_update->bind_param("ii", $quantidade, $id_epi);
                    $stmt_update->execute();
                    $stmt_update->close();

                } else {
                    $sucesso = false;
                    $mensagem = "Falha: Estoque insuficiente para o EPI '{$result_check['nome_epi']}'.";
                    break; // Sai do loop e aciona o ROLLBACK
                }
            }

            // C. Finaliza a Transação
            if ($sucesso) {
                $conn->commit();
                $mensagem = "Entrega de EPI(s) registrada com sucesso!";
                $mensagem_tipo = 'success';
            } else {
                $conn->rollback();
                $mensagem_tipo = 'error';
            }

        } catch (Exception $e) {
            $conn->rollback();
            $mensagem = "Erro interno ao registrar a entrega: " . $e->getMessage();
            $mensagem_tipo = 'error';
        }
    }
}
$conn->close(); // Fecha a conexão após todo o processamento
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Entrega de EPI - SST</title>
    <style>
        /* Estilos básicos (os mesmos do dashboard para consistência) */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background-color: white; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); border-radius: 8px; }
        .main-header { background-color: #007bff; color: white; padding: 15px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 1300px; margin: 0 auto; padding: 0 20px; }
        .main-header h1 { margin: 0; font-size: 1.5em; }

        h2 { border-bottom: 2px solid #ddd; padding-bottom: 5px; margin-top: 20px; }
        
        /* Estilos do Formulário */
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        textarea { resize: vertical; }
        button[type="submit"], #adicionar-epi { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; font-weight: bold; transition: background-color 0.2s; }
        button[type="submit"]:hover, #adicionar-epi:hover { background-color: #218838; }
        
        /* Estilos do Item Dinâmico */
        .epi-item { border: 1px solid #eee; padding: 15px; margin-top: 10px; border-radius: 4px; background-color: #f9f9f9; display: flex; gap: 10px; align-items: flex-end; }
        .epi-item > * { flex-grow: 1; }
        .epi-item .input-group { flex-basis: 45%; }
        .remover-item { background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-top: 0; flex-grow: 0; }
        
        /* Estilos de Mensagens */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Alerta de Estoque */
        .estoque-alerta { color: #dc3545; font-size: 0.8em; margin-top: 5px; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <h1>🛡️ Registro de Entrega de EPI</h1>
            <nav>
                <a href="epi_dashboard.php">Voltar ao Dashboard</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Transação de Entrega</h2>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem_tipo; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="entrega_epi.php">
            
            <label for="id_colaborador">Colaborador Recebedor:</label>
            <select name="id_colaborador" id="id_colaborador" required>
                <option value="">-- Selecione o Colaborador --</option>
                <?php foreach ($colaboradores as $colab): ?>
                    <option value="<?php echo htmlspecialchars($colab['id_colaborador']); ?>">
                        <?php echo htmlspecialchars($colab['nome']) . " (" . htmlspecialchars($colab['matricula']) . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <h3>Itens para Entrega</h3>
            
            <div id="lista-epis">
                </div>
            
            <button type="button" id="adicionar-epi">➕ Adicionar Mais EPI</button>

            <label for="observacao">Observações (Opcional):</label>
            <textarea name="observacao" id="observacao" rows="3" placeholder="Ex: Entrega de rotina para roçagem da área externa."></textarea>
            
            <button type="submit" name="registrar_entrega">📦 Finalizar e Registrar Entrega</button>
        </form>

    </div>

    <template id="epi-template">
        <div class="epi-item" data-index="INDEX_PLACEHOLDER">
            <div class="input-group">
                <label for="epi_select_INDEX_PLACEHOLDER">EPI:</label>
                <select name="epis[INDEX_PLACEHOLDER][id_epi]" id="epi_select_INDEX_PLACEHOLDER" required class="epi-select">
                    <option value="">-- Selecione o EPI --</option>
                    <?php foreach ($epis_disponiveis as $epi): ?>
                        <option 
                            value="<?php echo htmlspecialchars($epi['id_epi']); ?>" 
                            data-estoque="<?php echo htmlspecialchars($epi['estoque']); ?>"
                            data-nome="<?php echo htmlspecialchars($epi['nome_epi']); ?>"
                        >
                            <?php echo htmlspecialchars($epi['nome_epi']) . " (Estoque: " . htmlspecialchars($epi['estoque']) . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="input-group">
                <label for="qnt_input_INDEX_PLACEHOLDER">Quantidade:</label>
                <input type="number" name="epis[INDEX_PLACEHOLDER][quantidade]" id="qnt_input_INDEX_PLACEHOLDER" min="1" required class="qnt-input">
                <div class="estoque-alerta"></div>
            </div>
            
            <button type="button" class="remover-item">Remover</button>
        </div>
    </template>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listaEPIs = document.getElementById('lista-epis');
            const templateEPI = document.getElementById('epi-template');
            const btnAdicionar = document.getElementById('adicionar-epi');
            let itemIndex = 0; // Contador global de índices
            const estoques = <?php echo $epis_json; ?>;

            // Função para adicionar um novo bloco de EPI
            function adicionarEPI() {
                const clone = templateEPI.content.firstElementChild.cloneNode(true);
                
                // Substitui o placeholder do índice em todos os atributos e nomes
                clone.innerHTML = clone.innerHTML.replace(/INDEX_PLACEHOLDER/g, itemIndex);
                clone.setAttribute('data-index', itemIndex);
                
                // Adiciona o novo bloco à lista
                listaEPIs.appendChild(clone);

                // Configura o evento para remoção
                clone.querySelector('.remover-item').addEventListener('click', function() {
                    clone.remove();
                    recalcularAlertas(); // Recalcula estoque após remoção
                });

                // Configura o listener para validação de estoque no novo item
                const selectElement = clone.querySelector('.epi-select');
                const quantityElement = clone.querySelector('.qnt-input');
                
                selectElement.addEventListener('change', recalcularAlertas);
                quantityElement.addEventListener('input', recalcularAlertas);
                
                itemIndex++;
            }

            // Função de Validação de Estoque
            function recalcularAlertas() {
                const itens = listaEPIs.querySelectorAll('.epi-item');
                const estoqueUtilizado = {}; // { id_epi: quantidade_total_retirada }

                // 1. Calcula a demanda total para cada EPI
                itens.forEach(item => {
                    const select = item.querySelector('.epi-select');
                    const input = item.querySelector('.qnt-input');
                    
                    const id = select.value;
                    const qnt = parseInt(input.value) || 0;
                    
                    if (id) {
                        estoqueUtilizado[id] = (estoqueUtilizado[id] || 0) + qnt;
                    }
                });

                // 2. Verifica a disponibilidade e exibe alertas
                itens.forEach(item => {
                    const select = item.querySelector('.epi-select');
                    const input = item.querySelector('.qnt-input');
                    const alertaDiv = item.querySelector('.estoque-alerta');
                    
                    const id = select.value;
                    const qnt = parseInt(input.value) || 0;
                    alertaDiv.textContent = ''; // Limpa alertas anteriores

                    if (!id) return;

                    const epiData = estoques.find(e => e.id_epi == id);
                    if (!epiData) return;
                    
                    const totalRetirado = estoqueUtilizado[id];
                    const estoqueAtual = parseInt(epiData.estoque);

                    // Verifica se o estoque TOTAL daquele EPI é suficiente para o total pedido em TODOS os itens
                    if (totalRetirado > estoqueAtual) {
                        alertaDiv.textContent = `Atenção: Estoque (Disponível: ${estoqueAtual}) insuficiente! Total pedido: ${totalRetirado}`;
                        input.setCustomValidity('Estoque insuficiente para a retirada total.');
                    } else if (qnt > 0 && qnt <= estoqueAtual && totalRetirado <= estoqueAtual) {
                        // Se estiver OK e o estoque estiver próximo do mínimo, pode dar um aviso
                         const restante = estoqueAtual - totalRetirado;
                         if (restante <= epiData.qnt_minima) {
                            alertaDiv.textContent = `AVISO: Após esta entrega, o estoque restante será CRÍTICO: ${restante} (Mínimo: ${epiData.qnt_minima})`;
                         }
                        input.setCustomValidity(''); // Limpa a validação se estiver OK
                    } else {
                        input.setCustomValidity('');
                    }
                });
            }


            // ------------------
            // INICIALIZAÇÃO
            // ------------------
            btnAdicionar.addEventListener('click', adicionarEPI);
            
            // Adiciona o primeiro item na inicialização
            adicionarEPI();
            
            // Adiciona listener para recalcular alertas (útil para quando o usuário edita manualmente o estoque)
            listaEPIs.addEventListener('change', recalcularAlertas);
            listaEPIs.addEventListener('input', recalcularAlertas);
        });
    </script>
</body>
</html>
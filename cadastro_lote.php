<?php
// =================================================================
// 1. INCLUSÃO DO ARQUIVO DE CONEXÃO
// =================================================================
include 'conexao.php'; 

$mensagem_status = '';
$pagina_atual = 'cadastro_lote.php'; 

// Define os padrões
define('DEFAULT_SITUACAO_ID', 1); 
define('DEFAULT_STATUS', 1);       

// IDs PADRÃO DE CHAVE ESTRANGEIRA (CONFIRMADOS PELO USUÁRIO)
const ID_FUNCAO_PADRAO = 200100003; 
const ID_SETOR_PADRAO = 29;         
const ID_EQUIPE_PADRAO = 147;       

// =================================================================
// 2. LÓGICA DE CADASTRO EM LOTE (Com correção do CPF para NULL)
// =================================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Filtramos o POST para remover linhas completamente vazias ANTES de processar
    $nomes = array_filter($_POST['nome'] ?? [], function($value) { return trim($value) !== ''; });

    $matriculas = $_POST['matricula'] ?? [];
    $datas_admissao = $_POST['data_admissao'] ?? [];
    
    $registros_sucesso = 0;
    $registros_ignorados = 0;
    $erros_detalhados = []; 
    
    // Variáveis locais para bind_param
    $situacao_padrao = DEFAULT_SITUACAO_ID;
    $status_padrao = DEFAULT_STATUS;
    $funcao_padrao = ID_FUNCAO_PADRAO;
    $setor_padrao = ID_SETOR_PADRAO;
    $equipe_padrao = ID_EQUIPE_PADRAO;
    
    // CORREÇÃO CRUCIAL 1: Usar NULL para o CPF (opcional e único)
    $cpf_padrao = NULL; 
    
    // CORREÇÃO CRUCIAL 2: Sexo definido como 'Masculino' (obrigatório e padrão)
    $sexo_padrao = 'Masculino';

    if (count($nomes) > 0) {
        
        $sql = "INSERT INTO colaboradores (nome, matricula, data_admissao, cpf, sexo, setor_id, funcao_id, equipe_id, situacao_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             $mensagem_status = "<div class='alert alert-danger'>❌ Erro na preparação do SQL para LOTE: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            foreach (array_keys($nomes) as $key_original) {
                
                $nome_original = trim($nomes[$key_original]);
                $matricula_val = trim($matriculas[$key_original] ?? '');
                $data_admissao_val = trim($datas_admissao[$key_original] ?? '');
                
                // Usamos o índice $key_original + 1 para a numeração da linha na mensagem de erro
                $linha_numero = $key_original + 1;

                // --- LÓGICA DE VALIDAÇÃO E IGNORAR (continue) ---
                $campos_faltando = [];

                if (empty($nome_original)) $campos_faltando[] = 'Nome';
                if (empty($matricula_val)) $campos_faltando[] = 'Matrícula';
                if (empty($data_admissao_val)) $campos_faltando[] = 'Data de Admissão';

                if (!empty($campos_faltando)) {
                    $erros_detalhados[] = "Linha **#{$linha_numero}** (`{$nome_original}`): Faltando: " . implode(', ', $campos_faltando);
                    $registros_ignorados++;
                    continue; 
                }
                
                // Processamento de dados
                $nome_db = mb_strtoupper($conn->real_escape_string($nome_original), 'UTF-8');
                
                // Bind_param: sssssiiiii (10 parâmetros)
                $stmt->bind_param("sssssiiiii", 
                    $nome_db,                 // s
                    $matricula_val,           // s
                    $data_admissao_val,       // s
                    $cpf_padrao,              // s (NULL)
                    $sexo_padrao,             // s ('Masculino')
                    $setor_padrao,            // i
                    $funcao_padrao,           // i
                    $equipe_padrao,           // i
                    $situacao_padrao,         // i
                    $status_padrao            // i
                );
                
                if ($stmt->execute()) {
                    $registros_sucesso++;
                } else {
                    $erros_detalhados[] = "Linha **#{$linha_numero}** (`{$nome_db}`): Erro do DB: " . htmlspecialchars($stmt->error);
                    $registros_ignorados++;
                }
            }
            $stmt->close();
        }
    }
    
    $total_processado = $registros_sucesso + $registros_ignorados;
    
    if ($registros_sucesso > 0) {
        $mensagem_status .= "<div class='alert alert-success'>✅ Processamento de LOTE finalizado. **Sucesso:** {$registros_sucesso} registros.</div>";
    }

    if ($registros_ignorados > 0) {
        $mensagem_status .= "<div class='alert alert-warning'>⚠️ **Registros Ignorados/Com Erro:** {$registros_ignorados} linhas. Verifique os detalhes abaixo para corrigir ou excluir as linhas:<ul>";
        foreach ($erros_detalhados as $erro) {
             $mensagem_status .= "<li>{$erro}</li>";
        }
        $mensagem_status .= "</ul></div>";
    }
    
    if ($total_processado === 0 && count($_POST['nome'] ?? []) > 0) {
       $mensagem_status .= "<div class='alert alert-warning'>⚠️ Nenhuma linha preenchida com dados válidos para processamento.</div>";
    }

    // Se houve sucesso total no cadastro, limpamos os dados do POST para não repopular a tela
    if ($registros_sucesso > 0 && $registros_ignorados === 0) {
        $_POST = [];
    }
}

if (isset($conn)) {
    $conn->close();
}

$pageTitle = "Cadastro de Colaborador em Lote";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <style>
        /* Estilos CSS - Mantidos e Ajustados */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .main-header { background-color: #007bff; color: white; padding: 15px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 900px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .main-header h1 { margin: 0; font-size: 1.5em; }
        .main-header nav a { color: white; text-decoration: none; margin-left: 20px; padding: 5px 10px; border-radius: 4px; transition: background-color 0.2s; }
        .main-header nav a:hover { background-color: rgba(255, 255, 255, 0.2); }
        .menu-toggle { display: none; } 

        .form-section { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); margin-top: 30px; }
        .form-section h2 { color: #007bff; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5a6268; }

        /* --- Estilos de Lote --- */
        .lote-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .lote-table th, .lote-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .lote-table th { background-color: #f8f9fa; }
        .lote-table input[type="text"], .lote-table input[type="date"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; width: 100%; }
        .add-row-btn { margin-top: 10px; background-color: #28a745; color: white; }
        .add-row-btn:hover { background-color: #218838; }
        
        /* Estilo para o botão de Excluir - PEQUENO */
        .btn-delete { 
            background-color: #dc3545; 
            color: white; 
            padding: 4px 8px; 
            font-size: 0.7em; 
            line-height: 1; 
            font-weight: normal; 
            border-radius: 3px;
        }
        .btn-delete:hover { background-color: #c82333; }

        /* --- Estilos dos Alertas --- */
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; font-weight: 500; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
        .alert-warning ul { margin: 10px 0 0 0; padding-left: 20px; } 

    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <h1>Sistema SST</h1>
            <nav id="main-nav">
                <a href="index.php">Início</a>
                <a href="dashboard_treinamento.php">Treinamentos</a>
                <a href="index_colaboradores.php">Colaboradores</a>
                <a href="cadastro_treinamento.php">Novo Treinamento</a>
                <a href="relatorios.php">Relatórios</a>
            </nav>
        </div>
    </header>

    <main class="container">
        
        <section class="form-section">
            <h2><?php echo $pageTitle; ?></h2>

            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="cadastrar_colaborador.php" class="btn btn-secondary">
                    Voltar para Cadastro Individual
                </a>
            </div>

            <?php echo $mensagem_status; ?>

            <p class="alert alert-warning">
                ⚠️ **Modo LOTE:** Apenas **Nome, Matrícula e Data de Admissão** são obrigatórios para cada linha. Linhas incompletas serão ignoradas. O campo **SEXO** será preenchido automaticamente como **Masculino**.
            </p>

            <form action="<?php echo $pagina_atual; ?>" method="POST">
                
                <table class="lote-table" id="lote-tabela">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Nome Completo (*)</th>
                            <th style="width: 20%;">Matrícula (*)</th>
                            <th style="width: 20%;">Data de Admissão (*)</th>
                            <th style="width: 10%;">Ação</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Verifica se houve submissão com erro para repopular os campos
                        $linhas_a_exibir = 1;
                        if (!empty($erros_detalhados) && count($_POST['nome'] ?? []) > 0) {
                            $linhas_a_exibir = count($_POST['nome']);
                        }
                        
                        // Inicializa a variável para o contador de linhas no JS
                        $num_linhas_form = 0;
                        
                        for ($i = 0; $i < $linhas_a_exibir; $i++): 
                            $nome_val = htmlspecialchars($_POST['nome'][$i] ?? '');
                            $matricula_val = htmlspecialchars($_POST['matricula'][$i] ?? '');
                            $data_admissao_val = htmlspecialchars($_POST['data_admissao'][$i] ?? '');
                            $num_linhas_form = $i + 1;
                        ?>
                        <tr id="linha-<?php echo $i; ?>">
                            <td><input type="text" name="nome[]" placeholder="Nome do Colaborador" value="<?php echo $nome_val; ?>"></td>
                            <td><input type="text" name="matricula[]" placeholder="Matrícula" value="<?php echo $matricula_val; ?>"></td>
                            <td><input type="date" name="data_admissao[]" value="<?php echo $data_admissao_val; ?>"></td>
                            <td><button type="button" class="btn btn-delete" onclick="excluirLinha(this)">Excluir</button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between;">
                    <button type="button" class="btn add-row-btn" onclick="adicionarLinha()">+ Adicionar Mais Linhas</button>
                    <button type="submit" class="btn btn-primary">Salvar Lote de Colaboradores</button>
                </div>
            </form>

            <script>
                // O contador de linhas inicia com o número de linhas atualmente visíveis no HTML
                var linha_count = <?php echo $num_linhas_form; ?>;

                // Função para adicionar mais linhas dinamicamente
                function adicionarLinha() {
                    var tableBody = document.querySelector('#lote-tabela tbody');
                    var newRow = tableBody.insertRow();
                    newRow.id = `linha-${linha_count}`;
                    newRow.innerHTML = `
                        <td><input type="text" name="nome[]" placeholder="Nome do Colaborador"></td>
                        <td><input type="text" name="matricula[]" placeholder="Matrícula"></td>
                        <td><input type="date" name="data_admissao[]"></td>
                        <td><button type="button" class="btn btn-delete" onclick="excluirLinha(this)">Excluir</button></td>
                    `;
                    linha_count++;
                }
                
                // Função para excluir a linha
                function excluirLinha(button) {
                    var row = button.parentNode.parentNode;
                    var tableBody = row.parentNode;

                    // Verifica se é a última linha visível na tabela
                    if (tableBody.rows.length === 1) {
                         alert("É necessário manter pelo menos uma linha. Os campos foram limpos.");
                         // Limpa os campos da última linha em vez de remover
                         row.querySelector('input[name^="nome"]').value = '';
                         row.querySelector('input[name^="matricula"]').value = '';
                         row.querySelector('input[name^="data_admissao"]').value = '';
                         return;
                    }

                    // Remove a linha se houver mais de uma
                    if (confirm("Tem certeza que deseja remover esta linha do cadastro?")) {
                        row.remove();
                    }
                }
                
                function toggleMenu() {
                    var nav = document.getElementById('main-nav');
                    nav.classList.toggle('active');
                }
            </script>

        </section>

    </main>

</body>
</html>
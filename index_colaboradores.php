<?php
// =================================================================
// 1. INCLUSÃO DO ARQUIVO DE CONEXÃO E FUNÇÃO DE BUSCA
// =================================================================
include 'conexao.php'; // Inclui $conn. Se falhar, o script para.

$mensagem_status = '';

/**
 * Função para buscar dados de uma tabela de referência
 * @param mysqli $conn Objeto de conexão ao banco de dados.
 * @param string $tabela Nome da tabela (ex: 'setores').
 * @param string $id_coluna Nome da coluna ID (ex: 'id_setor').
 * @param string $desc_coluna Nome da coluna de descrição (ex: 'nome_setor').
 * @return array Um array associativo com ID => Descrição.
 */
function buscar_referencia($conn, $tabela, $id_coluna, $desc_coluna) {
    $dados = [];
    $sql = "SELECT {$id_coluna}, {$desc_coluna} FROM {$tabela} ORDER BY {$desc_coluna} ASC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dados[$row[$id_coluna]] = $row[$desc_coluna];
        }
    }
    return $dados;
}

// =================================================================
// 2. BUSCA DE DADOS DE REFERÊNCIA PARA OS SELECTS
// =================================================================

// ⚠️ ATENÇÃO: Se as tabelas abaixo não existirem, os arrays estarão vazios.
$setores = buscar_referencia($conn, 'setores', 'id_setor', 'nome_setor');
$funcoes = buscar_referencia($conn, 'funcoes', 'id_funcao', 'nome_funcao');
$equipes = buscar_referencia($conn, 'equipes', 'id_equipe', 'nome_equipe');
$situacoes = buscar_referencia($conn, 'situacao', 'id_situacao', 'desc_situacao');


// =================================================================
// 3. LÓGICA DE CADASTRO (Após envio do formulário)
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitização e coleta de TODOS os campos da tabela
    $nome = $conn->real_escape_string($_POST['nome'] ?? '');
    $matricula = $conn->real_escape_string($_POST['matricula'] ?? '');
    $data_admissao = $conn->real_escape_string($_POST['data_admissao'] ?? '');
    $cpf = $conn->real_escape_string($_POST['cpf'] ?? '');
    $sexo = $conn->real_escape_string($_POST['sexo'] ?? '');
    // Campos de chave estrangeira (IDs) - Eles vêm do SELECT, mas ainda são INTs no DB
    $setor_id = (int) ($_POST['setor_id'] ?? 0); 
    $funcao_id = (int) ($_POST['funcao_id'] ?? 0);
    $equipe_id = (int) ($_POST['equipe_id'] ?? 0);
    $situacao_id = (int) ($_POST['situacao_id'] ?? 0);
    $status = (int) ($_POST['status'] ?? 0); 
    
    // Validação básica
    if (empty($nome) || empty($cpf) || empty($data_admissao) || empty($matricula)) {
        $mensagem_status = "<div class='alert alert-warning'>⚠️ Por favor, preencha todos os campos obrigatórios (*).</div>";
    } elseif ($setor_id == 0 || $funcao_id == 0 || $situacao_id == 0) {
        // Validação adicional para garantir que um ID de referência foi selecionado (se o array não for vazio)
        $mensagem_status = "<div class='alert alert-warning'>⚠️ Por favor, selecione um Setor, Função e Situação válidos.</div>";
    } else {
        // 2. Prepara a consulta SQL
        $sql = "INSERT INTO colaboradores (nome, matricula, data_admissao, cpf, sexo, setor_id, funcao_id, equipe_id, situacao_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             $mensagem_status = "<div class='alert alert-danger'>❌ Erro na preparação do SQL: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            // Tipos de dados: sssssiiiii (5 strings, 5 integers)
            $stmt->bind_param("sssssiiiii", 
                $nome, $matricula, $data_admissao, $cpf, $sexo, 
                $setor_id, $funcao_id, $equipe_id, $situacao_id, $status
            );
            
            // 3. Executa a consulta
            if ($stmt->execute()) {
                $mensagem_status = "<div class='alert alert-success'>✅ Colaborador <strong>" . htmlspecialchars($nome) . "</strong> cadastrado com sucesso!</div>";
                // Limpa os dados do POST para resetar o formulário
                $_POST = array(); 
            } else {
                $mensagem_status = "<div class='alert alert-danger'>❌ Erro ao cadastrar: " . htmlspecialchars($stmt->error) . "</div>";
            }
            
            $stmt->close();
        }
    }
}

// IMPORTANTE: Fechamos a conexão no final do script
if (isset($conn)) {
    $conn->close();
}

// Título da página
$pageTitle = "Cadastro de Colaborador";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <style>
        /* Estilos base */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .main-header {
            background-color: #007bff;
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .main-header h1 {
            margin: 0;
            font-size: 1.5em;
        }
        .main-header nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .main-header nav a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .menu-toggle { display: none; } 

        /* --- Estilos do Formulário --- */
        .form-section {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        .form-section h2 {
            color: #007bff;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Duas colunas */
            gap: 20px;
        }
        .full-width {
            grid-column: span 2;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; 
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            grid-column: span 2; 
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        /* --- Estilos dos Alertas --- */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            font-weight: 500;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
        }

        /* --- MEDIA QUERIES (Responsivo) --- */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; }
            .main-header nav { display: none; flex-direction: column; width: 100%; margin-top: 10px; }
            .main-header nav a { margin: 5px 0; padding: 10px; width: 100%; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
            .menu-toggle { display: block; color: white; font-size: 1.5em; cursor: pointer; position: absolute; top: 15px; right: 20px; }
            .main-header nav.active { display: flex; }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <h1>Sistema SST</h1>
            <div class="menu-toggle" onclick="toggleMenu()">☰</div> 
            
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

            <?php echo $mensagem_status; ?>

            <form action="cadastro_colaborador.php" method="POST">
                
                <div class="form-grid">
                    
                    <div class="form-group full-width">
                        <label for="nome">Nome Completo (*)</label>
                        <input type="text" id="nome" name="nome" required 
                               value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>"
                               placeholder="Ex: João da Silva">
                    </div>

                    <div class="form-group">
                        <label for="matricula">Matrícula (*)</label>
                        <input type="text" id="matricula" name="matricula" required 
                               value="<?php echo htmlspecialchars($_POST['matricula'] ?? ''); ?>"
                               placeholder="Ex: 001234">
                    </div>
                    
                    <div class="form-group">
                        <label for="cpf">CPF (Somente números) (*)</label>
                        <input type="text" id="cpf" name="cpf" required maxlength="14"
                               value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>"
                               placeholder="12345678900">
                    </div>
                    
                    <div class="form-group">
                        <label for="data_admissao">Data de Admissão (*)</label>
                        <input type="date" id="data_admissao" name="data_admissao" required 
                               value="<?php echo htmlspecialchars($_POST['data_admissao'] ?? date('Y-m-d')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sexo">Sexo</label>
                        <select id="sexo" name="sexo">
                            <option value="">Selecione</option>
                            <option value="Masculino" <?php echo (($_POST['sexo'] ?? '') == 'Masculino' ? 'selected' : ''); ?>>Masculino</option>
                            <option value="Feminino" <?php echo (($_POST['sexo'] ?? '') == 'Feminino' ? 'selected' : ''); ?>>Feminino</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="setor_id">Setor</label>
                        <select id="setor_id" name="setor_id">
                            <option value="0">--- Selecione o Setor ---</option>
                            <?php 
                            $selected_setor = (int) ($_POST['setor_id'] ?? 0);
                            foreach ($setores as $id => $nome): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php echo ($id == $selected_setor ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nome); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="funcao_id">Função</label>
                        <select id="funcao_id" name="funcao_id">
                            <option value="0">--- Selecione a Função ---</option>
                            <?php 
                            $selected_funcao = (int) ($_POST['funcao_id'] ?? 0);
                            foreach ($funcoes as $id => $nome): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php echo ($id == $selected_funcao ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nome); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="equipe_id">Equipe</label>
                        <select id="equipe_id" name="equipe_id">
                            <option value="0">--- Selecione a Equipe ---</option>
                            <?php 
                            $selected_equipe = (int) ($_POST['equipe_id'] ?? 0);
                            foreach ($equipes as $id => $nome): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php echo ($id == $selected_equipe ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nome); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="situacao_id">Situação</label>
                        <select id="situacao_id" name="situacao_id">
                            <option value="0">--- Selecione a Situação ---</option>
                            <?php 
                            $selected_situacao = (int) ($_POST['situacao_id'] ?? 0);
                            foreach ($situacoes as $id => $nome): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php echo ($id == $selected_situacao ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nome); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status (Ativo/Inativo)</label>
                        <select id="status" name="status">
                            <option value="1" <?php echo (($_POST['status'] ?? 1) == 1 ? 'selected' : ''); ?>>1 - Ativo</option>
                            <option value="0" <?php echo (($_POST['status'] ?? 1) == 0 ? 'selected' : ''); ?>>0 - Inativo</option>
                        </select>
                        <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">`tinyint(1)`: 1 para Ativo, 0 para Inativo.</p>
                    </div>
                    
                    <div class="form-group full-width">
                        <p style="font-size: 0.8em; color: #6c757d;">(*) Campos obrigatórios.</p>
                    </div>


                    <div class="form-actions">
                        <a href="index_colaboradores.php" class="btn" style="background-color: #6c757d; color: white; text-decoration: none;">Voltar para Lista</a>
                        <button type="submit" class="btn btn-primary">Cadastrar Colaborador</button>
                    </div>
                </div>
            </form>

        </section>

    </main>
    
    <script>
        function toggleMenu() {
            var nav = document.getElementById('main-nav');
            nav.classList.toggle('active');
        }
    </script>

</body>
</html>
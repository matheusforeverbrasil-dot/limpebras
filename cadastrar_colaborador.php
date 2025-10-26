<?php
// =================================================================
// 1. INCLUSÃO DO ARQUIVO DE CONEXÃO E DEFINIÇÕES
// =================================================================
include 'conexao.php'; 

$mensagem_status = '';
$pagina_atual = 'cadastrar_colaborador.php'; 

// Define os IDs de Padrão (Valores confirmados no DB)
define('DEFAULT_SITUACAO_ID', 1); // ID para 'TRABALHANDO' (assumido)
define('DEFAULT_STATUS', 1);       // 1 para 'ATIVO'

// ======================================================================
// CORREÇÕES DE CHAVE ESTRANGEIRA (IDs Confirmados pelo usuário)
// ESTES VALORES FORAM CONFIRMADOS PARA EXISTIREM NAS TABELAS DE REFERÊNCIA.
// ======================================================================
const ID_FUNCAO_PADRAO = 200100003; // ID CONFIRMADO
const ID_SETOR_PADRAO = 29;         // ID CONFIRMADO
const ID_EQUIPE_PADRAO = 147;       // ID CONFIRMADO


/**
 * Função para buscar dados de uma tabela de referência
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
// NOTA: É necessário verificar se $conn está válida antes de usar. Assumimos que 'conexao.php'
// cuida disso ou que o script irá falhar elegantemente se não conectar.
$setores = buscar_referencia($conn, 'setores', 'id_setor', 'nome_setor');
$funcoes = buscar_referencia($conn, 'funcoes', 'id_funcao', 'nome_funcao');
$equipes = buscar_referencia($conn, 'equipes', 'id_equipe', 'nome_equipe');
$situacoes = buscar_referencia($conn, 'situacao', 'id_situacao', 'desc_situacao');


// =================================================================
// 3. LÓGICA DE CADASTRO INDIVIDUAL
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nome_original = $_POST['nome'] ?? '';
    // Conversão para Maiúsculas
    $nome = $conn->real_escape_string(mb_strtoupper($nome_original, 'UTF-8'));
    
    $matricula = $conn->real_escape_string($_POST['matricula'] ?? '');
    $data_admissao = $conn->real_escape_string($_POST['data_admissao'] ?? '');
    
    // --- CORREÇÃO DE CPF: Usar NULL se vazio para chaves UNIQUE opcionais ---
    // Removemos o real_escape_string pois NULL será tratado como tal no bind.
    $cpf = !empty($_POST['cpf']) ? $_POST['cpf'] : NULL;
    
    // --- CORREÇÃO DE SEXO: Usar 'Masculino' se vazio para evitar NOT NULL ---
    // O valor 0 (opção '--- Selecione ---') no HTML é considerado vazio (empty).
    $sexo = (!empty($_POST['sexo']) && $_POST['sexo'] !== '0') ? $_POST['sexo'] : 'Masculino';
    
    // -------------------------------------------------------------
    // --- Lógica de Correção da Chave Estrangeira de SETOR ---
    $setor_id_post = (int) ($_POST['setor_id'] ?? 0); 
    if ($setor_id_post === 0) {
        $setor_id = ID_SETOR_PADRAO; 
    } else {
        $setor_id = $setor_id_post;
    }
    // -------------------------------------------------------------
    
    // -------------------------------------------------------------
    // --- Lógica de Correção da Chave Estrangeira de FUNÇÃO ---
    $funcao_id_post = (int) ($_POST['funcao_id'] ?? 0);

    if ($funcao_id_post === 0) {
        $funcao_id = ID_FUNCAO_PADRAO; 
    } else {
        $funcao_id = $funcao_id_post;
    }
    // -------------------------------------------------------------

    // -------------------------------------------------------------
    // --- Lógica de Correção da Chave Estrangeira de EQUIPE ---
    $equipe_id_post = (int) ($_POST['equipe_id'] ?? 0); 

    if ($equipe_id_post === 0) {
        $equipe_id = ID_EQUIPE_PADRAO; 
    } else {
        $equipe_id = $equipe_id_post;
    }
    // -------------------------------------------------------------
    
    // Campos Padrão/Selecionado
    $situacao_id = (int) ($_POST['situacao_id'] ?? DEFAULT_SITUACAO_ID); 
    $status = (int) ($_POST['status'] ?? DEFAULT_STATUS); 
    
    // Validação (campos obrigatórios individuais)
    if (empty($nome_original) || empty($matricula) || empty($data_admissao)) {
        $mensagem_status = "<div class='alert alert-warning'>⚠️ Por favor, preencha Nome, Matrícula e Data de Admissão.</div>";
    } else {
        // Prepara a consulta SQL
        $sql = "INSERT INTO colaboradores (nome, matricula, data_admissao, cpf, sexo, setor_id, funcao_id, equipe_id, situacao_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             $mensagem_status = "<div class='alert alert-danger'>❌ Erro na preparação do SQL: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            // NÃO precisamos mais do tratamento de $cpf_db e $sexo_db para converter NULL para '',
            // porque agora $sexo é 'Masculino' e $cpf já é NULL ou string válida.
            
            // Tratamento especial: se $cpf é NULL, o mysqli bind_param para 's' (string) deve
            // ser capaz de lidar com isso. Se não lidar, o código abaixo pode ser usado, 
            // mas tentaremos primeiro passar a variável $cpf diretamente.
            
            // Tipos: s s s s s i i i i i (10 parâmetros)
            $stmt->bind_param("sssssiiiii", 
                $nome, $matricula, $data_admissao, $cpf, $sexo, 
                $setor_id, $funcao_id, $equipe_id, $situacao_id, $status
            );
            
            if ($stmt->execute()) {
                $mensagem_status = "<div class='alert alert-success'>✅ Colaborador <strong>" . htmlspecialchars($nome) . "</strong> cadastrado com sucesso!</div>";
                // Limpa os campos do formulário para evitar reenvio
                $_POST = array(); 
            } else {
                $mensagem_status = "<div class='alert alert-danger'>❌ Erro ao cadastrar: " . htmlspecialchars($stmt->error) . "</div>";
            }
            
            $stmt->close();
        }
    }
}

if (isset($conn)) {
    $conn->close();
}

$pageTitle = "Cadastro de Colaborador Individual";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <style>
        /* Estilos CSS - Mantidos */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        .main-header { background-color: #007bff; color: white; padding: 15px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 800px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .main-header h1 { margin: 0; font-size: 1.5em; }
        .main-header nav a { color: white; text-decoration: none; margin-left: 20px; padding: 5px 10px; border-radius: 4px; transition: background-color 0.2s; }
        .main-header nav a:hover { background-color: rgba(255, 255, 255, 0.2); }
        .menu-toggle { display: none; } 

        .form-section { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); margin-top: 30px; }
        .form-section h2 { color: #007bff; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="number"], .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: #007bff; outline: none; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }
        .form-actions { margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; grid-column: span 2; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5a6268; }

        /* Estilos dos Alertas */
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; font-weight: 500; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
        .alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }
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

            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="cadastro_lote.php" class="btn btn-secondary">
                    Efetuar Cadastro em Lote
                </a>
            </div>

            <?php echo $mensagem_status; ?>

            <form action="<?php echo $pagina_atual; ?>" method="POST">
                
                <div class="form-grid">
                    
                    <div class="form-group full-width">
                        <label for="nome">Nome Completo (*)</label>
                        <input type="text" id="nome" name="nome" required 
                            value="<?php echo htmlspecialchars($_POST['nome_original'] ?? ''); ?>"
                            placeholder="Ex: JOÃO DA SILVA">
                        <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">* Será salvo em MAIÚSCULAS no banco de dados.</p>
                    </div>

                    <div class="form-group">
                        <label for="matricula">Matrícula (*)</label>
                        <input type="text" id="matricula" name="matricula" required 
                            value="<?php echo htmlspecialchars($_POST['matricula'] ?? ''); ?>"
                            placeholder="Ex: 001234">
                    </div>
                    
                    <div class="form-group">
                        <label for="cpf">CPF (Opcional)</label>
                        <input type="text" id="cpf" name="cpf" maxlength="14"
                            value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>"
                            placeholder="12345678900">
                    </div>
                    
                    <div class="form-group">
                        <label for="data_admissao">Data de Admissão (*)</label>
                        <input type="date" id="data_admissao" name="data_admissao" required 
                            value="<?php echo htmlspecialchars($_POST['data_admissao'] ?? date('Y-m-d')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sexo">Sexo (Padrão: Masculino)</label>
                        <select id="sexo" name="sexo">
                            <option value="0">--- Selecione (Padrão: Masculino) ---</option>
                            <option value="Masculino" <?php echo (($_POST['sexo'] ?? '') == 'Masculino' ? 'selected' : ''); ?>>Masculino</option>
                            <option value="Feminino" <?php echo (($_POST['sexo'] ?? '') == 'Feminino' ? 'selected' : ''); ?>>Feminino</option>
                        </select>
                         <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">Se não selecionado, será usado o valor padrão: **Masculino**</p>
                    </div>

                    <div class="form-group">
                        <label for="setor_id">Setor (Opcional)</label>
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
                        <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">Se não selecionado, será usado o ID Padrão: **<?php echo ID_SETOR_PADRAO; ?>**</p>
                    </div>

                    <div class="form-group">
                        <label for="funcao_id">Função (Opcional)</label>
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
                           <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">Se não selecionado, será usado o ID Padrão: **<?php echo ID_FUNCAO_PADRAO; ?>**</p>
                    </div>

                    <div class="form-group">
                        <label for="equipe_id">Equipe (Opcional)</label>
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
                           <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">Se não selecionado, será usado o ID Padrão: **<?php echo ID_EQUIPE_PADRAO; ?>**</p>
                    </div>

                    <div class="form-group">
                        <label for="situacao_id">Situação</label>
                        <select id="situacao_id" name="situacao_id">
                            <option value="0">--- Selecione a Situação ---</option>
                            <?php 
                            $selected_situacao = (int) ($_POST['situacao_id'] ?? DEFAULT_SITUACAO_ID); 
                            foreach ($situacoes as $id => $nome): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php echo ($id == $selected_situacao ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nome); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                           <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">Padrão: TRABALHANDO (ID <?php echo DEFAULT_SITUACAO_ID; ?>)</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status (Ativo/Inativo)</label>
                        <select id="status" name="status">
                            <option value="1" <?php echo (($_POST['status'] ?? DEFAULT_STATUS) == 1 ? 'selected' : ''); ?>>1 - Ativo</option>
                            <option value="0" <?php echo (($_POST['status'] ?? DEFAULT_STATUS) == 0 ? 'selected' : ''); ?>>0 - Inativo</option>
                        </select>
                           <p style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">Padrão: ATIVO (1)</p>
                    </div>
                    
                    <div class="form-group full-width">
                        <p style="font-size: 0.8em; color: #6c757d;">(*) Campos obrigatórios.</p>
                    </div>


                    <div class="form-actions">
                        <a href="index_colaboradores.php" class="btn btn-secondary">Voltar para Lista</a>
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
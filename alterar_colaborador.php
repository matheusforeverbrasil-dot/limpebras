<?php
// Inclui o arquivo de conexão com o banco de dados
include 'conexao.php';
require_once 'header.php';  

// Define um token CSRF, se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = '';
$erros = [];

// Variáveis para manter os valores do formulário
$nome = '';
$matricula = '';
$data_admissao = '';
$cpf = '';
$sexo = '';
$setor_id = '';
$funcao_id = '';
$equipe_id = '';
$status = 1; // Inicializa a variável para evitar o erro, mesmo quando o checkbox não é marcado na primeira exibição

// Identificador do colaborador para edição
$id_colaborador = null;

// Preencher dropdowns com consultas
$sql_setores = "SELECT id_setor, nome_setor FROM setores ORDER BY nome_setor ASC";
$setores_res = $conn->query($sql_setores);

$sql_funcoes = "SELECT id_funcao, nome_funcao FROM funcoes ORDER BY nome_funcao ASC";
$funcoes_res = $conn->query($sql_funcoes);

$sql_equipes = "SELECT id_equipe, nome_equipe FROM equipes ORDER BY nome_equipe ASC";
$equipes_res = $conn->query($sql_equipes);

// --- Lógica para pré-preencher o formulário se estiver em modo de edição ---
// Se houver um ID na URL, carrega os dados do colaborador para edição
if (isset($_GET['id'])) {
    $id_colaborador = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id_colaborador) {
        $sql = "SELECT * FROM colaboradores WHERE id_colaborador = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_colaborador);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($colaborador = $resultado->fetch_assoc()) {
                $nome = $colaborador['nome'];
                $matricula = $colaborador['matricula'];
                $data_admissao = $colaborador['data_admissao'];
                $cpf = $colaborador['cpf'];
                $sexo = $colaborador['sexo'];
                $setor_id = $colaborador['setor_id'];
                $funcao_id = $colaborador['funcao_id'];
                $equipe_id = $colaborador['equipe_id'];
                $status = $colaborador['status'];
            } else {
                $erros[] = "Colaborador não encontrado.";
                $id_colaborador = null; // Reinicia o ID se não encontrar
            }
            $stmt->close();
        } else {
            error_log("Erro ao preparar a consulta SELECT: " . $conn->error);
            $erros[] = "Erro interno ao buscar dados do colaborador.";
        }
    } else {
        $erros[] = "ID inválido.";
    }
}

// --- Lógica para processar o formulário (INSERT ou UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validação do Token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erro: Token CSRF inválido.");
    }
    
    // 2. Filtragem e validação dos dados de entrada
    $nome_original = filter_var($_POST['nome'], FILTER_SANITIZE_STRING);
    $nome = strtoupper($nome_original);
    $matricula = filter_var($_POST['matricula'], FILTER_SANITIZE_STRING);
    $data_admissao = filter_var($_POST['data_admissao'], FILTER_SANITIZE_STRING);
    $cpf = filter_var($_POST['cpf'], FILTER_SANITIZE_STRING);
    $sexo = filter_var($_POST['sexo'], FILTER_SANITIZE_STRING);
    $setor_id = filter_var($_POST['setor_id'], FILTER_VALIDATE_INT);
    $funcao_id = filter_var($_POST['funcao_id'], FILTER_VALIDATE_INT);
    $equipe_id = filter_var($_POST['equipe_id'], FILTER_VALIDATE_INT);

    // Obtém o ID do colaborador do formulário, se existir
    $id_colaborador_form = filter_var($_POST['id_colaborador'] ?? null, FILTER_VALIDATE_INT);

    // Linha corrigida para lidar com o checkbox 'status'.
    $status = isset($_POST['status']) ? 1 : 0;
    
    // 3. Validação do lado do servidor
    if (empty($nome)) { $erros[] = "O nome é obrigatório."; }
    if (empty($matricula)) { $erros[] = "A matrícula é obrigatória."; }
    if (empty($cpf)) { $erros[] = "O CPF é obrigatório."; }
    if (!in_array($sexo, ['Masculino', 'Feminino'])) { $erros[] = "O sexo selecionado é inválido."; }
    
    // 4. Inserção ou Atualização no banco de dados
    if (empty($erros)) {
        if ($id_colaborador_form) { // É uma atualização
            $sql = "UPDATE colaboradores SET nome = ?, matricula = ?, data_admissao = ?, cpf = ?, sexo = ?, setor_id = ?, funcao_id = ?, equipe_id = ?, status = ? WHERE id_colaborador = ?";
            
            $stmt = $conn->prepare($sql);
            
            // Verifica se a preparação da query foi bem-sucedida
            if ($stmt) {
                // A ordem dos tipos e variáveis deve ser exata
                // s: nome, s: matricula, s: data_admissao, s: cpf, s: sexo, i: setor_id, i: funcao_id, i: equipe_id, i: status, i: id_colaborador
                $stmt->bind_param("sssssiiiii", $nome, $matricula, $data_admissao, $cpf, $sexo, $setor_id, $funcao_id, $equipe_id, $status, $id_colaborador_form);
                
                if ($stmt->execute()) {
                    $mensagem = "Colaborador atualizado com sucesso!";
                } else {
                    error_log("Erro ao executar UPDATE: " . $stmt->error);
                    $mensagem = "Erro ao atualizar colaborador. Por favor, tente novamente.";
                }
                $stmt->close();
            } else {
                error_log("Erro ao preparar a consulta UPDATE: " . $conn->error);
                $mensagem = "Erro interno ao atualizar colaborador.";
            }
        } else { // É uma nova inserção
            $sql = "INSERT INTO colaboradores (nome, matricula, data_admissao, cpf, sexo, setor_id, funcao_id, equipe_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            // Verifica se a preparação da query foi bem-sucedida
            if ($stmt) {
                $stmt->bind_param("sssssiiii", $nome, $matricula, $data_admissao, $cpf, $sexo, $setor_id, $funcao_id, $equipe_id, $status);

                if ($stmt->execute()) {
                    $mensagem = "Colaborador adicionado com sucesso!";
                    // Limpa os campos após a inserção bem-sucedida
                    $nome = $matricula = $data_admissao = $cpf = $sexo = $setor_id = $funcao_id = $equipe_id = '';
                    $status = 1;
                } else {
                    error_log("Erro ao executar INSERT: " . $stmt->error);
                    $mensagem = "Erro ao adicionar colaborador. Por favor, tente novamente.";
                }
                $stmt->close();
            } else {
                error_log("Erro ao preparar a consulta INSERT: " . $conn->error);
                $mensagem = "Erro interno ao adicionar colaborador.";
            }
        }
    } else {
        $mensagem = "Erro(s) no formulário: " . implode(" ", $erros);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id_colaborador ? 'Editar Colaborador' : 'Novo Colaborador'; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; color: #333; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); width: 100%; max-width: 1000px; margin-top: 20px; }
        h1 { color: #444; text-align: center; margin-bottom: 20px; }
        p { text-align: center; font-weight: bold; margin-bottom: 20px; }
        p.success { color: #28a745; }
        p.error { color: #dc3545; }
        form { display: grid; gap: 20px; }
        .form-blocks { display: flex; gap: 20px; align-items: flex-start; }
        .form-blocks fieldset { flex: 1; }
        fieldset { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-top: 10px; }
        legend { font-weight: 600; font-size: 1.1em; padding: 0 10px; color: #555; }
        .form-row { display: flex; flex-wrap: wrap; gap: 20px; }
        .form-group { flex: 1 1 45%; display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: 500; color: #555; }
        input[type="text"], input[type="date"], select { padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em; transition: border-color 0.3s; width: 100%; box-sizing: border-box; }
        input[type="text"]:focus, input[type="date"]:focus, select:focus { outline: none; border-color: #007bff; }
        .checkbox-group { display: flex; align-items: center; margin-top: 10px; }
        .checkbox-group input[type="checkbox"] { margin-right: 10px; }
        .button-group { display: flex; justify-content: space-between; margin-top: 20px; }
        button, a.button { padding: 12px 25px; border: none; border-radius: 6px; font-size: 1em; cursor: pointer; text-decoration: none; text-align: center; transition: background-color 0.3s; }
        button[type="submit"] { background-color: #28a745; color: #fff; }
        button[type="submit"]:hover { background-color: #218838; }
        a.button { background-color: #6c757d; color: #fff; }
        a.button:hover { background-color: #5a6268; }
        @media (max-width: 768px) { .form-blocks { flex-direction: column; } }
        @media (max-width: 600px) { .form-group { flex: 1 1 100%; } }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $id_colaborador ? 'Editar Colaborador' : 'Novo Colaborador'; ?></h1>
        <?php if (!empty($mensagem)): ?>
            <p class="<?php echo !empty($erros) ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </p>
        <?php endif; ?>

        <form action="alterar_colaborador.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <?php if ($id_colaborador): ?>
                <input type="hidden" name="id_colaborador" value="<?php echo htmlspecialchars($id_colaborador); ?>">
            <?php endif; ?>
            
            <div class="form-blocks">
                <!-- Bloco 1: Dados Pessoais -->
                <fieldset>
                    <legend>Dados Pessoais</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome:</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="matricula">Matrícula:</label>
                            <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($matricula); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_admissao">Data de Admissão:</label>
                            <input type="date" id="data_admissao" name="data_admissao" value="<?php echo htmlspecialchars($data_admissao); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="cpf">CPF:</label>
                            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cpf); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sexo">Sexo:</label>
                        <select id="sexo" name="sexo" required>
                            <option value="">Selecione...</option>
                            <option value="Masculino" <?php if ($sexo == 'Masculino') echo 'selected'; ?>>Masculino</option>
                            <option value="Feminino" <?php if ($sexo == 'Feminino') echo 'selected'; ?>>Feminino</option>
                        </select>
                    </div>
                </fieldset>

                <!-- Bloco 2: Dados Profissionais e Status -->
                <fieldset>
                    <legend>Informações Profissionais</legend>
                    <div class="form-group">
                        <label for="setor_id">Setor:</label>
                        <select id="setor_id" name="setor_id" required>
                            <option value="">Selecione...</option>
                            <?php 
                            $setores_res->data_seek(0);
                            while ($linha = $setores_res->fetch_assoc()): ?>
                                <option value="<?php echo $linha['id_setor']; ?>" <?php if ($setor_id == $linha['id_setor']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($linha['nome_setor']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="funcao_id">Função:</label>
                        <select id="funcao_id" name="funcao_id" required>
                            <option value="">Selecione...</option>
                            <?php 
                            $funcoes_res->data_seek(0);
                            while ($linha = $funcoes_res->fetch_assoc()): ?>
                                <option value="<?php echo $linha['id_funcao']; ?>" <?php if ($funcao_id == $linha['id_funcao']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($linha['nome_funcao']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="equipe_id">Equipe:</label>
                        <select id="equipe_id" name="equipe_id">
                            <option value="">Nenhuma</option>
                            <?php 
                            $equipes_res->data_seek(0);
                            while ($linha = $equipes_res->fetch_assoc()): ?>
                                <option value="<?php echo $linha['id_equipe']; ?>" <?php if ($equipe_id == $linha['id_equipe']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($linha['nome_equipe']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="status" name="status" value="1" <?php if ($status) echo 'checked'; ?>>
                        <label for="status">Colaborador Ativo</label>
                    </div>
                </fieldset>
            </div>

            <div class="button-group">
                <button type="submit"><?php echo $id_colaborador ? 'Atualizar' : 'Salvar'; ?></button>
                <a href="dashboard_colaboradores.php" class="button">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>

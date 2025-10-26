<?php
// Inclui o arquivo de conexão com o banco de dados
include 'conexao.php';

// Inicia a sessão para o token CSRF
session_start();

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
$status = 1;
$nome_original = '';

// Preencher dropdowns com consultas
$sql_setores = "SELECT id_setor, nome_setor FROM setores ORDER BY nome_setor ASC";
$setores = $conn->query($sql_setores);

$sql_funcoes = "SELECT id_funcao, nome_funcao FROM funcoes ORDER BY nome_funcao ASC";
$funcoes = $conn->query($sql_funcoes);

$sql_equipes = "SELECT id_equipe, nome_equipe FROM equipes ORDER BY nome_equipe ASC";
$equipes = $conn->query($sql_equipes);

// Processar o formulário, se for enviado via POST
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
    
    // 3. Validação do lado do servidor
    if (empty($nome)) { $erros[] = "O nome é obrigatório."; }
    if (empty($matricula)) { $erros[] = "A matrícula é obrigatória."; }
    if (empty($cpf)) { $erros[] = "O CPF é obrigatório."; }
    if (!in_array($sexo, ['Masculino', 'Feminino'])) { $erros[] = "O sexo selecionado é inválido."; }
    
    // 4. Inserção no banco de dados
    if (empty($erros)) {
        $status = 1; 

        $sql = "INSERT INTO colaboradores (nome, matricula, data_admissao, cpf, sexo, setor_id, funcao_id, equipe_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("sssssiiii", $nome, $matricula, $data_admissao, $cpf, $sexo, $setor_id, $funcao_id, $equipe_id, $status);

        if ($stmt->execute()) {
            $mensagem = "Colaborador adicionado com sucesso!";
            // Limpar formulário após o sucesso
            $nome_original = $matricula = $data_admissao = $cpf = $sexo = $setor_id = $funcao_id = $equipe_id = '';
        } else {
            error_log("Erro ao adicionar colaborador: " . $stmt->error);
            $mensagem = "Erro ao adicionar colaborador. Por favor, tente novamente.";
        }
        $stmt->close();
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
    <title>Novo Colaborador</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f9;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
        }
        h1 {
            color: #444;
            text-align: center;
            margin-bottom: 20px;
        }
        p {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        p.success {
            color: #28a745;
        }
        p.error {
            color: #dc3545;
        }
        form {
            display: grid;
            gap: 20px;
        }
        fieldset {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 10px;
        }
        legend {
            font-weight: 600;
            font-size: 1.1em;
            padding: 0 10px;
            color: #555;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .form-group {
            flex: 1 1 45%; 
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        input[type="text"],
        input[type="date"],
        select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        select:focus {
            outline: none;
            border-color: #007bff;
        }
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        button, a.button {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        button {
            background-color: #28a745;
            color: #fff;
        }
        button:hover {
            background-color: #218838;
        }
        a.button {
            background-color: #6c757d;
            color: #fff;
        }
        a.button:hover {
            background-color: #5a6268;
        }
        @media (max-width: 600px) {
            .form-group {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Novo Colaborador</h1>
        <p class="<?php echo !empty($erros) ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($mensagem); ?>
        </p>

        <form action="novo_colaborador.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <fieldset>
                <legend>Dados Pessoais</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome_original); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($matricula); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="data_admissao">Data de Admissão:</label>
                        <input type="date" id="data_admissao" name="data_admissao" value="<?php echo htmlspecialchars($data_admissao); ?>">
                    </div>
                    <div class="form-group">
                        <label for="cpf">CPF:</label>
                        <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cpf); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="sexo">Sexo:</label>
                        <select id="sexo" name="sexo" required>
                            <option value="">Selecione...</option>
                            <option value="Masculino" <?php echo ($sexo === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Feminino" <?php echo ($sexo === 'Feminino') ? 'selected' : ''; ?>>Feminino</option>
                        </select>
                    </div>
                    <div class="form-group"></div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Informações Profissionais</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="setor_id">Setor:</label>
                        <select id="setor_id" name="setor_id">
                            <option value="">Selecione...</option>
                            <?php 
                            $setores->data_seek(0);
                            while($row = $setores->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($row['id_setor']); ?>" <?php echo ($setor_id == $row['id_setor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nome_setor']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="funcao_id">Função:</label>
                        <select id="funcao_id" name="funcao_id">
                            <option value="">Selecione...</option>
                            <?php 
                            $funcoes->data_seek(0);
                            while($row = $funcoes->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($row['id_funcao']); ?>" <?php echo ($funcao_id == $row['id_funcao']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nome_funcao']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="equipe_id">Equipe:</label>
                        <select id="equipe_id" name="equipe_id">
                            <option value="">Selecione...</option>
                            <?php 
                            $equipes->data_seek(0);
                            while($row = $equipes->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($row['id_equipe']); ?>" <?php echo ($equipe_id == $row['id_equipe']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nome_equipe']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"></div>
                </div>
            </fieldset>

            <div class="button-group">
                <button type="submit">Salvar</button>
                <a href="index_colaboradores.php" class="button">Voltar</a>
            </div>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>

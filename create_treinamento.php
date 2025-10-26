<?php
// Arquivo: create_treinamento.php
include 'config_treinamento.php';

$mensagem = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Captura os IDs enviados pelo formulário
    $id_tipo_treinamento = $_POST['id_tipo_treinamento'];
    $data_vencimento = $_POST['data_vencimento'];
    $id_colaborador = $_POST['id_colaborador'];
    $resp_treinamento = $_POST['resp_treinamento'];

    // Consulta SQL com as novas chaves estrangeiras
    $sql = "INSERT INTO treinamentos (id_tipo_treinamento, data_vencimento, id_colaborador, resp_treinamento)
            VALUES (?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("isis", $id_tipo_treinamento, $data_vencimento, $id_colaborador, $resp_treinamento);

    if ($stmt->execute()) {
        $mensagem = "Treinamento adicionado com sucesso!";
    } else {
        $mensagem = "Erro ao adicionar treinamento: " . $stmt->error;
    }
    $stmt->close();
    $conexao->close();
    header("Location: index_treinamento.php?mensagem=" . urlencode($mensagem));
    exit;
}

// Busca todos os tipos de treinamento para o dropdown
$sql_tipos = "SELECT id_tipo_treinamento, nome_tipo_treinamento FROM tipos_treinamento ORDER BY nome_tipo_treinamento";
$tipos_treinamento_result = $conexao->query($sql_tipos);

// Busca todos os colaboradores para o dropdown
$sql_colaboradores = "SELECT id_colaborador, nome FROM colaboradores ORDER BY nome";
$colaboradores_result = $conexao->query($sql_colaboradores);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Adicionar Treinamento</title>
    <style>
        /* Estilo elegante e moderno */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #1c2b46;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px 0;
            border-radius: 8px;
            text-decoration: none;
            color: #fff;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn.adicionar {
            background-color: #007bff;
            width: 100%;
        }

        .btn.adicionar:hover {
            background-color: #0056b3;
        }

        .btn.voltar {
            background-color: #6c757d;
        }

        form label, form select, form input, form button {
            display: block;
            width: 100%;
            margin-bottom: 15px;
        }
        
        form input[type="text"],
        form input[type="date"],
        form select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Adicionar Novo Treinamento</h2>
        <a href="index_treinamento.php" class="btn voltar">Voltar para a Lista</a>
        <br><br>
        <form action="create_treinamento.php" method="post">
            <label for="id_tipo_treinamento">Tipo de Treinamento:</label>
            <select id="id_tipo_treinamento" name="id_tipo_treinamento" required>
                <option value="">Selecione um treinamento</option>
                <?php while ($row = $tipos_treinamento_result->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['id_tipo_treinamento']) ?>"><?= htmlspecialchars($row['nome_tipo_treinamento']) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="data_vencimento">Data de Vencimento:</label>
            <input type="date" id="data_vencimento" name="data_vencimento" required>

            <label for="id_colaborador">Colaborador:</label>
            <select id="id_colaborador" name="id_colaborador" required>
                <option value="">Selecione um colaborador</option>
                <?php while ($row = $colaboradores_result->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['id_colaborador']) ?>"><?= htmlspecialchars($row['nome']) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="resp_treinamento">Responsável pelo Treinamento:</label>
            <input type="text" id="resp_treinamento" name="resp_treinamento">

            <button type="submit" class="btn adicionar">Salvar Treinamento</button>
        </form>
    </div>
</body>
</html>
<?php
$conexao->close();
?>

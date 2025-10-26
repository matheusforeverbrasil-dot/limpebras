<?php
// Arquivo: update_treinamento.php
include 'config_treinamento.php';

$mensagem = '';
$treinamento = null;
$nome_colaborador = '';

if (isset($_GET['id_treinamento'])) {
    $id = $_GET['id_treinamento'];

    // Consulta para buscar os dados do treinamento, o nome do colaborador e o nome do tipo de treinamento
    $sql = "SELECT t.*, c.nome AS nome_colaborador, tt.nome_tipo_treinamento
            FROM treinamentos t
            JOIN colaboradores c ON t.id_colaborador = c.id_colaborador
            JOIN tipos_treinamento tt ON t.id_tipo_treinamento = tt.id_tipo_treinamento
            WHERE t.id_treinamento = ?";

    $stmt = $conexao->prepare($sql);
    if ($stmt === false) {
        die("Erro na preparação da consulta (Busca): " . $conexao->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $treinamento = $resultado->fetch_assoc();
    $stmt->close();

    // Se o treinamento foi encontrado, os nomes já estarão no array $treinamento
    // e podem ser usados no formulário.
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id_treinamento'];
    $id_tipo_treinamento = $_POST['id_tipo_treinamento'];
    $data_vencimento = $_POST['data_vencimento'];
    $id_colaborador = $_POST['id_colaborador'];
    $resp_treinamento = $_POST['resp_treinamento'];

    $sql = "UPDATE treinamentos SET id_tipo_treinamento=?, data_vencimento=?, id_colaborador=?, resp_treinamento=? WHERE id_treinamento=?";
    $stmt = $conexao->prepare($sql);

    if ($stmt === false) {
        die("Erro na preparação da consulta (Update): " . $conexao->error);
    }

    $stmt->bind_param("isisi", $id_tipo_treinamento, $data_vencimento, $id_colaborador, $resp_treinamento, $id);

    if ($stmt->execute()) {
        $mensagem = "Treinamento atualizado com sucesso!";
    } else {
        $mensagem = "Erro ao atualizar treinamento: " . $stmt->error;
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
    <meta charset="UTF-8">
    <title>Editar Treinamento</title>
    <style>
        /* Estilo elegante e moderno */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f0f2f5; color: #333; line-height: 1.6; margin: 0; padding: 40px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        h2 { color: #1c2b46; text-align: center; margin-bottom: 30px; font-weight: 600; }
        .mensagem { padding: 15px; margin-bottom: 25px; border-radius: 8px; text-align: center; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; font-weight: 500; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px 0; border-radius: 8px; text-decoration: none; color: #fff; text-align: center; border: none; cursor: pointer; transition: background-color 0.3s ease, transform 0.2s ease; font-weight: 500; }
        .btn:hover { transform: translateY(-2px); }
        .btn.editar { background-color: #28a745; width: 100%; }
        .btn.editar:hover { background-color: #1e7e34; }
        .btn.voltar { background-color: #6c757d; }
        form label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        form input, form select, form button { width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; transition: border-color 0.3s; }
        form input:focus, form select:focus { outline: none; border-color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Editar Treinamento</h2>
        <a href="index_treinamento.php" class="btn voltar">Voltar para a Lista</a>
        <br><br>
        <?php if ($treinamento): ?>
        <form action="update_treinamento.php" method="post">
            <input type="hidden" name="id_treinamento" value="<?= htmlspecialchars($treinamento['id_treinamento']) ?>">

            <label for="id_tipo_treinamento">Tipo de Treinamento:</label>
            <select id="id_tipo_treinamento" name="id_tipo_treinamento" required>
                <?php while ($row = $tipos_treinamento_result->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['id_tipo_treinamento']) ?>" <?= ($row['id_tipo_treinamento'] == $treinamento['id_tipo_treinamento']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['nome_tipo_treinamento']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="data_vencimento">Data de Vencimento:</label>
            <input type="date" id="data_vencimento" name="data_vencimento" value="<?= htmlspecialchars($treinamento['data_vencimento']) ?>" required>

            <label for="id_colaborador">Colaborador:</label>
            <select id="id_colaborador" name="id_colaborador" required>
                <?php while ($row = $colaboradores_result->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['id_colaborador']) ?>" <?= ($row['id_colaborador'] == $treinamento['id_colaborador']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['nome']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="resp_treinamento">Responsável pelo Treinamento:</label>
            <input type="text" id="resp_treinamento" name="resp_treinamento" value="<?= htmlspecialchars($treinamento['resp_treinamento']) ?>">

            <button type="submit" class="btn editar">Atualizar Treinamento</button>
        </form>
        <?php else: ?>
            <p>Treinamento não encontrado.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conexao->close();
?>

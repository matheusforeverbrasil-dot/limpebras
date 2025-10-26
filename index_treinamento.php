<?php
// Arquivo: index_treinamento.php
include 'config_treinamento.php';

// Mensagem de sucesso ou erro (se houver)
$mensagem = '';
if (isset($_GET['mensagem'])) {
    $mensagem = htmlspecialchars($_GET['mensagem']);
}

// Busca todos os treinamentos e os nomes do colaborador e do tipo de treinamento
$sql = "SELECT t.*, c.nome AS nome_colaborador, tt.nome_tipo_treinamento 
        FROM treinamentos t
        JOIN colaboradores c ON t.id_colaborador = c.id_colaborador
        JOIN tipos_treinamento tt ON t.id_tipo_treinamento = tt.id_tipo_treinamento";
$resultado = $conexao->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Treinamentos</title>
    <style>
        /* Estilo elegante e moderno (como fornecido anteriormente) */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f0f2f5; color: #333; line-height: 1.6; margin: 0; padding: 40px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        h2 { color: #1c2b46; text-align: center; margin-bottom: 30px; font-weight: 600; }
        .mensagem { padding: 15px; margin-bottom: 25px; border-radius: 8px; text-align: center; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; font-weight: 500; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px 0; border-radius: 8px; text-decoration: none; color: #fff; text-align: center; border: none; cursor: pointer; transition: background-color 0.3s ease, transform 0.2s ease; font-weight: 500; }
        .btn:hover { transform: translateY(-2px); }
        .btn.adicionar { background-color: #007bff; }
        .btn.adicionar:hover { background-color: #0056b3; }
        .btn.editar { background-color: #28a745; }
        .btn.editar:hover { background-color: #1e7e34; }
        .btn.excluir { background-color: #dc3545; }
        .btn.excluir:hover { background-color: #c82333; }
        .btn-acao { display: flex; gap: 8px; justify-content: flex-start; }
        table { width: 100%; border-collapse: collapse; margin-top: 25px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background-color: #f8f9fa; font-weight: 600; color: #555; }
        tbody tr:hover { background-color: #fafafa; }
        tbody tr:last-child td { border-bottom: none; }
        p { text-align: center; font-style: italic; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Treinamentos</h2>
        <a href="create_treinamento.php" class="btn adicionar">Adicionar Novo Treinamento</a>
        <br><br>
        <?php if (!empty($mensagem)): ?>
            <p class="mensagem"><?= $mensagem ?></p>
        <?php endif; ?>

        <?php if ($resultado->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome do Treinamento</th>
                        <th>Data de Vencimento</th>
                        <th>Nome Colaborador</th>
                        <th>Responsável</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_treinamento']) ?></td>
                            <td><?= htmlspecialchars($row['nome_tipo_treinamento']) ?></td>
                            <td><?= htmlspecialchars($row['data_vencimento']) ?></td>
                            <td><?= htmlspecialchars($row['nome_colaborador']) ?></td>
                            <td><?= htmlspecialchars($row['resp_treinamento']) ?></td>
                            <td class="btn-acao">
                                <a href="update_treinamento.php?id_treinamento=<?= $row['id_treinamento'] ?>" class="btn editar">Editar</a>
                                <a href="delete_treinamento.php?id_treinamento=<?= $row['id_treinamento'] ?>" class="btn excluir" onclick="return confirm('Tem certeza que deseja excluir este treinamento?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhum treinamento encontrado.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conexao->close();
?>

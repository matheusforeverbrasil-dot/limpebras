<?php
include 'conexao.php';
$mensagem = '';
$atestado = null;
$colaborador_nome = '';
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];  
    // Buscar dados do atestado
    $sql_atestado = "SELECT a.*, c.nome FROM atestados a JOIN colaboradores c ON a.colaborador_id = c.id_colaborador WHERE a.id_atestado = ?";
    $stmt = $conn->prepare($sql_atestado);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $atestado = $resultado->fetch_assoc();
    $colaborador_nome = $atestado['nome'];

    if (!$atestado) {
        $mensagem = "Atestado não encontrado.";
    }
    $stmt->close();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $colaborador_id = $_POST['colaborador_id'];
    $cid = $_POST['cid'];
    $data_atestado = $_POST['data_atestado'];
    $dias_afastamento = $_POST['dias_afastamento'];
    $dia_retorno = $_POST['dia_retorno'];
    $unidade_saude = $_POST['unidade_saude'];
    $sql = "UPDATE atestados SET colaborador_id=?, cid=?, data_atestado=?, dias_afastamento=?, dia_retorno=?, unidade_saude=? WHERE id_atestado=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssisi", $colaborador_id, $cid, $data_atestado, $dias_afastamento, $dia_retorno, $unidade_saude, $id);
    if ($stmt->execute()) {
        $mensagem = "Atestado atualizado com sucesso!";
        // Atualiza a variável atestado para refletir as mudanças
        $sql_atestado = "SELECT a.*, c.nome FROM atestados a JOIN colaboradores c ON a.colaborador_id = c.id_colaborador WHERE a.id_atestado = ?";
        $stmt_reload = $conn->prepare($sql_atestado);
        $stmt_reload->bind_param("i", $id);
        $stmt_reload->execute();
        $resultado_reload = $stmt_reload->get_result();
        $atestado = $resultado_reload->fetch_assoc();
        $colaborador_nome = $atestado['nome'];
        $stmt_reload->close();
    } else {
        $mensagem = "Erro ao atualizar atestado: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$pageTitle = isset($pageTitle) ? $pageTitle : 'Sistema de Gestão SST - Alterar Atestado';</title>
</head>
<body>
    <div class="container">
        <h1>Alterar Atestado</h1>
        <p><?php echo $mensagem; ?></p>
        <?php if ($atestado): ?>
            <form action="alterar_atestado.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($atestado['id_atestado']); ?>">
                <div class="form-group">
                    <label for="nome_colaborador">Colaborador:</label>
                    <input type="text" id="nome_colaborador" value="<?php echo htmlspecialchars($colaborador_nome); ?>" placeholder="Digite o nome do colaborador..." required>
                    <input type="hidden" id="colaborador_id" name="colaborador_id" value="<?php echo htmlspecialchars($atestado['colaborador_id']); ?>" required>
                    <div id="autocomplete-results" class="autocomplete-list"></div>
                </div>
                <div class="form-group">
                    <label for="cid">CID:</label>
                    <input type="text" id="cid" name="cid" value="<?php echo htmlspecialchars($atestado['cid']); ?>">
                </div>
                <div class="form-group">
                    <label for="data_atestado">Data do Atestado:</label>
                    <input type="date" id="data_atestado" name="data_atestado" value="<?php echo htmlspecialchars($atestado['data_atestado']); ?>">
                </div>
                <div class="form-group">
                    <label for="dias_afastamento">Dias de Afastamento:</label>
                    <input type="number" id="dias_afastamento" name="dias_afastamento" value="<?php echo htmlspecialchars($atestado['dias_afastamento']); ?>">
                </div>
                <div class="form-group">
                    <label for="dia_retorno">Dia de Retorno:</label>
                    <input type="date" id="dia_retorno" name="dia_retorno" value="<?php echo htmlspecialchars($atestado['dia_retorno']); ?>">
                </div>
                <div class="form-group">
                    <label for="unidade_saude">Unidade de Saúde:</label>
                    <input type="text" id="unidade_saude" name="unidade_saude" value="<?php echo htmlspecialchars($atestado['unidade_saude']); ?>">
                </div>
                <button type="submit">Salvar Alterações</button>
                <a href="index.php">Cancelar</a>
            </form>
        <?php else: ?>
            <p>Atestado não encontrado.</p>
        <?php endif; ?>
    </div>
    <script>
        document.getElementById('nome_colaborador').addEventListener('input', function() {
            const termo = this.value;
            const resultadosDiv = document.getElementById('autocomplete-results');

            if (termo.length >= 3) {
                fetch(`buscar_colaboradores.php?termo=${termo}`)
                    .then(response => response.json())
                    .then(data => {
                        resultadosDiv.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(colaborador => {
                                const div = document.createElement('div');
                                div.textContent = colaborador.nome;
                                div.onclick = function() {
                                    document.getElementById('nome_colaborador').value = colaborador.nome;
                                    document.getElementById('colaborador_id').value = colaborador.id_colaborador;
                                    resultadosDiv.innerHTML = '';
                                };
                                resultadosDiv.appendChild(div);
                            });
                        } else {
                            resultadosDiv.innerHTML = '<div>Nenhum colaborador encontrado</div>';
                        }
                    });
            } else {
                resultadosDiv.innerHTML = '';
            }
        });
    </script>
</body>
</html>


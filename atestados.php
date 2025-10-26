<?php
// Inclui a conexão com o banco de dados (conteúdo de conexao.php)
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'sistemaSST';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// --- Lógica de Paginação ---
$registros_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// --- Lógica de Busca ---
$termo_busca = isset($_GET['busca']) ? $conn->real_escape_string($_GET['busca']) : '';
$condicao_busca = '';
if (!empty($termo_busca)) {
    $condicao_busca = " WHERE c.nome LIKE '%$termo_busca%' ";
}

// Contar o total de registros (AGORA COM A CONDIÇÃO DE BUSCA)
$sql_total = "SELECT COUNT(*) AS total FROM atestados a JOIN colaboradores c ON a.colaborador_id = c.id_colaborador" . $condicao_busca;
$resultado_total = $conn->query($sql_total);
$total_registros = $resultado_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// --- Consulta SQL para buscar os atestados (com paginação e busca) ---
$sql_atestados = "
    SELECT 
        a.id_atestado,
        c.nome AS nome_colaborador,
        DATE_FORMAT(a.data_atestado, '%d/%m/%Y') AS data_atestado_formatada,
        a.dias_afastamento,
        DATE_FORMAT(a.dia_retorno, '%d/%m/%Y') AS dia_retorno_formatado,
        a.cid,
        a.caminho_pdf,
        DATE_FORMAT(a.dt_atestado, '%d/%m/%Y %H:%i:%s') AS dt_atestado_formatada
    FROM 
        atestados a
    JOIN 
        colaboradores c ON a.colaborador_id = c.id_colaborador
    $condicao_busca
    ORDER BY a.dt_atestado DESC
    LIMIT $offset, $registros_por_pagina
";
$resultado = $conn->query($sql_atestados);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Atestados</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-container { position: relative; }
        .search-container input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 250px; } /* Aumenta a largura do input */
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background-color: #fff; border: 1px solid #ccc; max-height: 150px; overflow-y: auto; z-index: 10; }
        .autocomplete-list div { padding: 8px; cursor: pointer; }
        .autocomplete-list div:hover { background-color: #f0f0f0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; word-wrap: break-word; font-size: 12px; }
        th { background-color: #f2f2f2; white-space: nowrap; }
        table tr:nth-of-type(odd) {
            background-color: #ffffff;
        }
        table tr:nth-of-type(even) {
            background-color: #f9f9f9;
        }
        /* Ajuste para o alinhamento dos botões */
        .actions { 
            display: flex; 
            gap: 5px; 
            justify-content: center;
            align-items: center;
            flex-wrap: wrap; /* Permite quebras de linha se necessário */
        }
        .actions a { 
            text-decoration: none; 
            padding: 5px 10px; 
            border-radius: 4px; 
            white-space: nowrap;
            display: inline-block;
        }
        .edit-btn { background-color: #4CAF50; color: white; }
        .delete-btn { background-color: #f44336; color: white; }
        .new-btn { background-color: #008CBA; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { padding: 8px 16px; text-decoration: none; border: 1px solid #ccc; margin: 0 4px; border-radius: 4px; }
        .pagination a.active { background-color: #008CBA; color: white; border: 1px solid #008CBA; }
        .view-pdf-btn { background-color: #007bff; color: white; }
        
        /* Classes para cada coluna */
        .col-colaborador { width: 30%; }
        .col-cid { width: 3%; }
        .col-dt-atestado { width: 9%; }
        .col-dias { width: 10%; }
        .col-dt-retorno { width: 10%; }
        .col-dt-registro { width: 10%; }
        .col-acoes { width: 22%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestão de Atestados</h1>
            <a href="novo_atestado.php" class="new-btn">Novo Atestado</a>
        </div>

        <div class="search-container">
            <input type="text" id="busca-nome" placeholder="Buscar por nome do colaborador..." autocomplete="off" value="<?php echo htmlspecialchars($termo_busca); ?>">
            <div id="autocomplete-results" class="autocomplete-list"></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-colaborador">Colaborador</th>
                    <th class="col-cid">CID</th>
                    <th class="col-dt-atestado">Data do Atestado</th>
                    <th class="col-dias">Dias de Afastamento</th>
                    <th class="col-dt-retorno">Dia de Retorno</th>
                    <th class="col-dt-registro">Data de Registro</th>
                    <th class="col-acoes">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td class="col-colaborador"><?php echo htmlspecialchars($row['nome_colaborador']); ?></td>
                            <td class="col-cid"><?php echo htmlspecialchars($row['cid']); ?></td>
                            <td class="col-dt-atestado"><?php echo htmlspecialchars($row['data_atestado_formatada']); ?></td>
                            <td class="col-dias"><?php echo htmlspecialchars($row['dias_afastamento']); ?></td>
                            <td class="col-dt-retorno"><?php echo htmlspecialchars($row['dia_retorno_formatado']); ?></td>
                            <td class="col-dt-registro"><?php echo htmlspecialchars($row['dt_atestado_formatada']); ?></td>
                            <td class="actions">
                                <a href="alterar_atestado.php?id=<?php echo $row['id_atestado']; ?>" class="edit-btn">Alterar</a>
                                <a href="deletar_atestado.php?id=<?php echo $row['id_atestado']; ?>" class="delete-btn" onclick="return confirm('Tem certeza que deseja deletar este registro?');">Deletar</a>
                                <?php if (!empty($row['caminho_pdf'])): ?>
                                    <a href="download_atestado.php?id=<?php echo $row['id_atestado']; ?>" class="view-pdf-btn" target="_blank">Ver PDF</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Nenhum atestado encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="atestados.php?pagina=<?php echo $i; ?><?php echo !empty($termo_busca) ? '&busca=' . urlencode($termo_busca) : ''; ?>" class="<?php echo ($pagina_atual == $i) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Script para Autocomplete -->
    <script>
        document.getElementById('busca-nome').addEventListener('input', function() {
            const termo = this.value;
            const resultadosDiv = document.getElementById('autocomplete-results');

            if (termo.length < 3) {
                resultadosDiv.innerHTML = '';
                if (termo === '') {
                    window.location.href = 'atestados.php';
                }
                return;
            }

            fetch(`buscar_colaboradores.php?termo=${termo}`)
                .then(response => response.json())
                .then(data => {
                    resultadosDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(colaborador => {
                            const div = document.createElement('div');
                            div.textContent = colaborador.nome;
                            div.onclick = function() {
                                document.getElementById('busca-nome').value = colaborador.nome;
                                resultadosDiv.innerHTML = '';
                                window.location.href = `atestados.php?busca=${encodeURIComponent(colaborador.nome)}`;
                            };
                            resultadosDiv.appendChild(div);
                        });
                    } else {
                        resultadosDiv.innerHTML = '<div>Nenhum colaborador encontrado</div>';
                    }
                });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>

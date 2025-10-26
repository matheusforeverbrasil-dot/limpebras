<?php
// Arquivo: novo_atestado.php
// Inclui o arquivo de conexão. Certifique-se de que ele está correto e funcional.
include 'conexao.php'; 

$mensagem = '';
$unidades_saude = [];

// Busca unidades de saúde para o <select>
$sql_unidades = "SELECT id, nome_u_saude FROM unidade_saude ORDER BY nome_u_saude";
$result_unidades = $conn->query($sql_unidades);
if ($result_unidades) {
    while ($row = $result_unidades->fetch_assoc()) {
        $unidades_saude[] = $row;
    }
} else {
    $mensagem = "Erro ao buscar unidades de saúde: " . $conn->error;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta e sanitização de dados
    $colaborador_id = filter_input(INPUT_POST, 'colaborador_id', FILTER_VALIDATE_INT);
    $cid = filter_input(INPUT_POST, 'cid', FILTER_SANITIZE_STRING);
    $data_atestado = filter_input(INPUT_POST, 'data_atestado', FILTER_SANITIZE_STRING);
    $dias_afastamento_post = filter_input(INPUT_POST, 'dias_afastamento', FILTER_VALIDATE_INT); 
    $dia_retorno = filter_input(INPUT_POST, 'dia_retorno', FILTER_SANITIZE_STRING);
    $unidade_saude = filter_input(INPUT_POST, 'unidade_saude', FILTER_VALIDATE_INT);
    
    // 1. VALIDAÇÃO BÁSICA DE CAMPOS (MENSAGENS DETALHADAS PARA DEBUG)
    if (!$colaborador_id) {
        $mensagem = "Erro: ID do Colaborador (colaborador_id) está vazio/inválido. Verifique o Autocomplete.";
        goto final_validation; // Pula para o final se a validação falhar
    } 
    if (empty($cid)) {
        $mensagem = "Erro: Campo CID está vazio.";
        goto final_validation;
    } 
    if (empty($data_atestado)) {
        $mensagem = "Erro: Campo Data do Atestado está vazio.";
        goto final_validation;
    } 
    if (empty($dia_retorno)) {
        $mensagem = "Erro: Campo Dia de Retorno está vazio.";
        goto final_validation;
    } 
    if (!$unidade_saude) {
        $mensagem = "Erro: Unidade de Saúde está vazia/inválida.";
        goto final_validation;
    } 
    if ($dias_afastamento_post === false || $dias_afastamento_post === null) {
        $mensagem = "Erro: Dias de Afastamento está vazio/inválido. Verifique o cálculo de datas.";
        goto final_validation;
    }
    
    // 2. VALIDAÇÃO DE DATAS E CÁLCULO DE DIAS (SEGURANÇA SERVER-SIDE)
    try {
        $dataInicio = new DateTime($data_atestado);
        $dataFim = new DateTime($dia_retorno);
    } catch (Exception $e) {
        $mensagem = "Erro: Formato de data inválido.";
        goto final_validation;
    }

    if ($dataFim < $dataInicio) {
        $mensagem = "Erro: A data de retorno não pode ser anterior à data do atestado.";
        goto final_validation;
    } else {
        // Calcula a diferença de dias no servidor
        $diff = $dataFim->diff($dataInicio);
        $dias_calculados_php = $diff->days; 
        
        // Verifica se o valor enviado pelo cliente corresponde ao cálculo do servidor
        if ($dias_calculados_php != $dias_afastamento_post) {
            $mensagem = "Erro de segurança: O número de dias de afastamento enviado está incorreto. (Esperado: {$dias_calculados_php}, Recebido: {$dias_afastamento_post})";
            goto final_validation;
        }
    }
    
    final_validation:
    
    // 3. LÓGICA DE UPLOAD 
    $caminho_pdf_db = null;
    if (strpos($mensagem, 'Erro') === false) { // Somente tenta o upload se não houver erros na validação de campos
        if (isset($_FILES['caminho_pdf']) && $_FILES['caminho_pdf']['error'] == 0) {
            $file_info = $_FILES['caminho_pdf'];
            $allowed_types = ['application/pdf'];
            
            if (!in_array($file_info['type'], $allowed_types) || pathinfo($file_info['name'], PATHINFO_EXTENSION) !== 'pdf') {
                $mensagem = "Erro: Apenas arquivos PDF são permitidos.";
            } elseif ($file_info['size'] > 5242880) { // 5MB
                $mensagem = "Erro: O arquivo é muito grande (máximo 5MB).";
            } else {
                $target_dir = __DIR__ . "/Atestado/";
                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0755, true)) {
                        $mensagem = "Erro ao criar o diretório de upload.";
                    }
                }
                
                if (strpos($mensagem, 'Erro') === false) {
                    $unique_name = $colaborador_id . "_" . uniqid() . ".pdf";
                    $target_file = $target_dir . $unique_name;
                    if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                        $caminho_pdf_db = "Atestado/" . $unique_name;
                    } else {
                        $mensagem = "Erro ao fazer o upload do arquivo PDF.";
                    }
                }
            }
        }
    }
    
    // 4. INSERT NO BANCO DE DADOS
    if (strpos($mensagem, 'Erro') === false) {
        $sql = "INSERT INTO atestados (colaborador_id, cid, data_atestado, dias_afastamento, dia_retorno, unidade_saude, caminho_pdf) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $tipos = "isssiis";
        
        $stmt->bind_param($tipos, $colaborador_id, $cid, $data_atestado, $dias_afastamento_post, $dia_retorno, $unidade_saude, $caminho_pdf_db);
        
        if ($stmt->execute()) {
            $mensagem = "Atestado adicionado com sucesso!";
            // Opcional: Limpar campos após sucesso
        } else {
            $mensagem = "Erro ao adicionar atestado: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Atestado</title>
    <style>
        /* Seus estilos CSS */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            padding: 20px;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            font-size: 2em;
            color: #007bff;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        label[for][required]:after {
            content: " *";
            color: #dc3545; 
            font-weight: bold;
        }
        input[type="text"], input[type="date"], input[type="number"], input[type="file"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .autocomplete-list {
            position: absolute;
            z-index: 100;
            background-color: #fff;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 150px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            top: 100%;
        }
        .autocomplete-list div {
            padding: 10px;
            cursor: pointer;
        }
        .autocomplete-list div:hover {
            background-color: #f0f0f0;
        }
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        button, .btn-link {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            text-align: center;
        }
        button {
            background-color: #28a745;
            color: #ffffff;
        }
        .btn-link {
            background-color: #6c757d;
            color: #ffffff;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .mensagem {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .mensagem.erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .mensagem.sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Novo Atestado</h1>
        <?php if ($mensagem): ?>
            <p class="mensagem <?php echo (strpos($mensagem, 'Erro') !== false) ? 'erro' : 'sucesso'; ?>">
                <?php echo $mensagem; ?>
            </p>
        <?php endif; ?>
        <form action="novo_atestado.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nome_colaborador" required>Colaborador:</label>
                <input type="text" id="nome_colaborador" placeholder="Digite o nome ou matrícula do colaborador..." required autocomplete="off">
                <input type="hidden" id="colaborador_id" name="colaborador_id" required>
                <div id="autocomplete-colaborador" class="autocomplete-list"></div>
            </div>
            <div class="form-group">
                <label for="cid_input" required>CID:</label>
                <input type="text" id="cid_input" name="cid" placeholder="Digite a sigla do CID..." required autocomplete="off">
                <div id="autocomplete-cid" class="autocomplete-list"></div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="data_atestado" required>Data do Atestado:</label>
                    <input type="date" id="data_atestado" name="data_atestado" required>
                </div>
                <div class="form-group">
                    <label for="dia_retorno" required>Dia de Retorno:</label>
                    <input type="date" id="dia_retorno" name="dia_retorno" required>
                </div>
            </div>

            <div class="form-group">
                <label for="dias_afastamento" required>Dias de Afastamento:</label>
                <input type="number" id="dias_afastamento" name="dias_afastamento" required readonly>
            </div>

            <div class="form-group">
                <label for="unidade_saude" required>Unidade de Saúde:</label>
                <select id="unidade_saude" name="unidade_saude" required>
                    <option value="">Selecione a unidade...</option>
                    <?php foreach ($unidades_saude as $unidade): ?>
                        <option value="<?php echo htmlspecialchars($unidade['id']); ?>"><?php echo htmlspecialchars($unidade['nome_u_saude']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="caminho_pdf">Anexar Atestado (PDF):</label>
                <input type="file" id="caminho_pdf" name="caminho_pdf" accept="application/pdf">
            </div>

            <div class="button-group">
                <button type="submit">Salvar</button>
                <a href="index.php" class="btn-link">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        // Variáveis globais para autocomplete
        const nomeColaborador = document.getElementById('nome_colaborador');
        const colaboradorId = document.getElementById('colaborador_id');
        const autocompleteColaborador = document.getElementById('autocomplete-colaborador');
        const cidInput = document.getElementById('cid_input');
        const autocompleteCid = document.getElementById('autocomplete-cid');
        
        // Elementos de cálculo de datas
        const dataAtestado = document.getElementById('data_atestado');
        const diaRetorno = document.getElementById('dia_retorno');
        const diasAfastamento = document.getElementById('dias_afastamento');


        // FUNÇÃO DE CÁLCULO DE DIAS (CLIENT-SIDE)
        function calcularDiasAfastamento() {
            if (dataAtestado.value && diaRetorno.value) {
                const dataInicio = new Date(dataAtestado.value);
                const dataFim = new Date(diaRetorno.value);

                dataInicio.setHours(0, 0, 0, 0);
                dataFim.setHours(0, 0, 0, 0);

                if (dataFim >= dataInicio) {
                    const diffTime = Math.abs(dataFim - dataInicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    diasAfastamento.value = diffDays; 
                } else {
                    diasAfastamento.value = 0;
                }
            } else {
                diasAfastamento.value = '';
            }
        }
        dataAtestado.addEventListener('change', calcularDiasAfastamento);
        diaRetorno.addEventListener('change', calcularDiasAfastamento);


        // ------------------------------------
        // LÓGICA DE AUTOCOMPLETE PARA COLABORADOR
        // * A CHAVE 'id_colaborador' ESTÁ SENDO USADA AQUI *
        // ------------------------------------
        nomeColaborador.addEventListener('input', function() {
            // Limpa o ID a cada digitação para forçar o preenchimento pelo clique
            colaboradorId.value = ''; 

            const termo = this.value;
            if (termo.length >= 3) {
                fetch(`buscar_colaboradores.php?termo=${termo}`)
                    .then(response => response.json())
                    .then(data => {
                        autocompleteColaborador.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.textContent = `${item.matricula} - ${item.nome}`;
                                div.onclick = () => {
                                    nomeColaborador.value = `${item.matricula} - ${item.nome}`;
                                    // *** PONTO CRÍTICO: CHAVE DO ID DO COLABORADOR ***
                                    colaboradorId.value = item.id_colaborador; 
                                    autocompleteColaborador.innerHTML = '';
                                };
                                autocompleteColaborador.appendChild(div);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar colaboradores:', error);
                    });
            } else {
                autocompleteColaborador.innerHTML = '';
            }
        });
        
        // ------------------------------------
        // LÓGICA DE AUTOCOMPLETE PARA CID (CORRIGIDO PARA buscar_cids.php e >= 1)
        // ------------------------------------
        cidInput.addEventListener('input', function() {
            const termo = this.value;
            if (termo.length >= 1) { // Inicia a busca com 1 caractere
                fetch(`buscar_cids.php?termo=${termo}`) // Nome do arquivo corrigido
                    .then(response => {
                        // Verifica se o JSON é válido
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        autocompleteCid.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                // Chaves: 'sigla' e 'descricao' (devem corresponder ao PHP)
                                div.textContent = `${item.sigla} - ${item.descricao}`; 
                                div.onclick = () => {
                                    cidInput.value = item.sigla;
                                    autocompleteCid.innerHTML = '';
                                };
                                autocompleteCid.appendChild(div);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CIDs:', error);
                        // Mensagem de erro para o usuário (opcional)
                        autocompleteCid.innerHTML = '<div>Erro na busca de CID. Tente novamente.</div>';
                    });
            } else {
                autocompleteCid.innerHTML = '';
            }
        });

        // ------------------------------------
        // LÓGICA DE FECHAR AUTOCOMPLETE AO CLICAR FORA
        // ------------------------------------
        document.addEventListener('click', function(e) {
            if (!nomeColaborador.contains(e.target) && !autocompleteColaborador.contains(e.target)) {
                autocompleteColaborador.innerHTML = '';
            }
            if (!cidInput.contains(e.target) && !autocompleteCid.contains(e.target)) {
                autocompleteCid.innerHTML = '';
            }
        });
        
    </script>
</body>
</html>
<?php
$conn->close();
?>
<?php
// =================================================================
// GERAR_FICHA_PDF.PHP - Geração da Ficha de Entrega de EPI (PDF)
// =================================================================
require 'vendor/autoload.php'; // Altere se o caminho for diferente
use Dompdf\Dompdf;
use Dompdf\Options;

// 🚨 ATENÇÃO: Credenciais (Ajuste conforme seu ambiente)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst'); 

if (!isset($_GET['ordem'])) {
    die("Número de Ordem não especificado.");
}

$numero_ordem = $_GET['ordem'];

// Conexão com o MySQL
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Erro de Conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ------------------------------------------------
// 1. BUSCA DOS DADOS DA FICHA DE ENTREGA
// ------------------------------------------------
$sql = "
    SELECT 
        e.numero_ordem, 
        e.data_entrega, 
        c.nome AS nome_colaborador, 
        c.matricula, 
        c.setor,
        e.observacao,
        de.quantidade_movimentada, 
        p.nome_epi,
        p.ca
    FROM entregas e
    JOIN colaboradores c ON e.id_colaborador = c.id_colaborador
    JOIN detalhes_entrega de ON e.id_entrega = de.id_entrega
    JOIN epis p ON de.id_epi = p.id_epi
    WHERE e.numero_ordem = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $numero_ordem);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Ordem de entrega #{$numero_ordem} não encontrada.");
}

$ficha_data = [
    'itens' => [],
    'colaborador' => null,
    'numero_ordem' => $numero_ordem,
    'data_entrega' => null,
    'observacao' => ''
];

while ($row = $result->fetch_assoc()) {
    if (!$ficha_data['colaborador']) {
        $ficha_data['colaborador'] = $row['nome_colaborador'];
        $ficha_data['matricula'] = $row['matricula'];
        $ficha_data['setor'] = $row['setor'];
        $ficha_data['data_entrega'] = date('d/m/Y H:i:s', strtotime($row['data_entrega']));
        $ficha_data['observacao'] = $row['observacao'];
    }
    
    $ficha_data['itens'][] = [
        'nome_epi' => $row['nome_epi'],
        'ca' => $row['ca'],
        'quantidade' => $row['quantidade_movimentada']
    ];
}

$stmt->close();
$conn->close();

// ------------------------------------------------
// 2. GERAÇÃO DO CONTEÚDO HTML DO PDF
// ------------------------------------------------

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ficha de EPI - ' . $ficha_data['numero_ordem'] . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { font-size: 14pt; margin: 0; color: #007bff; }
        .header h2 { font-size: 10pt; margin: 0; font-weight: normal; }
        .dados-colab, .tabela-epi, .assinaturas { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .dados-colab td, .tabela-epi th, .tabela-epi td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .dados-colab th { background-color: #f2f2f2; text-align: right; width: 25%; }
        .tabela-epi th { background-color: #007bff; color: white; font-weight: bold; }
        .assinaturas td { border: none; padding-top: 40px; text-align: center; width: 50%; }
        .linha-assinatura { border-top: 1px solid #000; margin-top: 5px; }
        .termo { font-size: 9pt; margin-top: 30px; text-align: justify; }
    </style>
</head>
<body>

    <div class="header">
        <h1>FICHA DE ENTREGA E CONTROLE DE EPI (NR-6)</h1>
        <h2>Empresa SST | Ordem de Entrega: ' . $ficha_data['numero_ordem'] . '</h2>
    </div>

    <h3>Dados da Entrega</h3>
    <table class="dados-colab">
        <tr>
            <th>DATA/HORA DA ENTREGA:</th><td>' . $ficha_data['data_entrega'] . '</td>
            <th>MATRÍCULA:</th><td>' . $ficha_data['matricula'] . '</td>
        </tr>
        <tr>
            <th>COLABORADOR:</th><td>' . $ficha_data['colaborador'] . '</td>
            <th>SETOR:</th><td>' . $ficha_data['setor'] . '</td>
        </tr>
    </table>

    <h3>Itens Retirados</h3>
    <table class="tabela-epi">
        <thead>
            <tr>
                <th style="width: 50%;">Nome do EPI</th>
                <th style="width: 15%;">CA</th>
                <th style="width: 15%;">Quantidade</th>
            </tr>
        </thead>
        <tbody>';

foreach ($ficha_data['itens'] as $item) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($item['nome_epi']) . '</td>';
    $html .= '<td>' . htmlspecialchars($item['ca']) . '</td>';
    $html .= '<td>' . htmlspecialchars($item['quantidade']) . '</td>';
    $html .= '</tr>';
}

$html .= '
        </tbody>
    </table>

    <p style="font-size: 10pt;"><strong>Observações da Entrega:</strong> ' . (empty($ficha_data['observacao']) ? 'Nenhuma.' : htmlspecialchars($ficha_data['observacao'])) . '</p>
    
    <div class="termo">
        <strong>TERMO DE RESPONSABILIDADE:</strong> Declaro que recebi os Equipamentos de Proteção Individual (EPIs) listados acima, em perfeito estado de conservação e uso, e fui devidamente treinado quanto ao seu uso correto e guarda. Comprometo-me a zelar pela integridade dos EPIs e a utilizá-los conforme as normas de segurança e as determinações da empresa. Tenho ciência de que a recusa ou o uso inadequado constitui falta grave.
    </div>

    <table class="assinaturas">
        <tr>
            <td>
                <div class="linha-assinatura" style="width: 70%; margin: 0 auto;"></div>
                Nome e Assinatura do Colaborador
            </td>
            <td>
                <div class="linha-assinatura" style="width: 70%; margin: 0 auto;"></div>
                Nome e Assinatura do Técnico/Responsável
            </td>
        </tr>
    </table>

</body>
</html>
';

// ------------------------------------------------
// 3. CONFIGURAÇÃO E SAÍDA DO DOMPDF
// ------------------------------------------------

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Saída para o navegador (download)
$filename = "Ficha_EPI_{$numero_ordem}.pdf";

// Se o usuário quiser exportar para CSV, ele pode usar uma opção na página buscar_fichas.php (próximo passo)
$dompdf->stream($filename, ["Attachment" => true]);

exit(0);
<?php
// Define o cabeçalho para garantir que o navegador ou sistema receba JSON
header('Content-Type: application/json');

// Desativa a exibição de erros (melhor prática para endpoints de dados)
ini_set('display_errors', 0);
error_reporting(0);

// =================================================================
// 0. CARREGAR CONFIGURAÇÕES DE COLUNAS E CONSTANTES GLOBAIS
// =================================================================
// O config_loader.php define as constantes de nome de colunas (COL_FIRST_NAME, COL_LAST_NAME, etc.)
require_once __DIR__ . '/../src/config_loader.php';

// =================================================================
// 1. CONFIGURAÇÃO SEGURA E CONEXÃO COM O BANCO DE DADOS
// =================================================================
// O config_db.php (localizado dois níveis acima) define as credenciais e a função criar_conexao()
require_once __DIR__ . '/../../../config_db.php'; 

// --- Conexão com o banco de dados de pilotos ---
$conn_pilotos = null;
try {
    // DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS devem ser definidas em config_db.php
    $conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);
} catch (Exception $e) {
    // Retorna erro JSON em caso de falha na conexão
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    http_response_code(500);
    exit;
}

// =================================================================
// 2. QUERY PARA BUSCAR OS IDs E O NOME DOS PILOTOS VALIDADOS
// =================================================================
// Usamos CONCAT para juntar o primeiro e o último nome
$pilots_sql = "
    SELECT 
        CONCAT(" . COL_FIRST_NAME . ", ' ', " . COL_LAST_NAME . ") AS display_name,
        " . COL_EMAIL_PILOTO . ",
        " . COL_VATSIM_ID . ", 
        " . COL_IVAO_ID . "
    FROM 
        " . DB_PILOTOS_NAME . "." . PILOTS_TABLE . "
    WHERE
        " . COL_VALIDADO . " = 'true'
        -- Opcional: Garante que pelo menos um dos campos de ID não seja nulo ou vazio
        AND ((" . COL_VATSIM_ID . " IS NOT NULL AND " . COL_VATSIM_ID . " != '') OR (" . COL_IVAO_ID . " IS NOT NULL AND " . COL_IVAO_ID . " != ''))
    ORDER BY
        display_name ASC
";

$pilots_list = [];
$pilots_result = $conn_pilotos->query($pilots_sql);

if ($pilots_result) {
    while ($pilot = $pilots_result->fetch_assoc()) {
        // Remove IDs nulos/vazios para limpeza dos dados de saída
        $cleaned_pilot = array_filter($pilot);
        $pilots_list[] = $cleaned_pilot;
    }
}

// Fecha a conexão com o banco de dados
$conn_pilotos->close();

// =================================================================
// 3. RETORNA A LISTA EM FORMATO JSON
// =================================================================
echo json_encode($pilots_list);
?>
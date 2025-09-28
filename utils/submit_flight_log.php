<?php
// Define o cabeçalho para garantir que o navegador ou sistema receba JSON
header('Content-Type: application/json');

// Desativa a exibição de erros (melhor prática para endpoints de dados)
ini_set('display_errors', 0);
error_reporting(0);

// =================================================================
// 0. CARREGAR CONFIGURAÇÕES GLOBAIS
// =================================================================
// Carrega as constantes de coluna e a função t()
require_once __DIR__ . '/../src/config_loader.php';
// Carrega a função criar_conexao() e as constantes de credenciais (DB_VOOS_NAME, etc.)
require_once __DIR__ . '/../../../config_db.php';

// =================================================================
// 1. VERIFICAR MÉTODO HTTP E ENTRADAS OBRIGATÓRIAS (POST)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido. Este endpoint aceita apenas requisições POST.']);
    http_response_code(405); // Método Não Permitido
    exit;
}

$required_fields = ['userId', 'flightPlan_departureId', 'flightPlan_arrivalId', 'fuel_used', 'landing_vs'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode(['status' => 'error', 'message' => "Campo obrigatório ausente ou vazio: {$field}."]);
        http_response_code(400); // Requisição Inválida
        exit;
    }
}

// Sanitiza e atribui os dados de entrada
$userId = trim($_POST['userId']);
$departureId = strtoupper(trim($_POST['flightPlan_departureId']));
$arrivalId = strtoupper(trim($_POST['flightPlan_arrivalId']));
// Importante: usar floatval para garantir o tipo numérico
$fuelUsed = floatval($_POST['fuel_used']); 
$landingVS = floatval($_POST['landing_vs']); 

// =================================================================
// 2. CONFIGURAÇÃO E CONEXÃO COM A BASE DE DADOS DE VOOS
// =================================================================
$conn_voos = null;
try {
    $conn_voos = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);
    if ($conn_voos->connect_error) {
        throw new Exception("Falha na conexão.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Falha na conexão com o banco de dados.']);
    http_response_code(500);
    exit;
}

// =================================================================
// 3. ATUALIZAR O REGISTRO MAIS RECENTE (MÉTODO OTIMIZADO)
// =================================================================

// Otimização: Implementa a sugestão do usuário usando UPDATE + ORDER BY + LIMIT 1.
// A condição WHERE inclui userId, DEP e ARR para garantir que apenas o último
// voo DAQUELA ROTA seja atualizado.
$sql_update = "
    UPDATE voos
    SET fuel_used = ?, 
        landing_vs = ? 
    WHERE userId = ? 
      AND flightPlan_departureId = ? 
      AND flightPlan_arrivalId = ? 
    ORDER BY createdAt DESC
    LIMIT 1
";

$stmt_update = $conn_voos->prepare($sql_update);

if ($stmt_update === false) {
    $error_message = $conn_voos->error;
    $conn_voos->close();
    echo json_encode(['status' => 'error', 'message' => "Erro de preparação da consulta UPDATE: {$error_message}"]);
    http_response_code(500);
    exit;
}

// Binding: ddsss (double, double, string, string, string) para os 5 placeholders
$stmt_update->bind_param("ddsss", $fuelUsed, $landingVS, $userId, $departureId, $arrivalId);

if ($stmt_update->execute()) {
    $affected_rows = $stmt_update->affected_rows;

    if ($affected_rows > 0) {
        $response = [
            'status' => 'success',
            'message' => 'Dados de voo (último registro correspondente) atualizados com sucesso.',
            'affected_rows' => $affected_rows,
            'updated_values' => ['fuel_used' => $fuelUsed, 'landing_vs' => $landingVS]
        ];
        http_response_code(200);
    } else {
        // Se afetou 0 linhas, significa que nenhum registro satisfaz o WHERE + ORDER BY + LIMIT
        $response = [
            'status' => 'not_found',
            'message' => "Nenhum voo recente encontrado que corresponda aos critérios (userId: {$userId}, Origem: {$departureId}, Destino: {$arrivalId}) para atualização."
        ];
        http_response_code(404);
    }
} else {
    $response = [
        'status' => 'error',
        'message' => 'Falha ao executar a atualização do banco de dados.',
        'db_error' => $stmt_update->error
    ];
    http_response_code(500);
}

$stmt_update->close();
$conn_voos->close();

// =================================================================
// 4. RETORNA A RESPOSTA EM FORMATO JSON
// =================================================================
echo json_encode($response, JSON_PRETTY_PRINT);
<?php
// =================================================================
// SCRIPT PARA DESABILITAR UM PILOTO (VERSÃO COM NOME NA MENSAGEM)
// =================================================================

// 1. Carregar configurações e conexão
require_once __DIR__ . '/config_loader.php';
require_once __DIR__ . '/../../../config_db.php'; // Ajuste o caminho se necessário

// Verificar se o ID e o nome do piloto foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilot_id']) && isset($_POST['pilot_name'])) {
    $pilot_id_to_disable = intval($_POST['pilot_id']);
    $pilot_name = $_POST['pilot_name'];

    if ($pilot_id_to_disable > 0) {
        $conn_pilotos = null;
        try {
            // 2. Conectar ao banco de dados de pilotos
            $conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);

            // 3. Preparar a query de UPDATE para a tabela de metadados
            $meta_table_name = 'wp_postmeta';
            $meta_key_name = '_validado';

            $sql = "UPDATE {$meta_table_name} SET meta_value = 'false' WHERE post_id = ? AND meta_key = ?";
            
            $stmt = $conn_pilotos->prepare($sql);
            $stmt->bind_param("is", $pilot_id_to_disable, $meta_key_name);

            // 4. Executar a query
            if ($stmt->execute()) {
                // Sucesso
                $stmt->close();
                $conn_pilotos->close();
                // 5. Redirecionar de volta com mensagem de sucesso (usando o nome)
                $success_msg = urlencode($pilot_name);
                header('Location: ../est.php?filtro=ativos_em_alerta&status=success&pilot_name=' . $success_msg);
                exit;
            } else {
                // Falha na execução
                throw new Exception("Erro ao executar a atualização no banco de dados.");
            }
        } catch (Exception $e) {
            if (isset($conn_pilotos) && $conn_pilotos) {
                $conn_pilotos->close();
            }
            // 5. Redirecionar de volta com mensagem de erro
            $error_msg = urlencode("Falha ao desabilitar: " . $e->getMessage());
            header('Location: ../est.php?filtro=ativos_em_alerta&status=error&message=' . $error_msg);
            exit;
        }
    }
}

// Se o acesso não for via POST ou os dados não forem válidos, redireciona
header('Location: ../est.php?filtro=ativos_em_alerta');
exit;
?>
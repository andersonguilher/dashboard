<?php
// =================================================================
// SCRIPT FINAL PARA DESABILITAR PILOTO E ENVIAR E-MAIL (COM FEEDBACK DE ENVIO)
// =================================================================

// 1. Carregar configurações e conexão
require_once __DIR__ . '/config_loader.php';
require_once __DIR__ . '/../../../config_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilot_id']) && isset($_POST['pilot_name'])) {
    $pilot_id_to_disable = intval($_POST['pilot_id']);
    $pilot_name = $_POST['pilot_name'];

    if ($pilot_id_to_disable > 0) {
        $conn_pilotos = null;
        try {
            $conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);

            $sql_get_email = "SELECT " . COL_EMAIL_PILOTO . " FROM " . PILOTS_TABLE . " WHERE " . COL_POST_ID . " = ?";
            $stmt_email = $conn_pilotos->prepare($sql_get_email);
            $stmt_email->bind_param("i", $pilot_id_to_disable);
            $stmt_email->execute();
            $result_email = $stmt_email->get_result();
            $pilot_email_data = $result_email->fetch_assoc();
            $pilot_email = $pilot_email_data[COL_EMAIL_PILOTO] ?? null;
            $stmt_email->close();
            
            $meta_table_name = 'wp_postmeta';
            $meta_key_name = '_validado';
            $sql_disable = "UPDATE {$meta_table_name} SET meta_value = 'false' WHERE post_id = ? AND meta_key = ?";
            $stmt_disable = $conn_pilotos->prepare($sql_disable);
            $stmt_disable->bind_param("is", $pilot_id_to_disable, $meta_key_name);

            if ($stmt_disable->execute()) {
                $email_sent_flag = 0; // Inicia o flag como 0 (não enviado)

                if ($pilot_email && filter_var($pilot_email, FILTER_VALIDATE_EMAIL)) {
                    $to      = $pilot_email;
                    $subject = t('disable_email_subject');
                    // Usa o e-mail da companhia e o nome da companhia do settings.json
                    $company_name_from_settings = $settings['company_name'] ?? 'Sua Companhia Aérea Virtual';
                    $from_email = $settings['company_email'] ?? 'contato@kafly.com.br';
                    $message = sprintf(t('disable_email_body'), $pilot_name, $company_name_from_settings);

                    $display_name = $company_name_from_settings;

                    $headers  = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
                    $headers .= "From: " . $display_name . " <" . $from_email . ">" . "\r\n";

                    if (@mail($to, $subject, $message, $headers)) {
                        $email_sent_flag = 1; // Se o e-mail foi enviado, muda o flag para 1
                    }
                }

                $stmt_disable->close();
                $conn_pilotos->close();
                
                $success_msg = urlencode($pilot_name);
                header('Location: ../est.php?filtro=ativos_em_alerta&status=success&pilot_name=' . $success_msg . '&email_sent=' . $email_sent_flag);
                exit;
            } else {
                throw new Exception("Erro ao executar a atualização no banco de dados.");
            }
        } catch (Exception $e) {
            if (isset($conn_pilotos) && $conn_pilotos) {
                $conn_pilotos->close();
            }
            $error_msg = urlencode("Falha ao desabilitar: " . $e->getMessage());
            header('Location: ../est.php?filtro=ativos_em_alerta&status=error&message=' . $error_msg);
            exit;
        }
    }
}

header('Location: ../est.php?filtro=ativos_em_alerta');
exit;
?>

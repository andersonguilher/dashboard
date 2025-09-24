<?php
// Habilita a exibição de erros para ajudar na depuração, caso algo inesperado aconteça
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Caminho para o arquivo de configurações
$settings_file = __DIR__ . '/../../config/settings.json';
// Variável para armazenar mensagens de erro que serão exibidas na página
$error_message = '';
$success_message = '';

// --- VERIFICAÇÃO DE LEITURA DO ARQUIVO ---
if (!file_exists($settings_file)) {
    // Tenta criar o arquivo com valores padrão se ele não existir
    $default_settings = json_encode(['theme' => 'default', 'language' => 'pt'], JSON_PRETTY_PRINT);
    if (@file_put_contents($settings_file, $default_settings) === false) {
        $error_message = "<strong>Erro Crítico:</strong> O arquivo <code>settings.json</code> não existe e não pôde ser criado. Verifique as permissões de escrita na pasta <code>" . __DIR__ . "</code>.";
    }
} elseif (!is_readable($settings_file)) {
    $error_message = "<strong>Erro Crítico:</strong> O arquivo <code>settings.json</code> existe, mas não pode ser lido. Verifique as permissões do arquivo (tente <code>chmod 664</code>).";
}

// --- LÓGICA PARA SALVAR (COM VERIFICAÇÃO) ---
// Só executa se não houver um erro crítico na leitura do arquivo
if (empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Antes de tentar salvar, verificamos se o arquivo pode ser escrito
    if (!is_writable($settings_file)) {
        $error_message = "<strong>Falha ao Salvar:</strong> O PHP não tem permissão para escrever no arquivo <code>settings.json</code>. Ajuste as permissões do arquivo para <code>664</code> ou <code>666</code>.";
    } else {
        $current_settings = json_decode(file_get_contents($settings_file), true);

        // Atualiza os valores com os dados do formulário
        $current_settings['language'] = $_POST['language'] ?? 'pt';
        $current_settings['theme'] = $_POST['theme'] ?? 'default';

        // Tenta salvar os dados no arquivo
        $bytes_written = file_put_contents($settings_file, json_encode($current_settings, JSON_PRETTY_PRINT));

        if ($bytes_written === false) {
            $error_message = "<strong>Falha ao Salvar:</strong> Ocorreu um erro desconhecido ao tentar escrever no arquivo <code>settings.json</code>.";
        } else {
            // Apenas redireciona se a gravação foi bem-sucedida
            header('Location: index.php?status=success');
            exit;
        }
    }
}

// LÓGICA PARA LER AS CONFIGURAÇÕES ATUAIS PARA EXIBIR NO FORMULÁRIO
$settings = [];
if (empty($error_message)) {
    $settings = json_decode(file_get_contents($settings_file), true);
}
$current_lang = $settings['language'] ?? 'pt';
$current_theme = $settings['theme'] ?? 'default';

// Verifica se a página foi redirecionada com sucesso
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success_message = 'Configurações globais salvas com sucesso!';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Globais do Site</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 700px; margin: auto; background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #1e3a5f; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 25px; }
        label { display: block; font-weight: 500; margin-bottom: 8px; color: #555; }
        select, .color-options { width: 100%; padding: 12px; border-radius: 5px; border: 1px solid #ccc; font-size: 1em; }
        .color-options { display: flex; justify-content: space-around; padding: 0; border: none; }
        .color-option { display: flex; flex-direction: column; align-items: center; cursor: pointer; }
        .color-preview { width: 50px; height: 50px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 5px; }
        .color-option input[type="radio"] { display: none; }
        .color-option input[type="radio"]:checked + .color-preview { border-color: #007bff; }
        #default-preview { background-color: #0d6efd; }
        #dark-preview { background-color: #343a40; }
        #ocean-preview { background-color: #17a2b8; }
        #red-preview { background-color: #dc3545; }
        button { display: block; width: 100%; padding: 15px; border-radius: 5px; border: none; font-size: 1.1em; background-color: #007bff; color: white; cursor: pointer; font-weight: 500; }
        button:disabled { background-color: #a0a0a0; cursor: not-allowed; }
        .message-box { text-align: center; padding: 15px; border: 1px solid; border-radius: 5px; margin-bottom: 20px; word-wrap: break-word; }
        .message-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Painel de Controle Global</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="message-box message-error">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message-box message-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="language">Idioma Global</label>
                <select name="language" id="language" <?= !empty($error_message) ? 'disabled' : '' ?>>
                    <option value="pt" <?= $current_lang === 'pt' ? 'selected' : '' ?>>Português</option>
                    <option value="es" <?= $current_lang === 'es' ? 'selected' : '' ?>>Español</option>
                </select>
            </div>

            <div class="form-group">
                <label>Esquema de Cores Global</label>
                <div class="color-options">
                    <label class="color-option">
                        <input type="radio" name="theme" value="default" <?= $current_theme === 'default' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>>
                        <div class="color-preview" id="default-preview"></div>
                        Padrão
                    </label>
                    <label class="color-option">
                        <input type="radio" name="theme" value="dark" <?= $current_theme === 'dark' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>>
                        <div class="color-preview" id="dark-preview"></div>
                        Escuro
                    </label>
                    <label class="color-option">
                        <input type="radio" name="theme" value="ocean" <?= $current_theme === 'ocean' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>>
                        <div class="color-preview" id="ocean-preview"></div>
                        Oceano
                    </label>
                    <label class="color-option">
                        <input type="radio" name="theme" value="red" <?= $current_theme === 'red' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>>
                        <div class="color-preview" id="red-preview"></div>
                        Vermelho
                    </label>
                </div>
            </div>

            <button type="submit" <?= !empty($error_message) ? 'disabled' : '' ?>>Salvar para Todos</button>
        </form>
    </div>
</body>
</html>
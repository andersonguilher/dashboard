<?php
// Habilita a exibição de erros para ajudar na depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

$settings_file = __DIR__ . '/../config/settings.json';
$error_message = '';
$success_message = '';

if (!file_exists($settings_file)) {
    $default_settings = json_encode([
        'theme' => 'default',
        'language' => 'pt',
        'company_name' => '',       // Campo adicionado
        'company_email' => '',      // Campo adicionado
        'database_mappings' => [
            'pilots_table' => 'Dados_dos_Pilotos',
            'columns' => [
                'post_id' => 'post_id', 'first_name' => 'first_name', 'last_name' => 'last_name',
                'vatsim_id' => 'vatsim_id', 'ivao_id' => 'ivao_id', 'foto_perfil' => 'foto_perfil',
                'validado' => 'validado', 'matricula' => 'matricula', 'id_piloto' => 'id_piloto',
                'email_piloto' => 'email_piloto'
            ]
        ]
    ], JSON_PRETTY_PRINT);
    if (@file_put_contents($settings_file, $default_settings) === false) {
        $error_message = "<strong>Erro Crítico:</strong> O arquivo <code>settings.json</code> não existe e não pôde ser criado.";
    }
} elseif (!is_readable($settings_file) || !is_writable($settings_file)) {
    $error_message = "<strong>Erro Crítico:</strong> O arquivo <code>settings.json</code> não pode ser lido ou escrito.";
}

// SALVAR CONFIGURAÇÕES
if (empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_settings = json_decode(file_get_contents($settings_file), true);

    $current_settings['language'] = $_POST['language'] ?? 'pt';
    $current_settings['theme'] = $_POST['theme'] ?? 'default';
    $current_settings['company_name'] = $_POST['company_name'] ?? '';  // Salva o campo
    $current_settings['company_email'] = $_POST['company_email'] ?? ''; // Salva o campo

    if (isset($_POST['db_mappings'])) {
        $current_settings['database_mappings']['pilots_table'] = trim($_POST['db_mappings']['pilots_table']);
        foreach ($_POST['db_mappings']['columns'] as $key => $value) {
            $current_settings['database_mappings']['columns'][$key] = trim($value);
        }
    }

    if (file_put_contents($settings_file, json_encode($current_settings, JSON_PRETTY_PRINT)) === false) {
        $error_message = "<strong>Falha ao Salvar:</strong> Ocorreu um erro ao tentar escrever no arquivo <code>settings.json</code>.";
    } else {
        header('Location: index.php?status=success');
        exit;
    }
}

// LER CONFIGURAÇÕES ATUAIS
$settings = [];
if (empty($error_message)) {
    $settings = json_decode(file_get_contents($settings_file), true);
}
$current_lang = $settings['language'] ?? 'pt';
$current_theme = $settings['theme'] ?? 'default';
$db_mappings = $settings['database_mappings'] ?? [];
$company_name = $settings['company_name'] ?? '';
$company_email = $settings['company_email'] ?? '';

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success_message = 'Configurações salvas com sucesso!';
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
/* TODO: manter estilos exatamente iguais à versão anterior */
body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; color: #333; margin: 0; padding: 20px; }
.container { max-width: 800px; margin: auto; background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: relative; }
h1, h2 { color: #1e3a5f; text-align: center; }
h2 { font-size: 1.2em; border-top: 1px solid #eee; padding-top: 25px; margin-top: 30px; }
.form-group, .form-grid-item { margin-bottom: 20px; }
label { display: block; font-weight: 500; margin-bottom: 8px; color: #555; }
input[type="text"], input[type="email"], select { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 1em; box-sizing: border-box; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.color-options { display: flex; justify-content: space-around; padding: 0; border: none; }
.color-option { display: flex; flex-direction: column; align-items: center; cursor: pointer; }
.color-preview { width: 50px; height: 50px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 5px; }
.color-option input[type="radio"] { display: none; }
.color-option input[type="radio"]:checked + .color-preview { border-color: #007bff; }
#default-preview { background-color: #0d6efd; } #dark-preview { background-color: #343a40; } #ocean-preview { background-color: #17a2b8; } #red-preview { background-color: #dc3545; }
button { display: block; width: 100%; padding: 15px; border-radius: 5px; border: none; font-size: 1.1em; background-color: #007bff; color: white; cursor: pointer; font-weight: 500; }
button:disabled { background-color: #a0a0a0; cursor: not-allowed; }
.message-box { text-align: center; padding: 15px; border-radius: 5px; margin-bottom: 20px; word-wrap: break-word; }
.message-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
.message-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.back-link { position: absolute; top: 20px; left: 20px; text-decoration: none; color: #555; background-color: #f0f2f5; padding: 8px 12px; border-radius: 5px; font-weight: 500; transition: background-color 0.2s; }
.back-link:hover { background-color: #e2e6ea; }
</style>
</head>
<body>
<div class="container">
    <a href="../index.php" class="back-link">&larr; Voltar ao Dashboard</a>
    <h1>Painel de Controle Global</h1>

    <?php if (!empty($error_message)): ?>
        <div class="message-box message-error"><?= $error_message ?></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <div class="message-box message-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <form action="index.php" method="POST">
        <h2>Configurações Gerais</h2>
        <div class="form-group">
            <label for="company_name">Nome da Companhia</label>
            <input type="text" name="company_name" id="company_name" value="<?= htmlspecialchars($company_name) ?>" <?= !empty($error_message) ? 'disabled' : '' ?>>
        </div>
        <div class="form-group">
            <label for="company_email">E-mail da Companhia</label>
            <input type="email" name="company_email" id="company_email" value="<?= htmlspecialchars($company_email) ?>" <?= !empty($error_message) ? 'disabled' : '' ?>>
        </div>
        <div class="form-grid">
            <div class="form-grid-item">
                <label for="language">Idioma Global</label>
                <select name="language" id="language" <?= !empty($error_message) ? 'disabled' : '' ?>>
                    <option value="pt" <?= $current_lang === 'pt' ? 'selected' : '' ?>>Português</option>
                    <option value="es" <?= $current_lang === 'es' ? 'selected' : '' ?>>Español</option>
                </select>
            </div>
            <div class="form-grid-item">
                <label>Esquema de Cores</label>
                <div class="color-options">
                    <label class="color-option"><input type="radio" name="theme" value="default" <?= $current_theme === 'default' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>><div class="color-preview" id="default-preview"></div>Padrão</label>
                    <label class="color-option"><input type="radio" name="theme" value="dark" <?= $current_theme === 'dark' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>><div class="color-preview" id="dark-preview"></div>Escuro</label>
                    <label class="color-option"><input type="radio" name="theme" value="ocean" <?= $current_theme === 'ocean' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>><div class="color-preview" id="ocean-preview"></div>Oceano</label>
                    <label class="color-option"><input type="radio" name="theme" value="red" <?= $current_theme === 'red' ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>><div class="color-preview" id="red-preview"></div>Vermelho</label>
                </div>
            </div>
        </div>

        <h2>Mapeamento da Tabela de Pilotos</h2>
        <div class="form-group">
            <label for="db_table">Nome da Tabela de Pilotos</label>
            <input type="text" name="db_mappings[pilots_table]" id="db_table" value="<?= htmlspecialchars($db_mappings['pilots_table'] ?? 'Dados_dos_Pilotos') ?>" <?= !empty($error_message) ? 'disabled' : '' ?>>
        </div>
        <div class="form-grid">
            <?php 
            $columns = ['id_piloto' => 'ID Único do Piloto', 'post_id' => 'ID (Post)', 'first_name' => 'Primeiro Nome', 'last_name' => 'Último Nome', 'vatsim_id' => 'ID Vatsim', 'ivao_id' => 'ID Ivao', 'foto_perfil' => 'Foto de Perfil', 'validado' => 'Coluna de Validação', 'matricula' => 'Matrícula/Callsign', 'email_piloto' => 'E-mail do Piloto'];
            foreach ($columns as $key => $label):
            ?>
            <div class="form-grid-item">
                <label for="col_<?= $key ?>"><?= $label ?></label>
                <input type="text" name="db_mappings[columns][<?= $key ?>]" id="col_<?= $key ?>" value="<?= htmlspecialchars($db_mappings['columns'][$key] ?? $key) ?>" <?= !empty($error_message) ? 'disabled' : '' ?>>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" <?= !empty($error_message) ? 'disabled' : '' ?> style="margin-top: 20px;">Salvar Configurações</button>
    </form>
</div>
</body>
</html>

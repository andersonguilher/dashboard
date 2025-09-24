<?php
// ===================================
// 1. CARREGAR CONFIGURAÇÕES GLOBAIS DO ARQUIVO JSON
// ===================================
$settings_file = __DIR__ . '/../config/settings.json';
$settings = [];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
}

// Define os valores padrão caso o arquivo esteja vazio ou corrompido
$lang_code = $settings['language'] ?? 'pt';
$theme = $settings['theme'] ?? 'default';

// ===================================
// 2. CARREGAR IDIOMA
// ===================================
$lang_file = __DIR__ . '/../config/lang/' . $lang_code . '.php';

if (file_exists($lang_file)) {
    require_once $lang_file;
} else {
    // Fallback para português se o arquivo de idioma não for encontrado
    require_once __DIR__ . '/../config/lang/pt.php';
}

// Função de atalho para tradução
function t($key) {
    global $lang;
    return $lang[$key] ?? $key; // Retorna a chave se a tradução não for encontrada
}

// ===================================
// 3. APLICAR TEMA DE CORES GLOBAL
// ===================================
function apply_color_theme() {
    global $theme; // Usa a variável global $theme lida do arquivo JSON
    $css_vars = '';

    switch ($theme) {
        case 'default':
            $css_vars = "
                --background-color: #f0f2f5; --card-background-color: #ffffff; --text-color: #333333;
                --text-color-light: #666666; --border-color: #e0e0e0; --primary-color: #0d6efd;
                --primary-color-dark: #0a58ca;
            ";
            break;
        case 'dark':
            $css_vars = "
                --background-color: #1c1c1e; --card-background-color: #2c2c2e; --text-color: #f0f0f0;
                --text-color-light: #a0a0a0; --border-color: #444444; --primary-color: #0a84ff;
                --primary-color-dark: #339aff;
            ";
            break;
        case 'ocean':
            $css_vars = "
                --background-color: #e0f7fa; --card-background-color: #ffffff; --text-color: #004d40;
                --text-color-light: #00796b; --border-color: #b2dfdb; --primary-color: #00acc1;
                --primary-color-dark: #00838f;
            ";
            break;
        case 'red':
            $css_vars = "
                --background-color: #f8f0f1; --card-background-color: #ffffff; --text-color: #333333;
                --text-color-light: #721c24; --border-color: #f5c6cb; --primary-color: #dc3545;
                --primary-color-dark: #b02a37;
            ";
            break;
        default:
            // Se o tema não for reconhecido, aplica o 'default'
             $css_vars = "
                --background-color: #f0f2f5; --card-background-color: #ffffff; --text-color: #333333;
                --text-color-light: #666666; --border-color: #e0e0e0; --primary-color: #0d6efd;
                --primary-color-dark: #0a58ca;
            ";
            break;
    }

    // Retorna o bloco de estilo para ser inserido no <head>
    echo "<style>:root { " . $css_vars . " }</style>";
}
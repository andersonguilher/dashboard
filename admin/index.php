<?php
// Habilita a exibição de erros para ajudar na depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

$settings_file = __DIR__ . '/../config/settings.json';
$error_message = '';
$success_message = '';

// --- Função Auxiliar para garantir estrutura aninhada (mantida do código anterior) ---
function initialize_settings_structure($current_settings) {
    // Inicializa a estrutura da homepage se não existir, com links padrão
    if (!isset($current_settings['homepage_config'])) {
        $current_settings['homepage_config'] = [
            'logo_url' => 'assets/logo.png',
            // Removido 'hero_image_url'
            'menu_links' => [
                // CORREÇÃO: HOME APONTA PARA A SUBPASTA
                [ "text" => "Home", "url" => "home/index.php", "is_cta" => false ],
                [ "text" => "Frota", "url" => "financial/index.php", "is_cta" => false ],
                [ "text" => "Carreiras", "url" => "#", "is_cta" => false ],
                [ "text" => "Pilotos", "url" => "est.php", "is_cta" => false ],
                [ "text" => "Acervo", "url" => "#", "is_cta" => false ],
                [ "text" => "Online", "url" => "#", "is_cta" => false ],
                [ "text" => "Staff", "url" => "#", "is_cta" => false ],
                // CORREÇÃO: ESTATÍSTICAS APONTA PARA O NOVO ARQUIVO RAIZ
                [ "text" => "Estatísticas", "url" => "index.php", "is_cta" => false ],
                [ "text" => "Contato", "url" => "#", "is_cta" => false ],
                [ "text" => "Inscreva-se", "url" => "#", "is_cta" => true ]
            ]
        ];
    }
    return $current_settings;
}

if (!file_exists($settings_file)) {
    $default_settings_array = [
        'theme' => 'default',
        'language' => 'pt',
        'company_name' => '',
        'company_email' => '',
        'database_mappings' => [
            'pilots_table' => 'Dados_dos_Pilotos',
            'columns' => [
                'post_id' => 'post_id', 'first_name' => 'first_name', 'last_name' => 'last_name',
                'vatsim_id' => 'vatsim_id', 'ivao_id' => 'ivao_id', 'foto_perfil' => 'foto_perfil',
                'validado' => 'validado', 'matricula' => 'matricula', 'id_piloto' => 'id_piloto',
                'email_piloto' => 'email_piloto'
            ]
        ]
    ];
    $default_settings_array = initialize_settings_structure($default_settings_array);
    $default_settings = json_encode($default_settings_array, JSON_PRETTY_PRINT);

    if (@file_put_contents($settings_file, $default_settings) === false) {
        $error_message = "<strong>Erro Crítico:</strong> O arquivo <code>settings.json</code> não existe e não pôde ser criado.";
    }
} elseif (!is_readable($settings_file) || !is_writable($settings_file)) {
    $error_message = "<strong>Erro Crítico:</strong> O arquivo <code>settings.json</code> não pode ser lido ou escrito.";
}

// SALVAR CONFIGURAÇÕES
if (empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_settings = json_decode(file_get_contents($settings_file), true);
    $current_settings = initialize_settings_structure($current_settings); // Garante a estrutura antes de salvar

    // Configurações Gerais
    $current_settings['language'] = $_POST['language'] ?? 'pt';
    $current_settings['theme'] = $_POST['theme'] ?? 'default';
    $current_settings['company_name'] = $_POST['company_name'] ?? '';
    $current_settings['company_email'] = $_POST['company_email'] ?? '';

    // Mapeamento DB
    if (isset($_POST['db_mappings'])) {
        $current_settings['database_mappings']['pilots_table'] = trim($_POST['db_mappings']['pilots_table']);
        foreach ($_POST['db_mappings']['columns'] as $key => $value) {
            $current_settings['database_mappings']['columns'][$key] = trim($value);
        }
    }

    // Configurações da Homepage
    if (isset($_POST['homepage_config'])) {
        $current_settings['homepage_config']['logo_url'] = trim($_POST['homepage_config']['logo_url'] ?? '');
        // Removida a lógica de salvar hero_image_url
    }

    // Menu Links
    if (isset($_POST['menu_link_text']) && isset($_POST['menu_link_url']) && is_array($_POST['menu_link_text'])) {
        $new_menu = [];
        // Reindexa e processa os links
        $keys = array_keys($_POST['menu_link_text']);
        foreach ($keys as $key) {
            $text = trim($_POST['menu_link_text'][$key]);
            $url = trim($_POST['menu_link_url'][$key] ?? '#');
            $is_cta = isset($_POST['menu_link_is_cta'][$key]) && $_POST['menu_link_is_cta'][$key] === '1';

            if (!empty($text)) {
                $new_menu[] = [
                    'text' => $text,
                    'url' => $url,
                    'is_cta' => $is_cta
                ];
            }
        }
        $current_settings['homepage_config']['menu_links'] = $new_menu;
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
    $settings = initialize_settings_structure($settings); // Garante a estrutura ao carregar
}
$current_lang = $settings['language'] ?? 'pt';
$current_theme = $settings['theme'] ?? 'default';
$db_mappings = $settings['database_mappings'] ?? [];
$company_name = $settings['company_name'] ?? '';
$company_email = $settings['company_email'] ?? '';

// Configurações da Homepage
$homepage_config = $settings['homepage_config'] ?? [];
$logo_url = $homepage_config['logo_url'] ?? 'assets/logo.png';
// Removida a variável $hero_image_url

$menu_links = $homepage_config['menu_links'] ?? [];

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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
/* Base Styles */
body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; color: #333; margin: 0; padding: 20px; }
.container { max-width: 1100px; margin: auto; background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: relative; }
h1 { color: #1e3a5f; text-align: center; margin-bottom: 30px; }

/* Section Grouping */
.config-section {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}
.config-section h2 { 
    font-size: 1.5em; 
    border-bottom: 2px solid #ddd; 
    padding-bottom: 10px; 
    margin-top: 0; 
    margin-bottom: 20px;
    color: #1e3a5f;
    text-align: left;
}

/* Form Elements */
.form-group, .form-grid-item { margin-bottom: 20px; }
label { display: block; font-weight: 500; margin-bottom: 8px; color: #555; }
input[type="text"], input[type="email"], select { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 1em; box-sizing: border-box; background-color: #fff; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }

/* Color Options */
.color-options { display: flex; justify-content: space-around; padding: 0; border: none; }
.color-option { display: flex; flex-direction: column; align-items: center; cursor: pointer; }
.color-preview { width: 50px; height: 50px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 5px; }
.color-option input[type="radio"] { display: none; }
.color-option input[type="radio"]:checked + .color-preview { border-color: #007bff; }
#default-preview { background-color: #0d6efd; } #dark-preview { background-color: #343a40; } #ocean-preview { background-color: #17a2b8; } #red-preview { background-color: #dc3545; }

/* Menu Configuration Specific Styles */
#menu-links-container { margin-top: 20px; }
.menu-item-header { 
    display: grid; 
    grid-template-columns: 2fr 3fr 70px 40px; 
    gap: 10px; 
    margin-bottom: 10px; 
    font-weight: 700; 
    padding: 0 10px; 
    color: #1e3a5f;
    font-size: 0.9em;
    text-transform: uppercase;
}
.menu-item-row { 
    display: grid; 
    grid-template-columns: 2fr 3fr 70px 40px; 
    gap: 10px; 
    margin-bottom: 10px; 
    align-items: center; 
    border: 1px solid #e9ecef; 
    background-color: #fff; 
    padding: 10px; 
    border-radius: 5px; 
}
.menu-item-row input[type="checkbox"] { width: auto; margin: 0; }
.menu-item-row input[type="text"] { width: 100%; padding: 8px; font-size: 0.9em; }

.remove-link-btn { 
    background-color: #dc3545; 
    color: white; 
    border: none; 
    padding: 8px 10px; 
    border-radius: 5px; 
    cursor: pointer; 
    font-size: 0.9em; 
    width: 35px;
}
.add-link-btn { 
    background-color: #28a745; 
    color: white; 
    border: none; 
    padding: 10px 15px; 
    border-radius: 5px; 
    cursor: pointer; 
    display: block; 
    width: 100%; 
    margin-top: 20px; 
    font-size: 1em;
}
.link-label { display: flex; align-items: center; gap: 5px; margin: 0; font-weight: 400; }

/* Utility */
button[type="submit"] { display: block; width: 100%; padding: 15px; border-radius: 5px; border: none; font-size: 1.1em; background-color: #0d6efd; color: white; cursor: pointer; font-weight: 500; margin-top: 30px; }
button[type="submit"]:disabled { background-color: #a0a0a0; cursor: not-allowed; }
.message-box { text-align: center; padding: 15px; border-radius: 5px; margin-bottom: 20px; word-wrap: break-word; }
.message-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
.message-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.back-link { position: absolute; top: 20px; left: 20px; text-decoration: none; color: #555; background-color: #f0f2f5; padding: 8px 12px; border-radius: 5px; font-weight: 500; transition: background-color 0.2s; }
.back-link:hover { background-color: #e2e6ea; }
</style>
</head>
<body>
<div class="container">
    <a href="../home/index.php" class="back-link">&larr; Voltar ao Home</a>
    <h1>Painel de Controle Global</h1>

    <?php if (!empty($error_message)): ?>
        <div class="message-box message-error"><?= $error_message ?></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <div class="message-box message-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <form action="index.php" method="POST">
        
        <div class="config-section">
            <h2>Configurações Gerais e Aparência</h2>
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
        </div>
        
        <div class="config-section">
            <h2>Configurações da Homepage</h2>
            <div class="form-group">
                <label for="logo_url">URL do Logo (Header)</label>
                <input type="text" name="homepage_config[logo_url]" id="logo_url" value="<?= htmlspecialchars($logo_url) ?>" placeholder="Ex: assets/img/logo_header.png" <?= !empty($error_message) ? 'disabled' : '' ?>>
            </div>
            <h3 style="font-size: 1.1em; margin-top: 30px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;">Links de Navegação (Menu)</h3>
            <div id="menu-links-container">
                <div class="menu-item-header">
                    <div>Texto do Menu</div>
                    <div>URL/Caminho</div>
                    <div>CTA?</div>
                    <div></div>
                </div>
                <?php $link_index_counter = 0; ?>
                <?php foreach ($menu_links as $link): ?>
                    <div class="menu-item-row">
                        <input type="text" name="menu_link_text[<?= $link_index_counter ?>]" value="<?= htmlspecialchars($link['text']) ?>" placeholder="Texto" <?= !empty($error_message) ? 'disabled' : '' ?>>
                        <input type="text" name="menu_link_url[<?= $link_index_counter ?>]" value="<?= htmlspecialchars($link['url']) ?>" placeholder="URL/Caminho" <?= !empty($error_message) ? 'disabled' : '' ?>>
                        <label class="link-label">
                            <input type="checkbox" name="menu_link_is_cta[<?= $link_index_counter ?>]" value="1" <?= $link['is_cta'] ? 'checked' : '' ?> <?= !empty($error_message) ? 'disabled' : '' ?>>
                            Sim
                        </label>
                        <button type="button" class="remove-link-btn" title="Remover" <?= !empty($error_message) ? 'disabled' : '' ?>><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php $link_index_counter++; ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="add-link-btn" id="add-link-btn" <?= !empty($error_message) ? 'disabled' : '' ?>><i class="fa-solid fa-plus"></i> Adicionar Link</button>
        </div>

        <div class="config-section">
            <h2>Mapeamento da Tabela de Pilotos</h2>
            <div class="form-group">
                <label for="db_table">Nome da Tabela de Pilotos</label>
                <input type="text" name="db_mappings[pilots_table]" id="db_table" value="<?= htmlspecialchars($db_mappings['pilots_table'] ?? 'Dados_dos_Pilotos') ?>" <?= !empty($error_message) ? 'disabled' : '' ?>>
            </div>
            <div class="form-grid form-grid-3">
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
        </div>

        <button type="submit" <?= !empty($error_message) ? 'disabled' : '' ?>>Salvar Configurações</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('menu-links-container');
        const addBtn = document.getElementById('add-link-btn');
        let indexCounter = <?= $link_index_counter ?>; // Começa após o último item existente

        function addRow(text = '', url = '#', isCta = false) {
            const index = indexCounter++;
            const row = document.createElement('div');
            row.classList.add('menu-item-row');
            row.innerHTML = `
                <input type="text" name="menu_link_text[${index}]" value="${text}" placeholder="Texto">
                <input type="text" name="menu_link_url[${index}]" value="${url}" placeholder="URL/Caminho">
                <label class="link-label">
                    <input type="checkbox" name="menu_link_is_cta[${index}]" value="1" ${isCta ? 'checked' : ''}>
                    Sim
                </label>
                <button type="button" class="remove-link-btn" title="Remover"><i class="fa-solid fa-trash"></i></button>
            `;
            
            // Adiciona event listener ao botão de remover
            row.querySelector('.remove-link-btn').addEventListener('click', (e) => {
                e.target.closest('.menu-item-row').remove();
            });

            container.appendChild(row);
        }

        // Adiciona nova linha ao clicar no botão
        addBtn.addEventListener('click', () => addRow());

        // Adiciona event listener aos botões de remover existentes
        document.querySelectorAll('.remove-link-btn').forEach(btn => {
            btn.addEventListener('click', (e) => e.target.closest('.menu-item-row').remove());
        });
    });
</script>
</body>
</html>
<?php
// =================================================================
// 0. CARREGAR CONFIGURAÇÕES DE IDIOMA E TEMA
// =================================================================
require_once __DIR__ . '/src/config_loader.php';

// =================================================================
// 1. CONFIGURAÇÃO SEGURA E CONEXÃO COM O BANCO DE DADOS
// =================================================================
require_once __DIR__ . '/../../config_db.php'; // Ajuste o caminho se necessário

// --- Conexões com os bancos de dados ---
$conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);
$conn_voos = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);

// Lógica para exibir mensagens de status (agora com tradução)
$status_message = '';
$status_class = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success' && isset($_GET['pilot_name'])) {
        $pilot_name = htmlspecialchars(urldecode($_GET['pilot_name']));
        // Usa sprintf para inserir o nome na string traduzida
        $status_message = sprintf(t('pilot_disabled_success'), "<strong>" . $pilot_name . "</strong>");
        $status_class = 'message-success';
    } elseif ($_GET['status'] === 'error') {
        $status_message = 'Erro: ' . htmlspecialchars($_GET['message']);
        $status_class = 'message-error';
    }
}

// =================================================================
// 2. LÓGICA PHP
// =================================================================
$show_pilot_stats = false;
$show_alert_list = false;
$alert_list_pilots = [];

if (isset($_GET['filtro']) && $_GET['filtro'] === 'ativos_em_alerta') {
    $show_alert_list = true;
    // ATUALIZAÇÃO: Adicionada a condição para filtrar por data de registro
    $alert_sql = "
        SELECT 
            p." . COL_POST_ID . " as ID, 
            CONCAT(p." . COL_FIRST_NAME . ", ' ', p." . COL_LAST_NAME . ") as display_name, 
            p." . COL_FOTO_PERFIL . ",
            p.post_date_gmt,
            MAX(v.createdAt) as last_flight_date
        FROM " . DB_PILOTOS_NAME . "." . PILOTS_TABLE . " p
        LEFT JOIN " . DB_VOOS_NAME . ".voos v ON v.userId IN (p." . COL_VATSIM_ID . ", p." . COL_IVAO_ID . ")
        WHERE 
            p." . COL_VALIDADO . " = 'true' AND
            p.post_date_gmt < DATE_SUB(NOW(), INTERVAL 29 DAY)
        GROUP BY p." . COL_POST_ID . ", display_name, p." . COL_FOTO_PERFIL . ", p.post_date_gmt
        HAVING last_flight_date < DATE_SUB(NOW(), INTERVAL 15 DAY) OR last_flight_date IS NULL
        ORDER BY last_flight_date ASC, display_name ASC;
    ";
    $alert_result = $conn_pilotos->query($alert_sql);
    if($alert_result) { while($row = $alert_result->fetch_assoc()) { $alert_list_pilots[] = $row; } }

} elseif (isset($_GET['pilot_id']) && !empty($_GET['pilot_id']) && !isset($_GET['status'])) {
    $show_pilot_stats = true;
    $selected_pilot_id = intval($_GET['pilot_id']);
    $pilot_info_sql = "SELECT *, CONCAT(" . COL_FIRST_NAME . ", ' ', " . COL_LAST_NAME . ") as display_name FROM " . PILOTS_TABLE . " WHERE " . COL_POST_ID . " = ?";
    $stmt_piloto = $conn_pilotos->prepare($pilot_info_sql);
    $stmt_piloto->bind_param("i", $selected_pilot_id);
    $stmt_piloto->execute();
    $pilot_data = $stmt_piloto->get_result()->fetch_assoc();
    $stmt_piloto->close();

    if ($pilot_data) {
        $vatsim_id = $pilot_data[COL_VATSIM_ID] ?? null;
        $ivao_id = $pilot_data[COL_IVAO_ID] ?? null;
        $stats = [];
        function format_seconds($seconds) {
            if (!$seconds || $seconds <= 0) return '00:00';
            $h = floor($seconds / 3600); $m = floor(($seconds % 3600) / 60);
            return sprintf('%02d:%02d', $h, $m);
        }
        $total_flights_sql = "SELECT COUNT(*) as total FROM voos WHERE userId IN (?, ?)";
        $stmt_voos = $conn_voos->prepare($total_flights_sql); $stmt_voos->bind_param("ss", $vatsim_id, $ivao_id); $stmt_voos->execute();
        $stats['total_flights'] = $stmt_voos->get_result()->fetch_assoc()['total'] ?? 0; $stmt_voos->close();
        $total_time_sql = "SELECT SUM(time) as total_seconds FROM voos WHERE userId IN (?, ?)";
        $stmt_voos = $conn_voos->prepare($total_time_sql); $stmt_voos->bind_param("ss", $vatsim_id, $ivao_id); $stmt_voos->execute();
        $total_seconds = $stmt_voos->get_result()->fetch_assoc()['total_seconds'] ?? 0;
        $stats['total_hours'] = format_seconds($total_seconds); $stmt_voos->close();
        $aircraft_sql = "SELECT flightPlan_aircraft_model, COUNT(*) as count FROM voos WHERE userId IN (?, ?) AND flightPlan_aircraft_model IS NOT NULL AND flightPlan_aircraft_model != '' GROUP BY flightPlan_aircraft_model ORDER BY count DESC LIMIT 1";
        $stmt_voos = $conn_voos->prepare($aircraft_sql); $stmt_voos->bind_param("ss", $vatsim_id, $ivao_id); $stmt_voos->execute();
        $stats['most_used_aircraft'] = $stmt_voos->get_result()->fetch_assoc()['flightPlan_aircraft_model'] ?? t('not_available_abbr'); $stmt_voos->close();
        $passengers_sql = "SELECT SUM(peopleOnBoard) as total FROM voos WHERE userId IN (?, ?)";
        $stmt_voos = $conn_voos->prepare($passengers_sql); $stmt_voos->bind_param("ss", $vatsim_id, $ivao_id); $stmt_voos->execute();
        $stats['total_passengers'] = $stmt_voos->get_result()->fetch_assoc()['total'] ?? 0; $stmt_voos->close();
        $week_hours_sql = "SELECT SUM(time) as week_seconds FROM voos WHERE userId IN (?, ?) AND createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt_voos = $conn_voos->prepare($week_hours_sql); $stmt_voos->bind_param("ss", $vatsim_id, $ivao_id); $stmt_voos->execute();
        $week_seconds = $stmt_voos->get_result()->fetch_assoc()['week_seconds'] ?? 0;
        $stats['week_hours'] = format_seconds($week_seconds); $stmt_voos->close();
        $last_flight_sql = "SELECT flightPlan_departureId, flightPlan_arrivalId, createdAt FROM voos WHERE userId IN (?, ?) ORDER BY createdAt DESC LIMIT 1";
        $stmt_voos = $conn_voos->prepare($last_flight_sql); $stmt_voos->bind_param("ss", $vatsim_id, $ivao_id); $stmt_voos->execute();
        $last_flight_result = $stmt_voos->get_result()->fetch_assoc(); $stmt_voos->close();

        $stats['last_flight_info'] = t('no_flight_recorded');
        $stats['is_inactive'] = false;
        $stats['days_inactive'] = 0;
        $stats['inactive_class'] = '';

        if ($last_flight_result) {
            $last_flight_date_obj = new DateTime($last_flight_result['createdAt']);
            $stats['last_flight_info'] = sprintf(t('last_flight_details'), $last_flight_result['flightPlan_departureId'], $last_flight_result['flightPlan_arrivalId'], $last_flight_date_obj->format('d/m/Y H:i'));
            $interval = (new DateTime())->diff($last_flight_date_obj);
            $stats['days_inactive'] = $interval->days;
            if ($stats['days_inactive'] > 15) { $stats['is_inactive'] = true; }
        } else {
             $stats['is_inactive'] = true;
             $stats['days_inactive'] = null;
        }
        
        if ($stats['is_inactive']) {
            $days = $stats['days_inactive'];
            if (is_null($days)) { $stats['inactive_class'] = 'status-black';
            } elseif ($days > 60) { $stats['inactive_class'] = 'status-red';
            } elseif ($days > 45) { $stats['inactive_class'] = 'status-orange';
            } else { $stats['inactive_class'] = 'status-yellow'; }
        }
    }
}

$pilotos_sql = "
    SELECT 
        " . COL_POST_ID . " as ID, 
        CONCAT(" . COL_FIRST_NAME . ", ' ', " . COL_LAST_NAME . ") as display_name 
    FROM " . PILOTS_TABLE . " 
    WHERE " . COL_FIRST_NAME . " IS NOT NULL AND " . COL_FIRST_NAME . " != '' 
    ORDER BY display_name ASC
";
$pilotos_result = $conn_pilotos->query($pilotos_sql);

$conn_pilotos->close();
$conn_voos->close();
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('pilot_statistics_title') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: var(--background-color); color: var(--text-color); margin: 0; padding: 10px; }
        .container { max-width: 1200px; margin: auto; background-color: var(--card-background-color); padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        h1 { color: var(--text-color); margin: 0; font-size: 1.8em; }
        .filter-container { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; }
        .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center; }
        .filter-form select, .filter-form button, .btn-filter-special { padding: 10px 15px; border-radius: 5px; border: 1px solid var(--border-color); font-size: 1em; text-decoration: none; display: inline-block; width: 100%; box-sizing: border-box; background-color: var(--card-background-color); color: var(--text-color);}
        .filter-form select { min-width: 280px; }
        .filter-form button { background-color: var(--primary-color); color: white; border-color: var(--primary-color); cursor: pointer; }
        .btn-filter-special { background-color: #ffc107; color: #212529; border-color: #ffc107; text-align: center; }
        .pilot-profile { display: flex; align-items: center; text-align: center; flex-direction: column; gap: 15px; margin-bottom: 30px; background-color: var(--background-color); padding: 20px; border-radius: 8px; }
        .pilot-profile img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color); background-color: #e9ecef; }
        .pilot-info { display: flex; flex-direction: column; align-items: center; }
        .pilot-meta { display: flex; align-items: center; gap: 15px; margin-top: 8px; }
        .pilot-profile h2 { margin: 0; color: var(--text-color); font-size: 1.6em; }
        .callsign { color: var(--text-color-light); font-size: 1.1em; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.9em; font-weight: 500; color: #fff; }
        .status-ativo { background-color: #28a745; }
        .status-inativo { background-color: #dc3545; }
        .alert-inactive { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: 500; border: 1px solid transparent; border-left-width: 5px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .stat-card { background-color: var(--background-color); border-left: 5px solid var(--primary-color); padding: 20px; border-radius: 8px; text-align: center; transition: all 0.3s ease; }
        .stat-card .icon { font-size: 2.2em; color: var(--primary-color); margin-bottom: 15px; opacity: 0.8; transition: color 0.3s ease; }
        .stat-card .title { font-size: 1em; color: var(--text-color-light); margin-bottom: 5px; font-weight: 500; }
        .stat-card .value { font-size: 1.8em; font-weight: 700; color: var(--text-color); transition: color 0.3s ease; }
        .alert-inactive.status-yellow { background-color: #fff3cd; border-color: #ffeeba; color: #856404; border-left-color: #ffc107; }
        .alert-inactive.status-orange { background-color: #ffe8d9; border-color: #ffc9ab; color: #721c24; border-left-color: #fd7e14; }
        .alert-inactive.status-red    { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; border-left-color: #dc3545; }
        .alert-inactive.status-black  { background-color: #d6d8d9; border-color: #c6c8ca; color: #1b1e21; border-left-color: #343a40; }
        .stat-card.status-yellow { border-left-color: #ffc107; }
        .stat-card.status-yellow .icon, .stat-card.status-yellow .value { color: #ffc107; }
        .stat-card.status-orange { border-left-color: #fd7e14; }
        .stat-card.status-orange .icon, .stat-card.status-orange .value { color: #fd7e14; }
        .stat-card.status-red { border-left-color: #dc3545; }
        .stat-card.status-red .icon, .stat-card.status-red .value { color: #dc3545; }
        .stat-card.status-black { border-left-color: #343a40; }
        .stat-card.status-black .icon, .stat-card.status-black .value { color: #343a40; }
        .alert-list-container { padding: 10px 0; }
        .alert-list-container h2 { text-align: center; color: var(--text-color-light); font-size: 1.5em; }
        .alert-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr; gap: 15px; }
        .placeholder { text-align: center; padding: 40px; color: var(--text-color-light); font-size: 1.1em; }
        .message-box { text-align: center; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 500; }
        .message-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .alert-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background-color: var(--background-color);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            border-left-width: 5px;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .alert-list li:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        .alert-list-link {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: var(--text-color);
            flex-grow: 1;
        }
        .alert-list-link img {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        .pilot-info-text {
            display: flex;
            flex-direction: column;
        }
        .pilot-name {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 2px;
        }
        .registration-date {
            font-size: 0.8em;
            color: var(--text-color-light);
            margin-bottom: 5px;
        }
        .last-flight {
            font-size: 0.9em;
            font-weight: 700;
        }

        .alert-list li.status-black { border-left-color: #343a40; }
        .alert-list li.status-red { border-left-color: #dc3545; }
        .alert-list li.status-orange { border-left-color: #fd7e14; }
        .alert-list li.status-yellow { border-left-color: #ffc107; }

        .alert-list li.status-black .last-flight { color: #343a40; }
        .alert-list li.status-red .last-flight { color: #dc3545; }
        .alert-list li.status-orange .last-flight { color: #fd7e14; }
        .alert-list li.status-yellow .last-flight { color: #ffc107; }

        .pilot-actions form { margin: 0; }
        .btn-disable {
            background-color: transparent;
            color: var(--text-color-light);
            border: 1px solid var(--border-color);
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }
        .btn-disable:hover {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        /* ESTILOS PARA O MODAL DE CONFIRMAÇÃO */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background-color: var(--card-background-color);
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: scale(0.9);
            transition: all 0.3s ease;
        }
        .modal-overlay.active .modal-box {
            transform: scale(1);
        }
        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            margin: 0 0 15px 0;
            color: var(--text-color);
        }
        .modal-text {
            font-size: 1em;
            color: var(--text-color-light);
            margin-bottom: 25px;
        }
        .modal-text strong {
            color: var(--primary-color);
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .modal-btn {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .modal-btn-confirm {
            background-color: #dc3545;
            color: white;
        }
        .modal-btn-confirm:hover {
            background-color: #a71d2a;
        }
        .modal-btn-cancel {
            background-color: var(--border-color);
            color: var(--text-color);
        }
        .modal-btn-cancel:hover {
            background-color: #6c757d;
        }

        @media (min-width: 768px) {
            body { padding: 20px; } h1 { font-size: 2.2em; } .container { padding: 35px; } .filter-container { flex-wrap: nowrap; } .filter-form { flex-wrap: nowrap; } .filter-form select, .filter-form button, .btn-filter-special { width: auto; } .pilot-profile { flex-direction: row; text-align: left; } .pilot-profile h2 { font-size: 2em; } .pilot-info { align-items: flex-start; } .stat-card { text-align: left; } .alert-list { grid-template-columns: 1fr 1fr; gap: 20px; }
        }
         @media (max-width: 900px) {
            .alert-list { grid-template-columns: 1fr; }
        }
    </style>
    <?php apply_color_theme(); ?>
</head>
<body>
    <div class="container">
        <div class="header"><h1><i class="fa-solid fa-plane-circle-check"></i> <?= t('pilot_status_header') ?></h1></div>
        
        <?php if (!empty($status_message)): ?>
            <div class="message-box <?= $status_class ?>"><?= $status_message ?></div>
        <?php endif; ?>

        <div class="filter-container">
            <form action="" method="GET" class="filter-form">
                <label for="pilot_id_select"><?= t('view_individual_stats') ?></label>
                <select name="pilot_id" id="pilot_id_select" onchange="this.form.submit()">
                    <option value=""><?= t('select_a_pilot') ?></option>
                    <?php if ($pilotos_result && $pilotos_result->num_rows > 0) {
                        $pilotos_result->data_seek(0);
                        while($row = $pilotos_result->fetch_assoc()) {
                            $selected = ($show_pilot_stats && $selected_pilot_id == $row['ID']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['ID']) . "' $selected>" . htmlspecialchars($row['display_name']) . "</option>";
                        }
                    } ?>
                </select>
                <button type="submit"><?= t('view_button') ?></button>
            </form>
            <a href="?filtro=ativos_em_alerta" class="btn-filter-special"><i class="fa-solid fa-bell"></i> <?= t('view_pilots_on_alert') ?></a>
        </div>
        
        <?php if ($show_pilot_stats && $pilot_data): ?>
            <div class="pilot-profile">
                <img src="<?= htmlspecialchars($pilot_data[COL_FOTO_PERFIL] ?? 'piloto.png') ?>" onerror="this.onerror=null; this.src='assets/images/piloto.png';" alt="Foto de Perfil">
                <div class="pilot-info">
                    <h2><?= htmlspecialchars($pilot_data['display_name']) ?></h2>
                    <div class="pilot-meta">
                        <div class="callsign"><?= htmlspecialchars($pilot_data[COL_MATRICULA] ?? t('not_available_abbr')) ?></div>
                        <?php 
                            $status_class = ($pilot_data[COL_VALIDADO] === 'true') ? 'status-ativo' : 'status-inativo'; 
                            $status_text = ($pilot_data[COL_VALIDADO] === 'true') ? t('status_active') : t('status_inactive'); 
                        ?>
                        <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                    </div>
                </div>
            </div>

            <?php if ($stats['is_inactive']): ?>
            <div class="alert-inactive <?= $stats['inactive_class'] ?>">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php if (is_null($stats['days_inactive'])): ?>
                    <strong><?= t('alert_title') ?>:</strong> <?= t('alert_never_flew') ?>
                <?php else: ?>
                    <strong><?= t('alert_title') ?>:</strong> <?= sprintf(t('alert_inactive_for_days'), $stats['days_inactive']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><div class="icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="title"><?= t('total_hours') ?></div><div class="value"><?= $stats['total_hours'] ?></div></div>
                <div class="stat-card"><div class="icon"><i class="fa-solid fa-plane-departure"></i></div><div class="title"><?= t('total_flights') ?></div><div class="value"><?= $stats['total_flights'] ?></div></div>
                <div class="stat-card"><div class="icon"><i class="fa-solid fa-jet-fighter"></i></div><div class="title"><?= t('most_used_aircraft') ?></div><div class="value"><?= htmlspecialchars($stats['most_used_aircraft']) ?></div></div>
                <div class="stat-card"><div class="icon"><i class="fa-solid fa-users"></i></div><div class="title"><?= t('passengers_transported') ?></div><div class="value"><?= number_format($stats['total_passengers'], 0, ',', '.') ?></div></div>
                <div class="stat-card"><div class="icon"><i class="fa-solid fa-calendar-week"></i></div><div class="title"><?= t('hours_in_week') ?></div><div class="value"><?= $stats['week_hours'] ?></div></div>
                
                <div class="stat-card <?= $stats['inactive_class'] ?>">
                    <div class="icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="title"><?= t('last_flight') ?></div>
                    <div class="value" style="font-size: 1.2em;"><?= htmlspecialchars($stats['last_flight_info']) ?></div>
                </div>
            </div>

        <?php elseif ($show_alert_list): ?>
            <div class="alert-list-container">
                <h2><i class="fa-solid fa-bell"></i> <?= t('active_pilots_on_alert') ?></h2>
                <p style="text-align:center; color: var(--text-color-light);"><?= t('pilots_on_alert_description') ?></p>
                <?php if (!empty($alert_list_pilots)): ?>
                    <ul class="alert-list">
                        <?php foreach ($alert_list_pilots as $pilot): ?>
                        <?php
                            $li_class = ''; $status_text = '';
                            if (is_null($pilot['last_flight_date'])) {
                                $li_class = 'status-black'; $status_text = t('status_never_flew');
                            } else {
                                $last_flight_date = new DateTime($pilot['last_flight_date']);
                                $days_inactive = (new DateTime())->diff($last_flight_date)->days;
                                if ($days_inactive > 60) { $li_class = 'status-red';
                                } elseif ($days_inactive > 45) { $li_class = 'status-orange';
                                } elseif ($days_inactive > 30) { $li_class = 'status-yellow'; }
                                $status_text = sprintf(t('status_inactive_for_days'), $days_inactive);
                            }
                        ?>
                        <li class="<?= $li_class ?>">
                            <a href="?pilot_id=<?= htmlspecialchars($pilot['ID']) ?>" class="alert-list-link">
                                <img src="<?= htmlspecialchars($pilot[COL_FOTO_PERFIL] ?? 'piloto.png') ?>" onerror="this.onerror=null; this.src='assets/images/piloto.png';" alt="Foto">
                                <div class="pilot-info-text">
                                    <div class="pilot-name"><?= htmlspecialchars($pilot['display_name']) ?></div>
                                    <?php if (!empty($pilot['post_date_gmt'])): ?>
                                        <div class="registration-date">
                                            <i class="fa-solid fa-calendar-alt"></i> 
                                            <?php
                                                $registration_date = new DateTime($pilot['post_date_gmt']);
                                                $days_since_registration = (new DateTime())->diff($registration_date)->days;
                                                echo sprintf(
                                                    t('registered_on_days'),
                                                    $registration_date->format('d/m/Y'),
                                                    $days_since_registration
                                                );
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="last-flight"><i class="fa-solid fa-triangle-exclamation"></i> <?= $status_text ?></div>
                                </div>
                            </a>
                            <div class="pilot-actions">
                                <form action="src/disable_pilot.php" method="POST" class="disable-form">
                                    <input type="hidden" name="pilot_id" value="<?= htmlspecialchars($pilot['ID']) ?>">
                                    <input type="hidden" name="pilot_name" value="<?= htmlspecialchars($pilot['display_name']) ?>">
                                    <button type="submit" class="btn-disable" title="Desabilitar Piloto">
                                        <i class="fa-solid fa-user-slash"></i> Desabilitar
                                    </button>
                                </form>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="placeholder" style="background-color: #e9f7ef; color: #155724; padding: 20px; border-radius: 8px;">
                        <i class="fa-solid fa-check-circle"></i>
                        <p><?= t('no_pilots_on_alert') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="placeholder">
                <i class="fa-solid fa-arrow-up"></i>
                <p><?= t('select_pilot_placeholder') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="confirmation-modal">
        <div class="modal-box">
            <h2 class="modal-title" id="modal-title"><?= t('disable_confirmation_title') ?></h2>
            <p class="modal-text" id="modal-text"></p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" id="modal-cancel-btn"><?= t('cancel_button') ?></button>
                <button class="modal-btn modal-btn-confirm" id="modal-confirm-btn"><?= t('confirm_button') ?></button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalOverlay = document.getElementById('confirmation-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalText = document.getElementById('modal-text');
        const confirmBtn = document.getElementById('modal-confirm-btn');
        const cancelBtn = document.getElementById('modal-cancel-btn');
        let formToSubmit = null;

        document.querySelectorAll('.disable-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                formToSubmit = this;

                const pilotName = this.querySelector('input[name="pilot_name"]').value;
                const confirmationText = '<?= t('disable_confirmation_text') ?>';
                
                modalText.innerHTML = confirmationText.replace('%s', `<strong>${pilotName}</strong>`);
                modalOverlay.classList.add('active');
            });
        });

        confirmBtn.addEventListener('click', function() {
            if (formToSubmit) {
                formToSubmit.submit();
            }
        });

        cancelBtn.addEventListener('click', function() {
            modalOverlay.classList.remove('active');
            formToSubmit = null;
        });

        modalOverlay.addEventListener('click', function(e) {
            if (e.target === this) {
                modalOverlay.classList.remove('active');
                formToSubmit = null;
            }
        });
    });
    </script>
</body>
</html>
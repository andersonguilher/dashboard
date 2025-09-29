<?php
// =================================================================
// 1. CONFIGURAÇÃO E CONEXÃO
// =================================================================
require_once __DIR__ . '/../../../config_db.php';
$conn = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);

// Pega o modelo da aeronave da URL e valida
$selected_model = $_GET['model'] ?? null;
if (!$selected_model) {
    die("Modelo de aeronave não fornecido.");
}

// =================================================================
// 2. PARÂMETROS E QUERIES FINANCEIRAS PARA O MODELO
// =================================================================
define('AIRPORT_TAX_PER_PAX', 45.20);
define('HANDLING_FEE_PER_FLIGHT', 850.00);
define('FUEL_PRICE_PER_LITER', 5.85);
// Fator de conversão US Gallon para Litros
define('GALLONS_TO_LITERS', 3.78541); 

// Define o filtro para voos com fuel_used > 0, lendo da URL
$filter_real_fuel = isset($_GET['real_fuel']) && $_GET['real_fuel'] == '1';

// REMOÇÃO DA LÓGICA DE FILTRO DE MÊS AUTOMÁTICO: 
// Apenas inicializamos a variável. O filtro de ano já é aplicado abaixo.
$filter_month = ""; 


// Query base com todos os cálculos, FILTRADA para o modelo selecionado
$model_financial_query = "
    SELECT
        v.time, v.peopleOnBoard, v.flightPlan_aircraft_model AS aircraft_model, v.createdAt, v.flightPlan_departureId as orig, v.flightPlan_arrivalId as dest, v.fuel_used,
        -- CÁLCULOS AGORA UTILIZAM OS VALORES DA MATRÍCULA ESPECÍFICA (f)
        (v.peopleOnBoard * (v.time / 3600) * COALESCE(f.revenue_per_pax_per_hour, 120)) AS revenue,
        (v.time / 3600) * COALESCE(f.operational_cost_per_hour, 2000) as cost_ops,
        (v.time / 3600) * COALESCE(f.maintenance_per_hour, 500) as cost_maint,
        (
            -- CORREÇÃO: Converte v.fuel_used (galões) para litros antes de multiplicar.
            COALESCE(v.fuel_used * " . GALLONS_TO_LITERS . ", (v.time / 3600) * COALESCE(f.fuel_consumption_per_hour, 3000)) * " . FUEL_PRICE_PER_LITER . "
        ) as cost_fuel,
        (v.peopleOnBoard * " . AIRPORT_TAX_PER_PAX . " + " . HANDLING_FEE_PER_FLIGHT . ") as cost_fees,
        -- O cálculo de PROOFIT também foi atualizado para usar os valores da matrícula e o fator de conversão
        ((v.peopleOnBoard * (v.time / 3600) * COALESCE(f.revenue_per_pax_per_hour, 120)) - ((v.time / 3600) * COALESCE(f.operational_cost_per_hour, 2000) + (v.time / 3600) * COALESCE(f.maintenance_per_hour, 500) + (COALESCE(v.fuel_used * " . GALLONS_TO_LITERS . ", (v.time / 3600) * COALESCE(f.fuel_consumption_per_hour, 3000))) * " . FUEL_PRICE_PER_LITER . " + (v.peopleOnBoard * " . AIRPORT_TAX_PER_PAX . " + " . HANDLING_FEE_PER_FLIGHT . "))) as profit
    FROM voos v
    -- NOVO JOIN: Agora junta voos com a frota pela matrícula, não pelo modelo
    LEFT JOIN frota f ON v.registration = f.registration
    WHERE v.flightPlan_aircraft_model = ? AND v.time > 0 AND v.peopleOnBoard > 0
    AND YEAR(v.createdAt) = YEAR(NOW()) -- FILTRO PERMANENTE DO ANO ATUAL
    " . ($filter_real_fuel ? " AND v.fuel_used > 0 " : "") . " -- Filtro Combustível Real
    {$filter_month} -- Variável $filter_month agora é sempre vazia aqui
";

// --- KPIs Agregados ---
// A query usa o $model_financial_query, então a filtragem do mês é automática
$stmt_kpi = $conn->prepare("
    SELECT
        COUNT(*) as total_flights, SUM(time) as total_seconds, SUM(peopleOnBoard) as total_pax,
        SUM(revenue) as total_revenue, SUM(profit) as total_profit, SUM(cost_fuel) as total_cost_fuel,
        SUM(cost_maint) as total_cost_maint, SUM(cost_ops) as total_cost_ops, SUM(cost_fees) as total_cost_fees
    FROM ({$model_financial_query}) as model_data
");
$stmt_kpi->bind_param("s", $selected_model);
$stmt_kpi->execute();
$kpi_data = $stmt_kpi->get_result()->fetch_assoc();
$stmt_kpi->close();

// --- Dados para o gráfico de lucro mensal ---
// Agora mostra o histórico do ano atual (JAN até MÊS ATUAL) se o filtro de combustível real estiver ativo.
$stmt_chart = $conn->prepare("
    SELECT MONTH(createdAt) as month_num, SUM(profit) as monthly_profit
    FROM ({$model_financial_query}) as model_data
    WHERE YEAR(createdAt) = YEAR(NOW())
    GROUP BY month_num ORDER BY month_num ASC
");
$stmt_chart->bind_param("s", $selected_model);
$stmt_chart->execute();
$chart_result = $stmt_chart->get_result();
$stmt_chart->close();

$chart_yearly_labels_template = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$chart_profit_data = array_fill(0, 12, 0);
while ($row = $chart_result->fetch_assoc()) {
    $month_index = $row['month_num'] - 1;
    $chart_profit_data[$month_index] = round($row['monthly_profit'] ?? 0);
}
// Mantém a visualização anual completa até o mês atual
$current_month_number = date('n');
$chart_labels = array_slice($chart_yearly_labels_template, 0, $current_month_number);
$chart_profit_data = array_slice($chart_profit_data, 0, $current_month_number);


// --- Voos recentes com Lucro/Prejuízo ---
// A query usa o $model_financial_query, então a filtragem do ano e combustível é aplicada
$stmt_flights = $conn->prepare("SELECT createdAt, orig, dest, time, peopleOnBoard, profit FROM ({$model_financial_query}) as q ORDER BY createdAt DESC LIMIT 15");
$stmt_flights->bind_param("s", $selected_model);
$stmt_flights->execute();
$recent_flights = $stmt_flights->get_result();
$stmt_flights->close();

$conn->close();

function format_seconds_to_hm($seconds) {
    if (!$seconds || $seconds <= 0) return '00:00';
    $h = floor($seconds / 3600); $m = floor(($seconds % 3600) / 60);
    return sprintf('%02d:%02d', $h, $m);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório: <?= htmlspecialchars($selected_model) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.js"></script>
    <style>
        :root { --bg: #1a1c2c; --card-bg: #242744; --border: #3a3f70; --text-primary: #ffffff; --text-secondary: #a0a0c0; --accent: #4a72ff; --success: #48c774; --danger: #f14668; }
        * { box-sizing: border-box; }
        html, body { height: auto; margin: 0; padding: 0; }
        body { font-family: 'Roboto', sans-serif; background-color: var(--bg); color: var(--text-primary); }
        .main-container { width: 100%; max-width: 1400px; margin: 0 auto; padding: 20px; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 25px; margin-bottom: 20px; }
        .report-header { text-align: center; }
        .report-header h1 { margin: 0; font-size: 2em; }
        .report-header p { color: var(--text-secondary); margin-top: 5px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .kpi-item .label { font-size: 0.9em; color: var(--text-secondary); margin-bottom: 10px; text-transform: uppercase; }
        .kpi-item .value { font-size: 1.8em; font-weight: 700; }
        .kpi-item .value.positive { color: var(--success); } .kpi-item .value.negative { color: var(--danger); }
        .chart-container { position: relative; height: 300px; }
        .flights-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .flights-table th, .flights-table td { text-align: left; padding: 12px 8px; border-bottom: 1px solid var(--border); }
        .flights-table th { color: var(--text-secondary); }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--accent); text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="main-container">
        <a href="index.php" class="back-link">&larr; Voltar ao Dashboard Principal</a>

        <div class="card report-header">
            <h1><?= htmlspecialchars($selected_model) ?></h1>
            <p>Relatório Detalhado de Performance Financeira e Operacional</p>
            <?php if ($filter_real_fuel): ?>
                <p style="color: var(--success); font-weight: 500;">
                    (Filtro Ativo: Apenas Voos com Combustível Real do Ano Atual)
                </p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Resumo Geral do Modelo</h2>
            <div class="kpi-grid">
                <div class="kpi-item"><div class="label">Receita Total</div><div class="value" style="color:var(--accent)">R$ <?= number_format($kpi_data['total_revenue'] ?? 0, 2, ',', '.') ?></div></div>
                <div class="kpi-item"><div class="label">Lucro/Prejuízo Total</div><div class="value <?= ($kpi_data['total_profit'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($kpi_data['total_profit'] ?? 0, 2, ',', '.') ?></div></div>
                <div class="kpi-item"><div class="label">Total de Voos</div><div class="value"><?= number_format($kpi_data['total_flights'] ?? 0) ?></div></div>
                <div class="kpi-item"><div class="label">Total de Passageiros</div><div class="value"><?= number_format($kpi_data['total_pax'] ?? 0) ?></div></div>
            </div>
        </div>

        <div class="card">
            <h2>Evolução do Lucro Mensal (Ano Atual)</h2>
            <div class="chart-container"><canvas id="graficoLucroMensal"></canvas></div>
        </div>

        <div class="card">
            <h2>Voos Recentes</h2>
            <div style="overflow-x: auto;">
                <table class="flights-table">
                    <thead><tr><th>Data</th><th>Origem</th><th>Destino</th><th>Duração</th><th>Passageiros</th><th>Lucro/Prejuízo</th></tr></thead>
                    <tbody>
                        <?php if ($recent_flights->num_rows > 0): while($flight = $recent_flights->fetch_assoc()): ?>
                            <tr>
                                <td><?= (new DateTime($flight['createdAt']))->format('d/m/Y') ?></td>
                                <td><?= htmlspecialchars($flight['orig']) ?></td>
                                <td><?= htmlspecialchars($flight['dest']) ?></td>
                                <td><?= format_seconds_to_hm($flight['time']) ?></td>
                                <td><?= htmlspecialchars($flight['peopleOnBoard']) ?></td>
                                <td class="<?= ($flight['profit'] ?? 0) >= 0 ? 'positive' : 'negative' ?>" style="font-weight:bold;">R$ <?= number_format($flight['profit'] ?? 0, 2, ',', '.') ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center;">Nenhum voo registrado para este modelo com os filtros atuais.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartGridColor = 'rgba(255, 255, 255, 0.1)';
    const chartTextColor = '#a0a0c0';
    const successColor = getComputedStyle(document.documentElement).getPropertyValue('--success').trim();
    const dangerColor = getComputedStyle(document.documentElement).getPropertyValue('--danger').trim();
    
    const profitData = <?= json_encode($chart_profit_data) ?>;
    
    new Chart(document.getElementById('graficoLucroMensal'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Lucro Mensal',
                data: profitData,
                backgroundColor: profitData.map(val => val < 0 ? dangerColor : successColor),
                borderColor: profitData.map(val => val < 0 ? dangerColor : successColor),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { ticks: { color: chartTextColor }, grid: { color: chartGridColor } },
                x: { ticks: { color: chartTextColor }, grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
</body>
</html>
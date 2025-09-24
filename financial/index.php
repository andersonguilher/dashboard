<?php
// =================================================================
// 1. CONFIGURAÇÃO E CONEXÃO
// =================================================================
require_once __DIR__ . '/../../../config_db.php';
$conn = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);

// =================================================================
// 2. PARÂMETROS E QUERIES
// =================================================================
define('AIRPORT_TAX_PER_PAX', 45.20);
define('HANDLING_FEE_PER_FLIGHT', 850.00);
define('FUEL_PRICE_PER_LITER', 5.85);

$financial_query_base = "
    SELECT
        v.time, v.peopleOnBoard, v.flightPlan_aircraft_model AS aircraft_model, v.createdAt, v.flightPlan_departureId as orig, v.flightPlan_arrivalId as dest,
        (v.peopleOnBoard * (v.time / 3600) * COALESCE(f_avg.avg_rev_pax_hr, 120)) AS revenue,
        (v.time / 3600) * COALESCE(f_avg.avg_op_cost, 2000) as cost_ops,
        (v.time / 3600) * COALESCE(f_avg.avg_maint, 500) as cost_maint,
        (v.time / 3600) * COALESCE(f_avg.avg_fuel, 3000) * " . FUEL_PRICE_PER_LITER . " as cost_fuel,
        (v.peopleOnBoard * " . AIRPORT_TAX_PER_PAX . " + " . HANDLING_FEE_PER_FLIGHT . ") as cost_fees
    FROM voos v
    LEFT JOIN (
        SELECT model, 
               AVG(operational_cost_per_hour) AS avg_op_cost, 
               AVG(maintenance_per_hour) AS avg_maint,
               AVG(fuel_consumption_per_hour) AS avg_fuel,
               AVG(revenue_per_pax_per_hour) AS avg_rev_pax_hr
        FROM frota GROUP BY model
    ) AS f_avg ON v.flightPlan_aircraft_model = f_avg.model
    WHERE v.time > 0 AND v.peopleOnBoard > 0
";
$financial_query_base_with_profit = "
    SELECT q.*, 
           (q.cost_ops + q.cost_maint + q.cost_fuel + q.cost_fees) as total_cost,
           (q.revenue - (q.cost_ops + q.cost_maint + q.cost_fuel + q.cost_fees)) as profit 
    FROM ({$financial_query_base}) as q
";

$total_kpi_sql = "SELECT COALESCE(SUM(revenue), 0) as total_revenue, COALESCE(SUM(profit), 0) as total_profit, COALESCE(SUM(peopleOnBoard), 0) as total_pax FROM ({$financial_query_base_with_profit}) as financial_data";
$total_kpi_result = $conn->query($total_kpi_sql)->fetch_assoc();
$monthly_kpi_sql = "SELECT COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(profit), 0) as profit, COALESCE(SUM(peopleOnBoard), 0) as pax FROM ({$financial_query_base_with_profit}) as financial_data WHERE MONTH(createdAt) = MONTH(NOW()) AND YEAR(createdAt) = YEAR(NOW())";
$monthly_kpi = $conn->query($monthly_kpi_sql)->fetch_assoc();
$last_month_kpi_sql = "SELECT COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(profit), 0) as profit, COALESCE(SUM(peopleOnBoard), 0) as pax FROM ({$financial_query_base_with_profit}) as financial_data WHERE MONTH(createdAt) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(createdAt) = YEAR(NOW() - INTERVAL 1 MONTH)";
$last_month_kpi = $conn->query($last_month_kpi_sql)->fetch_assoc();
$profit_by_acft_sql = "SELECT aircraft_model, SUM(profit) as total_profit FROM ({$financial_query_base_with_profit}) as financial_data GROUP BY aircraft_model ORDER BY total_profit DESC LIMIT 5";
$profit_by_acft_result = $conn->query($profit_by_acft_sql);
$chart_yearly_sql = "
    SELECT 
        MONTH(createdAt) as month_num, 
        SUM(revenue) as revenue, SUM(profit) as profit,
        SUM(total_cost) as cost_total, SUM(cost_fuel) as cost_fuel,
        SUM(cost_maint) as cost_maint, SUM(cost_ops) as cost_ops, SUM(cost_fees) as cost_fees
    FROM ({$financial_query_base_with_profit}) as financial_data 
    WHERE YEAR(createdAt) = YEAR(NOW()) GROUP BY month_num ORDER BY month_num ASC
";
$yearly_trend_result = $conn->query($chart_yearly_sql);
$chart_yearly_labels_template = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$chart_data = [
    'revenue' => array_fill(0, 12, 0), 'profit' => array_fill(0, 12, 0),
    'cost_total' => array_fill(0, 12, 0), 'cost_fuel' => array_fill(0, 12, 0),
    'cost_maint' => array_fill(0, 12, 0), 'cost_ops' => array_fill(0, 12, 0),
    'cost_fees' => array_fill(0, 12, 0)
];
if ($yearly_trend_result) {
    while ($row = $yearly_trend_result->fetch_assoc()) { 
        $month_index = $row['month_num'] - 1;
        foreach ($chart_data as $key => &$value) {
            $value[$month_index] = round($row[$key] ?? 0);
        }
    }
}
$current_month_number = date('n');
$chart_yearly_labels = array_slice($chart_yearly_labels_template, 0, $current_month_number);
foreach ($chart_data as $key => &$value) {
    $value = array_slice($value, 0, $current_month_number);
}
$recent_flights_log_sql = "SELECT orig, dest, profit, createdAt FROM ({$financial_query_base_with_profit}) as q ORDER BY createdAt DESC LIMIT 5";
$recent_flights_log = $conn->query($recent_flights_log_sql);
$fleet_list_result = $conn->query("SELECT registration, model, category, operational_cost_per_hour, fuel_consumption_per_hour FROM frota WHERE model IN (SELECT DISTINCT flightPlan_aircraft_model FROM voos WHERE YEAR(createdAt) = YEAR(NOW())) ORDER BY model, registration");
$conn->close();
function calculate_percentage_change($current, $previous) {
    if ($previous == 0) return ['value' => 0, 'class' => 'neutral', 'icon' => '&#8212;'];
    $change = (($current - $previous) / abs($previous)) * 100;
    $class = $change >= 0 ? 'positive' : 'negative';
    $icon = $change >= 0 ? '&#9650;' : '&#9660;';
    return ['value' => round($change), 'class' => $class, 'icon' => $icon];
}
$revenue_change = calculate_percentage_change($monthly_kpi['revenue'], $last_month_kpi['revenue']);
$profit_change = calculate_percentage_change($monthly_kpi['profit'], $last_month_kpi['profit']);
$pax_change = calculate_percentage_change($monthly_kpi['pax'], $last_month_kpi['pax']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle Financeiro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.js"></script>
    <style>
        :root { --bg: #1a1c2c; --card-bg: #242744; --border: #3a3f70; --text-primary: #ffffff; --text-secondary: #a0a0c0; --accent: #4a72ff; --success: #48c774; --danger: #f14668; }
        * { box-sizing: border-box; }
        html, body { height: auto; margin: 0; padding: 0; }
        body { font-family: 'Roboto', sans-serif; background-color: var(--bg); color: var(--text-primary); }
        .main-container { width: 100%; max-width: 1800px; margin: 0 auto; padding: 20px; }
        .dashboard-container { display: grid; gap: 20px; grid-template-columns: repeat(4, 1fr); grid-template-rows: auto; grid-template-areas: "header header header header" "kpi1 kpi2 kpi3 kpi4" "mainchart mainchart mainchart side" "footer footer footer footer"; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 25px; display: flex; flex-direction: column; }
        .header { grid-area: header; flex-direction: row; align-items: center; justify-content: space-between; }
        .kpi-card { grid-area: kpi; }
        .main-chart { grid-area: mainchart; }
        .side-cards { grid-area: side; display: flex; flex-direction: column; gap: 20px; }
        .footer-card { grid-area: footer; }
        #kpi-revenue { grid-area: kpi1; } #kpi-profit { grid-area: kpi2; } #kpi-pax { grid-area: kpi3; } #kpi-profit-pax { grid-area: kpi4; }
        h1 { font-size: clamp(1.2rem, 2vw, 1.8rem); margin: 0; font-weight: 500; }
        .header p { font-size: clamp(0.7rem, 1vw, 0.9rem); margin: 0; color: var(--text-secondary); }
        .kpi-card .label { font-size: 0.9em; margin-bottom: 15px; }
        .kpi-card .value { font-size: 2.2em; margin-bottom: 15px; }
        .kpi-card .trend { font-size: 0.9em; }
        .card-title { font-size: 1.2em; margin: 0 0 20px 0; }
        .list-item, .fleet-table { font-size: 0.9em; }
        .kpi-card .label { display: flex; align-items: center; color: var(--text-secondary); text-transform: uppercase; font-weight: 400; }
        .kpi-card .label i { font-size: 1.2em; margin-right: 10px; width: 20px; text-align: center; }
        .kpi-card .value { font-weight: 700; }
        .kpi-card .trend { display: flex; align-items: center; }
        .kpi-card .trend.positive { color: var(--success); } .kpi-card .trend.negative { color: var(--danger); }
        .kpi-card .trend span { font-weight: 700; margin-right: 5px; }
        .card-title { font-weight: 500; }
        .chart-container { position: relative; height: 350px; }
        .list, .table-container { overflow-y: auto; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .list-item:last-child { border-bottom: none; }
        .list-item .item-label { font-weight: 500; }
        .list-item .item-value.positive { color: var(--success); font-weight: 700; }
        .list-item .item-value.negative { color: var(--danger); font-weight: 700; }
        .table-container { max-height: 400px; }
        .fleet-table { width: 100%; border-collapse: collapse; }
        .fleet-table th, .fleet-table td { text-align: left; padding: 12px 8px; border-bottom: 1px solid var(--border); }
        .fleet-table th { color: var(--text-secondary); }
        .fleet-table a { color: var(--accent); text-decoration: none; font-weight: 500; }
        .chart-controls { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .control-btn { background-color: transparent; border: 1px solid var(--border); color: var(--text-secondary); padding: 8px 16px; border-radius: 20px; cursor: pointer; transition: all 0.2s ease; font-weight: 500; }
        .control-btn:hover { background-color: var(--border); color: var(--text-primary); }
        .control-btn.active { background-color: var(--accent); color: var(--text-primary); border-color: var(--accent); }
        
        /* CSS para o cabeçalho fixo e o campo de pesquisa */
        .fixed-header {
            height: 400px; /* Ajuste a altura conforme necessário */
            overflow-y: auto;
            position: relative;
        }

        .fixed-header thead th {
            position: sticky;
            top: 0;
            background-color: var(--card-bg); /* Garante que o fundo do cabeçalho seja o mesmo do card */
            z-index: 10;
            border-bottom: 2px solid var(--accent); /* Opcional, para destacar a linha */
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--bg);
            color: var(--text-primary);
            font-size: 1em;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-input::placeholder {
            color: var(--text-secondary);
        }

        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(74, 114, 255, 0.2);
        }
        
        @media (max-width: 1200px) { .dashboard-container { grid-template-columns: repeat(2, 1fr); grid-template-areas: "header header" "kpi1 kpi2" "kpi3 kpi4" "mainchart mainchart" "side side" "footer footer"; } .side-cards { flex-direction: row; } .side-cards .card { flex: 1; } }
        @media (max-width: 768px) { .main-container { padding: 10px; } .dashboard-container { grid-template-columns: 1fr; grid-template-areas: "header" "kpi1" "kpi2" "kpi3" "kpi4" "mainchart" "side" "footer"; } .side-cards { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="dashboard-container">
             <header class="card header"><div><h1>Painel de Controle Financeiro</h1><p>Análise de performance da companhia</p></div><p>Atualizado em: <?= date('d/m/Y H:i') ?></p></header>
            <div class="card kpi-card" id="kpi-revenue"><div class="label"><i class="fa-solid fa-dollar-sign"></i>Receita (Mês)</div><div class="value">R$ <?= number_format($monthly_kpi['revenue'] ?? 0, 2, ',', '.') ?></div><div class="trend <?= $revenue_change['class'] ?>"><span><?= $revenue_change['icon'] ?> <?= $revenue_change['value'] ?>%</span> vs. Mês Anterior</div></div>
            <div class="card kpi-card" id="kpi-profit"><div class="label"><i class="fa-solid fa-arrow-trend-up"></i>Lucro (Mês)</div><div class="value">R$ <?= number_format($monthly_kpi['profit'] ?? 0, 2, ',', '.') ?></div><div class="trend <?= $profit_change['class'] ?>"><span><?= $profit_change['icon'] ?> <?= $profit_change['value'] ?>%</span> vs. Mês Anterior</div></div>
            <div class="card kpi-card" id="kpi-pax"><div class="label"><i class="fa-solid fa-users"></i>Passageiros (Mês)</div><div class="value"><?= number_format($monthly_kpi['pax'] ?? 0) ?></div><div class="trend <?= $pax_change['class'] ?>"><span><?= $pax_change['icon'] ?> <?= $pax_change['value'] ?>%</span> vs. Mês Anterior</div></div>
            <div class="card kpi-card" id="kpi-profit-pax"><div class="label"><i class="fa-solid fa-chart-pie"></i>Lucro Total / PAX</div><?php $profit_per_pax = ($total_kpi_result['total_pax'] > 0) ? $total_kpi_result['total_profit'] / $total_kpi_result['total_pax'] : 0; ?><div class="value">R$ <?= number_format($profit_per_pax, 2, ',', '.') ?></div><div class="trend" style="color: var(--text-secondary);">Média geral da operação</div></div>

            <main class="card main-chart">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div class="card-title">Evolução Financeira (Ano Atual)</div>
                    <div class="chart-controls" id="chart-cost-toggle"><button class="control-btn active" data-cost="cost_total" data-label="Custo Total" data-color="#f14668">Custo Total</button><button class="control-btn" data-cost="cost_fuel" data-label="Custo de Combustível" data-color="#ff9f40">Combustível</button><button class="control-btn" data-cost="cost_maint" data-label="Custo de Manutenção" data-color="#ffcd56">Manutenção</button><button class="control-btn" data-cost="cost_ops" data-label="Custo Operacional" data-color="#4bc0c0">Operações</button><button class="control-btn" data-cost="cost_fees" data-label="Taxas e Encargos" data-color="#9966ff">Taxas</button></div>
                </div>
                <div class="chart-container"><canvas id="graficoEvolucaoAnual"></canvas></div>
            </main>

            <aside class="side-cards">
                <div class="card"><div class="card-title">Top 5 Aeronaves (Lucro)</div><div class="list">
                    <?php if($profit_by_acft_result) { while($row = $profit_by_acft_result->fetch_assoc()): ?>
                    <div class="list-item"><span class="item-label"><?= htmlspecialchars($row['aircraft_model']) ?></span><span class="item-value <?= $row['total_profit'] >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($row['total_profit'], 0, ',', '.') ?></span></div>
                    <?php endwhile; } ?>
                </div></div>
                <div class="card"><div class="card-title">Log de Voos Recentes</div><div class="list">
                    <?php if($recent_flights_log) { while($row = $recent_flights_log->fetch_assoc()): ?>
                    <div class="list-item"><span class="item-label"><?= htmlspecialchars($row['orig']) ?> &rarr; <?= htmlspecialchars($row['dest']) ?></span><span class="item-value <?= $row['profit'] >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($row['profit'], 0, ',', '.') ?></span></div>
                    <?php endwhile; } ?>
                </div></div>
            </aside>

            <footer class="card footer-card">
                <div class="card-title">Frota Operacional</div>
                <div style="margin-bottom: 15px;">
                    <input type="text" id="modelSearch" placeholder="Pesquisar por modelo..." class="search-input">
                </div>
                <div class="table-container fixed-header">
                    <table class="fleet-table">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Modelo</th>
                                <th>Categoria</th>
                                <th>Custo Oper. / Hora</th>
                                <th>Consumo / Hora (L)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($fleet_list_result) { while($row = $fleet_list_result->fetch_assoc()): ?>
                            <tr class="fleet-row" data-model="<?= htmlspecialchars($row['model']) ?>">
                                <td><a href="relatorio_aeronave.php?model=<?= urlencode($row['model']) ?>"><?= htmlspecialchars($row['registration']) ?></a></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td>R$ <?= number_format($row['operational_cost_per_hour'], 0, ',', '.') ?></td>
                                <td><?= number_format($row['fuel_consumption_per_hour'], 0, ',', '.') ?> L</td>
                            </tr>
                            <?php endwhile; } ?>
                        </tbody>
                    </table>
                </div>
            </footer>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const costData = {
            cost_total: <?= json_encode($chart_data['cost_total']) ?>, cost_fuel: <?= json_encode($chart_data['cost_fuel']) ?>,
            cost_maint: <?= json_encode($chart_data['cost_maint']) ?>, cost_ops: <?= json_encode($chart_data['cost_ops']) ?>,
            cost_fees:  <?= json_encode($chart_data['cost_fees']) ?>
        };
        const chartGridColor = 'rgba(255, 255, 255, 0.1)'; const chartTextColor = '#a0a0c0';
        const successColor = getComputedStyle(document.documentElement).getPropertyValue('--success').trim();
        const dangerColor = getComputedStyle(document.documentElement).getPropertyValue('--danger').trim();
        const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
        const profitData = <?= json_encode($chart_data['profit']) ?>; const revenueData = <?= json_encode($chart_data['revenue']) ?>;

        const chartCtx = document.getElementById('graficoEvolucaoAnual').getContext('2d');
        const annualChart = new Chart(chartCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_yearly_labels) ?>,
                datasets: [
                    { label: 'Receita', data: revenueData, borderColor: accentColor, fill: false, tension: 0.4 },
                    { 
                      label: 'Lucro', data: profitData, 
                      fill: true, tension: 0.4,
                      // --- LÓGICA CORRIGIDA E MAIS ROBUSTA ---
                      // Colore o segmento (trecho entre 2 pontos) da linha
                      segment: {
                          borderColor: ctx => ctx.p0.raw < 0 || ctx.p1.raw < 0 ? dangerColor : successColor,
                          backgroundColor: ctx => ctx.p0.raw < 0 || ctx.p1.raw < 0 ? 'rgba(241, 70, 104, 0.1)' : 'rgba(72, 199, 116, 0.1)',
                      }
                    },
                    { label: 'Custo Total', data: costData.cost_total, borderColor: dangerColor, fill: false, tension: 0.4, borderDash: [5, 5] }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false, ticks: { color: chartTextColor }, grid: { color: chartGridColor, borderColor: chartGridColor } }, x: { ticks: { color: chartTextColor }, grid: { display: false } } }, plugins: { legend: { position: 'top', align: 'end', labels: { color: chartTextColor, boxWidth: 12, padding: 20 } } } }
        });
        const controlButtons = document.querySelectorAll('.control-btn');
        controlButtons.forEach(button => {
            button.addEventListener('click', () => {
                controlButtons.forEach(btn => btn.classList.remove('active')); button.classList.add('active');
                const costType = button.dataset.cost; const newLabel = button.dataset.label; const newColor = button.dataset.color;
                annualChart.data.datasets[2].label = newLabel;
                annualChart.data.datasets[2].data = costData[costType];
                annualChart.data.datasets[2].borderColor = newColor;
                annualChart.update();
            });
        });

        // Lógica para o filtro de pesquisa da frota
        const searchInput = document.getElementById('modelSearch');
        const fleetRows = document.querySelectorAll('.fleet-row');

        searchInput.addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();

            fleetRows.forEach(row => {
                const model = row.dataset.model.toLowerCase();
                if (model.includes(searchTerm)) {
                    row.style.display = ''; // Mostra a linha
                } else {
                    row.style.display = 'none'; // Esconde a linha
                }
            });
        });
    });
    </script>
</body>
</html>
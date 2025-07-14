<?php
class IFM_Stock_Central {

    public static function render_stock_central() {
        // Filter-Status holen
        $status_filter = $_GET['stock_status'] ?? 'all';

        // Daten vorbereiten für Diagramme
        $main_products = [
            'VITARING' => [],
            'VITARING PRO' => [],
            'VITARING Travel Case' => [],
            'VITARING Wireless Charger' => [],
        ];
        $products = wc_get_products(['status'=>'publish','limit'=>-1]);
        foreach ($products as $product) {
            $name = $product->get_name();
            if (stripos($name, 'PRO') !== false) $main_products['VITARING PRO'][] = $product;
            elseif (stripos($name, 'Travel') !== false) $main_products['VITARING Travel Case'][] = $product;
            elseif (stripos($name, 'Wireless') !== false) $main_products['VITARING Wireless Charger'][] = $product;
            else $main_products['VITARING'][] = $product;
        }

        // Für Pie-Chart: Status zählen
        $status_count = ['green'=>0, 'orange'=>0, 'red'=>0];
        // Für Bar-Chart: Hauptgruppen-Bestände
        $group_stock = [
            'VITARING' => 0,
            'VITARING PRO' => 0,
            'VITARING Travel Case' => 0,
            'VITARING Wireless Charger' => 0
        ];

        foreach ($main_products as $group => $prods) {
            foreach ($prods as $product) {
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $var_id) {
                        $var = wc_get_product($var_id);
                        $stock = $var->get_stock_quantity();
                        $group_stock[$group] += (int)$stock;
                        $ampel = self::ampel_status($stock);
                        if (isset($status_count[$ampel['filter']])) $status_count[$ampel['filter']]++;
                    }
                } else {
                    $stock = $product->get_stock_quantity();
                    $group_stock[$group] += (int)$stock;
                    $ampel = self::ampel_status($stock);
                    if (isset($status_count[$ampel['filter']])) $status_count[$ampel['filter']]++;
                }
            }
        }

        // HTML-Ausgabe beginnt
        ?>
        <div class="wrap">
            <h1> Stock Central</h1>
            <form method="get" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="ifm-stock-central">
                <label for="stock_status"><strong>Nur anzeigen:</strong></label>
                <select name="stock_status" id="stock_status" onchange="this.form.submit()">
                    <option value="all"   <?= $status_filter=='all'   ? 'selected' : '' ?>>Alle</option>
                    <option value="green" <?= $status_filter=='green' ? 'selected' : '' ?>>Verfügbar </option>
                    <option value="orange"<?= $status_filter=='orange'? 'selected' : '' ?>>Niedrig </option>
                    <option value="red"   <?= $status_filter=='red'   ? 'selected' : '' ?>>Ausverkauft </option>
                </select>
            </form>

            <!-- Diagramm 1: Pie Chart Status -->
            <div style="display:flex;flex-wrap:wrap;gap:32px;align-items:center;margin-bottom:24px;">
                <div style="min-width:260px;">
                    <h3 style="margin-top:0;">Lagerstatus gesamt</h3>
                    <canvas id="statusPie" width="240" height="240"></canvas>
                </div>
                <!-- Diagramm 2: Bar Chart Gruppenbestand -->
                <div style="min-width:340px;">
                    <h3 style="margin-top:0;">Bestand je Hauptgruppe</h3>
                    <canvas id="groupBar" width="320" height="220"></canvas>
                </div>
            </div>

            <!-- Tabelle mit Produkten & Varianten -->
            <div class="ifm-stock-central-flex">
        <?php

        foreach ($main_products as $title => $group_products) {
            echo '<div class="stock-col"><h3>' . esc_html($title) . '</h3>';
            echo '<table class="wp-list-table widefat"><thead><tr>
                <th>Produktgruppe</th>
                <th>Variantenmerkmale</th>
                <th>Lager</th>
                <th>Status</th>
            </tr></thead><tbody>';

            $has_rows = false;

            foreach ($group_products as $product) {
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $var_id) {
                        $var = wc_get_product($var_id);
                        $attrs = [];
                        foreach ($var->get_attributes() as $attr => $value) {
                            $attrs[] = wc_attribute_label(str_replace('attribute_', '', $attr)) . ': ' . ucfirst($value);
                        }
                        $stock = $var->get_stock_quantity();
                        $ampel_data = self::ampel_status($stock);
                        if ($status_filter !== 'all' && $ampel_data['filter'] !== $status_filter) continue;
                        $has_rows = true;
                        echo '<tr>
                            <td>'.esc_html($product->get_name()).'</td>
                            <td>'.esc_html(implode(', ', $attrs)).'</td>
                            <td>'.esc_html($stock).'</td>
                            <td>'.$ampel_data['label'].'</td>
                        </tr>';
                    }
                } else {
                    $stock = $product->get_stock_quantity();
                    $ampel_data = self::ampel_status($stock);
                    if ($status_filter !== 'all' && $ampel_data['filter'] !== $status_filter) continue;
                    $has_rows = true;
                    echo '<tr>
                        <td>'.esc_html($product->get_name()).'</td>
                        <td>-</td>
                        <td>'.esc_html($stock).'</td>
                        <td>'.$ampel_data['label'].'</td>
                    </tr>';
                }
            }
            if (!$has_rows) {
                echo '<tr><td colspan="4" style="text-align:center;">Keine Produkte</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        ?>
            </div>
        </div>
        <style>
        .ifm-stock-central-flex {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
        }
        .stock-col {
            flex: 1 1 0;
            min-width: 260px;
            background: #fafbfc;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 1px 3px #0001;
        }
        .stock-col table { width: 100%; font-size: 14px; }
        .stock-col h3 { margin-bottom: 10px; }
        </style>
        <!-- Chart.js Integration -->
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            // PIE CHART - Status
            const ctxPie = document.getElementById('statusPie').getContext('2d');
            new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: ['Verfügbar', 'Niedrig', 'Ausverkauft'],
                    datasets: [{
                        data: [
                            <?= (int)$status_count['green'] ?>,
                            <?= (int)$status_count['orange'] ?>,
                            <?= (int)$status_count['red'] ?>
                        ],
                        backgroundColor: [
                            '#2ecc40',
                            '#ffb900',
                            '#e00'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // BAR CHART - Gruppenbestände
            const ctxBar = document.getElementById('groupBar').getContext('2d');
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: ['VITARING','VITARING PRO','Travel Case','Wireless Charger'],
                    datasets: [{
                        label: 'Gesamtbestand',
                        data: [
                            <?= (int)$group_stock['VITARING'] ?>,
                            <?= (int)$group_stock['VITARING PRO'] ?>,
                            <?= (int)$group_stock['VITARING Travel Case'] ?>,
                            <?= (int)$group_stock['VITARING Wireless Charger'] ?>
                        ],
                        backgroundColor: [
                            '#6baed6',
                            '#6baed6',
                            '#6baed6',
                            '#6baed6'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });
        </script>
        <?php
    }

    // Ampel: gibt Label (mit Farbe) und Filterwert zurück
    private static function ampel_status($stock) {
        if ($stock === null) return ['label'=>'–','filter'=>'unknown'];
        if ($stock === 0) return [
            'label'=>'<span style="color:red;font-weight:bold;">● Ausverkauft</span>',
            'filter'=>'red'
        ];
        if ($stock < 10) return [
            'label'=>'<span style="color:orange;font-weight:bold;">● Niedrig</span>',
            'filter'=>'orange'
        ];
        return [
            'label'=>'<span style="color:green;font-weight:bold;">● Verfügbar</span>',
            'filter'=>'green'
        ];
    }
}

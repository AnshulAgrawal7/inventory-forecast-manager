<?php
// mPDF Autoload laden (wenn noch nicht geladen)
require_once IFM_PLUGIN_DIR . 'vendor/autoload.php';

// Lager-Daten aus der Klasse holen
$main_products = [
    'VITARING' => [],
    'VITARING PRO' => [],
    'VITARING Travel Case' => [],
    'VITARING Wireless Charger' => [],
];

$products = wc_get_products(['status' => 'publish', 'limit' => -1]);

foreach ($products as $product) {
    $name = $product->get_name();
    if (stripos($name, 'PRO') !== false) {
        $main_products['VITARING PRO'][] = $product;
    } elseif (stripos($name, 'Travel') !== false) {
        $main_products['VITARING Travel Case'][] = $product;
    } elseif (stripos($name, 'Wireless') !== false) {
        $main_products['VITARING Wireless Charger'][] = $product;
    } else {
        $main_products['VITARING'][] = $product;
    }
}

// HTML Ausgabe für PDF
$html = '
<style>
body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
h1 { color: #19549a; margin-bottom: 15px; }
h2 { margin-top: 30px; margin-bottom: 10px; color: #19549a; }
table { border-collapse: collapse; width: 100%; margin-bottom: 25px; font-size: 14px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f7fafc; }
tr:nth-child(even) { background-color: #f9f9f9; }
.status-green { color: green; font-weight: bold; }
.status-orange { color: orange; font-weight: bold; }
.status-red { color: red; font-weight: bold; }
</style>

<h1>Stock Central - Lagerübersicht</h1>
';

foreach ($main_products as $group_title => $products_group) {
    $html .= '<h2>' . htmlspecialchars($group_title) . '</h2>';
    $html .= '<table>
        <thead>
            <tr>
                <th>Produkt / Variante</th>
                <th>Variantenmerkmale</th>
                <th>Lagerbestand</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

    $has_rows = false;

    foreach ($products_group as $product) {
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $child_id) {
                $var = wc_get_product($child_id);
                $attrs = [];
                foreach ($var->get_attributes() as $attr => $value) {
                    $attrs[] = wc_attribute_label(str_replace('attribute_', '', $attr)) . ': ' . ucfirst($value);
                }
                $stock_qty = $var->get_stock_quantity();
                $status = get_stock_status_label($stock_qty);

                $html .= '<tr>
                    <td>' . htmlspecialchars($product->get_name()) . '</td>
                    <td>' . htmlspecialchars(implode(', ', $attrs)) . '</td>
                    <td>' . intval($stock_qty) . '</td>
                    <td class="' . $status['class'] . '">' . $status['label'] . '</td>
                </tr>';

                $has_rows = true;
            }
        } else {
            $stock_qty = $product->get_stock_quantity();
            $status = get_stock_status_label($stock_qty);

            $html .= '<tr>
                <td>' . htmlspecialchars($product->get_name()) . '</td>
                <td>-</td>
                <td>' . intval($stock_qty) . '</td>
                <td class="' . $status['class'] . '">' . $status['label'] . '</td>
            </tr>';

            $has_rows = true;
        }
    }

    if (!$has_rows) {
        $html .= '<tr><td colspan="4" style="text-align:center;">Keine Produkte vorhanden</td></tr>';
    }

    $html .= '</tbody></table>';
}

// Hilfsfunktion für Status
function get_stock_status_label($stock) {
    if ($stock === null) {
        return ['label' => '–', 'class' => ''];
    }
    if ($stock === 0) {
        return ['label' => 'Ausverkauft', 'class' => 'status-red'];
    }
    if ($stock < 10) {
        return ['label' => 'Niedrig', 'class' => 'status-orange'];
    }
    return ['label' => 'Verfügbar', 'class' => 'status-green'];
}

// PDF generieren
$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'orientation' => 'P',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 18,
    'margin_bottom' => 10,
]);

$mpdf->SetTitle('Stock Central - Lagerübersicht');
$mpdf->WriteHTML($html);
$mpdf->Output('ifm-stock-central.pdf', \Mpdf\Output\Destination::INLINE);
exit;

<?php
class IFM_Offline_Sales {

    public static function handle_form() {
        if (
            isset($_POST['ifm_offline_submit']) &&
            check_admin_referer('ifm_save_offline_sale', 'ifm_nonce')
        ) {
            error_log("🟡 handle_form() wurde aufgerufen.");

            global $wpdb;

            $product_ids = $_POST['product_id'] ?? [];
            $quantities  = $_POST['quantity'] ?? [];
            $sale_prices = $_POST['sale_price'] ?? [];
            $sale_date   = sanitize_text_field($_POST['sale_date']);

            $table = $wpdb->prefix . 'ifm_offline_sales';

            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = intval($product_ids[$i]);
                $quantity   = intval($quantities[$i]);
                $price      = floatval($sale_prices[$i]);

                error_log("Eintrag $i: Produkt=$product_id | Menge=$quantity | Preis=$price | Datum=$sale_date");

                if ($product_id && $quantity && $price) {
                    $wpdb->insert($table, [
                        'product_id' => $product_id,
                        'quantity'   => $quantity,
                        'sale_price' => $price,
                        'sale_date'  => $sale_date,
                    ]);

                    $product = wc_get_product($product_id);
                    if ($product && $product->managing_stock()) {
                        $old_stock = $product->get_stock_quantity();
                        $new_stock = max(0, $old_stock - $quantity);
                        $product->set_stock_quantity($new_stock);
                        $product->save();

                        error_log("📦 Lagerbestand von Produkt $product_id reduziert: $old_stock → $new_stock");
                    }
                }
            }

            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>Offline-Verkäufe wurden gespeichert & Lager aktualisiert.</p></div>';
            });
        } else {
            error_log("🔴 handle_form() wurde NICHT ausgeführt – Bedingung fehlgeschlagen");
        }
    }

    public static function render_form() {
        ?>
        <h2>Offline-Verkäufe erfassen</h2>
        <form method="post" id="ifm-offline-form">
            <?php wp_nonce_field('ifm_save_offline_sale', 'ifm_nonce'); ?>

            <table class="form-table" id="product-rows"></table>

            <p><button type="button" class="button" id="add-product-row">➕ Produkt hinzufügen</button></p>

            <p><label for="sale_date">📅 Verkaufsdatum:</label>
                <input type="date" name="sale_date" required>
            </p>

            <p><strong>💰 Gesamtsumme: <span id="total-sum">0,00 €</span></strong></p>

            <?php submit_button('Speichern', 'primary', 'ifm_offline_submit'); ?>
        </form>

        <script>
        window.ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('product-rows');
            const addBtn = document.getElementById('add-product-row');
            const totalSumEl = document.getElementById('total-sum');

            let rowIndex = 0;
            const productList = <?php echo json_encode(self::get_products()); ?>;

            const formatCurrency = (num) => (parseFloat(num) || 0).toFixed(2).replace('.', ',') + ' €';

            const recalculateTotal = () => {
                let sum = 0;
                container.querySelectorAll('tr').forEach(row => {
                    const price = parseFloat(row.querySelector('.price')?.value || 0);
                    sum += price;
                });
                totalSumEl.textContent = formatCurrency(sum);
            };

            const addRow = () => {
                const tr = document.createElement('tr');

                tr.innerHTML = `
                    <td>
                        <select name="product_id[]" class="product-select" required>
                            <option value="">– Produkt wählen –</option>
                            ${productList.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                        </select>
                    </td>
                    <td><input type="number" name="quantity[]" class="qty" min="1" value="1" required></td>
                    <td><input type="text" name="sale_price[]" class="price" value="0.00" readonly></td>
                    <td><button type="button" class="button remove-row">✖</button></td>
                `;

                container.appendChild(tr);

                tr.querySelector('.product-select').addEventListener('change', e => {
                    const id = e.target.value;
                    fetch(window.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=ifm_get_product_price&product_id=${id}`
                    })
                    .then(res => res.json())
                    .then(json => {
                        if (json.success) {
                            const qty = tr.querySelector('.qty').value || 1;
                            const unitPrice = parseFloat(json.data.price);
                            const total = unitPrice * qty;
                            tr.querySelector('.price').value = total.toFixed(2);
                            recalculateTotal();
                        }
                    });
                });

                tr.querySelector('.qty').addEventListener('input', () => {
                    const qty = parseInt(tr.querySelector('.qty').value || 1);
                    if (qty > 0) {
                        const unitPrice = parseFloat(tr.querySelector('.price').value) / qty;
                        tr.querySelector('.price').value = (unitPrice * qty).toFixed(2);
                        recalculateTotal();
                    }
                });

                tr.querySelector('.remove-row').addEventListener('click', () => {
                    tr.remove();
                    recalculateTotal();
                });

                rowIndex++;
            };

            addBtn.addEventListener('click', addRow);
            addRow();
        });
        </script>
        <?php
    }

    private static function get_products() {
        $args = ['limit' => -1, 'status' => 'publish'];
        $products = wc_get_products($args);
        $options = [];

        foreach ($products as $product) {
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);
                    $attributes = $variation->get_attributes();
                    $attr_string = [];

                    foreach ($attributes as $key => $value) {
                        $label = wc_attribute_label(str_replace('attribute_', '', $key));
                        $attr_string[] = $label . ': ' . ucfirst($value);
                    }

                    $options[] = [
                        'id' => $variation->get_id(),
                        'name' => $product->get_name() . ' – ' . implode(', ', $attr_string)
                    ];
                }
            } else {
                $options[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name()
                ];
            }
        }

        return $options;
    }
}
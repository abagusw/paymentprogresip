<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>INVOICE</title>
    <link rel="stylesheet"
          href="{{ asset('resources/css/templates.css') }}">
    <link rel="stylesheet" href="{{ asset('resources/css/custom-pdf.css') }}">
</head>
<body>
<header class="clearfix" style="width: 100%">

    <div id="logo">
        <img src="https://projectmultatuli.org/wp-content/uploads/2023/11/LOGO-dengan-slogan2-e1700032263423.png" id="invoice-logo">
    </div>

    <div id="client">
        <div>            
            <b>{{ $client->client_name }}</b>
        </div>
        <?php if ($invoice->client_vat_id) {
            echo '<div>VAT: ' . $invoice->client_vat_id . '</div>';
        }
        if ($invoice->client_tax_code) {
            echo '<div>Tax Code: ' . $invoice->client_tax_code . '</div>';
        }
        if ($invoice->client_address_1) {
            echo '<div>' . htmlspecialchars($invoice->client_address_1, ENT_QUOTES) . '</div>';
        }
        if ($invoice->client_address_2) {
            echo '<div>' . htmlspecialchars($invoice->client_address_2, ENT_QUOTES) . '</div>';
        }
        if ($invoice->client_city || $invoice->client_state || $invoice->client_zip) {
            echo '<div>';
            if ($invoice->client_city) {
                echo htmlspecialchars($invoice->client_city, ENT_QUOTES) . ' ';
            }
            if ($invoice->client_state) {
                echo htmlspecialchars($invoice->client_state, ENT_QUOTES) . ' ';
            }
            if ($invoice->client_zip) {
                echo htmlspecialchars($invoice->client_zip, ENT_QUOTES);
            }
            echo '</div>';
        }
        if ($invoice->client_country) {
            echo '<div>Indonesia</div>';
        }

        echo '<br/>';

        if ($invoice->client_phone) {
            echo '<div>Phone: ' . htmlspecialchars($invoice->client_phone, ENT_QUOTES) . '</div>';
        } ?>

    </div>
    <div id="company">
        <div><b>Project Multatuli</b></div>
        <?php if ($invoice->user_vat_id) {
            echo '<div>VAT: ' . $invoice->user_vat_id . '</div>';
        }
        if ($invoice->user_tax_code) {
            echo '<div>Tax Code: ' . $invoice->user_tax_code . '</div>';
        }
        if ($invoice->user_address_1) {
            echo '<div>' . htmlspecialchars($invoice->user_address_1, ENT_QUOTES) . '</div>';
        }
        if ($invoice->user_address_2) {
            echo '<div>' . htmlspecialchars($invoice->user_address_2, ENT_QUOTES) . '</div>';
        }
        if ($invoice->user_city || $invoice->user_state || $invoice->user_zip) {
            echo '<div>';
            if ($invoice->user_city) {
                echo htmlspecialchars($invoice->user_city, ENT_QUOTES) . ' ';
            }
            if ($invoice->user_state) {
                echo htmlspecialchars($invoice->user_state, ENT_QUOTES) . ' ';
            }
            if ($invoice->user_zip) {
                echo htmlspecialchars($invoice->user_zip, ENT_QUOTES);
            }
            echo '</div>';
        }
        if ($invoice->user_country) {
            echo '<div>Indonesia</div>';
        }

        echo '<br/>';
        ?>
    </div>

</header>

<main style="width: 100%">

    <div class="invoice-details clearfix">
        <table width="100%" style="table-layout:fixed;">
            <tr>
                <td>Invoice Date :</td>
                <td><?php echo date('d-m-Y', strtotime($invoice->invoice_date_created)); ?></td>
            </tr>
            <tr>
                <td class="text-red">Due Date :</td>
                <td class="text-red"><?php echo date('d-m-Y', strtotime($invoice->invoice_date_due)); ?></td>
            </tr>
            <tr>
                <td class="text-red">Amount :</td>
                <td class="text-red"><?php echo number_format($invoice_amount->invoice_total, (0) ? 2 : 0, 0,'.'); ?></td>
            </tr>
        </table>
    </div>

    <h1 class="invoice-title text-red"><?php echo 'Invoice ' . $invoice->invoice_number; ?></h1>

    <table class="item-table" width="100%" style="table-layout:fixed;">
        <thead>
        <tr>
            <th class="item-name">Item</th>
            <th class="item-desc">Description</th>
            <th class="item-amount text-right">Quantity</th>
            <th class="item-price text-right">Price</th>
            <th class="item-total text-right">Total</th>
        </tr>
        </thead>
        <tbody>

        <?php
        foreach ($items as $item) { ?>
            <tr>
                <td><?php echo htmlspecialchars($item->item_name, ENT_QUOTES); ?></td>
                <td><?php echo nl2br(htmlspecialchars($item->item_description, ENT_QUOTES)); ?></td>
                <td class="text-right">
                    <?php echo $item->item_quantity; ?>
                    <?php if ($item->item_product_unit) : ?>
                        <br>
                        <small><?php htmlspecialchars($item->item_product_unit, ENT_QUOTES); ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <?php echo number_format($item->item_price, (0) ? 2 : 0, 0,'.'); ?>
                </td>
                <td class="text-right">
                    <?php echo number_format($item->item_price * $item->item_quantity, (0) ? 2 : 0, 0,'.'); ?>
                </td>
            </tr>
        <?php } ?>

        </tbody>
        <tbody class="invoice-sums">

        <tr>
            <td <?php echo($show_item_discounts ? 'colspan="5"' : 'colspan="4"'); ?> class="text-right">
                Subtotal
            </td>
            <td class="text-right"><?php echo number_format($invoice_amount->invoice_item_subtotal, (0) ? 2 : 0, 0,'.'); ?></td>
        </tr>

        <?php if ($invoice->invoice_item_tax_total > 0) { ?>
            <tr>
                <td <?php echo($show_item_discounts ? 'colspan="5"' : 'colspan="4"'); ?> class="text-right">
                    Item Tax
                </td>
                <td class="text-right">
                    <?php echo number_format($invoice->invoice_item_tax_total, (0) ? 2 : 0, 0,'.'); ?>
                </td>
            </tr>
        <?php } ?>

        <?php if ($invoice->invoice_discount_percent != '0.00') : ?>
            <tr>
                <td <?php echo($show_item_discounts ? 'colspan="5"' : 'colspan="4"'); ?> class="text-right">
                    Discount
                </td>
                <td class="text-right">
                    <?php echo number_format($invoice->invoice_discount_percent, (0) ? 2 : 0, 0, '.'); ?>%
                </td>
            </tr>
        <?php endif; ?>
        <?php if ($invoice->invoice_discount_amount != '0.00') : ?>
            <tr>
                <td <?php echo($show_item_discounts ? 'colspan="5"' : 'colspan="4"'); ?> class="text-right">
                    Discount
                </td>
                <td class="text-right">
                    <?php echo number_format($invoice->invoice_discount_amount, (0) ? 2 : 0, 0,'.'); ?>
                </td>
            </tr>
        <?php endif; ?>

        <tr>
            <td <?php echo($show_item_discounts ? 'colspan="5"' : 'colspan="4"'); ?> class="text-right">
                <b>Total</b>
            </td>
            <td class="text-right">
                <b><?php echo number_format($invoice_amount->invoice_total, (0) ? 2 : 0, 0,'.'); ?></b>
            </td>
        </tr>
        <tr>
            <td <?php echo($show_item_discounts ? 'colspan="5"' : 'colspan="4"'); ?> class="text-right">
                Paid
            </td>
            <td class="text-right">
                <?php echo number_format($invoice_amount->invoice_paid, (0) ? 2 : 0, 0,'.'); ?>
            </td>
        </tr>
        </tbody>
    </table>

</main>

<watermarktext content="Overdue" alpha="0.3" />

<footer style="width: 100%">
    <?php if ($invoice->invoice_terms) : ?>
        <div class="notes">
            <b>Terms</b><br/>
            <?php echo nl2br(htmlspecialchars($invoice->invoice_terms, ENT_QUOTES)); ?>
        </div>
    <?php endif; ?>
</footer>

</body>
</html>

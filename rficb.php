<?php
$nzshpcrt_gateways[$num]['name'] = 'Rficb';
$nzshpcrt_gateways[$num]['internalname'] = 'rficb';
$nzshpcrt_gateways[$num]['function'] = 'gateway_rficb';
$nzshpcrt_gateways[$num]['form'] = "form_rficb";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_rficb";
$nzshpcrt_gateways[$num]['display_name'] = 'Оплата через платежный интегратор rficb.ru';
$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/onpay.gif';

function to_float1($sum) {
    if (strpos($sum, ".")) {
        $sum = round($sum, 2);
    } else {
        $sum = $sum . ".0";
    }
    return $sum;
}

function gateway_rficb($separator, $sessionid) {
    global $wpdb;
    $purchase_log_sql = "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= " . $sessionid . " LIMIT 1";
    $purchase_log = $wpdb->get_results($purchase_log_sql, ARRAY_A);
    $cart_sql = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`='" . $purchase_log[0]['id'] . "'";
    $cart = $wpdb->get_results($cart_sql, ARRAY_A);
    $rficb_url = get_option('rficb_url') . get_option('rficb_login');
    $data['order_id'] = $purchase_log[0]['id'];
    //$data['currency'] = get_option('rficb_curcode');
    //$data['url_success'] = get_option('siteurl') . "/?rficb_callback=true";
    //$data['pay_mode'] = 'fix';
    $email_data = $wpdb->get_results("SELECT `id`,`type` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` WHERE `type` IN ('email') AND `active` = '1'", ARRAY_A);
    foreach ((array) $email_data as $email) {
        $data['default_email'] = $_POST['collected_data'][$email['id']];
    }
    if (($_POST['collected_data'][get_option('email_form_field')] != null) && ($data['email'] == null)) {
        $data['default_email'] = $_POST['collected_data'][get_option('email_form_field')];
    }
    $currency_code = $wpdb->get_results("SELECT `code` FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id`='" . get_option('currency_type') . "' LIMIT 1", ARRAY_A);
    $local_currency_code = $currency_code[0]['code'];
    $rficb_currency_code = get_option('rficb_curcode');

    $curr = new CURRENCYCONVERTER();
    $decimal_places = 2;
    $total_price = 0;
    $i = 1;
    $all_donations = true;
    $all_no_shipping = true;
    foreach ($cart as $item) {
        $product_data = $wpdb->get_results("SELECT * FROM `" . $wpdb->posts . "` WHERE `id`='" . $item['prodid'] . "' LIMIT 1", ARRAY_A);
        $product_data = $product_data[0];
        $variation_count = count($product_variations);
        $variation_sql = "SELECT * FROM `" . WPSC_TABLE_CART_ITEM_VARIATIONS . "` WHERE `cart_id`='" . $item['id'] . "'";
        $variation_data = $wpdb->get_results($variation_sql, ARRAY_A);
        $variation_count = count($variation_data);
        if ($variation_count >= 1) {
            $variation_list = " (";
            $j = 0;
            foreach ($variation_data as $variation) {
                if ($j > 0) {
                    $variation_list .= ", ";
                }
                $value_id = $variation['venue_id'];
                $value_data = $wpdb->get_results("SELECT * FROM `" . WPSC_TABLE_VARIATION_VALUES . "` WHERE `id`='" . $value_id . "' LIMIT 1", ARRAY_A);
                $variation_list .= $value_data[0]['name'];
                $j++;
            }
            $variation_list .= ")";
        } else {
            $variation_list = '';
        }
        $local_currency_productprice = $item['price'];
        $local_currency_shipping = $item['pnp'];
        $rficb_currency_productprice = $local_currency_productprice;
        $rficb_currency_shipping = $local_currency_shipping;
        $data['amount_' . $i] = number_format(sprintf("%01.2f", $rficb_currency_productprice), $decimal_places, '.', '');
        $data['quantity_' . $i] = $item['quantity'];
        $total_price = $total_price + ($data['amount_' . $i] * $data['quantity_' . $i]);
        if ($all_no_shipping != false)
            $total_price = $total_price + $data['shipping_' . $i] + $data['shipping2_' . $i];
        $i++;
    }
    $base_shipping = $purchase_log[0]['base_shipping'];
    if (($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false)) {
        $data['handling_cart'] = number_format($base_shipping, $decimal_places, '.', '');
        $total_price += number_format($base_shipping, $decimal_places, '.', '');
    }
    $data['cost'] = $total_price;
    $data['name'] = 'покупка в магазине';
    //$sum_for_md5 = to_float1($data['price']);
    $data['key'] = get_option('rficb_key');
    if (WPSC_GATEWAY_DEBUG == true) {
        exit("<pre>" . print_r($data, true) . "</pre>");
    }
    $output = "
		<form id=\"rficb_form\" name=\"rficb_form\" method=\"post\" action=\"https://partner.rficb.ru/a1lite/input\">\n";

    foreach ($data as $n => $v) {
        $output .= "			<input type=\"hidden\" name=\"$n\" value=\"$v\" />\n";
    }

    $output .= "			<input type=\"submit\" value=\"Continue to rficb\" />
		</form>
	";

    if (get_option('rficb_debug') == 1) {
        echo ("DEBUG MODE ON!!<br/>");
        echo("The following form is created and would be posted to rficb for processing.  Press submit to continue:<br/>");
        echo("<pre>" . htmlspecialchars($output) . "</pre>");
    }

    echo($output);

    if (get_option('rficb_debug') == 0) {
       echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('rficb_form').submit();</script>";
    }

    exit();
}

function nzshpcrt_rficb_callback() {
    if(isset($_GET['rficb_callback'])) {
    global $wpdb;
    $crc = $_POST['check'];
    $rficb_skey = get_option('rficb_skey');
    $data = array(
        'tid' => $_POST['tid'],
        'name' => $_POST['name'],
        'comment' => $_POST['comment'],
        'partner_id' => $_POST['partner_id'],
        'service_id' => $_POST['service_id'],
        'order_id' => $_POST['order_id'],
        'type' => $_POST['type'],
        'partner_income' => $_POST['partner_income'],
        'system_income' => $_POST['system_income'],
        'test' => $_POST['test'],
    );

    $check = md5(join('', array_values($data)) .$rficb_skey );
    if ($check == $crc) { 
    echo 'OK payment order №'.$data[order_id];
    $wpdb->update(WPSC_TABLE_PURCHASE_LOGS, array('processed' => 3, 'date' => time()), array('id' => $data[order_id]), array('%d', '%s'), array('%d'));
                    exit;}
    else echo 'Bad payment!'; exit;
    } 
}

function nzshpcrt_rficb_results() {
    if (isset($_POST['cs1']) && ($_POST['cs1'] != '') && ($_GET['sessionid'] == '')) {
        $_GET['sessionid'] = $_POST['cs1'];
    }
}

function submit_rficb() {
    if (isset($_POST['rficb_skey'])) {
        update_option('rficb_skey', $_POST['rficb_skey']);
    }

    if (isset($_POST['rficb_key'])) {
        update_option('rficb_key', $_POST['rficb_key']);
    }

    if (isset($_POST['rficb_url'])) {
        update_option('rficb_url', $_POST['rficb_url']);
    }
    if (isset($_POST['rficb_debug'])) {
        update_option('rficb_debug', $_POST['rficb_debug']);
    }
    if (!isset($_POST['rficb_form']))
        $_POST['rficb_form'] = array();
    foreach ((array) $_POST['rficb_form'] as $form => $value) {
        update_option(('rficb_form_' . $form), $value);
    }
    return true;
}

function form_rficb() {

    $rficb_url = ( get_option('rficb_url') == '' ? 'http://secure.rficb.ru/pay/' . get_option('rficb_login') : get_option('rficb_url') );
    $rficb_salt = ( get_option('rficb_key') == '' ? 'changeme' : get_option('rficb_key') );
        $rficb_debug = get_option('rficb_debug');
    $rficb_debug1 = "";
    $rficb_debug2 = "";
    switch ($rficb_debug) {
        case 0:
            $rficb_debug2 = "checked ='checked'";
            break;
        case 1:
            $rficb_debug1 = "checked ='checked'";
            break;
    }


    $output = "
		<tr>
			<td>Секретный ключ</td>
			<td><input type='text' size='40' value='" . get_option('rficb_skey') . "' name='rficb_skey' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td> <td><small>Введите секретный ключ, указанный в настройках вашего сервиса на сайте rficb</small></td>
		</tr>

		<tr>
			<td>Ключ платежа</td>
			<td><input type='text' size='40' value='" . get_option('rficb_key') . "' name='rficb_key' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td> <td><small>Введите ключ платежа, взятый из сгенерированной платежной формы на сайте rficb</small></td>
		</tr>

		<tr>
			<td>Адрес URL API</td>
			<td><input type='text' size='40' value='http://" . $_SERVER['SERVER_NAME'] . '/' . "' name='rficb_return_url' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td> <td><small>Скопируйте и вставьте этот
			адрес в настройках магазина на сайте rficb.ru в поле URL
			API.</small></td>
		</tr>
    		<tr>
			<td>Отладка</td>
			<td>
				<input type='radio' value='1' name='rficb_debug' id='onpay_debug1' " . $rficb_debug1 . " /> <label for='rficb_debug1'>" . __('Yes', 'wpsc') . "</label> &nbsp;
				<input type='radio' value='0' name='rficb_debug' id='onpay_debug2' " . $rficb_debug2 . " /> <label for='rficb_debug2'>" . __('No', 'wpsc') . "</label>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>Режим отладки.</small></td>
		</tr>

</tr>";

    return $output;
}

add_action('init', 'nzshpcrt_rficb_callback');
add_action('init', 'nzshpcrt_rficb_results');
?>

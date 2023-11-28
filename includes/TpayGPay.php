<?php

namespace Tpay;

use WC_Order;

class TpayGPay extends TpayGateways
{
    const CHANNEL_ID = 68;

    public function __construct()
    {
        parent::__construct(TPAYGPAY_ID, TPAYGPAY);
        $this->has_terms_checkbox = true;
        $this->icon = apply_filters('woocommerce_tpay_icon', plugin_dir_url(__FILE__).'../views/img/google_pay.png');
    }

    public function init_form_fields()
    {
        parent::tpay_init_form_fields(false);
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        $agreements = '';

        if ($this->has_terms_checkbox) {
            $agreements = $this->gateway_helper->agreements_field();
        }

        include plugin_dir_path(__FILE__).'../views/html/agreements.php';
    }

    public function process_payment($order_id)
    {
        $this->crc = $this->createCRC($order_id);
        $order = new WC_Order($order_id);
        $this->set_payment_data($order, self::CHANNEL_ID);
        $result = $this->process_transaction($order);

        if ('success' == $result['result']) {
            if ($errors_list = $this->gateway_helper->tpay_has_errors($result)) {
                $this->gateway_helper->tpay_logger('Nieudana próba płatności Gpay- zwrócone następujące błędy: '.implode(' ', $errors_list));
                wc_add_notice(implode(' ', $errors_list), 'error');

                return false;
            }
            $redirect = $result['transactionPaymentUrl'] ?: $this->get_return_url($order);
            $md5 = md5($this->id_seller.$result['title'].$this->payment_data['amount'].$this->crc.$this->security_code);
            update_post_meta($order->ID, '_transaction_id', $result['transactionId']);
            update_post_meta($order->ID, '_md5_checksum', $md5);
            update_post_meta($order->ID, '_crc', $this->crc);
            update_post_meta($order->ID, '_payment_method', $this->id);
            $this->gateway_helper->tpay_logger('Udane zamówienie, płatność Gpay, redirect na: '.$redirect);

            return [
                'result' => 'success',
                'redirect' => $redirect,
            ];
        }
        wc_add_notice(__('Payment error', 'tpay'), 'error');

        return false;
    }
}

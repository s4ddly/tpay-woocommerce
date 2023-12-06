<?php

namespace Tpay;

use Tpay\Dtos\Channel;
use WC_Order;

class PekaoInstallments extends TpayGateways
{
    public function __construct()
    {
        parent::__construct(TPAYPEKAOINSTALLMENTS_ID, TPAYPEKAOINSTALLMENTS);
        $this->has_terms_checkbox = true;
        $this->icon = apply_filters('woocommerce_tpay_icon', plugin_dir_url(__FILE__).'../views/img/raty_pekao.png');
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

        $channels = $this->channels();
        $cartTotal = $this->getCartTotal();

        $list = $this->filter_out_constraints(array_filter($channels, function (Channel $channel) use ($cartTotal) {
            foreach ($channel->groups as $group) {
                if ($group->id !== TPAYPEKAOINSTALLMENTS) {
                    return false;
                }
            }

            if (empty($channel->groups)) {
                return false;
            }

            return true;
        }));

        $agreements = '';

        if ($this->has_terms_checkbox) {
            $agreements = $this->gateway_helper->agreements_field();
        }

        include plugin_dir_path(__FILE__).'../views/html/pekao.php';
    }

    public function process_payment($order_id)
    {
        $this->crc = $this->createCRC($order_id);
        $order = new WC_Order($order_id);
        $groupID = TPAYPEKAOINSTALLMENTS;
        $this->set_payment_data($order, $groupID);
        $channelId = $this->request->get('tpay-channelID');
        if ($channelId) {
            $this->payment_data['pay'] = ['channelId' => (int) $channelId];
        }
        $result = $this->process_transaction($order);
        if ('success' == $result['result']) {
            if ($errors_list = $this->gateway_helper->tpay_has_errors($result)) {
                $this->gateway_helper->tpay_logger('Nieudana próba płatności ratalnej- zwrócone następujące błędy: '.implode(' ', $errors_list));
                wc_add_notice(implode(' ', $errors_list), 'error');

                return false;
            }
            $redirect = $result['transactionPaymentUrl'] ? $result['transactionPaymentUrl'] : $this->get_return_url($order);
            $order->set_transaction_id($result['transactionId']);
            $order->save();
            $md5 = md5($this->id_seller.$result['title'].$this->payment_data['amount'].$this->crc.$this->security_code);
            update_post_meta($order->ID, '_transaction_id', $result['transactionId']);
            update_post_meta($order->ID, '_md5_checksum', $md5);
            update_post_meta($order->ID, '_crc', $this->crc);
            update_post_meta($order->ID, '_payment_method', $this->id);
            $this->gateway_helper->tpay_logger('Udane zamówienie, płatność ratalna, redirect na: '.$redirect);

            return [
                'result' => 'success',
                'redirect' => $redirect,
            ];
        }
        wc_add_notice(__('Payment error', 'tpay'), 'error');

        return false;
    }
}

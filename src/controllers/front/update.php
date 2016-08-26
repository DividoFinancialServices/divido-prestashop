<?php

class DividofinancingUpdateModuleFrontController extends ModuleFrontController {
    public function postProcess ()
    {
		$input = file_get_contents('php://input');
		$data  = json_decode($input);

		if (!isset($data->status)) {
	        die();	
		}

        $lookup = $this->module->getLookup($data->metadata->cart_id);
		if (! $lookup) {
			die();
		}

        $hash = $this->module->hashCart($lookup['salt'], $data->metadata->cart_id);
        if ($hash != $data->metadata->hash) {
            die();
        }

        $cart = new Cart($data->metadata->cart_id);
        if (! Validate::isLoadedObject($cart)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $orderId = null;
        $createStatus = Configuration::get('DIVIDO_CREATE_ORDER_STATUS');
        if ($createStatus == $data->status) {
            $customer = new Customer($cart->id_customer);
            if (! Validate::isLoadedObject($customer)) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

            $this->module->validateOrder(
                $data->metadata->cart_id, 
                Configuration::get('PS_OS_PREPARATION'),
                $total,
                $this->module->displayName,
                null, array(), null, false,
                $customer->secure_key);
        } 

        $this->updateLookup($data, $orderId);

        echo 'ps-' . _PS_VERSION_ . ',divido-'.$this->module->version;
        exit;
    }

    private function updateLookup($data, $orderId) 
    {
        $data = array(
            'cart_id'               => $data->metadata->cart_id,
            'credit_application_id' => $data->application,
            'order_id'              => $orderId,
            'canceled'              => $data->status == DividoFinancing::DIVIDO_STATUS_CANCELLED ? 1 : 0,
            'declined'              => $data->status == DividoFinancing::DIVIDO_STATUS_DECLINED ? 1 : 0,
        );

        foreach ($data as $key => $val) {
            if (is_null($val)) {
                unset($data['key']);
            }
        }

        $this->module->createLookup($data);
    }
}

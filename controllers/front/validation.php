<?php
class DividofinancingValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
			Tools::redirect('index.php?controller=order&step=1');
        }


		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module) {
			if ($module['name'] == 'dividofinancing') {
				$authorized = true;
				break;
			}
        }

		if (!$authorized) {
			die($this->module->l('This payment method is not available.', 'validation'));
        }

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			Tools::redirect('index.php?controller=order&step=1');
        }

        $apiKey = $this->module->getApiKey();

        xdebug_break();
		$total   = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $deposit_percentage = Tools::getValue('divido_deposit') / 100;
        $deposit = round($deposit_percentage * $total, 2);

        $finance = Tools::getValue('divido_finance');

		$currency = $this->context->currency;
        $currencyCode = $currency->iso_code;
        
        $salt = uniqid('', true);
        $hash = $this->module->hash_cart($salt, $cart->id);

        $shipping = new Address(intval($cart->id_address_delivery));
        $countryCode = Country::getIsoById($shipping->id_country);

        $language = 'EN';

        $metadata = array(
            'cart_id' => $cart->id,
            'hash'    => $hash,
        );

        $cartProducts = $cart->getProducts();
        $products = array();
        foreach ($cartProducts as $prod) {
            $products[] = array(
                'type'     => 'product',
                'text'     => $prod['name'],
                'quantity' => $prod['cart_quantity'],
                'value'    => $prod['price_with_reduction'],
            );
        }

        $shipping_cost = $cart->getTotalShippingCost();
        if ($shipping_cost) {
            $products[] = array(
                'type'     => 'product',
                'text'     => 'Shipping',
                'quantity' => 1,
                'value'    => $shipping_cost,
            );
        }

        $customerData = array(
            'title'         => '',
            'middlename'    => '',
            'country'       => $countryCode,
            'firstname'     => $customer->firstname,
            'lastname'      => $customer->lastname,
            'email'         => $customer->email,
            'postcode'      => $shipping->postcode,
            'mobile_number' => $shipping->phone_mobile,
            'phone_number'  => $shipping->phone,
        );

        $responseUrl = $this->context->link->getModuleLink('dividofinancing', 'update');
        $checkoutUrl = $this->context->link->getPageLink('order');
        $redirectParameters = array(
            'id_cart'   => (int)$cart->id,
            'id_module' => (int)$this->module->id,
            'id_order'  => $this->module->currentOrder,
            'key'       => $customer->secure_key,
        );
        $redirectUrl = $this->context->link->getPageLink('order-confirmation', null, null, $redirectParameters);

        $requestData = array(
            'merchant'     => $apiKey,
            'deposit'      => $deposit,
            'finance'      => $finance,
            'country'      => $countryCode,
            'language'     => $language,
            'currency'     => $currencyCode,
            'metadata'     => $metadata,
            'customer'     => $customerData,
            'products'     => $products,
            'response_url' => $responseUrl,
            'checkout_url' => $checkoutUrl,
            'redirect_url' => $redirectUrl,
        );

        $result = $this->module->doCreditRequest($requestData);

        if ($result->status == "ok") {
            $this->module->createLookup(array(
                'salt' => $salt,
                'cart_id' => $cart->id,
                'deposit_amount' => $deposit,
                'credit_request_id' => $result->id,
            ));

            Tools::redirect($result->url);
        }
	}
}

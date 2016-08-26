<?php
class DividofinancingPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $plans = $this->module->getCartPlans($cart);
        $plans = array_map(function ($p) { return $p->id; }, $plans);
        $plans = implode(',', $plans);

        /*
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }
         */

        $this->context->smarty->assign(array(
            'nbProducts'       => $cart->nbProducts(),
            'cust_currency'    => $cart->id_currency,
            'currencies'       => $this->module->getCurrency((int)$cart->id_currency),
            'total'            => $cart->getOrderTotal(true, Cart::BOTH),
            'this_path'        => $this->module->getPathUri(),
            'this_path_divido' => $this->module->getPathUri(),
            'this_path_ssl'    => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
            'plans'            => $plans,
        ));

        $this->setTemplate('payment_execution.tpl');
    }
}

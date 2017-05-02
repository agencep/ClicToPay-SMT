<?php


/*
 * (c) 2017 ExtrumWeb International <info@extrumweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class ClictopaySuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $context = Context::getContext();
        $cart = $context->cart;
        $clictopay = new Clictopay();
        $customer = new Customer($cart->id_customer);
        $this->errors[] = Tools::displayError("Your order has been confirmed");
        Tools::redirect('index.php?controller=order-confirmation');
    }
}

<?php


/*
 * (c) 2017 ExtrumWeb International <info@extrumweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class ClictopayEchecModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $this->errors[] = Tools::displayError("An error has occurred in the payment module, Please inform us by email: support@extrumweb.com");
        Tools::redirect('index.php?controller=order');
    }
}

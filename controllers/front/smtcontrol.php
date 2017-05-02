<?php


/*
 * (c) 2017 ExtrumWeb International <info@extrumweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class ClictopaySmtcontrolModuleFrontController extends ModuleFrontController
{
    public $Reference;
    public $Action;
    public $Params;
    public $Montant;
    public $Cart;

    public function initContent()
    {
        parent::initContent();
        $this->errors[] = Tools::displayError('An error has occurred in the payment module, Please inform us by email: support@extrumweb.com');

        $this->Reference = Tools::getValue('Reference');
        $this->Action = Tools::getValue('Action');
        $this->Params = (isset($_GET['Param'])) ? Tools::getValue('Param') : null;

        $this->Cart = new Cart((int)$this->Reference);
        $this->Montant = sprintf("%.3f",$this->Cart->getOrderTotal());

        if (empty($this->Reference) || empty($this->Action)) {
            exit;
        }



        if (!$resp = $this->$_GET['Action']()) {
            return false;
        }

        // Response sent to the SMT server
        die($resp);
    }


    /**
     * Detail of order required by SMT
     */
    public function DETAIL()
    {
        return "Reference=" . $this->Cart->id . "&Action=" . $this->Action . "&Reponse=" . $this->Montant;
    }

    /**
     * If payment accepted by SMT, Validate the Purchase Order,
     */
    public function ACCORD()
    {
        $this->module->setOrder(Configuration::get('SMT_OS_ACCEPTED'), $this->Cart, $this->Params);
        return "Reference=" . $this->Cart->id . "&Action=" . $this->Action . "&Reponse=OK";
    }

    /**
     * If a transaction error received by the SMT, Update the Purchase Order.
     */
    public function ERREUR()
    {
        $this->module->setOrder(Configuration::get('SMT_OS_ERROR'), $this->Cart);
        return $response = "Reference=" . $this->Cart->id . "&Action=" . $this->Action . "&Reponse=OK";
    }

    /**
     * If a refusal of payment received by the SMT, Update the Purchase Order.
     */
    public function REFUS()
    {
        $this->module->setOrder(Configuration::get('SMT_OS_REFUSED'), $this->Cart);
        return "Reference=" . $this->Cart->id . "&Action=" . $this->Action . "&Reponse=OK";
    }

    /**
     * If a cancellation of payment received by the SMT, Update the Purchase Order.
     */
    public function ANNULATION()
    {
        $this->module->setOrder(Configuration::get('SMT_OS_CANCELED'), $this->Cart);
        return "Reference=" . $this->Cart->id . "&Action=" . $this->Action . "&Reponse=OK";
    }
}

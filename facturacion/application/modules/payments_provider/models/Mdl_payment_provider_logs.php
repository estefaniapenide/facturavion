<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */

/**
 * Class Mdl_Payment_Logs
 */
class Mdl_Payment_provider_Logs extends Response_Model
{
    public $table = 'ip_merchant_responses';
    public $primary_key = 'ip_merchant_responses.merchant_response_id';
    public function default_select()
    {
        $this->db->select("
            ip_invoices_provider.invoice_provider_number,
            ip_merchant_responses.*", false);
    }
    public function default_order_by()
    {
        $this->db->order_by('ip_merchant_responses.merchant_response_id DESC');
    }

    public function default_join()
    {
        $this->db->join('ip_invoices_provider', 'ip_invoices_provider.invoice_provider_id = ip_merchant_responses.invoice_id');
    }

}

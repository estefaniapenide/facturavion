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
 * Class Mdl_Invoice_Tax_Rates
 */
class Mdl_Invoice_Provider_Tax_Rates extends Response_Model
{
    public $table = 'ip_invoice_provider_tax_rates';
    public $primary_key = 'ip_invoice_provider_tax_rates.invoice_provider_tax_rate_id';

    public function default_select()
    {
        $this->db->select('ip_tax_rates.tax_rate_name AS invoice_provider_tax_rate_name');
        $this->db->select('ip_tax_rates.tax_rate_percent AS invoice_provider_tax_rate_percent');
        $this->db->select('ip_invoice_provider_tax_rates.*');
    }

    public function default_join()
    {
        $this->db->join('ip_tax_rates', 'ip_tax_rates.tax_rate_id = ip_invoice_provider_tax_rates.tax_rate_id');
    }

    /**
     * @param null $id
     * @param null $db_array
     * @return void
     */
    public function save($id = null, $db_array = null)
    {
        parent::save($id, $db_array);

        $this->load->model('invoices_provider/mdl_invoice_provider_amounts');

        if (isset($db_array['invoice_provider_id'])) {
            $invoice_id = $db_array['invoice_provider_id'];
        } else {
            $invoice_id = $this->input->post('invoice_provider_id');
        }

        if ($invoice_id) {
            $this->mdl_invoice_provider_amounts->calculate_invoice_taxes($invoice_id);
            $this->mdl_invoice_provider_amounts->calculate($invoice_id);
        }

    }

    /**
     * @return array
     */
    public function validation_rules()
    {
        return array(
            'invoice_provider_id' => array(
                'field' => 'invoice_provider_id',
                'label' => trans('invoice_provider'),
                'rules' => 'required'
            ),
            'tax_rate_id' => array(
                'field' => 'tax_rate_id',
                'label' => trans('tax_rate'),
                'rules' => 'required'
            ),
            'include_item_tax' => array(
                'field' => 'include_item_tax',
                'label' => trans('tax_rate_placement'),
                'rules' => 'required'
            )
        );
    }

}

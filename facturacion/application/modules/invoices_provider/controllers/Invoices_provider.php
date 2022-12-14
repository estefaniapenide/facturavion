<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */

/**
 * Class Invoices
 */
class Invoices_provider extends Admin_Controller
{

    /**
     * Invoices constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('mdl_invoices_provider');
    }

    public function index()
    {
        // Display all invoices by default
        redirect('invoices_provider/status/all');
    }

    /**
     * @param string $status
     * @param int $page
     */
    public function status($status = 'all', $page = 0)
    {
        // Determine which group of invoices to load
        switch ($status) {
            case 'draft':
                $this->mdl_invoices_provider->is_draft();
                break;
          /*   case 'sent':
                $this->mdl_invoices_provider->is_sent();
                break; */
            case 'invoiced':
                $this->mdl_invoices_provider->is_invoiced();
                break;
            case 'paid':
                $this->mdl_invoices_provider->is_paid();
                break;
            case 'overdue':
                $this->mdl_invoices_provider->is_overdue();
                break;
        }

        $this->mdl_invoices_provider->paginate(site_url('invoices_provider/status/' . $status), $page);
        $invoices = $this->mdl_invoices_provider->result();

        $this->layout->set(
            [
                'invoices_provider' => $invoices,
                'status' => $status,
                'filter_display' => true,
                'filter_placeholder' => trans('filter_invoices_provider'),
                'filter_method' => 'filter_invoices_provider',
                'invoice_provider_statuses' => $this->mdl_invoices_provider->statuses(),
            ]
        );

        $this->layout->buffer('content', 'invoices_provider/index');
        $this->layout->render();
    }

    public function archive()
    {
        $invoice_array = [];

        if (isset($_POST['invoice_provider_number'])) {
            $invoiceNumber = $_POST['invoice_provider_number'];
            $invoice_array = glob(UPLOADS_ARCHIVE_FOLDER . '*' . '_' . $invoiceNumber . '.pdf');
            $this->layout->set(
                [
                    'invoice_provider_archive' => $invoice_array,
                ]);
            $this->layout->buffer('content', 'invoices_provider/archive');
            $this->layout->render();

        } else {
            foreach (glob(UPLOADS_ARCHIVE_FOLDER . '*.pdf') as $file) {
                array_push($invoice_array, $file);
            }

            rsort($invoice_array);
            $this->layout->set(
                [
                    'invoices_archive' => $invoice_array,
                ]);
            $this->layout->buffer('content', 'invoices_provider/archive');
            $this->layout->render();
        }
    }

    /**
     * @param $invoice
     */
    public function download($invoice)
    {
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="' . urldecode($invoice) . '"');
        readfile(UPLOADS_ARCHIVE_FOLDER . urldecode($invoice));
    }


    /**
     * @param $invoice
     */
    public function download_pdf($invoice) //Pendiente!!
    {

    }


    /**
     * @param $invoice
     */
    public function add_pdf($invoice) //Pendiente!!
    {

    }

    /**
     * @param $invoice_id
     */
    public function view($invoice_id)
    {
        $this->load->model(
            [
                'mdl_invoice_provider_items',
                'tax_rates/mdl_tax_rates',
                'payment_methods/mdl_payment_methods',
                'mdl_invoice_provider_tax_rates',
                'custom_fields/mdl_custom_fields',
            ]
        );

        $this->load->helper("custom_values");
        //$this->load->helper("client");
        $this->load->model('units/mdl_units');
        $this->load->module('payments_provider');

        $this->load->model('custom_values/mdl_custom_values');
        $this->load->model('custom_fields/mdl_invoice_custom');

        $this->db->reset_query();

        /*$invoice_custom = $this->mdl_invoice_custom->where('invoice_id', $invoice_id)->get();

        if ($invoice_custom->num_rows()) {
            $invoice_custom = $invoice_custom->row();

            unset($invoice_custom->invoice_id, $invoice_custom->invoice_custom_id);

            foreach ($invoice_custom as $key => $val) {
                $this->mdl_invoices->set_form_value('custom[' . $key . ']', $val);
            }
        }*/

        $fields = $this->mdl_invoice_custom->by_id($invoice_id)->get()->result();
        $invoice = $this->mdl_invoices_provider->get_by_id($invoice_id);

        if (!$invoice) {
            show_404();
        }

        $custom_fields = $this->mdl_custom_fields->by_table('ip_invoice_provider_custom')->get()->result();
        $custom_values = [];
        foreach ($custom_fields as $custom_field) {
            if (in_array($custom_field->custom_field_type, $this->mdl_custom_values->custom_value_fields())) {
                $values = $this->mdl_custom_values->get_by_fid($custom_field->custom_field_id)->result();
                $custom_values[$custom_field->custom_field_id] = $values;
            }
        }

        foreach ($custom_fields as $cfield) {
            foreach ($fields as $fvalue) {
                if ($fvalue->invoice_custom_fieldid == $cfield->custom_field_id) {
                    // TODO: Hackish, may need a better optimization
                    $this->mdl_invoices_provider->set_form_value(
                        'custom[' . $cfield->custom_field_id . ']',
                        $fvalue->invoice_custom_fieldvalue
                    );
                    break;
                }
            }
        }

        // Check whether there are payment custom fields
        $payment_cf = $this->mdl_custom_fields->by_table('ip_payment_custom')->get();
        $payment_cf_exist = ($payment_cf->num_rows() > 0) ? "yes" : "no";

        $this->load->model('modelo303/mdl_modelo303');
        $this->layout->set(
            [
                'invoice' => $invoice,
                'impuestos' => $this->mdl_modelo303->ivasfactura($invoice->invoice_provider_number),
                'items' => $this->mdl_invoice_provider_items->where('invoice_provider_id', $invoice_id)->get()->result(),
                'invoice_id' => $invoice_id,
                'tax_rates' => $this->mdl_tax_rates->get()->result(),
                'invoice_provider_tax_rates' => $this->mdl_invoice_provider_tax_rates->where('invoice_provider_id', $invoice_id)->get()->result(),
                'units' => $this->mdl_units->get()->result(),
                'payment_methods' => $this->mdl_payment_methods->get()->result(),
                'custom_fields' => $custom_fields,
                'custom_values' => $custom_values,
                'custom_js_vars' => [
                    'currency_symbol' => get_setting('currency_symbol'),
                    'currency_symbol_placement' => get_setting('currency_symbol_placement'),
                    'decimal_point' => get_setting('decimal_point'),
                ],
                'invoice_statuses' => $this->mdl_invoices_provider->statuses(),
                'payment_cf_exist' => $payment_cf_exist,
            ]
        );


            $this->layout->buffer(
                [
                    ['modal_delete_invoice', 'invoices_provider/modal_delete_invoice'],
                    ['modal_add_invoice_tax', 'invoices_provider/modal_add_invoice_tax'],
                    ['modal_add_payment', 'payments_provider/modal_add_payment'],
                    ['content', 'invoices_provider/view'],
                ]
            );


        $this->layout->render();
    }

    /**
     * @param $invoice_id
     */
    public function delete($invoice_id)
    {
        // Get the status of the invoice
       // $invoice = $this->mdl_invoices_provider->get_by_id($invoice_id);


        if ($this->config->item('enable_invoice_provider_deletion') === true) {

            // If invoice refers to tasks, mark those tasks back to 'Complete'
            $this->load->model('tasks/mdl_tasks');
            $tasks = $this->mdl_tasks->update_on_invoice_delete($invoice_id);

            // Delete the invoice
            $this->mdl_invoices_provider->delete($invoice_id);
        } else {
            // Add alert that invoices can't be deleted
            $this->session->set_flashdata('alert_error', trans('invoice_deletion_forbidden'));
        }

        // Redirect to invoice index
        redirect('invoices_provider/index');
    }

    /**
     * @param $invoice_id
     * @param bool $stream
     * @param null $invoice_template
     */
/*     public function generate_pdf($invoice_id, $stream = true, $invoice_template = null)
    {
        $this->load->helper('pdf');

        if (get_setting('mark_invoices_sent_pdf') == 1) {
            $this->mdl_invoices->generate_invoice_number_if_applicable($invoice_id);
            $this->mdl_invoices->mark_sent($invoice_id);
        }

        generate_invoice_pdf($invoice_id, $stream, $invoice_template, null);
    } */

    /**
     * @param $invoice_id
     */
    public function generate_zugferd_xml($invoice_id)
    {
        $this->load->model('invoices_provider/mdl_invoice_provider_items');
        $this->load->library('ZugferdXml', [
            'invoice_provider' => $this->mdl_invoices_provider->get_by_id($invoice_id),
            'items' => $this->mdl_invoice_provider_items->where('invoice_provider_id', $invoice_id)->get()->result(),
        ]);

        $this->output->set_content_type('text/xml');
        $this->output->set_output($this->zugferdxml->xml());
    }


    /**
     * @param $invoice_id
     * @param $invoice_tax_rate_id
     */
    public function delete_invoice_tax($invoice_id, $invoice_tax_rate_id)
    {
        $this->load->model('mdl_invoice_provider_tax_rates');
        $this->mdl_invoice_provider_tax_rates->delete($invoice_tax_rate_id);

        $this->load->model('mdl_invoice_provider_amounts');
        $this->mdl_invoice_provider_amounts->calculate($invoice_id);

        redirect('invoices_provider/view/' . $invoice_id);
    }

    public function recalculate_all_invoices()
    {
        $this->db->select('invoice_provider_id');
        $invoice_ids = $this->db->get('ip_invoices_provider')->result();

        $this->load->model('mdl_invoice_provider_amounts');

        foreach ($invoice_ids as $invoice_id) {
            $this->mdl_invoice_provider_amounts->calculate($invoice_id->invoice_provider_id);
        }
    }
}
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
 * Class Mdl_Invoice_Amounts
 */
class Mdl_Invoice_Provider_Amounts extends CI_Model
{
    /**
     * IP_INVOICE_AMOUNTS
     * invoice_amount_id
     * invoice_id
     * invoice_item_subtotal    SUM(item_subtotal)
     * invoice_item_tax_total   SUM(item_tax_total)
     * invoice_tax_total
     * invoice_total            invoice_item_subtotal + invoice_item_tax_total + invoice_tax_total
     * invoice_paid
     * invoice_balance          invoice_total - invoice_paid
     *
     * IP_INVOICE_ITEM_AMOUNTS
     * item_amount_id
     * item_id
     * item_tax_rate_id
     * item_subtotal            item_quantity * item_price
     * item_tax_total           item_subtotal * tax_rate_percent
     * item_total               item_subtotal + item_tax_total
     *
     * @param $invoice_id
     */
    public function calculate($invoice_id)
    {
        // Get the basic totals
        $query = $this->db->query("
        SELECT  SUM(item_subtotal) AS invoice_item_subtotal,
		        SUM(item_tax_total) AS invoice_item_tax_total,
		        SUM(item_subtotal) + SUM(item_tax_total) AS invoice_total,
		        SUM(item_discount) AS invoice_item_discount
		FROM ip_invoice_provider_item_amounts
		WHERE item_id IN (
		    SELECT item_id FROM ip_invoice_provider_items WHERE invoice_provider_id = " . $this->db->escape($invoice_id) . "
		    )
        ");

        $invoice_amounts = $query->row();
        $invoice_item_subtotal = $invoice_amounts->invoice_item_subtotal - $invoice_amounts->invoice_item_discount;
        $invoice_subtotal = $invoice_item_subtotal + $invoice_amounts->invoice_item_tax_total;
        $invoice_total = $this->calculate_discount($invoice_id, $invoice_subtotal);
        // Get the amount already paid
        $query = $this->db->query("
          SELECT SUM(payment_amount) AS invoice_provider_paid
          FROM ip_payments_provider
          WHERE invoice_id = " . $this->db->escape($invoice_id)
        );

        $invoice_paid = $query->row()->invoice_provider_paid ? floatval($query->row()->invoice_provider_paid) : 0;

        // Create the database array and insert or update
        $db_array = array(
            'invoice_provider_id' => $invoice_id,
            'invoice_provider_item_subtotal' => $invoice_item_subtotal,
            'invoice_provider_item_tax_total' => $invoice_amounts->invoice_item_tax_total,
            'invoice_provider_total' => $invoice_total,
            'invoice_provider_paid' => $invoice_paid,
            'invoice_provider_balance' => $invoice_total - $invoice_paid
        );

        $this->db->where('invoice_provider_id', $invoice_id);

        if ($this->db->get('ip_invoice_provider_amounts')->num_rows()) {
            // The record already exists; update it
            $this->db->where('invoice_provider_id', $invoice_id);
            $this->db->update('ip_invoice_provider_amounts', $db_array);
        } else {
            // The record does not yet exist; insert it
            $this->db->insert('ip_invoice_provider_amounts', $db_array);
        }

        // Calculate the invoice taxes
        $this->calculate_invoice_taxes($invoice_id);

        // Get invoice status
        $this->load->model('invoices_provider/mdl_invoices_provider');
        $invoice = $this->mdl_invoices_provider->get_by_id($invoice_id);
        $invoice_is_credit = ($invoice->creditinvoice_parent_id > 0 ? true : false);

        // Set to paid if balance is zero
        if ($invoice->invoice_balance == 0) {
            // Check if the invoice total is not zero or negative
            if ($invoice->invoice_total != 0 || $invoice_is_credit) {

                $this->db->where('invoice_id', $invoice_id);
                $payment = $this->db->get('ip_payments_provider')->row();
                $payment_method_id = ($payment->payment_method_id ? $payment->payment_method_id : 0);

                $this->db->where('invoice_provider_id', $invoice_id);
                $this->db->set('invoice_provider_status_id', 3);
                $this->db->set('payment_method', $payment_method_id);
                $this->db->update('ip_invoices_provider');

                // Set to read-only if applicable
                if ( $this->config->item('disable_read_only') == false && $invoice->invoice_provider_status_id == get_setting('read_only_toggle_provider')) {
                    $this->db->where('invoice_provider_id', $invoice_id);
                    $this->db->set('is_read_only', 1);
                    $this->db->update('ip_invoices_provider');
                }
            }
        }
    }

    /**
     * @param $invoice_id
     * @param $invoice_total
     * @return float
     */
    public function calculate_discount($invoice_id, $invoice_total)
    {
        $this->db->where('invoice_provider_id', $invoice_id);
        $invoice_data = $this->db->get('ip_invoices_provider')->row();
        $total = (float)number_format($invoice_total, 2, '.', '');
        $discount_amount = (float)number_format($invoice_data->invoice_provider_discount_amount, 2, '.', '');
        $discount_percent = (float)number_format($invoice_data->invoice_provider_discount_percent, 2, '.', '');

        $total = $total - $discount_amount;
        $total = $total - round(($total / 100 * $discount_percent), 2);

        return $total;
    }

    /**
     * @param $invoice_id
     */
    public function calculate_invoice_taxes($invoice_id)
    {
        // First check to see if there are any invoice taxes applied
        $this->load->model('invoices_provider/mdl_invoice_provider_tax_rates');
        $invoice_tax_rates = $this->mdl_invoice_provider_tax_rates->where('invoice_provider_id', $invoice_id)->get()->result();

        if ($invoice_tax_rates) {
            // There are invoice taxes applied
            // Get the current invoice amount record
            $invoice_amount = $this->db->where('invoice_provider_id', $invoice_id)->get('ip_invoice_provider_amounts')->row();

            // Loop through the invoice taxes and update the amount for each of the applied invoice taxes
            foreach ($invoice_tax_rates as $invoice_tax_rate) {
                if ($invoice_tax_rate->include_item_tax) {
                    // The invoice tax rate should include the applied item tax
                    $invoice_tax_rate_amount = ($invoice_amount->invoice_provider_item_subtotal + $invoice_amount->invoice_provider_item_tax_total) * ($invoice_tax_rate->invoice_provider_tax_rate_percent / 100);
                } else {
                    // The invoice tax rate should not include the applied item tax
                    $invoice_tax_rate_amount = $invoice_amount->invoice_provider_item_subtotal * ($invoice_tax_rate->invoice_provider_tax_rate_percent / 100);
                }

                // Update the invoice tax rate record
                $db_array = array(
                    'invoice_provider_tax_rate_amount' => $invoice_tax_rate_amount
                );
                $this->db->where('invoice_provider_tax_rate_id', $invoice_tax_rate->invoice_provider_tax_rate_id);
                $this->db->update('ip_invoice_provider_tax_rates', $db_array);
            }

            // Update the invoice amount record with the total invoice tax amount
            $this->db->query("
              UPDATE ip_invoice_provider_amounts
              SET invoice_provider_tax_total = (
                SELECT SUM(invoice_provider_tax_rate_amount)
                FROM ip_invoice_provider_tax_rates
                WHERE invoice_provider_id = " . $this->db->escape($invoice_id) . ")
              WHERE invoice_provider_id = " . $this->db->escape($invoice_id));

            // Get the updated invoice amount record
            $invoice_amount = $this->db->where('invoice_provider_id', $invoice_id)->get('ip_invoice_provider_amounts')->row();

            // Recalculate the invoice total and balance
            $invoice_total = $invoice_amount->invoice_provider_item_subtotal + $invoice_amount->invoice_provider_item_tax_total + $invoice_amount->invoice_provider_tax_total;
            $invoice_total = $this->calculate_discount($invoice_id, $invoice_total);
            $invoice_balance = $invoice_total - $invoice_amount->invoice_provider_paid;

            // Update the invoice amount record
            $db_array = array(
                'invoice_provider_total' => $invoice_total,
                'invoice_provider_balance' => $invoice_balance
            );

            $this->db->where('invoice_provider_id', $invoice_id);
            $this->db->update('ip_invoice_provider_amounts', $db_array);
        } else {
            // No invoice taxes applied

            $db_array = array(
                'invoice_provider_tax_total' => '0.00'
            );

            $this->db->where('invoice_provider_id', $invoice_id);
            $this->db->update('ip_invoice_provider_amounts', $db_array);
        }
    }

    /**
     * @param null $period
     * @return mixed
     */
    public function get_total_invoiced($period = null)
    {
        switch ($period) {
            case 'month':
                return $this->db->query("
					SELECT SUM(invoice_provider_total) AS total_invoiced
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider
					WHERE MONTH(invoice_provider_date_created) = MONTH(NOW())
					AND YEAR(invoice_provider_date_created) = YEAR(NOW()))")->row()->total_invoiced;
            case 'last_month':
                return $this->db->query("
					SELECT SUM(invoice_provider_total) AS total_invoiced
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider
					WHERE MONTH(invoice_provider_date_created) = MONTH(NOW() - INTERVAL 1 MONTH)
					AND YEAR(invoice_provider_date_created) = YEAR(NOW() - INTERVAL 1 MONTH))")->row()->total_invoiced;
            case 'year':
                return $this->db->query("
					SELECT SUM(invoice_provider_total) AS total_invoiced
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider WHERE YEAR(invoice_provider_date_created) = YEAR(NOW()))")->row()->total_invoiced;
            case 'last_year':
                return $this->db->query("
					SELECT SUM(invoice_provider_total) AS total_invoiced
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider WHERE YEAR(invoice_provider_date_created) = YEAR(NOW() - INTERVAL 1 YEAR))")->row()->total_invoiced;
            default:
                return $this->db->query("SELECT SUM(invoice_provider_total) AS total_invoiced FROM ip_invoice_provider_amounts")->row()->total_invoiced;
        }
    }

    /**
     * @param null $period
     * @return mixed
     */
    public function get_total_paid($period = null)
    {
        switch ($period) {
            case 'month':
                return $this->db->query("
					SELECT SUM(invoice_provider_paid) AS total_paid
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider
					WHERE MONTH(invoice_provider_date_created) = MONTH(NOW())
					AND YEAR(invoice_provider_date_created) = YEAR(NOW()))")->row()->total_paid;
            case 'last_month':
                return $this->db->query("SELECT SUM(invoice_provider_paid) AS total_paid
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider
					WHERE MONTH(invoice_provider_date_created) = MONTH(NOW() - INTERVAL 1 MONTH)
					AND YEAR(invoice_provider_date_created) = YEAR(NOW() - INTERVAL 1 MONTH))")->row()->total_paid;
            case 'year':
                return $this->db->query("SELECT SUM(invoice_provider_paid) AS total_paid
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider WHERE YEAR(invoice_provider_date_created) = YEAR(NOW()))")->row()->total_paid;
            case 'last_year':
                return $this->db->query("SELECT SUM(invoice_provider_paid) AS total_paid
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider WHERE YEAR(invoice_provider_date_created) = YEAR(NOW() - INTERVAL 1 YEAR))")->row()->total_paid;
            default:
                return $this->db->query("SELECT SUM(invoice_provider_paid) AS total_paid FROM ip_invoice_provider_amounts")->row()->total_paid;
        }
    }

    /**
     * @param null $period
     * @return mixed
     */
    public function get_total_balance($period = null)
    {
        switch ($period) {
            case 'month':
                return $this->db->query("SELECT SUM(invoice_provider_balance) AS total_balance
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider
					WHERE MONTH(invoice_provider_date_created) = MONTH(NOW())
					AND YEAR(invoice_provider_date_created) = YEAR(NOW()))")->row()->total_balance;
            case 'last_month':
                return $this->db->query("SELECT SUM(invoice_provider_balance) AS total_balance
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider
					WHERE MONTH(invoice_provider_date_created) = MONTH(NOW() - INTERVAL 1 MONTH)
					AND YEAR(invoice_provider_date_created) = YEAR(NOW() - INTERVAL 1 MONTH))")->row()->total_balance;
            case 'year':
                return $this->db->query("SELECT SUM(invoice_provider_balance) AS total_balance
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider WHERE YEAR(invoice_provider_date_created) = YEAR(NOW()))")->row()->total_balance;
            case 'last_year':
                return $this->db->query("SELECT SUM(invoice_provider_balance) AS total_balance
					FROM ip_invoice_provider_amounts
					WHERE invoice_provider_id IN
					(SELECT invoice_provider_id FROM ip_invoices_provider WHERE YEAR(invoice_provider_date_created) = (YEAR(NOW() - INTERVAL 1 YEAR)))")->row()->total_balance;
            default:
                return $this->db->query("SELECT SUM(invoice_provider_balance) AS total_balance FROM ip_invoice_provider_amounts")->row()->total_balance;
        }
    }

    /**
     * @param string $period
     * @return array
     */
    public function get_status_totals($period = '')
    {
        switch ($period) {
            default:
            case 'this-month':
                $results = $this->db->query("
					SELECT ip_invoices_provider.invoice_provider_status_id, (CASE ip_invoices_provider.invoice_provider_status_id WHEN 3 THEN SUM(ip_invoice_provider_amounts.invoice_provider_paid) ELSE SUM(ip_invoice_provider_amounts.invoice_provider_balance) END) AS sum_total, COUNT(*) AS num_total
					FROM ip_invoice_provider_amounts
					JOIN ip_invoices_provider ON ip_invoices_provider.invoice_provider_id = ip_invoice_provider_amounts.invoice_provider_id
                        AND MONTH(ip_invoices_provider.invoice_provider_date_created) = MONTH(NOW())
                        AND YEAR(ip_invoices_provider.invoice_provider_date_created) = YEAR(NOW())
					GROUP BY ip_invoices_provider.invoice_provider_status_id")->result_array();
                break;
            case 'last-month':
                $results = $this->db->query("
					SELECT invoice_provider_status_id, (CASE ip_invoices_provider.invoice_provider_status_id WHEN 3 THEN SUM(invoice_paid) ELSE SUM(invoice_provider_balance) END) AS sum_total, COUNT(*) AS num_total
					FROM ip_invoice_provider_amounts
					JOIN ip_invoices_provider ON ip_invoices_provider.invoice_provider_id = ip_invoice_provider_amounts.invoice_provider_id
                        AND MONTH(ip_invoices_provider.invoice_date_created) = MONTH(NOW() - INTERVAL 1 MONTH)
                        AND YEAR(ip_invoices_provider.invoice_provider_date_created) = YEAR(NOW())
					GROUP BY ip_invoices_provider.invoice_provider_status_id")->result_array();
                break;
            case 'this-quarter':
                $results = $this->db->query("
					SELECT invoice_provider_status_id, (CASE ip_invoices_provider.invoice_provider_status_id WHEN 3 THEN SUM(ip_invoice_provider_amounts.invoice_provider_paid) ELSE SUM(ip_invoice_provider_amounts.invoice_provider_balance) END) AS sum_total, COUNT(*) AS num_total
					FROM ip_invoice_provider_amounts
					JOIN ip_invoices_provider ON ip_invoices_provider.invoice_provider_id = ip_invoice_provider_amounts.invoice_id
                        AND QUARTER(ip_invoices_provider.invoice_provider_date_created) = QUARTER(NOW())
                        AND YEAR(ip_invoices_provider.invoice_provider_date_created) = YEAR(NOW())
					GROUP BY ip_invoices_provider.invoice_provider_status_id")->result_array();
                break;
            case 'last-quarter':
                $results = $this->db->query("
					SELECT invoice_provider_status_id, (CASE ip_invoices_provider.invoice_provider_status_id WHEN 3 THEN SUM(invoice_provider_paid) ELSE SUM(invoice_provider_balance) END) AS sum_total, COUNT(*) AS num_total
					FROM ip_invoice_provider_amounts
					JOIN ip_invoices_provider ON ip_invoices_provider.invoice_provider_id = ip_invoice_provider_amounts.invoice_provider_id
                        AND QUARTER(ip_invoices_provider.invoice_provider_date_created) = QUARTER(NOW() - INTERVAL 1 QUARTER)
                        AND YEAR(ip_invoices_provider.invoice_provider_date_created) = YEAR(NOW())
					GROUP BY ip_invoices_provider.invoice_provider_status_id")->result_array();
                break;
            case 'this-year':
                $results = $this->db->query("
					SELECT invoice_provider_status_id, (CASE ip_invoices_provider.invoice_provider_status_id WHEN 3 THEN SUM(ip_invoice_provider_amounts.invoice_provider_paid) ELSE SUM(ip_invoice_provider_amounts.invoice_provider_balance) END) AS sum_total, COUNT(*) AS num_total
					FROM ip_invoice_provider_amounts
					JOIN ip_invoices_provider ON ip_invoices_provider.invoice_provider_id = ip_invoice_provider_amounts.invoice_provider_id
                        AND YEAR(ip_invoices_provider.invoice_provider_date_created) = YEAR(NOW())
					GROUP BY ip_invoices_provider.invoice_provider_status_id")->result_array();
                break;
            case 'last-year':
                $results = $this->db->query("
					SELECT invoice_provider_status_id, (CASE ip_invoices_provider.invoice_provider_status_id WHEN 3 THEN SUM(invoice_provider_paid) ELSE SUM(invoice_provider_balance) END) AS sum_total, COUNT(*) AS num_total
					FROM ip_invoice_provider_amounts
					JOIN ip_invoices_provider ON ip_invoices_provider.invoice_provider_id = ip_invoice_provider_amounts.invoice_provider_id
                        AND YEAR(ip_invoices_provider.invoice_provider_date_created) = YEAR(NOW() - INTERVAL 1 YEAR)
					GROUP BY ip_invoices_provider.invoice_provider_status_id")->result_array();
                break;
        }

        $return = array();

        foreach ($this->mdl_invoices_provider->statuses() as $key => $status) {
            $return[$key] = array(
                'invoice_provider_status_id' => $key,
                'class' => $status['class'],
                'label' => $status['label'],
                'href' => $status['href'],
                'sum_total' => 0,
                'num_total' => 0
            );
        }

        foreach ($results as $result) {
            $return[$result['invoice_provider_status_id']] = array_merge($return[$result['invoice_provider_status_id']], $result);
        }

        return $return;
    }

}

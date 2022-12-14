<script>
    $(function () {
        // Display the create quote modal
        $('#modal_copy_invoice').modal('show');

        // Select2 for all select inputs
        $(".simple-select").select2();

        <?php $this->layout->load_view('providers/script_select2_provider_id.js'); ?>

        // Creates the invoice
        $('#copy_invoice_confirm').click(function () {
            $.post("<?php echo site_url('invoices_provider/ajax/copy_invoice'); ?>", {
                    invoice_provider_id: <?php echo $invoice_id; ?>,
                    provider_id: $('#provider_id').val(),
                    invoice_provider_date_created: $('#invoice_provider_date_created_modal').val(),
                    invoice_group_id: $('#id_grupo_facturas_proveedor').val(),
                    invoice_provider_password: $('#invoice_provider_password').val(),
                    invoice_provider_time_created: '<?php echo date('H:i:s') ?>',
                    user_id: $('#user_id').val(),
                    payment_method: $('#payment_method').val()
                },
                function (data) {
                    <?php echo(IP_DEBUG ? 'console.log(data);' : ''); ?>
                    var response = JSON.parse(data);
                    if (response.success === 1) {
                    window.location = "<?php echo site_url('invoices_provider/view'); ?>/" + response.invoice_id;                 }
                    else {
                        // The validation was not successful
                        $('.control-group').removeClass('has-error');
                        for (var key in response.validation_errors) {
                            $('#' + key).parent().parent().addClass('has-error');
                        }
                    }
                });
        });
    });

</script>
<div id="modal_copy_invoice" class="modal modal-lg" role="dialog" aria-labelledby="modal_copy_invoice"
     aria-hidden="true">
    <form class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><i class="fa fa-close"></i></button>
            <h4 class="panel-title"><?php _trans('copy_invoice'); ?></h4>
        </div>
        <div class="modal-body">

            <input type="hidden" name="user_id" id="user_id" class="form-control"
                   value="<?php echo $invoice->user_id; ?>">
            <input type="hidden" name="payment_method" id="payment_method" class="form-control"
            value="<?php echo $invoice->payment_method; ?>">
            <div class="form-group">
                <label for="provider_id"><?php _trans('provider'); ?></label>
                <select name="provider_id" id="provider_id" class="form-control" autofocus="autofocus">
                    <option value="<?php echo $invoice->provider_id; ?>">
                        <?php echo $invoice->provider_name; ?>
                    </option>
                </select>
            </div>

            <div class="form-group has-feedback">
                <label for="invoice_provider_date_created_modal"><?php _trans('invoice_date'); ?>: </label>

                <div class="input-group">
                    <input name="invoice_provider_date_created_modal" id="invoice_provider_date_created_modal" class="form-control datepicker"
                           value="<?php echo date_from_mysql(date('Y-m-d', time()), true) ?>">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar fa-fw"></i>
                    </span>
                </div>
            </div>

            <input class="hidden" id="id_grupo_facturas_proveedor"
                   value="2">

        </div>

        <div class="modal-footer">
            <div class="btn-group">
                <button class="btn btn-success" id="copy_invoice_confirm" type="button">
                    <i class="fa fa-check"></i> <?php _trans('submit'); ?>
                </button>
                <button class="btn btn-danger" type="button" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php _trans('cancel'); ?>
                </button>
            </div>
        </div>

    </form>
</div>

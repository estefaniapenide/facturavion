<script>
    $(function () {
        $('#modal-create-credit-invoice').modal('show');
        $('#create-credit-confirm').click(function () {
            $.post("<?php echo site_url('invoices_provider/ajax/create_credit'); ?>", {
                    invoice_provider_id: <?php echo $invoice_id; ?>,
                    provider_id: $('#provider_id').val(),
                    invoice_provider_date_created: $('#invoice_provider_date_created').val(),
                    invoice_group_id: $('#invoice_group_id').val(),
                    invoice_provider_time_created: '<?php echo date('H:i:s') ?>',
                    invoice_provider_password: $('#invoice_provider_password').val(),
                    user_id: $('#user_id').val()
                },
                function (data) {
                    <?php echo(IP_DEBUG ? 'console.log(data);' : ''); ?>
                    var response = JSON.parse(data);
                    if (response.success === 1) {
                        window.location = "<?php echo site_url('invoices_provider/view'); ?>/" + response.invoice_provider_id;
                    }
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

<div id="modal-create-credit-invoice" class="modal modal-lg" role="dialog" aria-labelledby="modal-create-credit-invoice"
     aria-hidden="true">
    <form class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><i class="fa fa-close"></i></button>
            <h4 class="panel-title"><?php _trans('create_credit_invoice'); ?></h4>
        </div>
        <div class="modal-body">

            <input type="hidden" name="user_id" id="user_id" class="form-control"
                   value="<?php echo $invoice->user_id; ?>">

            <input type="hidden" name="parent_id" id="parent_id"
                   value="<?php echo $invoice->invoice_provider_id; ?>">

            <input type="hidden" name="provider_id" id="provider_id" class="hidden"
                   value="<?php echo $invoice->provider_id; ?>">

            <input type="hidden" name="invoice_provider_date_created" id="invoice_provider_date_created"
                   value="<?php $credit_date = date_from_mysql(date('Y-m-d', time()), true);
                   echo $credit_date; ?>">

            <div class="form-group">
                <label for="invoice_provider_password"><?php _trans('invoice_password'); ?></label>
                <input type="text" name="invoice_provider_password" id="invoice_provider_password" class="form-control"
                       value="<?php echo get_setting('invoice_provider_pre_password') == '' ? '' : get_setting('invoice_provider_pre_password'); ?>"
                       style="margin: 0 auto;" autocomplete="off">
            </div>
            <div>
                <select name="invoice_group_id" id="invoice_group_id" class="hidden">
                    <?php foreach ($invoice_groups as $invoice_group) { ?>
                        <option value="2">
                            <?php echo "Invoice Provider"; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <p><strong><?php _trans('credit_invoice_details'); ?></strong></p>

            <ul>
                <li><?php _trans('provider') . ': ' . htmlsc($invoice->provider_name); ?></li>
                <li><?php echo trans('credit_invoice_date') . ': ' . $credit_date; ?></li>
            </ul>

            <div class="alert alert-danger no-margin">
                <?php _trans('create_credit_invoice_alert'); ?>
            </div>

        </div>

        <div class="modal-footer">
            <div class="btn-group">
                <button class="btn btn-success" id="create-credit-confirm" type="button">
                    <i class="fa fa-check"></i> <?php _trans('confirm'); ?>
                </button>
                <button class="btn btn-danger" type="button" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php _trans('cancel'); ?>
                </button>
            </div>
        </div>

    </form>

</div>

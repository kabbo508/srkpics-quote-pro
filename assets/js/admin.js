
(function($){
    function loadLogs(){
        var container = $('#srkqp-live-log-list');
        if (!container.length) return;

        $.post(SRKQP_ADMIN.ajax_url, {
            action: 'srkqp_get_logs',
            nonce: SRKQP_ADMIN.nonce
        }, function(res){
            if (res.success) {
                container.html(res.data.html);
            }
        });
    }

    $(document).on('change', '.srkqp-status-select', function(){
        var select = $(this);
        select.prop('disabled', true);

        $.post(SRKQP_ADMIN.ajax_url, {
            action: 'srkqp_update_status',
            nonce: SRKQP_ADMIN.nonce,
            id: select.data('id'),
            status: select.val()
        }, function(res){
            if (!res.success) {
                alert(res.data && res.data.message ? res.data.message : 'Status update failed.');
            }
            loadLogs();
        }).always(function(){
            select.prop('disabled', false);
        });
    });


    $(document).on('click', '.srkqp-delete-btn', function(e){
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this quote request?')) {
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.post(SRKQP_ADMIN.ajax_url, {
            action: 'srkqp_delete_quote',
            nonce: SRKQP_ADMIN.nonce,
            id: btn.data('id')
        }, function(res){
            if (res.success) {
                btn.closest('tr').fadeOut(250, function(){
                    $(this).remove();
                });
                loadLogs();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
                btn.prop('disabled', false);
            }
        }).fail(function(xhr){
            var msg = 'Delete failed.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            alert(msg);
            btn.prop('disabled', false);
        });
    });

    loadLogs();
    setInterval(loadLogs, 15000);
})(jQuery);

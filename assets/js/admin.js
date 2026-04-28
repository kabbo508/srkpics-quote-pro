
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

    loadLogs();
    setInterval(loadLogs, 15000);
})(jQuery);


(function($){
    function moveLoopButtonsIntoThemeCards() {
        $('.srkqp-loop-btn').each(function(){
            var btn = $(this);
            var li = btn.closest('li.product, .product');
            if (!li.length) return;

            var target = li.find('.product-item__description').first();
            if (!target.length) {
                target = li.find('.woocommerce-loop-product__title').parent().first();
            }
            if (!target.length) {
                target = li;
            }

            if (!$.contains(target[0], btn[0])) {
                btn.appendTo(target);
            }
        });
    }

    function addSingleFallbackButton() {
        // Single product quote button is shortcode-only for Elementor templates.
        return;
    }

    function openPopup(btn) {
        var $btn = $(btn);
        var popup = $('#srkqp-popup');

        $('#srkqp-product-id').val($btn.data('product-id'));
        $('#srkqp-product-name-field').val($btn.data('product-name'));
        $('#srkqp-product-image-field').val($btn.data('product-image'));
        $('#srkqp-product-url-field').val($btn.data('product-url'));

        $('#srkqp-product-name').text($btn.data('product-name'));
        $('#srkqp-product-image').attr('src', $btn.data('product-image'));
        $('#srkqp-product-link').attr('href', $btn.data('product-url'));

        var form = $('#srkqp-form');
        form[0].reset();

        $('#srkqp-product-id').val($btn.data('product-id'));
        $('#srkqp-product-name-field').val($btn.data('product-name'));
        $('#srkqp-product-image-field').val($btn.data('product-image'));
        $('#srkqp-product-url-field').val($btn.data('product-url'));

        form.find('.srkqp-response').removeClass('success error').html('');
        popup.addClass('srkqp-active').attr('aria-hidden', 'false');
    }

    function closePopup() {
        $('#srkqp-popup').removeClass('srkqp-active').attr('aria-hidden', 'true');
    }

    $(document).ready(function(){
        moveLoopButtonsIntoThemeCards();
        addSingleFallbackButton();

        setTimeout(function(){ moveLoopButtonsIntoThemeCards(); addSingleFallbackButton(); }, 300);
        setTimeout(function(){ moveLoopButtonsIntoThemeCards(); addSingleFallbackButton(); }, 1000);
        setTimeout(function(){ moveLoopButtonsIntoThemeCards(); addSingleFallbackButton(); }, 2000);
    });

    $(document).on('ajaxComplete', function(){
        moveLoopButtonsIntoThemeCards();
        addSingleFallbackButton();
    });

    $(document).on('click', '.srkqp-quote-btn', function(e){
        e.preventDefault();
        openPopup(this);
    });

    $(document).on('click', '[data-srkqp-close]', function(e){
        e.preventDefault();
        closePopup();
    });

    $(document).on('keydown', function(e){
        if (e.key === 'Escape') closePopup();
    });

    $(document).on('submit', '#srkqp-form', function(e){
        e.preventDefault();

        var form = $(this);
        var response = form.find('.srkqp-response');
        var submit = form.find('.srkqp-submit');

        response.removeClass('success error').text('Submitting...');
        submit.prop('disabled', true);

        $.ajax({
            url: SRKQP.ajax_url,
            method: 'POST',
            data: form.serialize(),
            success: function(res){
                if (res.success) {
                    response.addClass('success').text(res.data.message);
                    form[0].reset();
                } else {
                    response.addClass('error').text(res.data && res.data.message ? res.data.message : 'Something went wrong.');
                }
            },
            error: function(){
                response.addClass('error').text('Server error. Please try again.');
            },
            complete: function(){
                submit.prop('disabled', false);
            }
        });
    });
})(jQuery);

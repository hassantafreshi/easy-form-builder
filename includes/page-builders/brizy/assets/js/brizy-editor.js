/**
 * Easy Form Builder - Brizy Editor Script
 *
 * @package EasyFormBuilder
 * @since 4.0.0
 */

(function($) {
    'use strict';

    // Initialize when Brizy editor is ready
    $(document).on('brizy.editor.ready', function() {
        console.log('EFB: Brizy editor ready');
    });

    // Refresh forms list when needed
    window.efbBrizyRefreshForms = function() {
        if (typeof efbBrizyData === 'undefined') {
            return;
        }

        $.ajax({
            url: efbBrizyData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'efb_brizy_get_forms',
                nonce: efbBrizyData.nonce
            },
            success: function(response) {
                if (response.success && response.data.forms) {
                    efbBrizyData.forms = response.data.forms;
                }
            }
        });
    };

})(jQuery);

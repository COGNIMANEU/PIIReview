( function ( mw, $ ) {
    'use strict';

    mw.PIIReview = {
        init: function () {
            this.initImageZoom();
            this.initPIIDetection();
            this.initProcessButton();

            // Update any dynamic elements after init
            this.updateUI();
        },

        initImageZoom: function() {
            // Image zoom functionality
            $(document).on('click', '.piireview-image', function() {
                $(this).toggleClass('piireview-zoomed');
                if ($(this).hasClass('piireview-zoomed')) {
                    $(this).css('cursor', 'zoom-out');
                } else {
                    $(this).css('cursor', 'zoom-in');
                }
            });

            // Zoom controls
            $(document).on('click', '.piireview-zoom-in', function(e) {
                e.stopPropagation();
                var img = $(this).closest('.piireview-image-container').find('.piireview-image');
                var scale = (img.data('scale') || 1) * 1.2;
                img.css('transform', 'scale(' + scale + ')');
                img.data('scale', scale);
            });

            $(document).on('click', '.piireview-zoom-out', function(e) {
                e.stopPropagation();
                var img = $(this).closest('.piireview-image-container').find('.piireview-image');
                var scale = (img.data('scale') || 1) / 1.2;
                img.css('transform', 'scale(' + scale + ')');
                img.data('scale', scale);
            });

            $(document).on('click', '.piireview-zoom-reset', function(e) {
                e.stopPropagation();
                var img = $(this).closest('.piireview-image-container').find('.piireview-image');
                img.css('transform', 'scale(1)');
                img.data('scale', 1);
            });
        },

        initPIIDetection: function() {
            // Simulate PII detection for each image
            $('.piireview-card').each(function() {
                var card = $(this);
                var statusIndicator = card.find('.piireview-status-indicator');

                // Simulate async PII detection process
                setTimeout(function() {
                    // This would be replaced with actual PyTorch integration
                    var hasPII = Math.random() > 0.5; // Simulate random detection

                    if (hasPII) {
                        statusIndicator.removeClass('piireview-status-scanning')
                            .addClass('piireview-status-detected')
                            .text(mw.msg('piireview-pii-detected'));

                        // Highlight the process button for PII detected
                        card.find('.piireview-button-process').addClass('piireview-button-highlight');
                    } else {
                        statusIndicator.removeClass('piireview-status-scanning')
                            .addClass('piireview-status-clear')
                            .text(mw.msg('piireview-no-pii'));
                    }
                }, 1500 + Math.random() * 1000); // Random delay for simulation
            });
        },

        initProcessButton: function() {
            $(document).on('click', '.piireview-button-process', function() {
                var fileId = $(this).data('file-id');
                var card = $('#card-' + fileId);

                // Show processing indicator
                card.addClass('piireview-processing');
                card.find('.piireview-status-indicator')
                    .removeClass('piireview-status-detected piireview-status-clear')
                    .addClass('piireview-status-processing')
                    .text(mw.msg('piireview-processing'));

                // This would be replaced with actual AJAX call to PyTorch component
                setTimeout(function() {
                    // Simulate processing completion
                    card.removeClass('piireview-processing');
                    card.find('.piireview-status-indicator')
                        .removeClass('piireview-status-processing')
                        .addClass('piireview-status-clear')
                        .text(mw.msg('piireview-pii-removed'));

                    // Update button state
                    card.find('.piireview-button-process')
                        .removeClass('piireview-button-highlight')
                        .prop('disabled', true)
                        .text(mw.msg('piireview-processed'));
                }, 2000); // Simulate processing time
            });
        },

        updateUI: function() {
            // Update batch progress
            var total = $('.piireview-card').length;
            var processed = $('.piireview-status-clear').length;

            if (total > 0) {
                var progressPercent = Math.round((processed / total) * 100);
                $('.piireview-progress-bar').css('width', progressPercent + '%');
                $('.piireview-progress-text').text(processed + ' / ' + total);
            }
        }
    };

    $( function () {
        mw.PIIReview.init();
    } );

}( mediaWiki, jQuery ) );

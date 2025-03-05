( function ( mw, $ ) {
    'use strict';

    mw.PIIReview = {
        init: function () {
            this.initImageZoom();
            this.initPIIDetection();
            this.initProcessButton();
            this.initBatchControls();
            this.initKeyboardNavigation();

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

        initBatchControls: function() {
            // Process all PII-detected images
            $('.piireview-batch-process').on('click', function() {
                $('.piireview-status-detected').each(function() {
                    var card = $(this).closest('.piireview-card');
                    card.find('.piireview-button-process').trigger('click');
                });
            });

            // Approve all clear images
            $('.piireview-batch-approve').on('click', function() {
                $('.piireview-status-clear').each(function() {
                    var card = $(this).closest('.piireview-card');
                    card.find('.piireview-button-approve').trigger('click');
                });
            });

            // Initialize search
            $('.piireview-search-input').on('input', function() {
                var query = $(this).val().toLowerCase();

                $('.piireview-card, .piireview-directory-card').each(function() {
                    var item = $(this);
                    var name = item.find('h3, .piireview-directory-name').text().toLowerCase();

                    if (name.indexOf(query) > -1) {
                        item.show();
                    } else {
                        item.hide();
                    }
                });
            });

            // Initialize sorting
            $('.piireview-sort-select').on('change', function() {
                var sortBy = $(this).val();
                var container = $('.piireview-content');

                // First, sort directories
                var dirs = container.find('.piireview-directory-card').get();
                dirs.sort(function(a, b) {
                    var aName = $(a).find('.piireview-directory-name').text();
                    var bName = $(b).find('.piireview-directory-name').text();
                    return aName.localeCompare(bName);
                });

                // Then sort files
                var files = container.find('.piireview-card').get();
                files.sort(function(a, b) {
                    var aVal, bVal;

                    if (sortBy === 'name') {
                        aVal = $(a).find('h3').text();
                        bVal = $(b).find('h3').text();
                        return aVal.localeCompare(bVal);
                    } else if (sortBy === 'date') {
                        aVal = $(a).find('.piireview-date').text();
                        bVal = $(b).find('.piireview-date').text();
                        return new Date(aVal) - new Date(bVal);
                    } else if (sortBy === 'size') {
                        aVal = $(a).find('.piireview-filesize').text();
                        bVal = $(b).find('.piireview-filesize').text();
                        return parseFloat(aVal) - parseFloat(bVal);
                    }

                    return 0;
                });

                // Reattach the sorted items, directories first
                $.each(dirs, function(i, dir) {
                    container.append(dir);
                });

                $.each(files, function(i, file) {
                    container.append(file);
                });
            });

            // Initialize filtering
            $('.piireview-filter-select').on('change', function() {
                var filter = $(this).val();

                // Always show directories
                $('.piireview-directory-card').show();

                if (filter === 'all') {
                    $('.piireview-card').show();
                } else if (filter === 'pii') {
                    $('.piireview-card').hide();
                    $('.piireview-status-detected').closest('.piireview-card').show();
                } else if (filter === 'clear') {
                    $('.piireview-card').hide();
                    $('.piireview-status-clear').closest('.piireview-card').show();
                }
            });
        },

        initKeyboardNavigation: function() {
            // Enable keyboard navigation between items
            $(document).on('keydown', function(e) {
                // Only apply if we're on the PIIReview page
                if (!$('.piireview-container').length) {
                    return;
                }

                var items = $('.piireview-card:visible, .piireview-directory-card:visible');
                if (!items.length) {
                    return;
                }

                // Find the current focused item
                var focused = items.filter('.piireview-focused');
                var index = focused.length ? items.index(focused) : -1;

                switch (e.keyCode) {
                    case 37: // Left arrow
                        if (index > 0) {
                            items.removeClass('piireview-focused');
                            items.eq(index - 1).addClass('piireview-focused')[0].scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                        break;

                    case 39: // Right arrow
                        if (index < items.length - 1) {
                            items.removeClass('piireview-focused');
                            items.eq(index + 1).addClass('piireview-focused')[0].scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                        break;

                    case 38: // Up arrow
                        // Calculate current row and move up if possible
                        var perRow = Math.floor($('.piireview-content').width() / 320); // Estimate items per row
                        if (perRow <= 0) perRow = 1;

                        if (index >= perRow) {
                            items.removeClass('piireview-focused');
                            items.eq(index - perRow).addClass('piireview-focused')[0].scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                        break;

                    case 40: // Down arrow
                        // Calculate current row and move down if possible
                        var perRow = Math.floor($('.piireview-content').width() / 320); // Estimate items per row
                        if (perRow <= 0) perRow = 1;

                        if (index + perRow < items.length) {
                            items.removeClass('piireview-focused');
                            items.eq(index + perRow).addClass('piireview-focused')[0].scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                        break;

                    case 13: // Enter
                        if (focused.hasClass('piireview-directory-card')) {
                            // Navigate to directory
                            window.location = focused.find('a').attr('href');
                        } else if (focused.hasClass('piireview-card')) {
                            // Focus on the image for better viewing
                            focused.find('.piireview-image').click();
                        }
                        break;
                }
            });

            // Set focus on first item when page loads
            var firstItem = $('.piireview-card:visible, .piireview-directory-card:visible').first();
            if (firstItem.length) {
                firstItem.addClass('piireview-focused');
            }
        },

        updateUI: function() {
            // Update batch progress (only count files, not directories)
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

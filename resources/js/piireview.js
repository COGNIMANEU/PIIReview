( function ( mw, $ ) {
    'use strict';

    mw.PIIReview = {
        init: function () {
            this.initImageZoom();
            this.initPIIDetection();
            this.initProcessButton();
            this.initBatchControls();
            this.initKeyboardNavigation();
            this.initRecursiveSearch();
            this.initImageModal();

            // Update any dynamic elements after init
            this.updateUI();
        },

        initImageModal: function() {

            // Open modal when clicking on image or card
            $(document).on('click', '.piireview-image-container, .piireview-card-header', function() {
                var card = $(this).closest('.piireview-card');
                var fileId = card.attr('id').replace('card-', '');

                // Get image data
                var imgElement = card.find('.piireview-image');
                var imgSrc = imgElement.attr('src');
                var imgFullsize = imgElement.data('fullsize') || imgSrc;

                // Get metadata
                var fileName = card.find('h3').text();
                var metadata = card.find('.piireview-metadata').html();
                var piiStatus = card.find('.piireview-pii-status').html();
                var notes = card.find('textarea').val();

                // Populate modal
                $('#piireview-modal .piireview-modal-title').text(fileName);
                $('#piireview-modal .piireview-modal-metadata').html(metadata);
                $('#piireview-modal .piireview-modal-image').attr('src', imgFullsize).data('scale', 1).css('transform', 'scale(1)');
                $('#piireview-modal .piireview-modal-pii-status').html(piiStatus);
                $('#piireview-modal .piireview-modal-notes textarea').val(notes);

                // Store original card ID for action buttons
                $('#piireview-modal').data('file-id', fileId);

                // Check if process button is disabled
                var processDisabled = card.find('.piireview-button-process').prop('disabled');
                $('#piireview-modal .piireview-modal-process').prop('disabled', processDisabled);

                // Show modal
                $('#piireview-modal').addClass('piireview-modal-show');

                // Prevent scrolling on body
                $('body').addClass('piireview-modal-open');
            });

            // Close modal when clicking close button or outside modal content
            $(document).on('click', '.piireview-modal-close, .piireview-modal', function(e) {
                if (e.target === this) {
                    $('#piireview-modal').removeClass('piireview-modal-show');
                    $('body').removeClass('piireview-modal-open');
        
                    // Sync notes back to original card
                    var fileId = $('#piireview-modal').data('file-id');
                    var notes = $('#piireview-modal .piireview-modal-notes textarea').val();
                    $('#card-' + fileId).find('textarea').val(notes);
                }
            });

            // Modal zoom controls
            $(document).on('click', '.piireview-modal-zoom-in', function() {
                var img = $('.piireview-modal-image');
                var scale = (img.data('scale') || 1) * 1.2;
                img.css('transform', 'scale(' + scale + ')');
                img.data('scale', scale);
            });

            $(document).on('click', '.piireview-modal-zoom-out', function() {
                var img = $('.piireview-modal-image');
                var scale = (img.data('scale') || 1) / 1.2;
                img.css('transform', 'scale(' + scale + ')');
                img.data('scale', scale);
            });

            $(document).on('click', '.piireview-modal-zoom-reset', function() {
                var img = $('.piireview-modal-image');
                img.css('transform', 'scale(1)');
                img.data('scale', 1);
            });

            // Action buttons in modal
            $(document).on('click', '.piireview-modal-approve', function() {
                var fileId = $('#piireview-modal').data('file-id');
                $('#card-' + fileId).find('.piireview-button-approve').trigger('click');
                $('#piireview-modal').removeClass('piireview-modal-show');
                $('body').removeClass('piireview-modal-open');
            });

            $(document).on('click', '.piireview-modal-reject', function() {
                var fileId = $('#piireview-modal').data('file-id');
                $('#card-' + fileId).find('.piireview-button-reject').trigger('click');
                $('#piireview-modal').removeClass('piireview-modal-show');
                $('body').removeClass('piireview-modal-open');
            });

            // In piireview.js, find this section in the initImageModal function:
            $(document).on('click', '.piireview-modal-process', function() {
                var fileId = $('#piireview-modal').data('file-id');
                $('#card-' + fileId).find('.piireview-button-process').trigger('click');
                
                // Use the pre-loaded text from the hidden element instead of mw.message()
                $(this).prop('disabled', true).text($('#piireview-processed-text').text());
            });

            // Keyboard navigation in modal
            $(document).on('keydown', function(e) {
                if (!$('#piireview-modal').hasClass('piireview-modal-show')) {
                    return;
                }

                switch (e.keyCode) {
                    case 27: // Escape
                        $('#piireview-modal').removeClass('piireview-modal-show');
                        $('body').removeClass('piireview-modal-open');
                        break;

                    case 37: // Left arrow - navigate to previous image
                        var fileId = $('#piireview-modal').data('file-id');
                        var currentCard = $('#card-' + fileId);
                        var prevCard = currentCard.prev('.piireview-card:visible');

                        if (prevCard.length) {
                            prevCard.find('.piireview-image-container').trigger('click');
                        }
                        break;

                    case 39: // Right arrow - navigate to next image
                        var fileId = $('#piireview-modal').data('file-id');
                        var currentCard = $('#card-' + fileId);
                        var nextCard = currentCard.next('.piireview-card:visible');

                        if (nextCard.length) {
                            nextCard.find('.piireview-image-container').trigger('click');
                        }
                        break;
                }
            });
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

            // Initialize search (modified to handle non-recursive search)
            $('.piireview-search-input').on('input', function() {
                // If recursive search is enabled, don't filter client-side
                if ($('.piireview-recursive-search-checkbox').prop('checked')) {
                    return;
                }

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

        initRecursiveSearch: function() {
            // Handle recursive search submission
            $('.piireview-search-input').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    var searchQuery = $(this).val().trim();
                    var isRecursive = $('.piireview-recursive-search-checkbox').prop('checked');

                    if (searchQuery && isRecursive) {
                        // Get current path
                        var currentUrl = new URL(window.location.href);
                        var currentPath = currentUrl.searchParams.get('path') || '';

                        // Redirect to same page with search parameters
                        window.location.href = mw.util.getUrl(null, {
                            'path': currentPath,
                            'search': searchQuery,
                            'recursive': '1'
                        });
                    }
                }
            });

            // Clear search when checkbox is unchecked if we're in a recursive search
            $('.piireview-recursive-search-checkbox').on('change', function() {
                if (!$(this).prop('checked')) {
                    var currentUrl = new URL(window.location.href);
                    if (currentUrl.searchParams.get('recursive')) {
                        // Return to the same path without search parameters
                        window.location.href = mw.util.getUrl(null, {
                            'path': currentUrl.searchParams.get('path') || ''
                        });
                    }
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

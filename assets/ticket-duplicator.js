(function($) {
    'use strict';

    var TD = {};

    TD.getTicketId = function() {
        var params = new URLSearchParams(window.location.search);
        var id = params.get('id');
        if (id && /^\d+$/.test(id))
            return parseInt(id, 10);

        // Fallback: parse from edit link
        var editHref = $('a[href*="tickets.php?id="][href*="a=edit"]').attr('href');
        if (editHref) {
            var m = editHref.match(/id=(\d+)/);
            if (m) return parseInt(m[1], 10);
        }
        return null;
    };

    TD.isTicketViewPage = function() {
        return window.location.pathname.indexOf('tickets.php') !== -1
            && TD.getTicketId()
            && !new URLSearchParams(window.location.search).get('a');
    };

    TD.init = function() {
        if (!TD.isTicketViewPage())
            return;

        if ($('#td-duplicate-btn').length)
            return;

        var ticketId = TD.getTicketId();
        if (!ticketId)
            return;

        var $btn = $('<a>')
            .attr('id', 'td-duplicate-btn')
            .addClass('action-button pull-right')
            .attr('data-placement', 'bottom')
            .attr('data-toggle', 'tooltip')
            .attr('title', 'Duplicate Ticket')
            .css('cursor', 'pointer')
            .html('<i class="icon-copy"></i>')
            .on('click', function(e) {
                e.preventDefault();
                TD.showQuantityPrompt(ticketId);
            });

        var $printBtn = $('.sticky.bar .content .pull-right.flush-right span.action-button:has(#ticket-print)');
        if ($printBtn.length) {
            $btn.insertAfter($printBtn);
        } else {
            $('.sticky.bar .content .pull-right.flush-right').prepend($btn);
        }
    };

    TD.showQuantityPrompt = function(ticketId) {
        var input = prompt(
            'Total number of tickets (including this one).\n' +
            'Enter 10 to create 9 new duplicates.\n\n' +
            'How many total tickets?',
            '2'
        );

        if (input === null) return;

        var total = parseInt(input, 10);
        if (isNaN(total) || total < 2) {
            alert('Please enter a number of 2 or more.');
            return;
        }

        var count = total - 1;
        if (count > 200) {
            alert('Maximum 200 duplicates at a time.');
            return;
        }

        if (!confirm('Create ' + count + ' duplicate ticket' + (count > 1 ? 's' : '') + '?'))
            return;

        TD.duplicateSequential(ticketId, count);
    };

    TD.duplicateSequential = function(ticketId, total) {
        var $btn = $('#td-duplicate-btn');
        var originalHtml = $btn.html();
        var created = 0;
        var firstId = null;
        var firstNumber = null;
        var lastNumber = null;

        // Show initial progress
        $btn.html('<i class="icon-spinner icon-spin"></i> <span id="td-progress">0/' + total + '</span>')
            .css('pointer-events', 'none');

        function createNext() {
            $.ajax({
                url: 'ajax.php/ticket-duplicator/duplicate',
                type: 'POST',
                data: { ticket_id: ticketId, count: 1, skip_source_note: 1 },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        created++;
                        if (!firstId) {
                            firstId = data.first_id;
                            firstNumber = data.first_number;
                        }
                        lastNumber = data.last_number;

                        // Update progress
                        $('#td-progress').text(created + '/' + total);

                        if (created < total) {
                            createNext();
                        } else {
                            onComplete();
                        }
                    } else {
                        onError(data.error || 'Unknown error');
                    }
                },
                error: function(xhr) {
                    var msg = 'Failed to duplicate ticket.';
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.error) msg = r.error;
                    } catch(e) {}
                    onError(msg);
                }
            });
        }

        function onComplete() {
            // Log one summary note on the original ticket
            $.ajax({
                url: 'ajax.php/ticket-duplicator/log-source-note',
                type: 'POST',
                data: {
                    ticket_id: ticketId,
                    count: created,
                    first_number: firstNumber,
                    last_number: lastNumber
                },
                dataType: 'json',
                complete: function() {
                    // Restore button
                    $btn.html(originalHtml).css('pointer-events', '');
                    $btn.on('click', function(e) {
                        e.preventDefault();
                        TD.showQuantityPrompt(ticketId);
                    });

                    // Show success notice
                    var $notice = $('#msg_notice');
                    if ($notice.length) {
                        $notice.find('#msg-txt').text(
                            created + ' duplicate ticket' + (created > 1 ? 's' : '') +
                            ' created! (#' + firstNumber + ' — #' + lastNumber + ')');
                        $notice.show().delay(10000).fadeOut();
                    }

                    // Reload the page to show the duplication notes
                    if (typeof $.pjax !== 'undefined') {
                        $.pjax.reload('#pjax-container');
                    }
                }
            });
        }

        function onError(msg) {
            // Restore button
            $btn.html(originalHtml).css('pointer-events', '');
            $btn.on('click', function(e) {
                e.preventDefault();
                TD.showQuantityPrompt(ticketId);
            });

            if (created > 0) {
                alert(msg + '\n\nSuccessfully created ' + created + ' of ' + total + ' tickets before the error.');
            } else {
                alert(msg);
            }
        }

        // Start the chain
        createNext();
    };

    // Initialize on document ready and on PJAX completion
    $(function() { TD.init(); });
    $(document).on('pjax:end', function() { TD.init(); });

})(jQuery);

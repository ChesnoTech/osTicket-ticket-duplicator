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

        // Don't add if already present (PJAX re-navigation)
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

        // Insert after the Print button span
        var $printBtn = $('.sticky.bar .content .pull-right.flush-right span.action-button:has(#ticket-print)');
        if ($printBtn.length) {
            $btn.insertAfter($printBtn);
        } else {
            // Fallback: prepend to the button bar
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

        if (input === null) return; // cancelled

        var total = parseInt(input, 10);
        if (isNaN(total) || total < 2) {
            alert('Please enter a number of 2 or more.');
            return;
        }

        var count = total - 1; // exclude the original
        if (count > 200) {
            alert('Maximum 200 duplicates at a time.');
            return;
        }

        if (!confirm('Create ' + count + ' duplicate ticket' + (count > 1 ? 's' : '') + '?'))
            return;

        TD.duplicateTicket(ticketId, count);
    };

    TD.duplicateTicket = function(ticketId, count) {
        var $btn = $('#td-duplicate-btn');
        var originalHtml = $btn.html();
        $btn.html('<i class="icon-spinner icon-spin"></i> <span id="td-progress">0/' + count + '</span>')
            .css('pointer-events', 'none');

        $.ajax({
            url: 'ajax.php/ticket-duplicator/duplicate',
            type: 'POST',
            data: { ticket_id: ticketId, count: count },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    var created = data.created || 0;
                    var $notice = $('#msg_notice');
                    if ($notice.length) {
                        $notice.find('#msg-txt').text(
                            created + ' duplicate ticket' + (created > 1 ? 's' : '') +
                            ' created successfully! (#' + data.first_number + ' — #' + data.last_number + ')');
                        $notice.show().delay(8000).fadeOut();
                    }
                    // Open first new ticket in a new tab
                    if (data.first_id) {
                        window.open('tickets.php?id=' + data.first_id, '_blank');
                    }
                } else {
                    alert('Failed to duplicate ticket: ' + (data.error || 'Unknown error'));
                }
                // Restore button
                $btn.html(originalHtml).css('pointer-events', '');
                $btn.on('click', function(e) {
                    e.preventDefault();
                    TD.showQuantityPrompt(ticketId);
                });
            },
            error: function(xhr) {
                var msg = 'Failed to duplicate ticket.';
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.error) msg = r.error;
                } catch(e) {}
                alert(msg);
                // Restore button
                $btn.html(originalHtml).css('pointer-events', '');
                $btn.on('click', function(e) {
                    e.preventDefault();
                    TD.showQuantityPrompt(ticketId);
                });
            }
        });
    };

    // Initialize on document ready and on PJAX completion
    $(function() { TD.init(); });
    $(document).on('pjax:end', function() { TD.init(); });

})(jQuery);

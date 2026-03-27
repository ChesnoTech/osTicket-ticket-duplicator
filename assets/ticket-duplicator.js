(function($) {
    'use strict';

    var TD = {};

    TD.getTicketId = function() {
        var params = new URLSearchParams(window.location.search);
        var id = params.get('id');
        if (id && /^\d+$/.test(id))
            return parseInt(id, 10);

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

        $.ajax({
            url: 'ajax.php/ticket-duplicator/check-access',
            type: 'GET',
            data: { ticket_id: ticketId },
            dataType: 'json',
            success: function(resp) {
                if (resp.allowed)
                    TD.insertButton(ticketId);
            },
            error: function() {
                TD.insertButton(ticketId);
            }
        });
    };

    TD.insertButton = function(ticketId) {
        if ($('#td-duplicate-btn').length)
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
                TD.showModal(ticketId);
            });

        var $printBtn = $('.sticky.bar .content .pull-right.flush-right span.action-button:has(#ticket-print)');
        if ($printBtn.length) {
            $btn.insertAfter($printBtn);
        } else {
            $('.sticky.bar .content .pull-right.flush-right').prepend($btn);
        }
    };

    // ── Modal UI ──

    TD.showModal = function(ticketId) {
        // Remove any existing modal
        $('#td-modal-overlay').remove();

        var ticketNumber = $('h2 a[href*="tickets.php"]').text().replace('#', '').trim()
            || $('title').text().replace(/.*#/, '').replace(/\s.*/, '').trim();

        var html =
            '<div id="td-modal-overlay" class="td-overlay">' +
            '<div class="td-modal">' +
              '<div class="td-modal-header">' +
                '<span>Duplicate Ticket #' + ticketNumber + '</span>' +
                '<a class="td-modal-close">&times;</a>' +
              '</div>' +
              '<div class="td-modal-body">' +
                '<div class="td-field-row">' +
                  '<label>Total copies (incl. original):</label>' +
                  '<input type="number" id="td-total" value="2" min="2" max="201" class="td-input-num">' +
                '</div>' +
                '<div class="td-field-row">' +
                  '<label class="td-checkbox-label">' +
                    '<input type="checkbox" id="td-manual-assembly"> ' +
                    'Enter 1C Assembly numbers manually' +
                  '</label>' +
                '</div>' +
                '<div id="td-assembly-section" style="display:none;">' +
                  '<div class="td-table-wrap">' +
                    '<table class="td-assembly-table">' +
                      '<thead><tr><th>#</th><th>1C Assembly Number</th></tr></thead>' +
                      '<tbody id="td-assembly-rows"></tbody>' +
                    '</table>' +
                  '</div>' +
                '</div>' +
              '</div>' +
              '<div class="td-modal-footer">' +
                '<button type="button" class="td-btn td-btn-cancel">Cancel</button>' +
                '<button type="button" class="td-btn td-btn-create" id="td-btn-create">Create <span id="td-create-count">1</span> duplicate(s)</button>' +
              '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        // Bind events
        var $overlay = $('#td-modal-overlay');
        var $total = $('#td-total');
        var $checkbox = $('#td-manual-assembly');

        function updateRows() {
            var count = Math.max(1, Math.min(200, parseInt($total.val(), 10) - 1)) || 1;
            $('#td-create-count').text(count);
            if (!$checkbox.is(':checked')) return;

            var $tbody = $('#td-assembly-rows');
            var existing = $tbody.find('tr').length;

            if (count > existing) {
                for (var i = existing + 1; i <= count; i++) {
                    $tbody.append(
                        '<tr><td class="td-row-num">' + i + '</td>' +
                        '<td><input type="text" class="td-assembly-input" maxlength="40" placeholder="Assembly number..."></td></tr>'
                    );
                }
            } else if (count < existing) {
                $tbody.find('tr').slice(count).remove();
            }
        }

        $total.on('input change', updateRows);

        $checkbox.on('change', function() {
            if (this.checked) {
                $('#td-assembly-section').slideDown(150);
                updateRows();
            } else {
                $('#td-assembly-section').slideUp(150);
            }
        });

        $overlay.find('.td-modal-close, .td-btn-cancel').on('click', function() {
            $overlay.remove();
        });

        $overlay.on('click', function(e) {
            if (e.target === this) $overlay.remove();
        });

        $('#td-btn-create').on('click', function() {
            var total = parseInt($total.val(), 10);
            if (isNaN(total) || total < 2) {
                $total.focus();
                return;
            }
            var count = Math.min(200, total - 1);
            var assemblyValues = null;

            if ($checkbox.is(':checked')) {
                assemblyValues = [];
                $('#td-assembly-rows .td-assembly-input').each(function() {
                    assemblyValues.push($.trim($(this).val()));
                });
            }

            $overlay.remove();
            TD.duplicateSequential(ticketId, count, assemblyValues);
        });

        // Focus the total input
        $total.focus().select();
        updateRows();
    };

    // ── Sequential duplication ──

    TD.duplicateSequential = function(ticketId, total, assemblyValues) {
        var $btn = $('#td-duplicate-btn');
        var originalHtml = $btn.html();
        var created = 0;
        var firstId = null;
        var firstNumber = null;
        var lastNumber = null;

        $btn.html('<i class="icon-copy" style="opacity:.5"></i>')
            .attr('title', '0/' + total)
            .css({'pointer-events': 'none', 'position': 'relative'})
            .append('<span id="td-progress" style="position:absolute;top:-8px;right:-8px;background:#e74c3c;color:#fff;font-size:10px;padding:1px 4px;border-radius:8px;line-height:14px;font-weight:bold;white-space:nowrap;">0/' + total + '</span>');

        function createNext() {
            var postData = { ticket_id: ticketId, count: 1, skip_source_note: 1 };

            // If manual assembly values provided, include for this iteration
            if (assemblyValues && assemblyValues[created]) {
                postData.assembly_value = assemblyValues[created];
            }

            $.ajax({
                url: 'ajax.php/ticket-duplicator/duplicate',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        created++;
                        if (!firstId) {
                            firstId = data.first_id;
                            firstNumber = data.first_number;
                        }
                        lastNumber = data.last_number;
                        $('#td-progress').text(created + '/' + total);
                        $btn.attr('title', created + '/' + total);

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
                    restoreButton();

                    var $notice = $('#msg_notice');
                    if ($notice.length) {
                        $notice.find('#msg-txt').text(
                            created + ' duplicate ticket' + (created > 1 ? 's' : '') +
                            ' created! (#' + firstNumber + ' — #' + lastNumber + ')');
                        $notice.show().delay(10000).fadeOut();
                    }

                    if (typeof $.pjax !== 'undefined') {
                        $.pjax.reload('#pjax-container');
                    }
                }
            });
        }

        function onError(msg) {
            restoreButton();
            if (created > 0) {
                alert(msg + '\n\nSuccessfully created ' + created + ' of ' + total + ' before the error.');
            } else {
                alert(msg);
            }
        }

        function restoreButton() {
            $btn.html(originalHtml).css({'pointer-events': '', 'position': ''});
            $btn.on('click', function(e) {
                e.preventDefault();
                TD.showModal(ticketId);
            });
        }

        createNext();
    };

    $(function() { TD.init(); });
    $(document).on('pjax:end', function() { TD.init(); });

})(jQuery);

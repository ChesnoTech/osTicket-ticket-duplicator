(function ($) {
    'use strict';

    var CHECK_URL   = 'ajax.php/ticket-duplicator/update/check';
    var INSTALL_URL = 'ajax.php/ticket-duplicator/update/install';
    var SESSION_KEY = 'td-update-dismissed';

    // ── Banner helpers ────────────────────────────────────────────────────────

    function createBanner() {
        if ($('#td-update-banner').length) return $('#td-update-banner');
        var $banner = $('<div id="td-update-banner">');
        // Insert after the page heading (h2 "Installed Plugins")
        var $target = $('form h2, h2, h1').filter(':visible').first();
        if ($target.length) {
            $banner.insertAfter($target);
        } else {
            $('div.content, #content, .container').first().prepend($banner);
        }
        return $banner;
    }

    function showBanner(state, html) {
        var $b = createBanner();
        $b.attr('class', 'td-update-banner td-update-' + state).html(html);
    }

    function removeBanner() {
        $('#td-update-banner').fadeOut(300, function () { $(this).remove(); });
    }

    // ── Check for update ─────────────────────────────────────────────────────

    function checkForUpdate() {
        if (sessionStorage.getItem(SESSION_KEY)) return;

        $.ajax({
            url:      CHECK_URL,
            type:     'GET',
            dataType: 'json',
            success: function (data) {
                if (data.available) {
                    showAvailableBanner(data.local, data.remote);
                } else if (!data.error && data.remote) {
                    showBanner('ok',
                        '<i class="icon-ok-sign"></i> ' +
                        'Ticket Duplicator v' + escHtml(data.local) + ' &mdash; up to date' +
                        ' <a class="td-update-dismiss" href="#">&times;</a>');
                    setTimeout(removeBanner, 4000);
                }
            },
            error: function () { /* silent fail — do not show anything */ }
        });
    }

    function showAvailableBanner(local, remote) {
        showBanner('available',
            '<i class="icon-upload"></i> ' +
            '<strong>Ticket Duplicator update available:</strong> ' +
            'v' + escHtml(local) + ' &rarr; v' + escHtml(remote) +
            ' <button class="td-update-btn" id="td-do-update">Install Update</button>' +
            ' <a class="td-update-dismiss" href="#">Dismiss</a>');

        $('#td-do-update').on('click', function (e) {
            e.preventDefault();
            installUpdate(local, remote);
        });
    }

    // ── Install update ────────────────────────────────────────────────────────

    function installUpdate(local, remote) {
        if (!confirm(
            'Install Ticket Duplicator v' + remote + '?\n\n' +
            'This will:\n' +
            '  \u2022 Back up current plugin files\n' +
            '  \u2022 Back up plugin database config\n' +
            '  \u2022 Download and install v' + remote + ' from GitHub\n\n' +
            'Current version: v' + local
        )) return;

        showBanner('loading',
            '<i class="icon-spinner icon-spin"></i> ' +
            'Installing Ticket Duplicator v' + escHtml(remote) + '&hellip; please wait');

        $.ajax({
            url:      INSTALL_URL,
            type:     'POST',
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    showBanner('ok',
                        '<i class="icon-ok-sign"></i> ' +
                        '<strong>Updated to v' + escHtml(data.new_version) + '!</strong>' +
                        ' Backups saved.' +
                        (data.backup_files ? ' Files: <code>' + escHtml(data.backup_files) + '</code>' : '') +
                        (data.backup_db    ? ' DB: <code>'    + escHtml(data.backup_db)    + '</code>' : '') +
                        ' &mdash; <button class="td-update-btn" onclick="location.reload()">Reload page</button>');
                } else {
                    showBanner('error',
                        '<i class="icon-warning-sign"></i> ' +
                        '<strong>Update failed:</strong> ' + escHtml(data.error || 'Unknown error') +
                        (data.backup_files ? ' (backup at <code>' + escHtml(data.backup_files) + '</code>)' : '') +
                        ' <a class="td-update-dismiss" href="#">Dismiss</a>');
                }
            },
            error: function (xhr) {
                var msg = 'Server error during update.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
                showBanner('error',
                    '<i class="icon-warning-sign"></i> ' +
                    '<strong>Update failed:</strong> ' + escHtml(msg) +
                    ' <a class="td-update-dismiss" href="#">Dismiss</a>');
            }
        });
    }

    // ── Dismiss ───────────────────────────────────────────────────────────────

    $(document).on('click', '.td-update-dismiss', function (e) {
        e.preventDefault();
        sessionStorage.setItem(SESSION_KEY, '1');
        removeBanner();
    });

    // ── Utilities ─────────────────────────────────────────────────────────────

    function escHtml(str) {
        return $('<span>').text(String(str)).html();
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    $(function () { checkForUpdate(); });

})(jQuery);

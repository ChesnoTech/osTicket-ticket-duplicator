(function ($) {
    'use strict';

    var CHECK_URL   = 'ajax.php/ticket-duplicator/update/check';
    var INSTALL_URL = 'ajax.php/ticket-duplicator/update/install';
    var SESSION_KEY = 'td-update-dismissed';

    // ── Panel builders ───────────────────────────────────────────────────────

    function createPanel() {
        if ($('#td-update-panel').length) return $('#td-update-panel');
        var $panel = $('<div id="td-update-panel">');
        var $target = $('form h2, h2, h1').filter(':visible').first();
        if ($target.length) {
            $panel.insertAfter($target);
        } else {
            $('div.content, #content, .container').first().prepend($panel);
        }
        return $panel;
    }

    function renderPanel(data) {
        var $panel = createPanel();
        var hasMinor = data.minor && data.minor.version;
        var hasMajor = data.major && data.major.version;
        var hasAny   = hasMinor || hasMajor;

        var html = '<div class="td-up-header">'
                 + '<span class="td-up-icon"><i class="icon-refresh"></i></span>'
                 + '<span class="td-up-title">Ticket Duplicator Updates</span>'
                 + '<span class="td-up-version">Installed: <strong>v' + esc(data.local) + '</strong></span>'
                 + '<a class="td-up-dismiss" href="#" title="Dismiss">&times;</a>'
                 + '</div>';

        if (!hasAny && !data.error) {
            html += '<div class="td-up-body">'
                  + '<div class="td-up-ok">'
                  + '<i class="icon-ok-sign"></i> You are running the latest version.'
                  + '</div></div>';
            $panel.html(html);
            setTimeout(function () { $panel.fadeOut(400, function () { $(this).remove(); }); }, 5000);
            return;
        }

        if (data.error && !hasAny) {
            html += '<div class="td-up-body">'
                  + '<div class="td-up-error-msg">'
                  + '<i class="icon-warning-sign"></i> ' + esc(data.error)
                  + '</div></div>';
            $panel.html(html);
            return;
        }

        html += '<div class="td-up-body">';

        if (hasMinor) {
            html += buildUpdateCard('minor', data.minor, data.local);
        }

        if (hasMajor) {
            html += buildUpdateCard('major', data.major, data.local);
        }

        html += '</div>';
        $panel.html(html);

        // Bind install buttons
        $panel.find('.td-up-install-btn').on('click', function (e) {
            e.preventDefault();
            var tag     = $(this).data('tag');
            var version = $(this).data('version');
            var type    = $(this).data('type');
            installUpdate(tag, version, type, data.local);
        });

        // Expand/collapse release notes
        $panel.find('.td-up-toggle-notes').on('click', function (e) {
            e.preventDefault();
            var $notes = $(this).closest('.td-up-card').find('.td-up-notes');
            $notes.slideToggle(200);
            var open = $notes.is(':visible');
            $(this).text(open ? 'Hide release notes' : 'Show release notes');
        });
    }

    function buildUpdateCard(type, update, local) {
        var isMajor = (type === 'major');
        var label   = isMajor ? 'Major Update' : 'Minor / Patch Update';
        var cls     = isMajor ? 'td-up-card td-up-card-major' : 'td-up-card td-up-card-minor';
        var icon    = isMajor ? 'icon-exclamation-sign' : 'icon-arrow-up';
        var btnCls  = isMajor ? 'td-up-install-btn td-up-btn-major' : 'td-up-install-btn td-up-btn-minor';

        var html = '<div class="' + cls + '">'
            + '<div class="td-up-card-header">'
            + '<span class="td-up-card-icon"><i class="' + icon + '"></i></span>'
            + '<span class="td-up-card-label">' + label + '</span>'
            + '</div>'
            + '<div class="td-up-card-body">'
            + '<div class="td-up-version-jump">'
            + 'v' + esc(local) + ' <span class="td-up-arrow">&rarr;</span> <strong>v' + esc(update.version) + '</strong>'
            + '</div>';

        if (isMajor) {
            html += '<div class="td-up-card-warning">'
                  + '<i class="icon-warning-sign"></i> '
                  + 'Major version — may contain breaking changes. Review the release notes before installing.'
                  + '</div>';
        }

        if (update.body) {
            html += '<a href="#" class="td-up-toggle-notes">Show release notes</a>'
                  + '<div class="td-up-notes" style="display:none;">'
                  + formatBody(update.body)
                  + '</div>';
        }

        html += '<button class="' + btnCls + '" data-tag="' + esc(update.tag) + '" '
              + 'data-version="' + esc(update.version) + '" data-type="' + type + '">'
              + 'Install ' + label
              + '</button>'
              + '</div></div>';

        return html;
    }

    // ── Install ──────────────────────────────────────────────────────────────

    function installUpdate(tag, version, type, local) {
        var typeLabel = (type === 'major') ? 'MAJOR' : 'minor';
        var warning = (type === 'major')
            ? '\n\n WARNING: This is a MAJOR update and may contain breaking changes!'
            : '';

        if (!confirm(
            'Install Ticket Duplicator v' + version + ' (' + typeLabel + ')?' + warning + '\n\n' +
            'This will:\n' +
            '  \u2022 Back up current plugin files\n' +
            '  \u2022 Back up plugin database config\n' +
            '  \u2022 Download and install v' + version + ' from GitHub\n\n' +
            'Current version: v' + local
        )) return;

        var $panel = $('#td-update-panel');
        $panel.find('.td-up-body').html(
            '<div class="td-up-loading">'
            + '<i class="icon-spinner icon-spin"></i> '
            + 'Installing v' + esc(version) + '&hellip; please wait'
            + '</div>'
        );

        $.ajax({
            url:      INSTALL_URL,
            type:     'POST',
            data:     { tag: tag },
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    showResult('ok',
                        '<i class="icon-ok-sign"></i> '
                        + '<strong>Updated to v' + esc(data.new_version) + '!</strong> '
                        + 'Backups saved.'
                        + (data.backup_files ? ' Files: <code>' + esc(data.backup_files) + '</code>' : '')
                        + (data.backup_db    ? ' DB: <code>'    + esc(data.backup_db)    + '</code>' : '')
                        + ' &mdash; <button class="td-up-install-btn td-up-btn-minor" onclick="location.reload()">Reload page</button>'
                    );
                } else {
                    showResult('error',
                        '<i class="icon-warning-sign"></i> '
                        + '<strong>Update failed:</strong> ' + esc(data.error || 'Unknown error')
                        + (data.rollback ? '<br><strong>Rollback:</strong> ' + esc(data.rollback) : '')
                        + (data.backup_files ? ' (backup at <code>' + esc(data.backup_files) + '</code>)' : '')
                    );
                }
            },
            error: function (xhr) {
                var msg = 'Server error during update.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
                showResult('error',
                    '<i class="icon-warning-sign"></i> '
                    + '<strong>Update failed:</strong> ' + esc(msg)
                );
            }
        });
    }

    function showResult(state, html) {
        var $panel = $('#td-update-panel');
        var cls = (state === 'ok') ? 'td-up-result-ok' : 'td-up-result-error';
        $panel.find('.td-up-body').html('<div class="' + cls + '">' + html + '</div>');
    }

    // ── Check for updates ────────────────────────────────────────────────────

    function checkForUpdate() {
        if (sessionStorage.getItem(SESSION_KEY)) return;

        $.ajax({
            url:      CHECK_URL,
            type:     'GET',
            dataType: 'json',
            success:  function (data) { renderPanel(data); },
            error:    function () { /* silent fail */ }
        });
    }

    // ── Dismiss ──────────────────────────────────────────────────────────────

    $(document).on('click', '.td-up-dismiss', function (e) {
        e.preventDefault();
        sessionStorage.setItem(SESSION_KEY, '1');
        $('#td-update-panel').fadeOut(300, function () { $(this).remove(); });
    });

    // ── Utilities ────────────────────────────────────────────────────────────

    function esc(str) {
        return $('<span>').text(String(str || '')).html();
    }

    /** Convert markdown-like release notes body to simple HTML */
    function formatBody(text) {
        // Sanitize HTML entities first
        text = esc(text);
        // **bold**
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Headers: ## or ###
        text = text.replace(/^#{2,3}\s+(.+)$/gm, '<strong>$1</strong>');
        // Bullet points
        text = text.replace(/^[\-\*]\s+(.+)$/gm, '<span class="td-up-bullet">&bull; $1</span>');
        // Line breaks
        text = text.replace(/\n/g, '<br>');
        return text;
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    $(function () { checkForUpdate(); });

})(jQuery);

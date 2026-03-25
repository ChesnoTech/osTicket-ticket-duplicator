<?php
/**
 * Ticket Duplicator Plugin - AJAX Controller
 *
 * @author  ChesnoTech
 * @version 1.0.0
 */

require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.ajax.php';

class TicketDuplicatorAjax extends AjaxController {

    private function requireStaff() {
        global $thisstaff;

        if (!$thisstaff || !$thisstaff->isValid()) {
            Http::response(403, JsonDataEncoder::encode(
                array('error' => 'Staff login required')));
            exit;
        }
    }

    private function getPluginConfig() {
        $plugin = Plugin::objects()->filter(array(
            'install_path' => 'plugins/ticket-duplicator',
        ))->first();

        if (!$plugin)
            return null;

        $instance = PluginInstance::objects()->filter(array(
            'plugin_id' => $plugin->getId(),
            'flags__hasbit' => PluginInstance::FLAG_ENABLED,
        ))->first();

        if (!$instance)
            return null;

        return $instance->getConfig();
    }

    function duplicate() {
        global $thisstaff;

        $this->requireStaff();

        $ticketId = (int) $_POST['ticket_id'];
        $count = max(1, min(200, (int) $_POST['count'] ?: 1));

        if (!$ticketId) {
            Http::response(400, JsonDataEncoder::encode(
                array('error' => 'Ticket ID is required')));
            return;
        }

        $ticket = Ticket::lookup($ticketId);
        if (!$ticket) {
            Http::response(404, JsonDataEncoder::encode(
                array('error' => 'Ticket not found')));
            return;
        }

        if (!$ticket->checkStaffPerm($thisstaff)) {
            Http::response(403, JsonDataEncoder::encode(
                array('error' => 'You do not have access to this ticket')));
            return;
        }

        // Load plugin config (use defaults if no instance configured)
        $config = $this->getPluginConfig();
        $prefix = $config ? $config->get('subject_prefix') : '[Duplicate] ';
        $copyPriority = $config ? $config->get('copy_priority') : true;
        $copySla = $config ? $config->get('copy_sla') : true;
        $copyAssignment = $config ? $config->get('copy_assignment') : false;

        // Build the base vars array for Ticket::create()
        // Use 'email' origin to bypass strict form validation for custom fields,
        // since we're duplicating an existing ticket (not creating a fresh one).
        global $cfg;
        $vars = array(
            'uid'      => $ticket->getUserId(),
            'topicId'  => $ticket->getTopicId(),
            'deptId'   => $ticket->getDeptId(),
            'emailId'  => $cfg->getDefaultEmailId(),
            'source'   => 'Web',
            'subject'  => $prefix . $ticket->getSubject(),
            'ip'       => $_SERVER['REMOTE_ADDR'],
        );

        if ($copyPriority && $ticket->getPriorityId())
            $vars['priorityId'] = $ticket->getPriorityId();

        if ($copySla && $ticket->getSLAId())
            $vars['slaId'] = $ticket->getSLAId();

        if ($copyAssignment) {
            if ($ticket->getStaffId())
                $vars['staffId'] = $ticket->getStaffId();
            if ($ticket->getTeamId())
                $vars['teamId'] = $ticket->getTeamId();
        }

        // Copy custom field values from original ticket's dynamic forms.
        // Skip fields whose getValue() returns objects (e.g. PriorityField
        // returns a Priority instance) — these break Ticket::create().
        require_once INCLUDE_DIR . 'class.dynamic_forms.php';
        foreach (DynamicFormEntry::forTicket($ticket->getId()) as $form) {
            foreach ($form->getAnswers() as $answer) {
                $field = $answer->getField();
                $fieldId = $field->get('id');
                $fieldName = $field->get('name');
                $value = $answer->getValue();
                if ($value !== null && $value !== '' && !is_object($value)) {
                    $vars[$fieldId] = $value;
                    if ($fieldName)
                        $vars[$fieldName] = $value;
                }
            }
        }

        // Get the first internal note as the message body
        $notes = $ticket->getNotes();
        $firstNote = $notes ? $notes->first() : null;
        if ($firstNote) {
            $body = $firstNote->getBody();
            $vars['message'] = (string) $body;
        } else {
            $vars['message'] = sprintf(
                'Duplicated from ticket <b>#%s</b>.',
                $ticket->getNumber());
        }

        // Create N duplicate tickets
        $created = array();
        $lastError = '';
        for ($i = 0; $i < $count; $i++) {
            $errors = array();
            $newTicket = Ticket::create($vars, $errors, 'email', false, false);

            if (!$newTicket) {
                $lastError = $errors['err'] ?: 'Failed to create duplicate ticket';
                break;
            }

            // Log internal note on the new ticket linking back to original
            $newTicket->logNote(
                'Duplicated Ticket',
                sprintf('This ticket was duplicated from <a href="tickets.php?id=%d"><b>#%s</b></a>.',
                    $ticket->getId(), $ticket->getNumber()),
                $thisstaff,
                false
            );

            $created[] = array(
                'id'     => $newTicket->getId(),
                'number' => $newTicket->getNumber(),
            );
        }

        if (empty($created)) {
            Http::response(400, JsonDataEncoder::encode(
                array('error' => $lastError)));
            return;
        }

        // Log one summary note on the ORIGINAL ticket
        $firstNum = $created[0]['number'];
        $lastNum = $created[count($created) - 1]['number'];
        if (count($created) == 1) {
            $noteBody = sprintf(
                'Ticket <a href="tickets.php?id=%d"><b>#%s</b></a> was created as a duplicate of this ticket.',
                $created[0]['id'], $firstNum);
        } else {
            $noteBody = sprintf(
                '%d duplicate tickets were created from this ticket: <b>#%s</b> through <b>#%s</b>.',
                count($created), $firstNum, $lastNum);
        }
        $ticket->logNote('Ticket Duplicated', $noteBody, $thisstaff, false);

        Http::response(200, JsonDataEncoder::encode(array(
            'success'      => true,
            'created'      => count($created),
            'first_id'     => $created[0]['id'],
            'first_number' => $firstNum,
            'last_number'  => $lastNum,
        )));
    }

    private function serveFile($file, $contentType, $maxAge = 86400) {
        if (!file_exists($file))
            Http::response(404, 'Not found');

        $etag = '"td-' . md5($file) . '-' . filemtime($file) . '"';
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=' . $maxAge);
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
                && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            Http::response(304, '');
            exit;
        }
        readfile($file);
        exit;
    }

    function serveJs() {
        $this->serveFile(dirname(__FILE__) . '/assets/ticket-duplicator.js',
            'application/javascript; charset=UTF-8');
    }

    function serveCss() {
        $this->serveFile(dirname(__FILE__) . '/assets/ticket-duplicator.css',
            'text/css; charset=UTF-8');
    }
}

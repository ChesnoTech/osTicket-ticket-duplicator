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

        if (!$thisstaff || !$thisstaff->getId()) {
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

    function checkAccess() {
        $this->requireStaff();

        $ticketId = (int) $_GET['ticket_id'];
        if (!$ticketId) {
            Http::response(200, JsonDataEncoder::encode(array('allowed' => true)));
            return;
        }

        $ticket = Ticket::lookup($ticketId);
        if (!$ticket) {
            Http::response(200, JsonDataEncoder::encode(array('allowed' => false)));
            return;
        }

        $config = $this->getPluginConfig();
        $allowed = $this->isTicketAllowed($ticket, $config);

        Http::response(200, JsonDataEncoder::encode(array('allowed' => $allowed)));
    }

    /**
     * Parse a ChoiceField value into an array of ID strings.
     * ChoiceField stores as JSON {"5":"Name","12":"Name"} or comma-separated.
     */
    private static function parseIdList($value) {
        if (!$value)
            return array();
        if (is_array($value))
            return array_map('strval', array_keys($value));
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded))
                return array_map('strval', array_keys($decoded));
            return array_filter(array_map('trim', explode(',', $value)));
        }
        return array();
    }

    /**
     * Get the list of manually-enterable field IDs + labels from config.
     * Returns array of ['id' => int, 'label' => string].
     */
    private function getManualFields($config) {
        if (!$config)
            return array();

        $raw = $config->get('manual_fields');
        $ids = self::parseIdList($raw);
        if (empty($ids))
            return array();

        // Look up labels for each field ID
        $fields = array();
        $sql = 'SELECT id, label FROM ' . FORM_FIELD_TABLE
             . ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')';
        $res = db_query($sql);
        if ($res) {
            while ($row = db_fetch_array($res))
                $fields[] = array('id' => (int) $row['id'], 'label' => $row['label']);
        }
        return $fields;
    }

    private function isTicketAllowed($ticket, $config) {
        global $thisstaff;

        if (!$config)
            return true;

        // Check agent's primary department against allowed departments
        $allowedDepts = self::parseIdList($config->get('allowed_depts'));
        if (!empty($allowedDepts)) {
            $agentDeptId = $thisstaff ? (string) $thisstaff->getDeptId() : '';
            if (!in_array($agentDeptId, $allowedDepts))
                return false;
        }

        // Check ticket's help topic against allowed topics
        $allowedTopics = self::parseIdList($config->get('allowed_topics'));
        if (!empty($allowedTopics)) {
            if (!in_array((string) $ticket->getTopicId(), $allowedTopics))
                return false;
        }

        return true;
    }

    function getConfig() {
        $this->requireStaff();

        $config = $this->getPluginConfig();
        $allowedDepts = array();
        $allowedTopics = array();

        if ($config) {
            $allowedDepts = self::parseIdList($config->get('allowed_depts'));
            $allowedTopics = self::parseIdList($config->get('allowed_topics'));
        }

        Http::response(200, JsonDataEncoder::encode(array(
            'allowed_depts'  => $allowedDepts,
            'allowed_topics' => $allowedTopics,
            'manual_fields'  => $this->getManualFields($config),
        )));
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

        // Check dept/topic access
        if (!$this->isTicketAllowed($ticket, $config)) {
            Http::response(403, JsonDataEncoder::encode(
                array('error' => 'Duplication not allowed for this ticket (department/topic restriction)')));
            return;
        }

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

            // Override specific field values if provided (manual entry mode)
            if (!empty($_POST['field_values'])) {
                $fieldValues = json_decode($_POST['field_values'], true);
                if (is_array($fieldValues) && !empty($fieldValues)) {
                    foreach (DynamicFormEntry::forTicket($newTicket->getId()) as $form) {
                        foreach ($form->getAnswers() as $answer) {
                            $fid = (string) $answer->getField()->get('id');
                            if (isset($fieldValues[$fid]) && $fieldValues[$fid] !== '') {
                                $answer->setValue(trim($fieldValues[$fid]));
                                $answer->save();
                            }
                        }
                    }
                }
            }

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

        $firstNum = $created[0]['number'];
        $lastNum = $created[count($created) - 1]['number'];

        // Log a summary note on the ORIGINAL ticket unless caller
        // opted out (sequential mode logs one summary at the end).
        $skipSourceNote = !empty($_POST['skip_source_note']);
        if (!$skipSourceNote) {
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
        }

        Http::response(200, JsonDataEncoder::encode(array(
            'success'      => true,
            'created'      => count($created),
            'first_id'     => $created[0]['id'],
            'first_number' => $firstNum,
            'last_number'  => $lastNum,
        )));
    }

    function logSourceNote() {
        global $thisstaff;
        $this->requireStaff();

        $ticketId = (int) $_POST['ticket_id'];
        $count    = (int) $_POST['count'];
        $firstNum = $_POST['first_number'] ?: '';
        $lastNum  = $_POST['last_number'] ?: '';

        $ticket = Ticket::lookup($ticketId);
        if (!$ticket) {
            Http::response(404, JsonDataEncoder::encode(
                array('error' => 'Ticket not found')));
            return;
        }

        if ($count == 1) {
            $noteBody = sprintf(
                'Ticket <b>#%s</b> was created as a duplicate of this ticket.',
                Format::htmlchars($firstNum));
        } else {
            $noteBody = sprintf(
                '%d duplicate tickets were created from this ticket: <b>#%s</b> through <b>#%s</b>.',
                $count,
                Format::htmlchars($firstNum),
                Format::htmlchars($lastNum));
        }
        $ticket->logNote('Ticket Duplicated', $noteBody, $thisstaff, false);

        Http::response(200, JsonDataEncoder::encode(array('success' => true)));
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

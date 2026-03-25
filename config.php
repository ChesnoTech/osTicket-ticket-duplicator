<?php
require_once INCLUDE_DIR . 'class.plugin.php';

class TicketDuplicatorConfig extends PluginConfig {

    function getOptions() {
        return array(
            'heading' => new SectionBreakField(array(
                'label' => /* trans */ 'Ticket Duplicator Settings',
            )),
            'subject_prefix' => new TextboxField(array(
                'label' => /* trans */ 'Subject Prefix',
                'hint'  => /* trans */ 'Text prepended to the subject of duplicated tickets.',
                'default' => '[Duplicate] ',
                'configuration' => array('size' => 40, 'length' => 100),
            )),
            'copy_priority' => new BooleanField(array(
                'label' => /* trans */ 'Copy Priority',
                'hint'  => /* trans */ 'Copy the priority level from the original ticket.',
                'default' => true,
            )),
            'copy_sla' => new BooleanField(array(
                'label' => /* trans */ 'Copy SLA',
                'hint'  => /* trans */ 'Copy the SLA plan from the original ticket.',
                'default' => true,
            )),
            'copy_assignment' => new BooleanField(array(
                'label' => /* trans */ 'Copy Assignment',
                'hint'  => /* trans */ 'Copy the staff/team assignment from the original ticket.',
                'default' => false,
            )),
        );
    }
}

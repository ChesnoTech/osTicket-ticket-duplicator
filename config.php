<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.dept.php';

/**
 * Custom ChoiceField that loads departments dynamically.
 */
class DeptChoiceField extends ChoiceField {
    function getChoices($verbose=false, $options=array()) {
        if (!isset($this->_choices)) {
            $this->_choices = array();
            $depts = Dept::getDepartments();
            if ($depts) {
                foreach ($depts as $id => $name)
                    $this->_choices[$id] = $name;
            }
        }
        return $this->_choices;
    }
}

/**
 * Custom ChoiceField that loads help topics dynamically.
 */
class TopicChoiceField extends ChoiceField {
    function getChoices($verbose=false, $options=array()) {
        if (!isset($this->_choices)) {
            $this->_choices = array();
            $res = db_query('SELECT topic_id, topic FROM ' . TOPIC_TABLE . ' ORDER BY topic');
            if ($res) {
                while ($row = db_fetch_array($res))
                    $this->_choices[$row['topic_id']] = $row['topic'];
            }
        }
        return $this->_choices;
    }
}

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

            'access_heading' => new SectionBreakField(array(
                'label' => /* trans */ 'Access Control',
                'hint'  => /* trans */ 'Restrict which tickets show the Duplicate button. Leave empty to allow all.',
            )),
            'allowed_depts' => new DeptChoiceField(array(
                'label' => /* trans */ 'Allowed Departments',
                'hint'  => /* trans */ 'Only agents in these departments can duplicate. Leave empty for all.',
                'required' => false,
                'configuration' => array(
                    'multiselect' => true,
                    'prompt' => 'All Departments',
                ),
            )),
            'allowed_topics' => new TopicChoiceField(array(
                'label' => /* trans */ 'Allowed Help Topics',
                'hint'  => /* trans */ 'Only show on tickets with selected help topics. Leave empty for all.',
                'required' => false,
                'configuration' => array(
                    'multiselect' => true,
                    'prompt' => 'All Help Topics',
                ),
            )),
        );
    }
}

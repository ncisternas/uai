<?php
require_once ($CFG->libdir . '/formslib.php');

class local_uai_notification_form extends moodleform {
    
    function definition() {
        global $DB, $CFG;
        
        $mform = $this->_form;
        $instance = $this->_customdata;

        $mform->addElement ( 'header', 'addcourse', get_string('addnotification', 'local_uai') );
        
        // Exam name
        $mform->addElement ( 'text', 'shortname', get_string ( 'shortname') );
        $mform->addRule ( 'shortname', get_string ( 'required' ), 'required', null, 'client' );
        $mform->addRule ( 'shortname', get_string ( 'maximumchars', '', 50 ), 'maxlength', 50, 'client' );
        $mform->setType ( 'shortname', PARAM_TEXT );
        
        // buttons
        $this->add_action_buttons ( true, get_string ( 'submit' ) );
    }
}
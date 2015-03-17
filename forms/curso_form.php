<?php

class local_uai_curso_form extends moodleform {
    
    function definition() {
        global $DB, $CFG;
        
        $mform = $this->_form;
        $instance = $this->_customdata;

        // Exam name
        $mform->addElement ( 'text', 'shortname', get_string ( 'shortname', 'local_uai' ) );
        $mform->addRule ( 'shortname', get_string ( 'required' ), 'required', null, 'client' );
        $mform->addRule ( 'shortname', get_string ( 'maximumchars', '', 50 ), 'maxlength', 50, 'client' );
        $mform->setType ( 'shortname', PARAM_TEXT );
        $mform->addHelpButton ( 'shortname', 'shortname', 'local_uai' );
        
        // buttons
        $this->add_action_buttons ( true, get_string ( 'submit' ) );
    }
}
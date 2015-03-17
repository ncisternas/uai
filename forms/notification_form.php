<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 *
 *
 * @package    local
 * @subpackage uai
 * @copyright  2015 Jorge Villalon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once ($CFG->libdir . '/formslib.php');

class local_uai_notification_form extends moodleform {
    
    function definition() {
        global $DB, $CFG;
        
        $mform = $this->_form;
        $instance = $this->_customdata;

        $mform->addElement ( 'header', 'addcourse', get_string('addnotification', 'local_uai') );
        
        // Course shortname
        $mform->addElement ( 'text', 'shortname', get_string ( 'shortname') );
        $mform->addRule ( 'shortname', get_string ( 'required' ), 'required', null, 'client' );
        $mform->addRule ( 'shortname', get_string ( 'maximumchars', '', 50 ), 'maxlength', 50, 'client' );
        $mform->setType ( 'shortname', PARAM_TEXT );
        
        // buttons
        $this->add_action_buttons ( true, get_string ( 'submit' ) );
    }
}
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
* @copyright  2015 Ilyan Triantafilo
* @copyright  2015 Jorge Villalon
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

//Página para buscar el curso de un alumno.

require_once(dirname(__FILE__) . '/../../config.php');
require_once 'locallib.php';

global $PAGE, $CFG, $OUTPUT, $DB, $USER;

$courseid = required_param('id', PARAM_INT);
$testuserid = optional_param('uid', 0, PARAM_INT);
$debugsend = optional_param('debugsend', false, PARAM_BOOL);

if(!$course = $DB->get_record('course', array('id'=>$courseid))) {
    print_error('Invalid course id');
}

$url = new moodle_url('/local/uai/notifyquizzes.php', array('id'=>$course->id));

$context = context_course::instance($course->id);

require_login($course);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');

$title = 'Enviar notificaciones de cuestionarios';
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

echo $OUTPUT->single_button(new moodle_url('/local/uai/notifyquizzes.php', array('id'=>$course->id, 'debugsend'=>true)), 'Send to yourself', 'GET');

local_uai_send_notifications(false, true, $debugsend, $course->id);

echo $OUTPUT->footer();
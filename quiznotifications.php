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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
/**
 *
 * @package local
 * @subpackage uai
 * @copyright 2015 Jorge Villalón {@link http://www.uai.cl}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once ($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once ('forms/notification_form.php');

// Sorting para for table
$tsort = optional_param('tsort', 'timecreated DESC', PARAM_ALPHA);

// Id of course to remove from notifications
$deleteid = optional_param('delete', 0, PARAM_INT);

// If deletion is confirmed
$confirmdelete = optional_param('confirm', false, PARAM_BOOL);

// We use the system context
$context = context_system::instance();

// The page url
$url = new moodle_url('/local/uai/quiznotifications.php');

// We require login
require_login();

// We require the user to have site configuration permission
require_capability('moodle/site:config', context_system::instance());

// Admin page setup
admin_externalpage_setup('quiznotifications');

// The page header and heading
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quiznotifications', 'local_uai'));

// We create the new notification form to validate if something was submitted
$form = new local_uai_notification_form();

// If something was submitted
if($form->get_data()) {
    $shortname = $form->get_data()->shortname;
    // Validate the course
    if(!$course = $DB->get_record('course', array('shortname'=>$shortname))) {
        echo $OUTPUT->notification(get_string('invalidshortname', 'local_uai'), 'notifyproblem');
    }
    // Validate it is a new notification 
    else if($DB->get_record('local_uai_quiz_notifications', array('course'=>$course->id))) {
        echo $OUTPUT->notification(get_string('notificationalreadyexists', 'local_uai'), 'notifyproblem');
    }
    // Create new notification and show message 
    else {
        $newnotification = new stdClass();
        $newnotification->course = $course->id;
        $newnotification->active = 1;
        $newnotification->timecreated = time();
        if($DB->insert_record('local_uai_quiz_notifications', $newnotification)) {
            echo $OUTPUT->notification(get_string('notificationadded', 'local_uai'), 'notifysuccess');
        } else {
            echo $OUTPUT->notification(get_string('probleminserting', 'local_uai'), 'notifyproblem');
        }
    }
}

// If the user wants to delete a notification
if($deleteid > 0) {
    if(!$qn = $DB->get_record('local_uai_quiz_notifications', array('course'=>$deleteid))) {
        echo $OUTPUT->notification(get_string('invalidcourse', 'local_uai'), 'notifyproblem');
    } else if($confirmdelete) {
        if($DB->delete_records('local_uai_quiz_notifications',  array('course'=>$deleteid))) {
            echo $OUTPUT->notification(get_string('notificationdeleted', 'local_uai'), 'notifysuccess');
        } else {
            echo $OUTPUT->notification(get_string('problemdeleting', 'local_uai'), 'notifyproblem');
        }
    } else if(!$course = $DB->get_record('course', array('id'=>$qn->course))){
            echo $OUTPUT->notification(get_string('invalidcourse', 'local_uai'), 'notifyproblem');
    } else {
        $message = get_string('confirmdeletenotification', 'local_uai', $course);
        $confirmurl = new moodle_url($url, array('delete'=>$deleteid, 'confirm'=>true));
        echo $OUTPUT->confirm($message, new single_button($confirmurl, get_string('confirm')), new single_button($url, get_string('cancel')));
        echo $OUTPUT->footer();
        die();
    }
}

// We create a flexible table (sortable)
$showpages = new flexible_table ('notifications');

// Table headers
$headers = array ();
$headers[] = get_string ('category');
$headers[] = get_string ('course');
$headers[] = get_string ('shortname');
$headers[] = get_string ('created', 'question');
$headers[] = get_string ( 'actions');

// Define flexible table (can be sorted in different ways)
$showpages->define_headers($headers);

// Table columns
$columns = array();
$columns[] = 'category';
$columns[] = 'course';
$columns[] = 'shortname';
$columns[] = 'timecreated';
$columns[] = 'actions';
$showpages->define_columns($columns);

// Define a base url
$showpages->define_baseurl($url);

// The sortable and non sortable columns
$showpages->no_sorting('actions');
$showpages->sortable ( true, 'timecreated', SORT_DESC );
$showpages->pageable ( true );

// We get the count for the data
$numcursos = $DB->count_records('local_uai_quiz_notifications');

// Set the page size
$showpages->pagesize ( 10, $numcursos);

// Setup the table
$showpages->setup ();

// If table is sorted set the query string
if($showpages->get_sql_sort()) {
    $tsort = $showpages->get_sql_sort();
}

// Get the notifications sorted according to table
$notifications = $DB->get_records_sql('
    SELECT c.fullname as course,
           c.shortname,
           qn.timecreated,
           c.id,
           cc.name as category
    FROM {local_uai_quiz_notifications} AS qn 
    INNER JOIN {course} AS c ON (c.id = qn.course)
    INNER JOIN {course_categories} AS cc ON (cc.id = c.category)
    ORDER BY ?', array($tsort));

// Add each row to the table
foreach($notifications as $notification) {
    $data = array();
    $data[] = $notification->category;
    $data[] = $notification->course;
    $data[] = $notification->shortname;
    $data[] = date ( "d M H:i", $notification->timecreated );
    $data[] = $OUTPUT->action_icon(new moodle_url($url, array('delete'=>$notification->id)), new pix_icon('t/delete', get_string('delete')));
    $showpages->add_data($data);
}

// Print the table
$showpages->print_html();

// Print the form
$form->display();

echo $OUTPUT->footer();

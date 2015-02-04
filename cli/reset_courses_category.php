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
 * This script allows you to reset any local user password.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2010 Jorge Villalon (http://villalon.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->libdir.'/moodlelib.php');      // moodle lib functions
require_once($CFG->libdir.'/coursecatlib.php');      // moodle lib functions
require_once($CFG->libdir.'/datalib.php');      // data lib functions
require_once($CFG->libdir.'/accesslib.php');      // access lib functions
require_once($CFG->libdir.'/gradelib.php');      // access lib functions
require_once($CFG->dirroot.'/course/lib.php');      // course lib functions
require_once($CFG->dirroot.'/enrol/guest/lib.php');      // guest enrol lib functions

// now get cli options
list($options, $unrecognized) = cli_get_params(array('help'=>false),
                                               array('h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Reset all course in a category, useful for semester shifts.

A course reset means:
- Deleting all user data, including quiz responses, forum messages, chat sessions, survey answers, etc.
- Unenroling all users, including teachers.
- Adding or activating guest enrolment.

There are no security checks here because anybody who is able to
execute this file may execute any PHP too.

Options:
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/cli/reset_courses_category.php
"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

cli_heading('Reset courses in category'); // TODO: localize

// First, show categories list to the user to pick a category
$list = coursecat::make_categories_list();
echo "\nAvailable categories:\n";
foreach($list as $key => $value) {
	echo "$value (id:$key)\n";
}
$prompt = "\nEnter category id"; // TODO: localize
$categoryid = cli_input($prompt);

// Validate if category id exists
if (!$category = $DB->get_record('course_categories', array('id'=>$categoryid))) {
    cli_error("Can not find category '$categoryid'");
}

$prompt = "You will reset courses in category $category->name. Are you sure? (y/n)"; // TODO: localize
$confirm = cli_input($prompt);

$errmsg = '';//prevent eclipse warning
if ($confirm != 'y') {
    cli_error($errmsg);
}

echo "Reset process started...\n\n";

// Get all the courses in the category
$courses=get_courses($category->id);

// Get all the roles, excluding Manager
$rolesToDelete = array();
$roles=get_all_roles();

foreach($roles as $role) {
	$rolesToDelete[]=$role->id;
}

// For each course, reset data
$i=0;
foreach($courses as $course) {
	echo("\nCourse: $course->shortname $course->fullname \n\n");
	if(strlen($course->shortname)>=3 && substr($course->shortname,strlen($course->shortname)-3,3)=='-BK') {
		echo "Skipping\n";
		continue;
	}
	$data = new stdClass();
	$data->id = $course->id;
	$data->courseid = $course->id;
//	$data->reset_logs=true;
	$data->reset_events=true;
	$data->delete_blog_associations=true;
//	$data->reset_course_completion=true;
	$data->reset_comments=true;
	$data->reset_roles_overrides=true;
	$data->reset_roles_local=true;
	$data->unenrol_users=$rolesToDelete;
	$data->unenrolled=true;
	$data->reset_groups_members=true;
	$data->reset_groups_remove=true;
	$data->reset_groupings_members=true;
	$data->reset_groupings_remove=true;
	
	if($grade_items = grade_item::fetch_all(array('courseid'=>$course->id))) {
		$data->reset_gradebook_grades=true;
	}
	
	$data->reset_comments=true;
	// Forum
	$data->reset_forum_all=true;
	// Assignment
	$data->reset_assign_submissions=true;
	// Chat
	$data->reset_chat=true;
	// Choice
	$data->reset_choice=true;
	// Quiz
	$data->reset_quiz_attempts=true;
	// Survey
	$data->reset_survey_answers=true;
	$status = reset_course_userdata($data);
	$i++;
	$error=false;
	foreach($status as $key => $return) {
		echo $return['component']." ".$return['item']." ";
		if(!empty($return['error'])) {
			echo "ERROR! ".$return['error'];
			$error=true;
		}
		echo "\n";
	}
	if($error) {
		continue;
	}
	$enrol = enrol_get_plugin('guest');
	$instance = $DB->get_record('enrol', array('courseid'=>$course->id,'enrol'=>'guest'));
	if(!$instance) {
		$instanceid = $enrol->add_default_instance($course);
    	$instance = $DB->get_record('enrol', array('id'=>$instanceid));
	}
	$instance->status = ENROL_INSTANCE_ENABLED;
    $DB->update_record('enrol', $instance);
	$course->shortname = $course->shortname . '-BK';
	update_course($course);
}
echo("\n\n");

echo "$i courses in category $category->name processed successfully\n";

exit(0); // 0 means success

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
"Removes self enrolment from courses for an entire category.

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

cli_heading('Remove self enrolment from courses in category'); // TODO: localize

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

$prompt = "You will remove self enrolment from all courses in category $category->name. Are you sure? (y/n)"; // TODO: localize
$confirm = cli_input($prompt);

$errmsg = '';//prevent eclipse warning
if ($confirm != 'y') {
    cli_error($errmsg);
}

echo "Removing process started...\n\n";

// Get all the courses in the category
$courses=get_courses($category->id);

// For each course, remove selfenrolment
$i=0;
foreach($courses as $course) {
	echo("\nCourse: $course->shortname $course->fullname ");

	$enrol = enrol_get_plugin('self');
	$instance = $DB->get_record('enrol', array('courseid'=>$course->id,'enrol'=>'self'));
	if($instance) {
		$enrol->delete_instance($instance);
		echo(" removing self...");
	}
	
	$enrol = enrol_get_plugin('guest');
	$instance = $DB->get_record('enrol', array('courseid'=>$course->id,'enrol'=>'guest'));
	if($instance) {
		$enrol->delete_instance($instance);
		echo(" removing guest...");
	}
	$i++;
	echo("\n\n");
}
echo("\n\n");

echo "$i courses in category $category->name processed successfully\n";

exit(0); // 0 means success

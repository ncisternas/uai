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
 * This script allows to send Workshop's feedback to students' emails.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2010 Jorge Villalon (http://www.villalon.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->libdir.'/moodlelib.php');      // moodle lib functions
require_once($CFG->libdir.'/datalib.php');      // data lib functions
require_once($CFG->libdir.'/accesslib.php');      // access lib functions
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
"Send feedback to students as reviewers from a workshop of a course.

There are no security checks here because anybody who is able to
execute this file may execute any PHP too.

Options:
-h, --help            Print out this help

Example:
\$sudo -u apache /usr/bin/php admin/cli/send_workshop_feedback.php
"; //TODO: localize - to be translated later when everything is finished

	echo $help;
	die;
}

cli_heading('Send workshop feedback'); // TODO: localize

$prompt = "\nEnter course id"; // TODO: localize
$courseid = cli_input($prompt);

// Validate if category id exists
if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
	cli_error("Can not find course '$courseid'");
}

echo "Course found: $course->fullname \n";

// Validate if category id exists
if (!$workshops = $DB->get_records('workshop', array('course'=>$courseid))) {
	cli_error("Can not find workshop for course '$courseid'");
}

echo "\nListing workshops in $course->fullname \n";
foreach($workshops as $key => $workshop) {
	echo "$workshop->id : $workshop->name \n";
}

$prompt = "Please enter workshop id:"; // TODO: localize
$workshopid = cli_input($prompt);

// Validate if category id exists
if (!$workshop = $DB->get_record('workshop', array('id'=>$workshopid))) {
	cli_error("Can not find workshop with id '$workshopid'");
}

if (!$feedbacks = $DB->get_records_sql("select s.title as title, " .
    "u2.username as reviewer, " .
    "u.username as author,  " .
    "a.feedbackreviewer as feedback " .
	"from {workshop_assessments} as a " .
	"inner join {workshop_submissions} as s on (a.submissionid = s.id) " .
	"inner join {workshop} as w on (w.id = s.workshopid) " .
	"inner join {user} as u on (u.id = s.authorid) " .
	"inner join {user} as u2 on (u2.id = a.reviewerid) " .
	"where w.id = $workshopid")) {
cli_error("No feedback found for workshop $workshop->name in course $course->fullname");
	}

	$i=0;
	foreach($feedbacks as $feedback) {
		$posttext = '<html>';
		$posttext .= '<h3>Evaluación de tu revisión</h3>';
		$posttext .= 'Envío que revisaste:<br>' . $feedback->title . '<br>';
		$posttext .= 'Evaluación:<br>' . $feedback->feedback;
		$posttext .= '</html>';

		$subject = "Evaluación de tu revisión en $workshop->name";

		$headers = "From: $workshop->name $course->shortname \r\n" .
    "Reply-To: noreply@webcursos.cloudlab.cl\r\n" .
    'Content-Type: text/html; charset="utf-8"' . "\r\n" .
	'X-Mailer: PHP/' . phpversion();

		$emailAlumno = str_replace("@uai.cl", "@alumnos.uai.cl", $feedback->reviewer);
		if($i==0) {
			echo "\nEmail sample:\n\n";
			echo "To: $emailAlumno \n";
			echo $headers . "\n";
			echo $subject . "\n";
			echo $posttext . "\n";
			$prompt = "\nDou you want to send the messages (y/n)?"; // TODO: localize
			$yesno = cli_input($prompt);

			if($yesno != "y") {
				echo "Bye\n";
				exit(1);
			}

		}

		echo "Sending mail to:$emailAlumno ";
		mail($emailAlumno, $subject, $posttext, $headers);
		echo(" ... sent!\n");
		$i++;
	}

	echo("\n\n");

	echo "$i emails sent successfully\n";

	exit(0); // 0 means success

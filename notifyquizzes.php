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

require_once(dirname(__FILE__) . '/../../config.php'); //obligatorio

global $PAGE, $CFG, $OUTPUT, $DB, $USER;

$courseid = required_param('id', PARAM_INT);
$testuserid = optional_param('uid', 0, PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$courseid))) {
    print_error('Invalid course id');
}

$url = new moodle_url('/local/uai/notifyquizzes.php');

$context = context_course::instance($course->id);

require_login($course);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');

$title = 'Enviar avisos de quizzes';
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$attempts = $DB -> get_recordset_sql(
    "SELECT 
    U.uid,
    firstname,
    lastname,
    email,
    IFNULL(finished, 0) as finished,
	IFNULL(maxscore, 0) as maxscore, 
	IFNULL(minscore, 0) as minscore, 
	IFNULL(avgscore, 0) as avgscore, 
    IFNULL(qmaxscore, 0) as qmaxscore,
    IFNULL(recent, 0) as recent,
    IFNULL(correct, 0) as correct,
	IFNULL(qids, 0) AS qids,
	IFNULL(qname, 0) as qname,
    IFNULL(timefinish, 0) as timefinish    
FROM
(SELECT u.id as uid,
		u.firstname,
        u.lastname,
		u.email
FROM mdl_user_enrolments ue
JOIN mdl_enrol e ON (e.id = ue.enrolid AND e.courseid = :courseid)
JOIN mdl_context c ON (c.contextlevel = 50 AND c.instanceid = e.courseid)
JOIN mdl_role_assignments ra ON (ra.contextid = c.id AND ra.roleid = 5 AND ra.userid = ue.userid)
JOIN mdl_user u ON (ue.userid = u.id)
GROUP BY u.id) AS U
LEFT JOIN
(
SELECT uid as uid, 
        SUM(finished) AS finished,
		MAX(score) as maxscore, 
		MIN(score) as minscore, 
		AVG(score) as avgscore, 
        MAX(maxscore) as qmaxscore,
        SUM(recent) as recent,
        SUM(correct) as correct,
		COUNT(distinct qid) AS qids,
		GROUP_CONCAT(qname) as qname,
        MAX(timefinish) as timefinish
FROM (
SELECT u.id as uid, 
        CASE WHEN qa.state = 'finished' THEN 1 ELSE 0 end AS finished,
		IFNULL(qa.sumgrades,0) AS score, 
        q.sumgrades AS maxscore,
        CASE WHEN qa.timefinish > unix_timestamp(ADDDATE(now(), INTERVAL -7 DAY)) THEN 1 ELSE 0 END as recent,
        CASE WHEN q.sumgrades = qa.sumgrades THEN 1 ELSE 0 END as correct,
		q.id AS qid,
		q.name AS qname,
        qa.timefinish
FROM {quiz} AS q 
INNER JOIN {quiz_attempts} AS qa ON (q.course = :courseid2 AND qa.quiz = q.id)
INNER JOIN {user} AS u ON (u.id = qa.userid)) AS T
GROUP BY uid) AS Q
ON (U.uid = Q.uid)",
    array('courseid'=>$courseid, 'courseid2'=>$courseid));

$userfrom = core_user::get_noreply_user();
$userfrom->maildisplay = true;

$coursestats = array();
$studentinfo = array();
$coursestats['avgscore'] = 0;
$coursestats['maxscore'] = 0;
$coursestats['maxuser'] = 0;
$coursestats['avgfinished'] = 0;
$coursestats['maxfinished'] = 0;
$coursestats['maxfinisheduser'] = 0;

$total=0;
$totalfinished=0;
foreach($attempts as $attempt)	{
    $studentinfo[$attempt->uid] = $attempt;
    if($coursestats['maxfinished'] < $attempt->finished) {
        $coursestats['maxfinished'] = $attempt->finished;
        $coursestats['maxfinisheduser'] = $attempt->uid;
    }
    if($coursestats['maxscore'] < $attempt->maxscore) {
        $coursestats['maxscore'] = $attempt->maxscore;
        $coursestats['maxuser'] = $attempt->uid;
    }
    if($attempt->avgscore > 0) {
        $coursestats['avgscore'] = 0;
        $total++;
    }
    if($attempt->finished > 0) {
        $coursestats['avgfinished'] += $attempt->finished;
        $totalfinished++;
    }
}
if($total > 0) {
    $coursestats['avgscore'] = round($coursestats['avgscore'] / $total, 1);
}
if($totalfinished > 0) {
    $coursestats['avgfinished'] = round($coursestats['avgfinished'] / $totalfinished, 1);
}

// Create progress bar
$pbar = new progress_bar('messagessent', 500, true);
$total = count($studentinfo);
$current = 1;

echo $OUTPUT->footer();

foreach($studentinfo as $uid => $studentinfo)	{

    $userto = null;
    if($testuserid > 0) {
        $userto = $DB->get_record('user', array('id'=>$testuserid));
    } else {
        $userto = $DB->get_record('user', array('id'=>$uid));
    }
    
    $pbar->update($current, $total, 'Notificando a ' . $userto->firstname . ' ' . $userto->lastname);
    
    $subject = 'Notificacion de intentos en tus quizzes.';

    $message = '<html>';
    $message .= 'Estimado(a) ' . $studentinfo->firstname . ' ' . $studentinfo->lastname . ' ,';
    $message .= '<br/>';
    $message .= 'Quiero que notes que respecto de tu trabajo con los ejercicios de wiris:<br/>';
    $message .= 'Esta semana realizaste  ' . $studentinfo->recent . '  intentos, de los cuales contestaste adecuadamente ' . $studentinfo->correct . '.<br/>';
    $message .= 'Desde el inicio del curso hasta ahora llevas acumulado un trabajo de ' . $studentinfo->finished .' intentos y en promedio un ';
    $message .= 'alumno del curso ha trabajado ' . $coursestats['avgfinished'] . ',  con un máximo de ' . $coursestats['maxfinished'] . ' intentos.<br/><br/>';
    $message .= 'Quedo en espera de tus dudas.';
    $message .= '</html>';
    
    $eventdata = new stdClass();
    $eventdata->component 		  = 'local_uai';
    $eventdata->name              = 'quizzes_notification';
    $eventdata->userto = $userto;
    $eventdata->userfrom		  = $userfrom;
    $eventdata->subject           = $subject;
    $eventdata->fullmessage       = format_text_email($message,FORMAT_HTML);
    $eventdata->fullmessageformat = FORMAT_HTML;
    $eventdata->fullmessagehtml   = $message;
    $eventdata->smallmessage      = $subject;
    $eventdata->notification      = 1; //this is only set to 0 for personal messages between users

    if($userto) {
        $send = message_send($eventdata);
        $current++;
        if($current == $total) {
            $pbar->update_full(100, 'Mensajes enviados');
        }
    } else {
        echo "Error sending message to $userto <hr>";
    }
}


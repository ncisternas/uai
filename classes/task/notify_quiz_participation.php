<?php
namespace local_uai\task;
use \stdClass;

class notify_quiz_participation extends \core\task\scheduled_task
{

    public function get_name()
    {
        // Shown in admin screens
        return get_string('notifyquizparticipation', 'local_uai');
    }

    public function execute()
    {
        global $CFG, $DB;
        
        require_once($CFG->dirroot . '/local/uai/locallib.php');
        
        mtrace("Starting quiz notifications");
        
        // Get the notifications configured
        $quiznotifications = $DB->get_records('local_uai_quiz_notifications');
        
        // If there are any
        if (count($quiznotifications) > 0) {
            
            // Process each course separatedly
            foreach ($quiznotifications as $quiznotification) {
                if (! $course = $DB->get_record('course', array(
                    'id' => $quiznotification->course
                ))) {
                    mtrace('Invalid course id ' . $quiznotification->course);
                    continue;
                }
                
                mtrace('Processing notifications for course ' . $course->fullname);
                
                // Get the attempts
                $attempts = local_uai_get_quiz_attempts($course->id);
                
                // Course stats default values and student info
                $coursestats = new stdClass();
                $coursestats->avgscore = 0;
                $coursestats->maxscore = 0;
                $coursestats->maxuser = 0;
                $coursestats->avgfinished = 0;
                $coursestats->maxfinished = 0;
                $coursestats->maxfinisheduser = 0;
                $studentinfo = array();
                
                $total = 0;
                $totalfinished = 0;
                foreach ($attempts as $attempt) {
                    $studentinfo[$attempt->uid] = $attempt;
                    if ($coursestats->maxfinished < $attempt->finished) {
                        $coursestats->maxfinished = $attempt->finished;
                        $coursestats->maxfinisheduser = $attempt->uid;
                    }
                    if ($coursestats->maxscore < $attempt->maxscore) {
                        $coursestats->maxscore = $attempt->maxscore;
                        $coursestats->maxuser = $attempt->uid;
                    }
                    if ($attempt->avgscore > 0) {
                        $coursestats->avgscore = 0;
                        $total ++;
                    }
                    if ($attempt->finished > 0) {
                        $coursestats->avgfinished += $attempt->finished;
                        $totalfinished ++;
                    }
                }
                if ($total > 0) {
                    $coursestats->avgscore = round($coursestats->avgscore / $total, 1);
                }
                if ($totalfinished > 0) {
                    $coursestats->avgfinished = round($coursestats->avgfinished / $totalfinished, 1);
                }
                
                $userfrom = \core_user::get_noreply_user();
                $userfrom->maildisplay = true;
                
                $current = 1;
                foreach ($studentinfo as $uid => $studentinfo) {
                    
                    // The user to be notified
                    $userto = $DB->get_record('user', array(
                        'id' => $uid
                    ));
                    
                    // Email subject
                    $subject = 'Notificacion de intentos en tus quizzes.';
                    
                    $message = '<html>';
                    $message .= '<p><strong>Estimado(a) ' . $studentinfo->firstname . ' ' . $studentinfo->lastname . '</strong>,</p>';
                    $message .= '<p>Quiero que notes que respecto de tu trabajo con los ejercicios de wiris:</p>';
                    $message .= '<p>Esta semana realizaste  ' . $studentinfo->recent . '  intentos';
                    if ($studentinfo->correct > 0) {
                        $message .= ', de los cuales contestaste adecuadamente ' . $studentinfo->correct;
                    }
                    $message .= '.<br/>';
                    $message .= 'Desde el inicio del curso hasta ahora llevas acumulado un trabajo de ' . $studentinfo->finished . ' intentos y en promedio un ';
                    $message .= 'alumno del curso ha trabajado ' . $coursestats->avgfinished . ',  con un máximo de ' . $coursestats->maxfinished . ' intentos.</p><br/><br/>';
                    $message .= 'Quedo en espera de tus dudas.';
                    $message .= '</html>';
                    
                    $eventdata = new stdClass();
                    $eventdata->component = 'local_uai';
                    $eventdata->name = 'quizzes_notification';
                    $eventdata->userto = $userto;
                    $eventdata->userfrom = $userfrom;
                    $eventdata->subject = $subject;
                    $eventdata->fullmessage = format_text_email($message, FORMAT_HTML);
                    $eventdata->fullmessageformat = FORMAT_HTML;
                    $eventdata->fullmessagehtml = $message;
                    $eventdata->smallmessage = $subject;
                    $eventdata->notification = 1; // this is only set to 0 for personal messages between users
                    
                    if ($userto) {
                        $send = message_send($eventdata);
                        $current ++;
                    } else {
                        mtrace("Error sending message to $userto");
                    }
                }
                mtrace($current . ' messages sent.');
            }
        } else {
            mtrace("No courses to notify. Closing.");
        }
    }
}
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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/lib.php');      // Librería de funciones de Course
require_once("$CFG->libdir/formslib.php");

/**
 * Gets all attempts
 * @param unknown $courseid
 * @return unknown
 */
function local_uai_get_quiz_attempts($courseid, $interval) {
    global $DB;
    
    $attempts = $DB->get_recordset_sql(
   "SELECT
    U.uid,
    firstname,
    lastname,
    email,
    IFNULL(finished, 0) as finished,
    IFNULL(correct, 0) as correct,
	IFNULL(maxscore, 0) as maxscore,
	IFNULL(minscore, 0) as minscore,
	IFNULL(avgscore, 0) as avgscore,
    IFNULL(qmaxscore, 0) as qmaxscore
FROM
(SELECT u.id as uid,
		u.firstname,
        u.lastname,
		u.email
FROM mdl_user_enrolments AS ue
JOIN mdl_enrol AS e ON (e.id = ue.enrolid AND e.courseid = :courseid)
JOIN mdl_context AS c ON (c.contextlevel = 50 AND c.instanceid = e.courseid)
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
        SUM(correct) as correct
FROM (
SELECT u.id as uid,
        CASE WHEN qa.state = 'finished' THEN 1 ELSE 0 end AS finished,
		IFNULL(qa.sumgrades,0) AS score,
        q.sumgrades AS maxscore,
        CASE WHEN q.sumgrades = qa.sumgrades THEN 1 ELSE 0 END as correct
FROM mdl_quiz AS q
INNER JOIN mdl_quiz_attempts AS qa ON (q.course = :courseid2 AND qa.quiz = q.id AND (q.timeopen >= unix_timestamp(ADDDATE(now(), INTERVAL :interval DAY)) OR q.timeopen = 0))
INNER JOIN mdl_user AS u ON (u.id = qa.userid)
ORDER BY q.id) AS T
GROUP BY uid) AS Q
ON (U.uid = Q.uid)", array(
        'courseid' => $courseid,
        'courseid2' => $courseid,
        'interval' => $interval
    ));

    return $attempts;
}

/**
 * 
 * @param unknown $attempts
 * @return multitype:stdClass multitype:unknown
 */
function local_uai_get_stats_from_attempts($attempts) {
    $studentinfo = array();
    
    // Course stats default values and student info
    $coursestats = new stdClass();
    $coursestats->avgfinished = 0;
    $coursestats->maxfinished = 0;
    $coursestats->maxfinisheduser = 0;
    
    $totalfinished = 0;
    foreach ($attempts as $attempt) {
        // Store student info
        $studentinfo[$attempt->uid] = $attempt;
    
        // Calculate stats
        if ($coursestats->maxfinished < $attempt->finished) {
            $coursestats->maxfinished = $attempt->finished;
            $coursestats->maxfinisheduser = $attempt->uid;
        }
        if ($attempt->finished > 0) {
            $coursestats->avgfinished += $attempt->finished;
            $totalfinished ++;
        }
    }
    if ($totalfinished > 0) {
        $coursestats->avgfinished = round($coursestats->avgfinished / $totalfinished, 1);
    }
    
    return array($studentinfo, $coursestats);
}

function local_uai_send_notifications($cron = true, $debug = false, $debugsend = false, $course = 0) {
    global $DB, $USER;
    
    // Get the notifications configured
    if($course)
        $quiznotifications = $DB->get_records('local_uai_quiz_notifications', array('course'=>$course));
    else
        $quiznotifications = $DB->get_records('local_uai_quiz_notifications');
    $numnotifications = count($quiznotifications);
    
    // If there are any
    if ($numnotifications > 0) {
    
        // Process each course separatedly
        foreach ($quiznotifications as $quiznotification) {
            if (! $course = $DB->get_record('course', array(
                'id' => $quiznotification->course
            ))) {
                $msg = 'Invalid course id ' . $quiznotification->course;
                if($cron)
                    mtrace($msg);
                else if($debug)
                    echo $msg;
                continue;
            }
    
            $msg = 'Processing notifications for course ' . $course->fullname;
            if($cron) {
                mtrace($msg);
            } else if($debug) {
                echo $msg;
            }
    
            // Get the attempts
            $attemptsSemester = local_uai_get_quiz_attempts($course->id, -365);
    
            // Calculate stats for the attempts
            list($studentinfoSemester, $coursestatsSemester) = local_uai_get_stats_from_attempts($attemptsSemester);
    
            // Get the attempts
            $attempts = local_uai_get_quiz_attempts($course->id, -7);
    
            $userfrom = \core_user::get_noreply_user();
            $userfrom->maildisplay = true;
    
            $totalmessages = 1;
            foreach ($attempts as $studentinfo) {
                // The user to be notified
                if($debugsend) {
                    $userto = $DB->get_record('user', array(
                        'id' => $USER->id
                    ));
                } else {
                    $userto = $DB->get_record('user', array(
                        'id' => $studentinfo->uid
                    ));
                }
    
                // Email subject
                $subject = 'Informe de tu trabajo on-line';
    
                $message = '<html>';
                $message .= '<p><strong>Estimado(a) ' . $studentinfo->firstname . ' ' . $studentinfo->lastname . '</strong>,</p>';
                $message .= '<p>Quiero que notes que respecto de tu trabajo con los ejercicios de wiris:</p>';
                $message .= '<p>Esta semana realizaste  ' . $studentinfo->finished . '  intentos';
                if ($studentinfo->correct > 0) {
                    $message .= ', de los cuales contestaste adecuadamente ' . $studentinfo->correct;
                }
                $message .= '.<br/>';
                $message .= 'Desde el inicio del curso hasta ahora llevas acumulado un trabajo de ' . $studentinfoSemester[$studentinfo->uid]->finished . ' intentos y en promedio un ';
                $message .= 'alumno del curso ha trabajado ' . $coursestatsSemester->avgfinished . ',  con un máximo de ' . $coursestatsSemester->maxfinished . ' intentos.</p><br/><br/>';
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
                    if(!$debug || $debugsend) {
                        $send = message_send($eventdata);
                    }
                    if($debug) {
                        echo '<hr>';
                        echo 'Subject: ' . $subject . '<br/>';
                        echo "To: $userto->firstname $userto->lastname &lt;$userto->email&gt;<br/>";
                        echo $message;
                    }
                    $totalmessages ++;
                } else if($cron) {
                    mtrace("Error sending message to $userto");
                }
                
                if($debugsend)
                    break;
            }
        }
    }

    return array($totalmessages, $numnotifications);
}
/**
 * Esta clase es la que relaciona los contenidos con
 * la BBDD de Omega.
 */
class omega {
	/**
	 * Se permite realizar una conexión con la BBDD de Omega
	 */
	function conexion(){
		//Se utilizan los datos guardados en freeTDS de "OmegaDB"
		if (!$db = mssql_connect('OmegaDB', 'webcursos', 'uai2011'))
		{
			die("No se ha podido conectar a la base de datos.<br />");
		}
		$BBDD = "OmegaDB";
		mssql_select_db($BBDD, $db);
	}
	
	
	/**
	 * Aquí se permite obtener la información de los periodos académicos de Omega.
	 */
	function obtenerPeriodosAcademicos(){
		//Se realiza la conexión con Omega (MSSQL)
		self::conexion();
		//Se realiza la consulta
		$sql = mssql_query("Select PeriodoAcademicoId, 
									NombrePerdiodo, 
									UnidadAcademica, 
									FechaInicio, 
									FechaTermino, 
									Estado, 
									TextoEstado, 
									Responsable, 
									Sede 
							From WebCursos_PeriodosAcademicos");
		$headers = array('NombrePerdiodo', 'UnidadAcademica', 'FechaInicio', 'FechaTermino', 'Estado', 'TextoEstado', 'Responsable', 'Sede');
		//Se extrae la información consutada
		$data = array();
		while($fila = mssql_fetch_assoc($sql)){
			foreach($headers as $header){
				$id = $fila['PeriodoAcademicoId'];
				$data[$id][$header] = $fila[$header];	
			}
		}
		mssql_free_result($sql);
		return $data;
	}
	

	
	function listarPeriodosAcademicos(){

		//Una condición extra en el query hacia la BDD, en el caso de que se quieran filtrar los periodos
		if(isset($_GET['unidad']))	{
			$condicionUnidadAcademica = "And UnidadAcademica = '". $_GET['unidad'] ."'";
		}
		else {
			$condicionUnidadAcademica = '';
		}
		
		//Se realiza la conección con Omega (MSSQL)
		self::conexion();
		//Se realiza la consulta
		$sql = mssql_query("Select PeriodoAcademicoId, 
									NombrePerdiodo, 
									UnidadAcademica, 
									FechaInicio, 
									FechaTermino, 
									Estado, 
									TextoEstado, 
									Responsable, 
									Sede 
							From WebCursos_PeriodosAcademicos
							Where TextoEstado != 'Cerrado'".
							$condicionUnidadAcademica
							."Order by UnidadAcademica desc;");
		
		//Se extrae la información consutada
		$data = array();
		while($fila = mssql_fetch_assoc($sql)){
				$id = $fila['PeriodoAcademicoId'];
				$data[$id] = $fila['UnidadAcademica'] ." | " .$fila['NombrePerdiodo'] ." | " .$fila['Sede'] ." (" .$fila['TextoEstado'] .")";	
			
		}
		mssql_free_result($sql);
		return $data;
	}
	
	function listarUnidadesAcademicas(){
		//Se realiza la conexión con Omega (MSSQL)
		self::conexion();
		//Se realiza la consulta
		$sql = mssql_query("Select Distinct UnidadAcademica
				From WebCursos_PeriodosAcademicos
				Order by UnidadAcademica desc;");
	
		//Se extrae la información consultada
		$data = array();
		//Primera opción variable: "Indicaciones" � "Unidad seleccionada"
		if(isset($_GET['unidad'])) {
			$data['selected'] = $_GET['unidad'];
		}
		else {
			$data['selected'] = "Seleccionar Unidad para filtrar Periodos";
		}
		while($fila = mssql_fetch_assoc($sql)){
			$id = $fila['UnidadAcademica'];
			$data[$id] = $id;
				
		}
		mssql_free_result($sql);
		return $data;
	}
	
}

/**
 * Esta clase permite relacionar los contenidos con la
 * BBDD de Sync_data  
 */
class syncData {
	/**
	 * Se realiza la conexión a la BBDD de sync_data
	 */
	private function conexion(){
		$conexion = mysqli_connect('webcursos-db.uai.cl', 'webcursos', 'arquitectura.2015', 'omega');
		if (!$conexion) {
		    die('Error de Conexión (' . mysqli_connect_errno() . ') '
            . mysqli_connect_error());
			}	
		mysqli_set_charset($conexion, "utf8");
		return $conexion;
	}
	
	/**
	 * Se seleccionan los datos de la tabla Sync_Data para mostrarlos en la página principal
	 */
	function seleccionarDatos(){
		global $CFG;
		$conexion = self::conexion();
		
		$sql = "SELECT periodo_academico_id, 
						categoria_id,
						active 
				FROM sync_data ORDER BY active DESC, periodo_academico_id DESC;";
		$query = mysqli_query($conexion, $sql);
		
		$data = array();
		$contador = 0;
		$ruta = $CFG->wwwroot.'/local/uai/';
		
		//Se obtienen las categorías de moodle
		$webcursos = new webcursos(); 
		$categorias = $webcursos->obtenerCategorias();
		
		//Se obtienen los periodos académicos de Omega
		$omega = new omega();
		$omegaData = $omega->obtenerPeriodosAcademicos();
		
		while($fila = mysqli_fetch_assoc($query)){
			
			
			$data[$contador]['periodo_academico_id'] = html_writer::start_tag('p',array('title'=>$omegaData[$fila['periodo_academico_id']]['UnidadAcademica']));
			$data[$contador]['periodo_academico_id'] .= $omegaData[$fila['periodo_academico_id']]['NombrePerdiodo'];
			$data[$contador]['periodo_academico_id'] .= html_writer::end_tag('p');
			//Se obtiene la categoría de moodle a la que corresponde la id.
			$categoria = $categorias[$fila['categoria_id']];
			$data[$contador]['categoria_id'] = html_writer::start_tag('p',array('title'=>$categoria));
			$data[$contador]['categoria_id'] .= $webcursos->ultimaCategoria($categoria);
			$data[$contador]['categoria_id'] .= html_writer::end_tag('p');
			
			$data[$contador]['sede'] = $omegaData[$fila['periodo_academico_id']]['Sede'];
			$data[$contador]['inicio'] = $omegaData[$fila['periodo_academico_id']]['FechaInicio'];
			$data[$contador]['termino'] = $omegaData[$fila['periodo_academico_id']]['FechaTermino'];
			
			//En caso que sea activo, debe indicarse con un ícono que así lo señale.
			$pai = $fila['periodo_academico_id'];  	//pai = periodo academico id
			$ci = $fila['categoria_id'];			//ci = categoría id
			
			if($fila['active']){
				
				$data[$contador]['active'] = html_writer::start_tag('a',array('href'=>$ruta.'syncomega.php?accion=activo&valor=1&omega='.$pai.'&categoria='.$ci)) 
											.html_writer::empty_tag('img',array('src'=>$ruta.'pix/marked.png')) 
											.html_writer::end_tag('a');
											
			}else{
				
				$data[$contador]['active'] = html_writer::start_tag('a',array('href'=>$ruta.'syncomega.php?accion=activo&valor=0&omega='.$pai.'&categoria='.$ci)) 
											.html_writer::empty_tag('img',array('src'=>$ruta.'pix/marker.png')) 
											.html_writer::end_tag('a');
											
			}
			
			//En la última fila debe permitir eliminar
			$data[$contador][6]= " " .html_writer::start_tag('a',array('href'=>$ruta.'syncomega.php?accion=eliminar&omega='.$pai.'&categoria='.$ci)) 
									.html_writer::empty_tag('img',array('src'=>$ruta.'pix/delete.png'))
									.html_writer::end_tag('a');
		
			
			$contador++;
		}
		
		return $data;
		mysqli_close($conexion);
	}

	/**
	 * Elimina un registro de la tabla Sync_Data
	 */
	function eliminarDatos($periodo_academico_id, $categoria_id){
		global $CFG;
		$conexion = self::conexion();
		
		$sql = "Delete FROM sync_data WHERE periodo_academico_id = '$periodo_academico_id' and categoria_id='$categoria_id';";
		$query = mysqli_query($conexion, $sql);
		mysqli_close($conexion);
	}
	
	/**
	 * Cambia la opción de visibilidad de la tabla Sync_Data
	 */
	function activarDatos($valor, $omega, $categoria){
		global $CFG;
		$conexion = self::conexion();
		
		if($valor==1){
			$valor = 0;
		}else{
			$valor = 1;
		}
		
		$sql = "UPDATE sync_data SET active = $valor WHERE periodo_academico_id = '$omega' and categoria_id='$categoria';";
		$query = mysqli_query($conexion, $sql);
		mysqli_close($conexion);
	}
	
	/**
	 * Función que permite insertar en la BBDD de Sync_data los registros creados
	 */
	function insertarDatos($periodoAcademico, $categoriaMoodle, $activo){
		global $CFG;
		$conexion = self::conexion();
		
		$sql = "INSERT INTO sync_data (periodo_academico_id, categoria_id, active) VALUES ('$periodoAcademico', '$categoriaMoodle', '$activo');";
		$query = mysqli_query($conexion, $sql);
		mysqli_close($conexion);
	}
	
}
	
class webcursos {
	
	function obtenerCategorias() {
		$list = coursecat::make_categories_list();
		return $list;
	}
	
	function ultimaCategoria($texto){
		$elementos = explode("/", $texto);
		$cantidad = count($elementos);
		$categoria = $elementos[$cantidad-1];
		return $categoria;
	}
}

/**
 * Clase que permite crear un formulario la página agregar.php
 */
class form_agregar extends moodleform {
	
	function definition() {
        global $CFG;

        $mform =& $this->_form;
        $category = $this->_customdata['category'];
        
        $omega = new omega();
        
        //con esto se obtienen las unidades academicas de Omega
        $unidadesAcademicas = $omega->listarUnidadesAcademicas();
        $attributes = array('id'=>'unidad');
        $mform->addElement('select', 'idOmega', 'Unidad Académica', $unidadesAcademicas, $attributes);
        
        //con esto se obtienen los periodos acad�micos de Omega
        $periodosAcademicos = $omega->listarPeriodosAcademicos();
        $mform->addElement('select', 'idOmega', 'Periodo Académico', $periodosAcademicos);
        
        //lo siguiente permite desplegar una lista con todas las categorías de moodle anidadas
        $displaylist = coursecat::make_categories_list();
        $mform->addElement('select', 'category', 'Categoría Moodle', $displaylist);
        
        //Y finalmente un select simple para designar si está o no activo.
		$mform->addElement('select', 'activo', 'Activo', array(0=>'Desactivar', 1=>'Activar')); //Select para configurar si el campo está activo o no
		$this->add_action_buttons(true,'Crear');
    }
} 

?>
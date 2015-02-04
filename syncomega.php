<?php
/**
 * @package		local
 * @author		Ignacio Opazo, Jorge Villalón
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('syncomega');

//Se obtienen los parámetros de la URL
$accion = optional_param('accion', '', PARAM_ALPHA);
$omega = optional_param('omega', 0, PARAM_INT);
$categoria = optional_param('categoria', 0, PARAM_INT);
$valor = optional_param('valor', -1, PARAM_INT);

$syncData = new syncData();

if($accion!='' && $omega!=0 && $categoria!=0){
	if($accion == 'activo' && $valor != -1){
		$syncData->activarDatos($valor, $omega, $categoria);
	}elseif($accion == 'eliminar'){
		$syncData->eliminarDatos($omega, $categoria);
	}
}

//Se define el formato de la página y su contenido.
$PAGE->set_pagelayout('standard');
$renderer = $PAGE->get_renderer('local_uai');

$data = $syncData->seleccionarDatos();

echo $renderer->index_page($data);


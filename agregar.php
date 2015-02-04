<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('syncomega');


//Se define el formato de la página y su contenido.
$PAGE->set_pagelayout('standard');
$renderer = $PAGE->get_renderer('local_uai');

$formulario = new form_agregar(); //Se crea el objeto $formulario

echo $renderer->adddata_page_start();

     if($data = $formulario->get_data()){ 
     	//Se obtienen los datos del formulario
     	$idCategoria = $data->category;
     	$activo = $data->activo;
     	$periodoAcademico = $data->idOmega;
     	$syncData = new syncData();
     	$syncData->insertarDatos($periodoAcademico, $idCategoria, $activo);
     	echo "La sincronización se ha creado con éxito. Estos cambios serán visibles en
     	la próxima actualización automática.";
     	
     }else{
     	$formulario->display(); //se muestra el formulario creado en el locallib
     }
echo $renderer->adddata_page_end();

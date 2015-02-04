<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // needs this condition or there is error on login page
	
	// Agregando UAI al menu de administración
	$ADMIN->add('root', new admin_category('uai', 'Administración UAI'));
	$ADMIN->add('uai', new admin_externalpage('syncomega', 'Sincronización con Omega',
	new moodle_url('/local/uai/syncomega.php')));
	
	$settings = new admin_settingpage('local_uai', 'UAI');
	$ADMIN->add('localplugins', $settings);
	
	// Terminos y condiciones
	$settings->add(new admin_setting_configcheckbox('local_uai_termsupload',
			'Términos y condiciones en subida de archivos',
			'Si se habilita, se pedirá aceptar términos y condiciones en el formulario de subida de archivos.', 
			'', '1', '0'));
	$settings->add(new admin_setting_configcheckbox('local_uai_debug',
			'Debuggin webcursos',
			'Si se habilita, se mostraran los avances que están en desarrollo.',
			'', '1', '0'));

}

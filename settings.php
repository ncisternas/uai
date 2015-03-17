<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // needs this condition or there is error on login page
	
	// Agregando UAI al menu de administración
	$ADMIN->add('root', new admin_category('uai', get_string('pluginname', 'local_uai')));
	$ADMIN->add('uai', new admin_externalpage('syncomega', get_string('syncomega', 'local_uai'),
	new moodle_url('/local/uai/syncomega.php')));
	$ADMIN->add('uai', new admin_externalpage('quiznotifications', get_string('quiznotifications', 'local_uai'),
	new moodle_url('/local/uai/quiznotifications.php')));
	
	$settings = new admin_settingpage('local_uai', 'UAI');
	$ADMIN->add('localplugins', $settings);
	
	$settings->add(new admin_setting_configcheckbox('local_uai_debug',
			'Debuggin webcursos',
			'Si se habilita, se mostraran los avances que están en desarrollo.',
			'', '1', '0'));

}

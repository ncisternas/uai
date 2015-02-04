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
 * @package    galyleo
 * @subpackage tools
 * @copyright  2014 Galyleo (http://www.galyleo.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->dirroot.'/group/lib.php');      // cli only functions


// now get cli options
list($options, $unrecognized) = cli_get_params(array('help'=>false),
		array('h'=>'help'));

if ($unrecognized) {
	$unrecognized = implode("\n  ", $unrecognized);
	cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
	$help =
	"Sincroniza grupos desde una tabla mdl_groups_sync que contiene tres columnas: shortname, groupname, username.

			Por cada línea en el curso identificado por shortname creará el grupo de nombre en la columna
			group (de no existir) y agregará como miembro al usuario indicado.

			NOTA: Si la tabla no existe en la base de datos, la creará.
			
			Opciones:
			-h, --help            Imprime esta ayuda

			Ejemplo:
			\$sudo -u apache /usr/bin/php admin/cli/sync_groups.php
			"; //TODO: localize - to be translated later when everything is finished

	echo $help;
	die;
}
cli_heading('Groups import'); // TODO: localize

$dbman = $DB->get_manager();

if(!$dbman->table_exists('groups_sync')) {
	echo "La tabla groups_sync no existe! Creando...";
	
	// Define table feria_ficha to be created
	$table = new xmldb_table('groups_sync');
	
	// Adding fields to table feria_ficha
	$table->add_field('shortname', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
	$table->add_field('groupname', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
	$table->add_field('username', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
	
	// Adding keys to table feria_ficha
	$table->add_key('primary', XMLDB_KEY_PRIMARY, array('shortname', 'groupname', 'username'));
	
	$dbman->create_table($table);
	
	echo "Tabla creada, debe poblarla antes de usar el script.\n";
}

$totalrows = $DB->count_records('groups_sync');

if($totalrows == 0) {
	cli_error("No hay grupos que sincronizar");
}

if (!$syncrows = $DB->get_recordset('groups_sync', null, 'shortname, groupname, username')) {
	cli_error("Error fatal trayendo datos de la tabla de sincronización");
}

$total = 0;

$courseshortname = null;
$course = null;
$group = null;
$groupname = null;
$groupscreated = 0;
$enrolments = 0;

foreach($syncrows as $sync) {
	if($courseshortname !== $sync->shortname) {
		$courseshortname = $sync->shortname;
		if(!$course = $DB->get_record('course', array('shortname'=>$sync->shortname))) {
			echo "Error! No existe curso $sync->shortname \n";
			continue;
		}
	}
	if($groupname !== $sync->groupname) {
		$groupname = $sync->groupname;
		if($groupid = groups_get_group_by_name($course->id, $sync->groupname)) {
			if(!$group = groups_get_group($groupid)) {
				echo "Error! Grupo id $groupid no existe!\n";
				continue;
			}
		} else {
			$group = new stdClass();
			$group->courseid = $course->id;
			$group->name = $sync->groupname;
			$group->timecreated = time();
			$group->timemodified = time();
			$group->id = groups_create_group($group);
			if(!$group->id) {
				$group = false;
			} else {
				$groupscreated++;
			}
		}
	}
	$user = $DB->get_record('user', array('username'=>$sync->username));
	if($user && $group && $course) {
		if(!groups_add_member($group, $user)) {
			$context = context_course::instance($course->id);
			if(is_enrolled($context, $user)) {
				echo "Error grave al agregar $user->username al grupo $group->name en curso $course->shortname \n";
			} else {
				echo "Error! El alumno $user->username no está matriculado en curso $course->shortname \n";				
			}
		} else {
			$total++;
		}
	} else {
		echo "Error! Fila inválida. ";
		if(!$user) {
			echo "Usuario $sync->username no existe.";
		}
		if(!$group) {
			echo "Grupo $sync->groupname no existe.";
		}
		if(!$course) {
			echo "Curso $sync->shortname no existe.";
		}
		echo " Detalle: shortname = $sync->shortname groupname = $sync->groupname username = $sync->username. \n";
	}
}

echo "------------------------------------------------------------\n";
echo "Filas encontradas en la tabla de sincronización: $totalrows \n";
echo "Grupos creados: $groupscreated \n";
echo "Filas correctamente procesadas: $total \n";
echo "Sincronización finalizada\n";

exit(0); // 0 means success
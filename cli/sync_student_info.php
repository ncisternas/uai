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
 * @package    local
 * @subpackage uai
 * @copyright  2015 Jorge Villalón (http://www.uai.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require('../locallib.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions

// now get cli options
list($options, $unrecognized) = cli_get_params(array('help'=>false),
    array('h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
    "Sincroniza las imágenes de los usuarios, descargándolas del sistema Omega si
        es que no las tenemos disponibles.
		
			Opciones:
			-h, --help            Imprime esta ayuda

			Ejemplo:
			sudo -u www-data /usr/bin/php admin/cli/sync_pictures.php
			"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

cli_heading('Sync pictures'); // TODO: localize

$usersidnumber = $DB->get_records_sql("
    SELECT * 
    FROM {user} AS u
    WHERE u.username LIKE '%uai.cl' AND (u.idnumber is null OR length(u.idnumber) = 0)");

echo count($usersidnumber) . " alumnos sin RUT\n";

echo "Conectandose a BD Omega\n";

$omega = new omega();

$usuariosOmega = $omega->obtieneImagenesUsuario();

echo count($usuariosOmega) . " alumnos encontrados en Omega\n";

foreach($usuariosOmega as $usuario) {
    echo "wget -q https://omega.uai.cl/WebForms/tools/LoadPersonaPicture.aspx?personaId=$usuario->idomega -O user$usuario->idomega";
}
echo "Done.\n\n";
exit(0);

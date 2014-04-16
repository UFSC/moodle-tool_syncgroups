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
 * Version details.
 *
 * @package    local_syncgroups
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("{$CFG->wwwroot}/group/lib.php");
require("{$CFG->wwwroot}/local/syncgroups/locallib.php");

if(!$sincronizacao = obtem_sincronizacao($argv[1])) {
     die;
}
if(!isset($sincronizacao->contexto)) {
     echo "??? Não informado o contexto no arquivo de configuração da sinronização\n";
     die;
}
$contexto = $sincronizacao->contexto;
$www_dir = "/home/moodle/public_html/{$contexto}";
$config = "{$www_dir}/config.php";
if(!is_readable($config)) {
     echo "??? Não localizado ou sem permissão de leitura ao arquivo de configuração do Moodle: '{$config}'\n";
     die;
}

include($config);
include("{$www_dir}/group/lib.php");
$CFG->debug = DEBUG_DEVELOPER;

foreach($sincronizacao->sincronizacoes AS $sinc) {
    echo "\n----------------------------------------------------------\n";

    $erro = false;
    echo "Curso de origem:\n";
    if($corigem = obtem_curso($sinc->origem)) {
        echo "Grupos:\n";
        if(!$grupos = obtem_grupos_curso($corigem->id, $sinc->grupos)) {
            $erro = true;
        }
    } else {
        $erro = true;
    }

    $cdestinos = array();
    echo "Cursos de destino:\n";
    foreach($sinc->destinos AS $dest) {
        if($cdest = obtem_curso($dest)) {
            $cdestinos[] = $cdest;
        } else {
            $erro = true;
        }
    }

    if($erro) {
        echo "\n**** Sincronização cancelada em função de erros ...\n";
        echo "\n**** Pressione <enter> para prosseguir ...\n";
        fgets(STDIN);
    } else {
        echo "\nSincronizar estes grupos? [s/n]: ";
        $ok = fgets(STDIN);
        if(!empty($ok) && $ok[0] == 's') {
            sincroniza_grupos($grupos, $cdestinos);
        } else {
            echo "\n**** Sincronização cancelada pelo operador ...\n";
        }
    }
}

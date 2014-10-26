<?php
/**
 * CakePHP Database Backup
 *
 * Backups structure and data from cake's database.
 * Usage:
 * $ cake Backups.backup
 * To backup all tables structure and data from default
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright 2014
 * @author    Cassandre CANTET
 * @link      https://github.com/ccantet/cakebackup
 * @package   plugins.Backups
 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('MySQL', 'Model/Datasource/Database');

class MySQLPerso extends MySQL {

    public function createSchema($schema, $tableName = null) {
        if ($tableName === null) {
            $tables = $this->listSources();
        } else {
            $tables = array($tableName);
        }
        $out = '';
        foreach ($tables as $table) {
            $res = $this->query('SHOW CREATE TABLE ' . $this->fullTableName($table));
            $out .= $res[0][0]['Create Table'] . ";";
        }

        return $out;
    }

    public function getStartRequest() {
        $text = "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */\n";
        return $text;
    }

    public function getEndRequest() {
        $text = "\n/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=1 */\n";
        return $text;
    }

}

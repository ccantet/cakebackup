<?php

/**
 * MySQL layer for DBO
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Model.Datasource.Database
 * @since         CakePHP(tm) v 0.10.5.1790
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('MySQL', 'Model/Datasource/Database');

/**
 * MySQL DBO driver object
 *
 * Provides connection and SQL generation for MySQL RDMS
 *
 * @package       Cake.Model.Datasource.Database
 */
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

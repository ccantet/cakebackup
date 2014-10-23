<?php

/**
 * CakePHP Database Backup
 *
 * Backups structure and data from cake's database.
 * Usage:
 * $ cake Backups.backup
 * To backup all tables structure and data from default
 *
 * TODO
 * Settings to choose datasource, table and output directory
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright 2012, Maldicore Group Pvt Ltd
 * @link      https://github.com/Maldicore/Backups
 * @package   plugns.Backups
 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('CakeSchema', 'Model');
App::uses('ConnectionManager', 'Model');
App::uses('Inflector', 'Utility');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('MySQLPerso', 'Backups.Model/Datasource/Database');

class BackupsAppShell extends Shell {

    protected $_datasourcePerso = false;

    public function getOptionParser() {
        $parser = parent::getOptionParser();
        $parser->addOptions(array(
            'path' => array(
                'short' => 'p',
                'help' => 'Path where backup file will be created',
                'default' => ROOT . DS . 'backups'
            ),
            'datasource' => array(
                'short' => 'd',
                'help' => 'Name of the detasource',
                'default' => 'default'
            ),
        ));
        return $parser;
    }

    protected function _getDatasource() {
        $datasource = ConnectionManager::getDataSource($this->params['datasource']);
        if (strtolower(get_class($datasource)) == 'mysql') {
            $this->_datasourcePerso = true;
            return new MySQLPerso($datasource->config);
        }
        $this->_datasourcePerso = false;
        return $datasource;
    }

    protected function _getStartRequest() {
        if (!$this->_datasourcePerso) {
            return '';
        }
        return $this->DataSource->getStartRequest();
    }

    protected function _getEndRequest() {
        if (!$this->_datasourcePerso) {
            return '';
        }

        return $this->DataSource->getEndRequest();
    }

}

?>

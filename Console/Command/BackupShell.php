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
App::uses('BackupsAppShell', 'Backups.Console/Command');

class BackupShell extends BackupsAppShell {

    public function getOptionParser() {
        $parser = parent::getOptionParser();
        $parser->addOptions(array(
            'only-data' => array(
                'help' => 'Export only data',
                'boolean' => true
            ),
            'only-structure' => array(
                'help' => 'Export only structure',
                'boolean' => true
            ),
        ));
        return $parser;
    }

    public function main() {
        if (!is_dir($this->params['path'])) {
            trigger_error('The path "' . $this->params['path'] . '" doesn\'t exist !', E_USER_ERROR);
        }
        if (!is_writable($this->params['path'])) {
            trigger_error('The path "' . $this->params['path'] . '" isn\'t writable !', E_USER_ERROR);
        }

        $this->DataSource = $this->_getDatasource();
        $this->File = new File($this->params['path'] . DS . $this->params['datasource'] . '_' . date('ymd\_His') . '.sql');
        $this->Schema = new CakeSchema(array('connection' => $this->params['datasource']));

        $this->out("Backing up...\n");
        $this->File->write($this->_getStartRequest());
        foreach ($this->DataSource->listSources() as $table) {
            $table = str_replace($this->DataSource->config['prefix'], '', $table);

            if (!$this->params['only-data']) {
                $cakeSchema = $this->DataSource->describe($table);
                $this->Schema->tables = array($table => $cakeSchema);

                $this->File->write("\n/* Drop statement for {$table} */\n");
                $this->File->write($this->DataSource->dropSchema($this->Schema, $table));

                $this->File->write("\n/* Backuping table schema {$table} */\n");
                $this->File->write($this->DataSource->createSchema($this->Schema, $table) . "\n");
            }
            if (!$this->params['only-structure']) {
                $this->File->write("\n/* Backuping table data {$table} */\n");
                $quantity = $this->__writeDatas($table);
            }
            $this->out('Table "' . $table . '" (' . $quantity . ')');
        }
        $this->File->write($this->_getEndRequest());
        $this->File->close();
        $this->out("\nFile \"" . $this->File->name . "\" saved succesfuly (" . $this->File->size() . " bytes)\n");

        // Zippping if necessary (> 10Mo)
        if (class_exists('ZipArchive') AND $this->File->size() > 1024 * 1024 * 10) {
            $this->__zip();
        }

        //Remove useless backups if necessary
        $number = Configure::read('Backups.numberBackupsKept');
        if (isset($number) AND ( empty($number) OR $number == 'all')) {
            $this->out("Removal useless backups was skipped because all backups are kepts.");
        } else if (isset($number) AND ( !is_numeric($number) OR $number < 0)) {
            $this->out("Incorrect parameter \"numberBackupsKept\". Can't delete useless backups.");
        } else {
            if (!isset($number)) {
                $number = 5;
            }
            $this->out("Delete useless backups...");
            $this->__deleteUselessFiles((int) $number);
            $this->out("Removal succesful\n");
        }

        $this->out("\nDatabase backup succesfuly.\n");
    }

    private function __zip() {
        $this->out('Zipping...');
        $pathZip = $this->File->Folder()->pwd() . DS . $this->File->name() . '.zip';
        $zip = new ZipArchive();
        $zip->open($pathZip, ZIPARCHIVE::CREATE);
        $zip->addFile($this->File->pwd(), $this->File->name);
        $zip->close();
        $FileZip = new File($pathZip);
        if ($FileZip->exists()) {
            $this->out('Zip "' . $FileZip->name . '" saved succesfuly ( ' . $FileZip->size() . " bytes)\n");
            $this->File->delete();
        }
        return true;
    }

    private function __deleteUselessFiles($number) {
        $listFiles = $this->File->Folder()->find('.*\.(zip|sql)', true);
        $files = array();
        foreach ($listFiles as $nameFile) {
            $files[] = new File($this->File->Folder()->pwd() . DS . $nameFile);
        }
        usort($files, function($fileA, $fileB) {
            return $fileB->lastChange() - $fileA->lastChange();
        });
        $filesToDeleted = array_slice($files, (int) $number);
        foreach ($filesToDeleted as $file) {
            $file->delete();
        }
        return true;
    }

    private function __writeInsertQuery($table, $fieldsInsertComma, $values) {
        $query = array(
            'table' => $this->DataSource->fullTableName($table),
            'fields' => $fieldsInsertComma,
            'values' => $values
        );

        $this->File->write($this->DataSource->renderStatement('create', $query) . ";\n");
        return true;
    }

    private function __writeDatas($table) {
        $ModelName = Inflector::classify($table);
        $Model = ClassRegistry::init($ModelName);
        $Model->useTable = $table;

        $fields = array_keys($this->DataSource->describe($table));
        $rows = $Model->find('all', array('fields' => $fields, 'recursive' => -1, 'callbacks' => false));

        $fieldInsert = array();
        $typeFields = array();
        foreach ($fields as $i => $field) {
            $typeFields[$i] = $Model->getColumnType($field);
            $fieldInsert[$i] = $this->DataSource->name($field);
        }

        $fieldsInsertComma = implode(', ', $fieldInsert);

        $firstInsert = true;
        $quantity = 0;
        foreach ($rows as $row) {
            $valueInsert = array();
            foreach ($fields as $i => $field) {
                $valueInsert[] = $this->DataSource->value($row[$ModelName][$field], $typeFields[$i], false);
            }
            if ($firstInsert) {
                $firstInsert = false;
                $values = "";
            } else {
                $values .= "),\n(";
            }
            $values .= implode(', ', $valueInsert);

            if (++$quantity % 20000 == 0) {
                $this->__writeInsertQuery($table, $fieldsInsertComma, $values);
                $firstInsert = true;
            }
        }

        if ($firstInsert !== true) {
            $this->__writeInsertQuery($table, $fieldsInsertComma, $values);
        }

        return $quantity;
    }

}

?>

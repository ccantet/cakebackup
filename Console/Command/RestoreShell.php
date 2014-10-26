<?php
/**
 * CakePHP Database Backup
 *
 * Restore structure and data from cake's database.
 * Usage:
 * $ cake Backups.restore
 * To restore all tables structure and data from default
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
App::uses('BackupsAppShell', 'Backups.Console/Command');

class RestoreShell extends BackupsAppShell {

    public function getOptionParser() {
        $parser = parent::getOptionParser();
        return $parser;
    }

    public function main() {
        if (!is_dir($this->params['path'])) {
            trigger_error('The path "' . $this->params['path'] . '" doesn\'t exist !', E_USER_ERROR);
        }
        if (!is_readable($this->params['path'])) {
            trigger_error('The path "' . $this->params['path'] . '" isn\'t readable !', E_USER_ERROR);
        }
        $backupFolder = new Folder($this->params['path']);

        $listFilesSql = $backupFolder->find('.*\.sql');
        $listFilesZip = $backupFolder->find('.*\.zip');
        if (!empty($listFilesZip) AND ! class_exists('ZipArchive')) {
            $this->out("Zip founded but none utility available to manipulate them, so they was ignored\n");
        } else {
            $listFiles = array_merge($listFilesSql, $listFilesZip);
        }
        $files = array();
        foreach ($listFiles as $nameFile) {
            $files[] = new File($backupFolder->pwd() . DS . $nameFile);
        }
        usort($files, function($fileA, $fileB) {
            return $fileB->lastChange() - $fileA->lastChange();
        });
        foreach ($files as $i => $file) {
            $this->out("[" . $i . "]: " . date("F j, Y, g:i:s a", $file->lastChange()));
        }

        App::import('Model', 'AppModel');

        $model = new AppModel(false, false);

        $this->hr();
        $userResponse = $this->in('Type Backup File Number? [or press enter to cancel]');

        if ($userResponse == "") {
            $this->out('Exiting');
            $this->_stop();
        } else if (!array_key_exists($userResponse, $files)) {
            $this->out("Invalid File Number");
            $this->_stop();
        } else {
            $file = $files[$userResponse];
            $this->out('Restoring file: ' . $file->name);

            if ($file->ext() == 'zip') {
                $this->out('Unzipping File...');
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($file->pwd()) === TRUE) {
                        $zip->extractTo(TMP);
                        $unzippedFile = new File(TMP . DS . $zip->getNameIndex(0));
                        $useTemp = true;
                        $zip->close();
                        $this->out('Successfully Unzipped');
                    } else {
                        $this->out('Unzip Failed');
                        $this->_stop();
                    }
                } else {
                    $this->out('ZipArchive not found, cannot Unzip File!');
                    $this->_stop();
                }
            } else {
                $useTemp = false;
                $unzippedFile = $file;
            }

            if ($unzippedFile->exists() AND $unzippedFile->ext() == 'sql') {
                $sqlContent = $unzippedFile->read();
                $this->out("Restoring Database...\n");
                $sql = explode("\n\n", $sqlContent);
                foreach ($sql as $s) {
                    $s = trim($s);
                    if (empty($s)) {
                        continue;
                    }

                    try {
                        $model->query($s);
                    } catch (PDOException $e) {
                        $this->out("Query failed : " . $e->getMessage());
                        $this->out("The query : $s");
                        $this->out("Restore failed\n");
                        $this->_stop();
                    }
                }
                if ($useTemp) {
                    $unzippedFile->delete();
                }
                $this->out("Restore successful !\n");
            } else {
                $this->out("Couldn't load contents of file {$unzippedFile->name}, aborting...");
                $this->_stop();
            }
        }
    }

}

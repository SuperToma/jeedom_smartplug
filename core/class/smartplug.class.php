<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

/**
 * Class smartplug
 */
class smartplug extends eqLogic
{

    public static $_widgetPossibility = array('custom' => true);

    public function cron()
    {
        foreach (eqLogic::byType('smartplug', true) as $smartplug) {
            $smartplug->readStatus($smartplug->getConfiguration('addr'));
        }
    }

    public function preUpdate()
    {
        if ($this->getConfiguration('addr') == '') {
            throw new Exception(__('L\adresse ne peut etre vide', __FILE__));
        }
    }

    public function preSave()
    {
        $this->setLogicalId($this->getConfiguration('addr'));
    }

    public function postSave()
    {
        $this->setLogicalId($this->getConfiguration('addr'));
        $smartplugCmd = $this->getCmd(null, 'status');
        if (!is_object($smartplugCmd)) {
            log::add('smartplug', 'debug', 'Création de la commande status');
            $smartplugCmd = new smartplugCmd();
            $smartplugCmd->setName(__('Statut', __FILE__));
            $smartplugCmd->setEqLogic_id($this->id);
            $smartplugCmd->setEqType('smartplug');
            $smartplugCmd->setLogicalId('status');
            $smartplugCmd->setConfiguration('command', '0x2b');
            $smartplugCmd->setConfiguration('argument', '0f06030000000004ffff');
            $smartplugCmd->setType('info');
            $smartplugCmd->setSubType('binary');
            $smartplugCmd->setTemplate("dashboard", "light");
            $smartplugCmd->setTemplate("mobile", "light");
            $smartplugCmd->save();
        }
        $cmId = $smartplugCmd->getId();
        $smartplugCmd = $this->getCmd(null, 'conso');
        if (!is_object($smartplugCmd)) {
            log::add('smartplug', 'debug', 'Création de la commande conso');
            $smartplugCmd = new smartplugCmd();
            $smartplugCmd->setName(__('Conso', __FILE__));
            $smartplugCmd->setEqLogic_id($this->id);
            $smartplugCmd->setEqType('smartplug');
            $smartplugCmd->setLogicalId('conso');
            $smartplugCmd->setConfiguration('command', '0x2b');
            $smartplugCmd->setConfiguration('argument', '0f06030000000004ffff');
            $smartplugCmd->setType('info');
            $smartplugCmd->setSubType('numeric');
            $smartplugCmd->save();
        }
        $this->readStatus($this->getConfiguration('addr'));
        $smartplugCmd = $this->getCmd(null, 'on');
        if (!is_object($smartplugCmd)) {
            log::add('smartplug', 'debug', 'Création de la commande on');
            $smartplugCmd = new smartplugCmd();
            $smartplugCmd->setName(__('Allumer', __FILE__));
            $smartplugCmd->setEqLogic_id($this->id);
            $smartplugCmd->setEqType('smartplug');
            $smartplugCmd->setLogicalId('on');
            $smartplugCmd->setConfiguration('command', '0x2b');
            $smartplugCmd->setConfiguration('argument', '0f06030001000005ffff');
            $smartplugCmd->setType('action');
            $smartplugCmd->setSubType('other');
            $smartplugCmd->setValue($cmId);
            $smartplugCmd->setTemplate("dashboard", "light");
            $smartplugCmd->setTemplate("mobile", "light");
            $smartplugCmd->save();
        }
        $smartplugCmd = $this->getCmd(null, 'off');
        if (!is_object($smartplugCmd)) {
            log::add('smartplug', 'debug', 'Création de la commande off');
            $smartplugCmd = new smartplugCmd();
            $smartplugCmd->setName(__('Eteindre', __FILE__));
            $smartplugCmd->setEqLogic_id($this->id);
            $smartplugCmd->setEqType('smartplug');
            $smartplugCmd->setLogicalId('off');
            $smartplugCmd->setConfiguration('command', '0x2b');
            $smartplugCmd->setConfiguration('argument', '0f06030000000004ffff');
            $smartplugCmd->setType('action');
            $smartplugCmd->setSubType('other');
            $smartplugCmd->setValue($cmId);
            $smartplugCmd->setTemplate("dashboard", "light");
            $smartplugCmd->setTemplate("mobile", "light");
            $smartplugCmd->save();
        }
    }

    /**
     * @param $addr
     * @param $command
     * @param $argument
     */
    public function sendCommand($addr, $command, $argument)
    {
        $smartplug = self::byLogicalId($addr, 'smartplug');
        log::add('smartplug', 'info', 'Commande : gatttool -b ' . $addr . ' --char-write -a ' . $command . ' -n ' . $argument);
        if ($smartplug->getConfiguration('maitreesclave') == 'deporte') {
            $ip = $smartplug->getConfiguration('addressip');
            $port = $smartplug->getConfiguration('portssh');
            $user = $smartplug->getConfiguration('user');
            $pass = $smartplug->getConfiguration('password');
            if (!$connection = ssh2_connect($ip, $port)) {
                log::add('smartplug', 'error', 'connexion SSH KO');
            } else {
                if (!ssh2_auth_password($connection, $user, $pass)) {
                    log::add('smartplug', 'error', 'Authentification SSH KO');
                } else {
                    log::add('smartplug', 'debug', 'Commande par SSH');
                    $hcion = ssh2_exec($connection, 'sudo hciconfig hciO up');
                    $result = ssh2_exec($connection, 'sudo gatttool -b ' . $addr . ' --char-write -a ' . $command . ' -n ' . $argument);
                    stream_set_blocking($result, true);
                    $result = stream_get_contents($result);

                    $closesession = ssh2_exec($connection, 'exit');
                    stream_set_blocking($closesession, true);
                    stream_get_contents($closesession);
                }
            }
        } else {
            exec('sudo hciconfig hciO up');
            exec('sudo gatttool -b ' . $addr . ' --char-write -a ' . $command . ' -n ' . $argument);
        }
    }

    /**
     * @param $addr
     */
    public function readStatus($addr)
    {
        $smartplug = self::byLogicalId($addr, 'smartplug');
        $cmdSuffix = ' --handle=0x002b --char-write-req --value=0f050400000005ffff --listen 1>&1';
        $cmdUniqId = date('ymdHis');

        log::add('smartplug', 'info', 'Commande ' . $cmdUniqId . ' : timeout -s INT 2 gatttool -b ' . $addr . $cmdSuffix);

        if ($smartplug->getConfiguration('maitreesclave') == 'deporte') {
            $ip = $smartplug->getConfiguration('addressip');
            $port = $smartplug->getConfiguration('portssh');
            $user = $smartplug->getConfiguration('user');
            $pass = $smartplug->getConfiguration('password');
            if (!$connection = ssh2_connect($ip, $port)) {
                log::add('smartplug', 'error', 'connexion SSH KO');
            } else {
                if (!ssh2_auth_password($connection, $user, $pass)) {
                    log::add('smartplug', 'error', 'Authentification SSH KO');
                } else {
                    log::add('smartplug', 'debug', 'Commande par SSH');
                    $hcion = ssh2_exec($connection, 'sudo hciconfig hciO up');
                    $cmd = ssh2_exec($connection, 'sudo timeout -s INT 2 gatttool -b ' . $addr . $cmdSuffix);
                    stream_set_blocking($cmd, true);
                    $cmd = stream_get_contents($cmd);
                    if (strstr($cmd, 'Notification handle = 0x002e value') !== false) {
                      $result[0] = 'Characteristic value was written successfully';
                      $result[1] = $cmd;
                    }

                    $closesession = ssh2_exec($connection, 'exit');
                    stream_set_blocking($closesession, true);
                    stream_get_contents($closesession);
                }
            }
        } else {
            exec('sudo hciconfig hciO up');
            exec('sudo timeout -s INT 2 gatttool -b ' . $addr . $cmdSuffix, $result, $return_var);
        }

        log::add('smartplug', 'info', 'Commande ' . $cmdUniqId . ' result : [' . implode("]  [", $result) . ']');

        $status = $power = 0;

        if (isset($result[0], $result[1]) &&
            $result[0] == 'Characteristic value was written successfully' &&
            strstr($result[1], 'Notification handle = 0x002e value') !== false
        ) {
            $data = explode(':', $result[1]);
            $data = explode(' ', trim((string)$data[1]));

            $status = (int)$data[4];
            $power = hexdec($data[6] . $data[7] . $data[8] . $data[9]) / 1000;
        } elseif (empty($result)) {
            log::add('smartplug', 'info', 'Commande ' . $cmdUniqId . ' : smartplug seems power off or too far');
        } else {
            log::add('smartplug', 'error', 'Commande ' . $cmdUniqId . ' : value is not correct');
        }

        /** @var smartplugCmd $smartplugCmd */
        $smartplugCmd = $this->getCmd(null, 'status');
        $smartplugCmd->setConfiguration('value', $status);
        $smartplugCmd->save();
        $smartplugCmd->event($status);

        /** @var smartplugCmd $smartplugCmd */
        $smartplugCmd = $this->getCmd(null, 'conso');
        $smartplugCmd->setConfiguration('value', $power);
        $smartplugCmd->save();
        $smartplugCmd->event($power);

        log::add('smartplug', 'info', 'Commande ' . $cmdUniqId . ' : status = ' . $status . ', power = ' . $power);
    }
}

/**
 * Class smartplugCmd
 */
class smartplugCmd extends cmd
{
    /**
     * @param null $_options
     * @return bool|mixed|string
     */
    public function execute($_options = null)
    {

        switch ($this->getType()) {
            case 'info' :
                return $this->getConfiguration('value');
            case 'action' :
                $eqLogic = $this->getEqLogic();
                smartplug::sendCommand($eqLogic->getConfiguration('addr'), $this->getConfiguration('command'), $this->getConfiguration('argument'));
                return true;
        }

    }
}

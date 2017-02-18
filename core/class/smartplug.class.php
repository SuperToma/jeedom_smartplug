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

class smartplug extends eqLogic
{
    const AWOX_HANDLE = '0x2b';

    const AWOX_CMD_ON_VALUE = '0f06030001000005ffff';
    const AWOX_CMD_OFF_VALUE = '0f06030000000004ffff';
    const AWOX_CMD_STATUS_CONSO_VALUE = '0f050400000005ffff';

    public static $_widgetPossibility = array('custom' => true);

    public function cron()
    {
        foreach (eqLogic::byType('smartplug', true) as $smartplug) {
            $smartplug->sendCommand($smartplug->getConfiguration('addr'), self::AWOX_HANDLE, self::AWOX_CMD_STATUS_CONSO_VALUE);
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
            $smartplugCmd->setConfiguration('command', self::AWOX_HANDLE);
            $smartplugCmd->setConfiguration('argument', self::AWOX_CMD_STATUS_CONSO_VALUE);
            $smartplugCmd->setType('info');
            $smartplugCmd->setSubType('binary');
            $smartplugCmd->setTemplate("dashboard", "light");
            $smartplugCmd->setTemplate("mobile", "light");
            $smartplugCmd->setIsHistorized(1);
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
            $smartplugCmd->setConfiguration('command', self::AWOX_HANDLE);
            $smartplugCmd->setConfiguration('argument', self::AWOX_CMD_STATUS_CONSO_VALUE);
            $smartplugCmd->setType('info');
            $smartplugCmd->setSubType('numeric');
            $smartplugCmd->setTemplate("dashboard", "badge");
            $smartplugCmd->setTemplate("mobile", "badge");
            $smartplugCmd->setIsHistorized(1);
            $smartplugCmd->save();
        }

        $smartplugCmd = $this->getCmd(null, 'on');
        if (!is_object($smartplugCmd)) {
            log::add('smartplug', 'debug', 'Création de la commande on');
            $smartplugCmd = new smartplugCmd();
            $smartplugCmd->setName(__('Allumer', __FILE__));
            $smartplugCmd->setEqLogic_id($this->id);
            $smartplugCmd->setEqType('smartplug');
            $smartplugCmd->setLogicalId('on');
            $smartplugCmd->setConfiguration('command', self::AWOX_HANDLE);
            $smartplugCmd->setConfiguration('argument', self::AWOX_CMD_ON_VALUE);
            $smartplugCmd->setType('action');
            $smartplugCmd->setSubType('other');
            $smartplugCmd->setValue($cmId);
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
            $smartplugCmd->setConfiguration('command', self::AWOX_HANDLE);
            $smartplugCmd->setConfiguration('argument', self::AWOX_CMD_OFF_VALUE);
            $smartplugCmd->setType('action');
            $smartplugCmd->setSubType('other');
            $smartplugCmd->setValue($cmId);
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

        $cmdAction = 'sudo gatttool --char-write -b '.$addr.' -a '.$command.' -n '.$argument;
        $cmdStatusConso = 'sudo timeout -s INT 2 gatttool --char-write-req -b '.$addr.' -a '.$command.' -n '.$argument.' --listen 1>&1';

        if ($argument != self::AWOX_CMD_STATUS_CONSO_VALUE) {
            $smartplug->execCommand($smartplug, $cmdAction);

            sleep(2); //refresh conso after on/off, but need to wait 2 seconds
            $smartplug->sendCommand($addr, $command, self::AWOX_CMD_STATUS_CONSO_VALUE);
        } else {
            $result = $smartplug->execCommand($smartplug, $cmdStatusConso);

            log::add('smartplug', 'info', '<<< ['.implode("]  [", $result).']');

            $status = $power = 0;

            if (is_array($result)) {
                $result = array_slice($result, -2);
            }

            if (isset($result[0], $result[1]) &&
                $result[0] == 'Characteristic value was written successfully' &&
                strstr($result[1], 'Notification handle = 0x002e value') !== false
            ) {
                $data = explode(':', $result[1]);
                $data = explode(' ', trim((string)$data[1]));

                $status = (int)$data[4];
                $power = hexdec($data[6] . $data[7] . $data[8] . $data[9]) / 1000;
            } elseif (empty($result)) {
                log::add('smartplug', 'info', '<<< Smartplug seems power off or too far');
            } else {
                log::add('smartplug', 'error', '<<< Return value is not correct');
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

            log::add('smartplug', 'info', 'status = '.$status.', power = '.$power);
        }
    }

    /**
     * @param $smartplug
     * @param $command
     * @return resource|string
     */
    protected function execCommand($smartplug, $command)
    {
        log::add('smartplug', 'info', '>>> '.$command);

        if ($smartplug->getConfiguration('maitreesclave') == 'deporte') {
            $ip = $smartplug->getConfiguration('addressip');
            $port = $smartplug->getConfiguration('portssh');
            $user = $smartplug->getConfiguration('user');
            $pass = $smartplug->getConfiguration('password');

            if (!$connection = ssh2_connect($ip, $port)) {
                log::add('smartplug', 'error', '<<< connexion SSH KO');
            } else {
                if (!ssh2_auth_password($connection, $user, $pass)) {
                    log::add('smartplug', 'error', '<<< Authentification SSH KO');
                } else {
                    log::add('smartplug', 'debug', '<<< Commande par SSH');
                    ssh2_exec($connection, 'sudo hciconfig hciO up');
                    $result = ssh2_exec($connection, $command);
                    stream_set_blocking($result, true);
                    $result = stream_get_contents($result);

                    if (strstr($result, 'Notification handle = 0x002e value') !== false) {
                        $result[0] = 'Characteristic value was written successfully';
                        $result[1] = $result;
                    }

                    $closesession = ssh2_exec($connection, 'exit');
                    stream_set_blocking($closesession, true);
                    stream_get_contents($closesession);
                }
            }
        } else {
            exec('sudo hciconfig hciO up');
            exec($command, $result);
        }

        return $result;
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
                (new smartplug)->sendCommand($eqLogic->getConfiguration('addr'), $this->getConfiguration('command'), $this->getConfiguration('argument'));

                return true;
        }
    }
}

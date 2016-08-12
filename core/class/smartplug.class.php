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

class smartplug extends eqLogic {

  public static $_widgetPossibility = array('custom' => true);

  public function preUpdate() {
    if ($this->getConfiguration('addr') == '') {
      throw new Exception(__('L\adresse ne peut etre vide',__FILE__));
    }
  }

  public function preSave() {
    $this->setLogicalId($this->getConfiguration('addr'));
  }

  public function postSave() {
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
      $smartplugCmd->save();
    }
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
      $smartplugCmd->save();
    }
  }

  public function sendCommand( $addr, $command, $argument ) {
    $smartplug = self::byLogicalId($addr, 'smartplug');
    log::add('smartplug', 'info', 'Commande : gatttool -b ' . $addr . ' --char-write -a ' . $command . ' -n ' . $argument);
    if ($smartplug->getConfiguration('maitreesclave') == 'deporte'){
      $ip=$smartplug->getConfiguration('addressip');
      $port=$smartplug->getConfiguration('portssh');
      $user=$smartplug->getConfiguration('user');
      $pass=$smartplug->getConfiguration('password');
      if (!$connection = ssh2_connect($ip,$port)) {
        log::add('smartplug', 'error', 'connexion SSH KO');
      }else{
        if (!ssh2_auth_password($connection,$user,$pass)){
          log::add('smartplug', 'error', 'Authentification SSH KO');
        }else{
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
    }else {
      exec('sudo hciconfig hciO up');
      exec('sudo gatttool -b ' . $addr . ' --char-write -a ' . $command . ' -n ' . $argument);
    }
  }

  public function readConso( $addr ) {
    $smartplug = self::byLogicalId($addr, 'smartplug');
    log::add('smartplug', 'info', 'Commande : gatttool -b ' . $addr . ' --handle=0x002b --char-write-req --value=0f050400000005ffff --listen');
    if ($smartplug->getConfiguration('maitreesclave') == 'deporte'){
      $ip=$smartplug->getConfiguration('addressip');
      $port=$smartplug->getConfiguration('portssh');
      $user=$smartplug->getConfiguration('user');
      $pass=$smartplug->getConfiguration('password');
      if (!$connection = ssh2_connect($ip,$port)) {
        log::add('smartplug', 'error', 'connexion SSH KO');
      }else{
        if (!ssh2_auth_password($connection,$user,$pass)){
          log::add('smartplug', 'error', 'Authentification SSH KO');
        }else{
          log::add('smartplug', 'debug', 'Commande par SSH');
          $hcion = ssh2_exec($connection, 'sudo hciconfig hciO up');
          $result = ssh2_exec($connection, 'sudo gatttool -b ' . $addr . ' --handle=0x002b --char-write-req --value=0f050400000005ffff --listen');
          stream_set_blocking($result, true);
          $result = stream_get_contents($result);

          $closesession = ssh2_exec($connection, 'exit');
          stream_set_blocking($closesession, true);
          stream_get_contents($closesession);
        }
      }
    }else {
      exec('sudo hciconfig hciO up');
      exec('sudo gatttool -b ' . $addr . ' --handle=0x002b --char-write-req --value=0f050400000005ffff --listen', $result, $return_var);
    }
    $result = explode('0f 0f 04 00 01 00 00 ', $result );
    $result = substr($result[1], 0, 5);
    $result = hexdec($result);
    $result = $result/1000;
    $smartplugCmd = $this->getCmd(null, 'conso');
    $smartplugCmd->setConfiguration('value',$result);
    $smartplugCmd->save();
    $smartplugCmd->event($result);
  }



}

class smartplugCmd extends cmd {

  public function execute($_options = null) {

    switch ($this->getType()) {
      case 'info' :
        return $this->getConfiguration('value');
      case 'action' :
        $eqLogic = $this->getEqLogic();
        smartplug::sendCommand($eqLogic->getConfiguration('addr'),$this->getConfiguration('command'),$this->getConfiguration('argument'));
        return true;
    }

  }
}

?>

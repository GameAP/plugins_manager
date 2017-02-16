<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Game AdminPanel (АдминПанель)
 *
 * 
 *
 * @package		Game AdminPanel
 * @author		Nikita Kuznetsov (ET-NiK)
 * @copyright	Copyright (c) 2013, Nikita Kuznetsov (http://hldm.org)
 * @license		http://gameap.ru/license.html
 * @link		http://gameap.ru
 * @filesource
*/

/**
 * AMXX Plugins Control
 * Модуль для удобной установки, включения и выключения плагинов AMX Mod X (только для GoldSource серверов)
 *
 * @package		Game AdminPanel
 * @author		Nikita Kuznetsov (ET-NiK)
*/

class Plugins_control extends CI_Model {
	
	// ----------------------------------------------------------------
    
    /**
     * Управление плагинами на сервере
    */
	public function load_plugins()
	{
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Получение списка плагинов, находящихся в plugins.ini
    */
	public function parse_plugins_in_cfg($cfg_contents = '')
	{
		$this->load->helper('patterns');
		$cfg_contents_array = explode("\n", $cfg_contents);
		
		$matches = get_matches('/(;*)(\w*\.amxx)(\s*;\s*\w*)/isx', $cfg_contents);

		$i = 0;
		foreach($matches as &$plug) {
			$plugins[$i]['enabled'] 	= (int)!(bool)$plug[1];
			$plugins[$i]['name'] 		= $plug[2];
			$plugins[$i]['description'] = trim(str_replace(';', '', trim($plug[3])));
			$i++;
		}
		
		return $plugins;
	}
	
}

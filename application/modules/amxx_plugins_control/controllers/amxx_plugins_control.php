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
class Amxx_plugins_control extends MX_Controller {
	
	//Template
	var $tpl_data = array();
	
	var $user_data = array();
	var $server_data = array();
	
	// Файлы плагинов
	private $_plugins_pattern = '/(\s*;*\s*)(\w*\.amxx)(\s*[debug]*\s*)(\s*;*\s*.*\s*)/isx';
	
	// Массив с плагинами из директории plugins
	private $_plugins_list		= array();
	
	// ----------------------------------------------------------------
	
	public function __construct()
    {
        parent::__construct();
		
		$this->load->database();
        $this->load->model('users');
		$this->load->model('servers');
		
		// Загрузка языковых файлов
        $this->lang->load('server_files');
        $this->lang->load('server_command');
        $this->lang->load('amxx_plugins_control');

        if ($this->users->check_user()) {
			//Base Template
			$this->tpl_data['title'] 		= lang('amxx_title_index');
			$this->tpl_data['heading']		= lang('amxx_header_index');
			$this->tpl_data['content'] 		= '';
			$this->tpl_data['menu'] 		= $this->parser->parse('menu.html', $this->tpl_data, true);
			$this->tpl_data['profile'] 		= $this->parser->parse('profile.html', $this->users->tpl_userdata(), true);
        
        } else {
            redirect('auth');
        }
    }
    
    
    // ----------------------------------------------------------------

    /**
     *  Отображение информационного сообщения (ошибки и т.п.)
    */
    private function _show_message($message = false, $link = false, $link_text = false)
    {
        
        if (!$message) {
			$message = lang('error');
		}
		
        if (!$link) {
			$link = 'javascript:history.back()';
		}
		
		if (!$link_text) {
			$link_text = lang('back');
		}

        $local_tpl_data['message'] = $message;
        $local_tpl_data['link'] = $link;
        $local_tpl_data['back_link_txt'] = $link_text;
        $this->tpl_data['content'] = $this->parser->parse('info.html', $local_tpl_data, true);
        $this->parser->parse('main.html', $this->tpl_data);
    }
    
    // ----------------------------------------------------------------
    
    /**
     * Получение данных сервера для шаблона
    */
    private function _get_servers_tpl($limit = 10000, $offset = 0)
    {

		$tpl_data = array();
		
		/* Получение игровых серверов GoldSource */
		if (!isset($this->servers_list)) {
			$this->servers->get_servers_list($this->users->auth_id, 'VIEW', array('enabled' => '1', 'installed' => '1'), $limit, $offset, 'goldsource');
		}
		
		$num = 0;
		foreach ($this->servers->servers_list as &$server_data){
			
			$tpl_data[$num]['server_id'] 	= $server_data['id'];
			$tpl_data[$num]['server_game'] 	= $server_data['game'];
			$tpl_data[$num]['server_name'] 	= $server_data['name'];
			$tpl_data[$num]['server_ip'] 	= $server_data['server_ip'];
			$tpl_data[$num]['server_port'] 	= $server_data['server_port'];
			
			$num++;
		}
		
		return $tpl_data;
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Получение списка плагинов, находящихся в plugins.ini
    */
	private function _parse_plugins_in_cfg($cfg_contents = '')
	{
		$this->load->helper('patterns');
		$cfg_contents_array = explode("\n", $cfg_contents);
		$plugins = array();
		
		$matches = get_matches($this->_plugins_pattern, $cfg_contents);

		foreach($matches as &$plug) {

			$plugins[$plug[2]]['enabled'] 	= (int)!(bool)$plug[1];
			$plugins[$plug[2]]['name'] 		= trim($plug[2]);
			$plugins[$plug[2]]['debug'] 	= (int)(bool)trim($plug[3]);
			$plugins[$plug[2]]['description'] = trim(str_replace(';', '', trim($plug[4])));
		}

		return $plugins;
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Получает данные списка плагинов и делает их пригоднями
     * для вставки в шаблон.
     * 
     * @param array		список плагинов из папки amxmodx/plugins
     * @param array		список плагинов из plugins.ini
     * 
     * @return array
    */
	private function _plugins_list_to_tpl($plugins_in_cfg = array())
	{
		$this->load->helper('form');

		$tpl_data = array();

		$i = 0;
		foreach($plugins_in_cfg as &$plug) {
			
			/* 
			 * Удаление значения из массива плагинов из amxmodx/plugins
			 * т.к. информация о нем уже находится в plugins.ini
			 * 
			 * Здесь можно было бы использовать функцию array_search, 
			 * но она почему-то отказалась работать с плагином под именем ConnectInfo.amxx
			 * поэтому в строках дублировался
			 * Бубен не помог =(( Одним словом - Шайтан ходил
			*/
			if ($keys = array_keys($this->_plugins_list, trim($plug['name']))) {
				unset($this->_plugins_list[$keys[0]]);
			}
			
			$tpl_data[$i]['name'] = $plug['name'];
			$tpl_data[$i]['description'] = $plug['description'];
			
			$checkbox_plug_name = str_replace('.amxx', '', $plug['name']);
			$tpl_data[$i]['checkbox_plugin_enabled'] = form_checkbox('plugins_enabled[' . $checkbox_plug_name . ']', 'accept', $plug['enabled']);
			$tpl_data[$i]['checkbox_debug_enabled'] = form_checkbox('plugins_debug[' . $checkbox_plug_name . ']', 'accept', $plug['debug']);
			$i++;
		}
		
		/* $i не сбрасываем!!! */
		foreach($this->_plugins_list as &$plug) {
			$tpl_data[$i]['name'] = $plug;
			$tpl_data[$i]['description'] = ''; // Описание отсутствует
			
			$checkbox_plug_name = str_replace('.amxx', '', $plug);
			$tpl_data[$i]['checkbox_plugin_enabled'] = form_checkbox('plugins_enabled[' . $checkbox_plug_name . ']', 'accept');
			$tpl_data[$i]['checkbox_debug_enabled'] = form_checkbox('plugins_debug[' . $checkbox_plug_name . ']', 'accept');
			$i++;
		}
		
		/* Callback функция для сортировки массива с плагинами по имени */
		$sort = function($a, $b) {
			$a['name'] = strtolower($a['name']);
			$b['name'] = strtolower($b['name']);
			if ($a['name'] == $b['name']) {
				return 0;
			}
			return ($a['name'] < $b['name']) ? -1 : 1;
		};

		usort($tpl_data, $sort);
		
		return $tpl_data;
		
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Сохранение плагинов
     * 
     * @param array		$plugins_enabled массив со включенными плагинами
     * @param string	$plugins_ini загруженный файл
     * @param array 	$plugins_in_cfg_list массив с обработанными данными плагинов
     * 
     * @return bool
    */
	private function _save_plugins($plugins_enabled = array(), $plugins_debug = array(), $plugins_ini = '', $plugins_in_cfg_list = array())
	{
		$plugins_ini_array = explode("\n", $plugins_ini);
		$new_strings = array();
		$plugins_left = array(); // Пройденные плагины (чтобы не повторялись)
		
		//~ print_r($plugins_debug);
		
		$i = 0;
		foreach($plugins_ini_array as &$string) {

			if (preg_match($this->_plugins_pattern, $string, $matches)) {
				
				$plugin_name = $matches[2];
				if (in_array($plugin_name, $plugins_left)) {
					$string = ''; // Тупо оставляем пустую строчку
					$i++;
					continue;
				}
				
				$plugins_left[] = $plugin_name; 

				/* Удаляем плагин из списка как использованный.
				 * Оставшиеся в переменной плагины будут добавлены
				 * в новые строчки plugins.ini */
				unset($this->_plugins_list[array_search($plugin_name, $this->_plugins_list)]);
				
				$debug = in_array($plugin_name, $plugins_debug) ? ' debug' : '';
				$description = '';
				$description = $plugins_in_cfg_list[$plugin_name]['description'] ? 
									"\t\t\t\t\t ; " . $plugins_in_cfg_list[$plugin_name]['description'] : 
									'';
				
				$string = in_array($plugin_name, $plugins_enabled) ?
											$plugin_name . $debug . $description :
											'; ' . $plugin_name . $debug . $description ;	// Плагин закомментирован
			}
			
			$i ++;
		}
		
		//~ print_r($this->_plugins_list);
		
		foreach($this->_plugins_list as &$plugin) {
			
			$debug = in_array($plugin, $plugins_debug) ? ' debug' : '';
			
			$new_strings[] = in_array($plugin, $plugins_enabled) ?
								$plugin . $debug :
								'; ' . $plugin . $debug ;	// Плагин закомментирован
		}

		$plugins_ini_contents = implode("\n" , $plugins_ini_array) . implode("\n" , $new_strings);
		
		if ($this->servers->write_file($this->servers->server_data['start_code'] . '/addons/amxmodx/configs/plugins.ini', $plugins_ini_contents)) {
			return true;
		} else {
			return false;
		}
	}
    
    // ----------------------------------------------------------------
    
    /**
     * Главная страница. Выбор сервера
    */
	public function index()
	{
		$local_tpl_data['servers_list'] = $this->_get_servers_tpl();
		$local_tpl_data['url'] 			= site_url('amxx_plugins_control/server');
		
		$this->tpl_data['content'] .= $this->parser->parse('servers/select_server.html', $local_tpl_data, true);
			
		$this->parser->parse('main.html', $this->tpl_data);
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Управление плагинами на сервере
    */
	public function server($server_id = false)
	{
		$this->load->library('form_validation');
		
		$local_tpl_data = array();
		$local_tpl_data['server_id'] = (int)$server_id;
		
		if (!$server_id) {
			$this->_show_message(lang('adm_servers_server_not_found'));
			return false;
		}
		
		/* Существует ли сервер */
		if (!$this->servers->get_server_data($server_id)) {
			$this->_show_message(lang('adm_servers_server_not_found'));
			return false;
		}
		
		if (strtolower($this->servers->server_data['engine']) != 'goldsource') {
			$this->_show_message(lang('amxx_no_golsource'));
			return false;
		}
		
		/* Проверка привилегий на сервер */
		$this->users->get_server_privileges($server_id);
		
		/* Проверка на права загрузки и правки конфигурационный файлов сервера */
		if(!$this->users->auth_servers_privileges['UPLOAD_CONTENTS']
		&& !$this->users->auth_servers_privileges['CHANGE_CONFIG']
		){
			$this->_show_message(lang('server_files_no_privileges'), site_url('admin/servers_files'));
			return false;
		}
		
		//~ $plugins_list = $this->servers->get_files_list(false, $this->servers->server_data['start_code'] . '/addons/amxmodx/plugins/*.amxx');
		
		/* Перебор плагинов */
		foreach($this->servers->get_files_list(false, $this->servers->server_data['start_code'] . '/addons/amxmodx/plugins/*.amxx') as $plugin) {
			$this->_plugins_list[] = basename($plugin['file_name']);
		}
		
		$cfg_plugins = $this->servers->read_file($this->servers->server_data['start_code'] . '/addons/amxmodx/configs/plugins.ini');
		$plugins_in_cfg_list = $this->_parse_plugins_in_cfg($cfg_plugins);
		
		if (!$plugins_in_cfg_list) {
			$this->_show_message(lang('amxx_plugins_get_errors'));
			return false;
		}
		
		$this->form_validation->set_rules('plugins_enabled', lang('enabled'), '');
		$this->form_validation->set_rules('plugins_debug', lang('amxx_debug'), '');
		
		if (!$this->form_validation->run()) {
			$local_tpl_data['plugins_list'] = $this->_plugins_list_to_tpl($plugins_in_cfg_list);
			$this->tpl_data['content'] = $this->parser->parse('plugins_list.html', $local_tpl_data, true);
		} else {
			
			$post_enabled 	= $this->input->post('plugins_enabled');
			$post_debug 	= $this->input->post('plugins_debug');
			
			$plugins_enabled 	= array();
			$plugins_debug 		= array();
			
			foreach($this->_plugins_list as &$plugin) {
				if (!empty($post_enabled)) {
					/* Включен ли плагин */
					if(array_key_exists(str_replace('.amxx', '', $plugin), $post_enabled)) {
						$plugins_enabled[] = $plugin;
					}
				}
				
				if (!empty($post_debug)) {
					/* Включен ли дебаг */
					if(array_key_exists(str_replace('.amxx', '', $plugin), $post_debug)) {
						$plugins_debug[] = $plugin;
					}
				}
			}
			
			if (!$this->_save_plugins($plugins_enabled, $plugins_debug, $cfg_plugins, $plugins_in_cfg_list)) {
				$this->_show_message(lang('amxx_plugins_save_failed'));
				return false;
			}

			/* Перезагружаем сервер 
			 * Отправляем серверу ркон команду restart
			 * Если сервер выключен, то никаких отправок не будет
			*/
			if ($this->input->post('save_and_restart') && $this->servers->server_status($this->servers->server_data['server_ip'], $this->servers->server_data['rcon_port'], $this->servers->servers->server_data['engine'])) {
				$this->load->driver('rcon');

				$this->rcon->set_variables(
										$this->servers->server_data['server_ip'],
										$this->servers->server_data['rcon_port'],
										$this->servers->server_data['rcon'], 
										$this->servers->servers->server_data['engine'],
										$this->servers->servers->server_data['engine_version']
				);

				$rcon_string = $this->rcon->command('restart');
			}
			
			$this->_show_message(lang('amxx_plugins_saved'), site_url('amxx_plugins_control/server/' . $server_id), lang('next'));
			return true;
			
		}

		$this->parser->parse('main.html', $this->tpl_data);
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Управление репозиториями с плагинами
     * 
     * Coming soon
    */
	public function repositories($game = 'valve')
	{
		/* Есть ли у пользователя админские права */
		if (false == $this->users->auth_data['is_admin']) {
			show_404();
		}
		
		$this->parser->parse('main.html', $this->tpl_data);
	}
	
}

/* End of file amxx_plugins_control.php */
/* Location: ./application/modules/amxx_plugins_control/controllers/amxx_plugins_control.php */

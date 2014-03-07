<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Game AdminPanel (АдминПанель)
 *
 * 
 *
 * @package		Game AdminPanel
 * @author		Nikita Kuznetsov (ET-NiK)
 * @copyright	Copyright (c) 2014, Nikita Kuznetsov (http://hldm.org)
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
		$this->load->model('servers/games');
		
		// Загрузка языковых файлов
		$this->lang->load('adm_servers');
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
    
    // -----------------------------------------------------------------
	
	/**
	 * Получение данных фильтра для вставки в шаблон
	 */
	private function _get_tpl_filter($filter = false)
	{
		if (!$filter) {
			$filter = $this->users->get_filter('servers_list');
		}
		
		if (empty($this->games->games_list)) {
			$this->games->get_games_list();
		}
		
		$games_option[0] = '---';
		foreach($this->games->games_list as &$game) {
			$games_option[ $game['code'] ] = $game['name'];
		}
		
		$tpl_data['filter_name']			= isset($filter['name']) ? $filter['name'] : '';
		$tpl_data['filter_ip']				= isset($filter['ip']) ? $filter['ip'] : '';
		
		$default = isset($filter['game']) ? $filter['game'] : null;
		$tpl_data['filter_games_dropdown'] 	= form_dropdown('filter_game', $games_option, $default);
		
		return $tpl_data;
	}
    
    // ----------------------------------------------------------------
    
    /**
     * Получение данных сервера для шаблона
    */
    private function _get_servers_tpl($filter, $limit = 10000, $offset = 0)
    {
		$tpl_data = array();
		
		$this->servers->set_filter($filter);
		
		/* Получение игровых серверов GoldSource */
		if (!isset($this->servers_list)) {
			$this->servers->get_servers_list($this->users->auth_id, 'VIEW', array('enabled' => '1', 'installed' => '1'), $limit, $offset, 'goldsource');
		}
		
		$tpl_data['url'] 			= site_url('admin/servers_files/server');
		$tpl_data['games_list'] 	= servers_list_to_games_list($this->servers->servers_list);

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
		
		$dir = get_ds_file_path($this->servers->server_data) . '/' . $this->servers->server_data['start_code'] . '/addons/amxmodx/';

		if (write_ds_file($dir . 'configs/plugins.ini', $plugins_ini_contents, $this->servers->server_data)) {
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
		$this->load->helper('games');
		
		$filter = $this->users->get_filter('servers_list');
		$local_tpl_data = $this->_get_tpl_filter($filter);
		
		$local_tpl_data 		+= $this->_get_servers_tpl($filter);
		$local_tpl_data['url'] 	= site_url('amxx_plugins_control/server');

		$this->tpl_data['content'] .= $this->parser->parse('servers/select_server.html', $local_tpl_data, true);
			
		$this->parser->parse('main.html', $this->tpl_data);
	}
	
	// ----------------------------------------------------------------
    
    /**
     * Управление плагинами на сервере
    */
	public function server($server_id = false)
	{
		$this->load->helper('ds');
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
		$dir = get_ds_file_path($this->servers->server_data) . '/' . $this->servers->server_data['start_code'] . '/addons/amxmodx/';
		
		try {
			$list_files = list_ds_files($dir . 'plugins', $this->servers->server_data, true, array('amxx'));
		} catch (Exception $e) {
			$message = $e->getMessage();
			
			$this->_show_message($message);

			// Сохраняем логи ошибок
			$log_data['type'] = 'server_files';
			$log_data['command'] = 'list_files';
			$log_data['user_name'] = $this->users->auth_login;
			$log_data['server_id'] = $this->servers->server_data['id'];
			$log_data['msg'] = $message;
			$log_data['log_data'] = 'Dir: ' . $dir . 'plugins';
			$this->panel_log->save_log($log_data);
			
			return false;
		}
		
		/* Перебор плагинов */
		foreach($list_files as $plugin) {
			$this->_plugins_list[] = basename($plugin['file_name']);
		}
		
		try {
			$cfg_plugins = read_ds_file($dir . 'configs/plugins.ini', $this->servers->server_data);
		} catch (Exception $e) {
			$message = $e->getMessage();
			
			$this->_show_message($message);

			// Сохраняем логи ошибок
			$log_data['type'] = 'server_files';
			$log_data['command'] = 'read_file';
			$log_data['user_name'] = $this->users->auth_login;
			$log_data['server_id'] = $this->servers->server_data['id'];
			$log_data['msg'] = $message;
			$log_data['log_data'] = 'File' . $dir . 'configs/plugins.ini';
			$this->panel_log->save_log($log_data);
			
			return false;
		}
		
		$plugins_in_cfg_list = $this->_parse_plugins_in_cfg($cfg_plugins);
		
		if (!$plugins_in_cfg_list) {
			$this->_show_message(lang('amxx_plugins_get_errors'));
			return false;
		}
		
		$this->form_validation->set_rules('plugins_enabled', lang('enabled'), '');
		$this->form_validation->set_rules('plugins_debug', lang('amxx_debug'), '');
		
		if (!$this->form_validation->run()) {
			
			if (validation_errors()) {
				$this->_show_message(validation_errors());
				return false;
			}
			
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
			
			try {
				$this->_save_plugins($plugins_enabled, $plugins_debug, $cfg_plugins, $plugins_in_cfg_list);
			} catch (Exception $e) {
				$message = $e->getMessage();
				
				$this->_show_message(lang('amxx_plugins_save_failed') . '<br />' . $message);

				// Сохраняем логи ошибок
				$log_data['type'] = 'server_files';
				$log_data['command'] = 'edit_config';
				$log_data['user_name'] = $this->users->auth_login;
				$log_data['server_id'] = $this->servers->server_data['id'];
				$log_data['msg'] = $message;
				$log_data['log_data'] = '';
				$this->panel_log->save_log($log_data);
				
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

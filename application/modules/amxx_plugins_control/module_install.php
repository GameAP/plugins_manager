<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Game AdminPanel (АдминПанель)
 *
 * 
 *
 * @package		Game AdminPanel
 * @author		Nikita Kuznetsov (ET-NiK)
 * @copyright	Copyright (c) 2013, Nikita Kuznetsov (http://hldm.org)
 * @license		http://www.gameap.ru/license.html
 * @link		http://www.gameap.ru
 * @filesource	
 */
 
/**
 * Структура базы данных amxx_plugins_control модуля
 *
 * @package		Game AdminPanel
 * @author		Nikita Kuznetsov (ET-NiK)
*/

$this->load->dbforge();

/*----------------------------------*/
/* 				amxx_plugins		*/
/*----------------------------------*/
if (!$this->db->table_exists('amxx_plugins')) {

	$fields = array(
			'id' => array(
								'type' => 'INT',
								'constraint' => 16, 
								'auto_increment' => true
			),
			
			'game_code' => array(
								'type' => 'CHAR',
								'constraint' => 64,
								'default' => 'valve',
			),
			
			'remote_link' => array(
								'type' => 'TEXT',
								'default' => '',
			),
			
			'local_link' => array(
								'type' => 'TEXT',
								'default' => '',
			),
			
			'name' => array(
								'type' => 'TINYTEXT',
								'default' => '',
			),
			
			'description' => array(
								'type' => 'TEXT',
								'default' => '',
			),
			
			'developer' => array(
								'type' => 'CHAR',
								'constraint' => 64,
								'default' => '',
			),
			
			'plugin_site' => array(
								'type' => 'CHAR',
								'constraint' => 64,
								'default' => '',
			),
			
			
	);

	$this->dbforge->add_field($fields);
	$this->dbforge->add_key('id', true);
	$this->dbforge->create_table('amxx_plugins');
}



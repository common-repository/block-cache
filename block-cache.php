<?php
/*
Plugin Name: Block Cache
*/

class BC {
	private $cache_type = 'sql';
	private $cache_time = null;
	function table(){
		global $wpdb;
		return $wpdb->prefix.'block_cache';
	}
	
	/*****
	 * 检查缓存是否存在
	 * $name 为缓存名字，必须
	 * $expired 检查缓存是否过期，可选
	 * 当 $expired 为 true 时，返回未过期的缓存，否则无论缓存是否过期都返回缓存的结果
	 *****/
	function has_cache($name = null, $expired = false){
		if(!is_string($name) || $name == null || '') return;
		global $wpdb;
		$table = self::table();
		if($expired){
			//$now = current_time('mysql');
			$sql = "SELECT * FROM $table WHERE name = '$name' AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_update) < expired AND 1=1 LIMIT 0,1";
		}else{
			$sql = "SELECT * FROM $table WHERE name = '$name' AND 1=1 LIMIT 0,1";
		}
		$has = $wpdb->get_results($sql, OBJECT_K);
		//return array($has,$sql);
		return $has;
	}
	
	/*****
	 * 删除缓存
	 * $name 为缓存名字，必须
	 * $expired 检查缓存是否过期，可选
	 * 当 $expired 为 true ，并且缓存已经过期时删除缓存，否则无论缓存是否过期都删除
	 *****/
	function delete_cache($name = null, $expired = false){
		if(!is_string($name) || $name == null || '') return;
		global $wpdb;
		$table = self::table();
		if($expired){
			$delete = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE name = %s AND AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_update) > expired", $name));
		}else{
			$delete = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE name = %s", $name));
		}
		return $delete;
	}
	
	/*****
	 * 获取缓存
	 * $name 为缓存名字，必须
	 * $expired 缓存过期时间，可选
	 *****/
	 function get_cache($name = null, $expired = false){
		$cache = self::has_cache($name, $expired);
		$cache[$name]->content = maybe_unserialize($cache[$name]->content);
		return $cache;
	 }
	 
	/*****
	 * 写入缓存
	 * $name 为缓存名字，必须
	 * $content 缓存内容，必须
	 * $expired 缓存过期时间，可选
	 * $overwrite 是否覆盖旧内容，可选
	 *****/
	 function set_cache($name = null, $content, $expired = 3600, $overwrite = true){
		if(!is_string($name) || ($name == null || '') || !$content) return;
		global $wpdb;
		$table = self::table();
		if($overwrite && self::has_cache($name)){
			$data['content'] = maybe_serialize($content);
			$data['last_update'] = current_time('mysql');
			$data['expired'] = $expired;
			$set = $wpdb->update($table, $data, array('name' => $name));
		}else{
			$data['name'] = $name;
			$data['content'] = maybe_serialize($content);
			$data['last_update'] = current_time('mysql');
			$data['expired'] = $expired;
			$set = $wpdb->insert($table, $data);
		}
		return $set;
	 }
	 
	/*****
	 * 更新缓存
	 * $name 为缓存名字，必须
	 * $content 缓存内容，必须
	 * $expired 缓存过期时间，可选
	 *****/
	 function update_cache($name = null, $content, $expired = 3600){
		if(!is_string($name) || ($name == null || '') || !$content) return;
		return self::set_cache($name, $content, $expired, true);
	 }
}

register_activation_hook(__FILE__,'block_cache_install');
function block_cache_install() {
	global $wpdb;
	$table_name = $wpdb->prefix.'block_cache';
	
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "
CREATE TABLE IF NOT EXISTS `$table_name` (
  `name` varchar(36) NOT NULL,
  `content` longtext NOT NULL,
  `last_update` datetime NOT NULL,
  `expired` bigint(20) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}
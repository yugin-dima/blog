<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group 
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004-2018 SoftNews Media Group
=====================================================
 This code is protected by copyright
=====================================================
 File: plugins.php
-----------------------------------------------------
 Use: AJAX plugins manage
=====================================================
*/

if(!defined('DATALIFEENGINE')) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

if($member_id['user_group'] != 1) {
	echo_error ($lang['sess_error']);
}

if( $_REQUEST['user_hash'] == "" or $_REQUEST['user_hash'] != $dle_login_hash ) {
	echo_error ($lang['sess_error']);
}

if( !check_referer( $config['http_home_url'].$config['admin_path']."?mod=plugins") ) {
	echo_error ($lang['no_referer']);
}

if( !$config['allow_plugins'] ) {
	echo_error ($lang['module_disabled']);
} elseif( DLEPlugins::$read_only ) {
	echo_error ($lang['plugins_errors_6']);
}

if(!function_exists('simplexml_load_string')) {
	echo_error ("You need the PHP 'SimpleXML' extension installed");
}

if( !class_exists('ZipArchive') ) {
	echo_error ("You need the PHP 'ZipArchive' extension installed");
}

include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/zipextract.class.php'));

if($_POST['id']) {
	
	$id = intval($_POST['id']);
	unset($_SESSION['upload_plugins']['id']);
	
} else $id = 0;

if( !isset($_SESSION['upload_plugins']['id']) ) $_SESSION['upload_plugins']['id'] = $id;


if($_POST['action'] == "checkftp") {
	
	try {
		
		$fs = new dle_zip_extract();
		$fs->FtpConnect( $_POST['ftp'] );
		$fs->DisconnectFTP();
		
	} catch ( Exception $e ) {
		
		echo_error ($e->getMessage(), false);

	}

	$_SESSION['upload_plugins']['ftp'] = $_POST['ftp'];
	
}


if ( isset($_SESSION['upload_plugins']['file']) AND isset($_SESSION['upload_plugins']['ftp']) ) {
	
	if ( file_exists( ENGINE_DIR . "/cache/system/" . md5('uploads_plugin'.SECURE_AUTH_KEY) . ".zip" ) ) {
		
		$_FILES['pluginfile']['tmp_name'] = ENGINE_DIR . "/cache/system/" . md5('uploads_plugin'.SECURE_AUTH_KEY) . ".zip";
		$_FILES['pluginfile']['name'] = md5('uploads_plugin'.SECURE_AUTH_KEY) . ".zip";
		
	} else {
		echo_error ($lang['upload_error_3']);
	}
	
} else {
	
	if( !$_FILES['pluginfile']['tmp_name'] OR !is_uploaded_file( $_FILES['pluginfile']['tmp_name'] ) ) {
		echo_error ($lang['upload_error_3']);
	}
	
}

function echo_error ($text, $unset = true) {
	
	if($unset AND isset( $_SESSION['upload_plugins']['file'] ) ) {
		unset($_SESSION['upload_plugins']['file']);
		@unlink(ENGINE_DIR . "/cache/system/" . md5('uploads_plugin'.SECURE_AUTH_KEY) . ".zip");
	}
	
	if($unset AND isset( $_SESSION['upload_plugins']['id'] ) ) {
		unset( $_SESSION['upload_plugins']['id'] );
	}
	
	echo json_encode(array('status' => 'error', 'text' => $text));
	die();

}

function install_xml_plugin ($plugin, $id) {
	global $config, $db, $member_id, $_TIME, $_IP, $lang;

	$id = intval($id);
	libxml_use_internal_errors(true);
	
	$xml = simplexml_load_string($plugin);
	
	if (!$xml) {
		
		$errors = libxml_get_errors();
		echo_error(sprintf( "XML error: %s at line %d", $errors[0]->message, $errors[0]->line ));
		
	} else {
		
		if ( $xml->name ) $name = (string)$xml->name;
		if ( $xml->description ) $description = (string)$xml->description;
		if ( $xml->icon ) $icon = (string)$xml->icon;
		if ( $xml->version ) $version = (string)$xml->version;
		if ( $xml->dleversion ) $dleversion = (string)$xml->dleversion;
		if ( $xml->versioncompare ) $versioncompare = (string)$xml->versioncompare;
		
		if( $versioncompare == "greater" ) $versioncompare = '>=';
		elseif ( $versioncompare == "less") $versioncompare = '<=';
		
		if ( $xml->mysqlinstall ) $_POST['mysqlinstall'] = trim((string)$xml->mysqlinstall);
		if ( $xml->mysqlupgrade ) $_POST['mysqlupgrade'] = trim((string)$xml->mysqlupgrade);
		if ( $xml->mysqlenable )  $_POST['mysqlenable'] = trim((string)$xml->mysqlenable);
		if ( $xml->mysqldisable ) $_POST['mysqldisable'] = trim((string)$xml->mysqldisable);
		if ( $xml->mysqldelete )  $_POST['mysqldelete'] = trim((string)$xml->mysqldelete);
		
		$i=0;
		$t=0;
		
		if ( $xml->file ) {
			foreach ($xml->file as $file) {
				$i++;
				$_POST['file'][$i] = (string)$file->attributes()->name;
				
				if ( $file->operation ) {
					foreach ($file->operation as $operation) {
						$t++;
						$_POST['fileaction'][$i][$t] = (string)$operation->attributes()->action;
						
						if($operation->searchcode) $_POST['filesearch'][$i][$t] = (string)$operation->searchcode;
						if($operation->replacecode) $_POST['filereplace'][$i][$t] = (string)$operation->replacecode;
						
					}
					
					
				}
				
			}
		}
		
		$name = $db->safesql(htmlspecialchars( trim($name), ENT_QUOTES, $config['charset'] ));
		$description = $db->safesql(htmlspecialchars( trim($description), ENT_QUOTES, $config['charset'] ));
		$icon = $db->safesql( clearfilepath( htmlspecialchars( trim($icon), ENT_QUOTES, $config['charset'] ), array ("gif", "jpg", "jpeg", "png" ) ) );
		$version = $db->safesql(htmlspecialchars( trim($version), ENT_QUOTES, $config['charset'] ));
		$dleversion = $db->safesql(htmlspecialchars( trim($dleversion), ENT_QUOTES, $config['charset'] ));
		if ( in_array( $versioncompare, array("==", ">=", "<=") ) ) $versioncompare = $db->safesql($versioncompare); else $versioncompare = '';
		
		$mysqlinstall = $db->safesql($_POST['mysqlinstall']);
		$mysqlupgrade = $db->safesql($_POST['mysqlupgrade']);
		$mysqlenable = $db->safesql($_POST['mysqlenable']);
		$mysqldisable = $db->safesql($_POST['mysqldisable']);
		$mysqldelete = $db->safesql($_POST['mysqldelete']);
		
		if( $dleversion AND $versioncompare) {
			if( !version_compare($config['version_id'], $dleversion, $versioncompare) ) {
				
				$versioncompare = str_replace(array("==", ">=", "<="), array($lang['plugins_vc_1'], $lang['plugins_vc_2'], $lang['plugins_vc_3']), $versioncompare);
				$lang['plugins_nerror_2'] = str_replace(array("{version}", "{versioncompare}", "{dleversion}"), array($dleversion,$versioncompare,$config['version_id']), $lang['plugins_nerror_2']);
				echo_error ($lang['plugins_nerror_2']);
			}
		}
		
		if( !$name ) echo_error ($lang['plugins_nerror']);
		
		$files = array();
		$allowed_action =array("replace", "before", "after", "replaceall", "create");
		
		if(is_array($_POST['file']) AND count($_POST['file']) ) {
			
			foreach($_POST['file'] as $key => $value) {
				$file_name = clearfilepath( trim($value) , array ("php", "lng" ) );
				
				if(!$file_name) continue;
				
				if( in_array( $file_name, DLEPlugins::$protected_files ) ) {
					
					$lang['plugins_errors_7'] = str_replace ("{file}", $file_name, $lang['plugins_errors_7']);
					echo_error ($lang['plugins_errors_7']);

				}
		
				if(is_array($_POST['fileaction'][$key]) AND count($_POST['fileaction'][$key]) ) {
					
					foreach($_POST['fileaction'][$key] as $key2 => $value2) {
						
						if( !in_array($value2, $allowed_action) ) continue;
						
						$file_action = $value2;
						$file_search = $_POST['filesearch'][$key][$key2];
						$file_replace = $_POST['filereplace'][$key][$key2];
						
						if( !trim($file_search) ) $file_search ='';
						if( !trim($file_replace) ) $file_replace ='';
	
						if( ($file_action == "replace" OR $file_action == "before" OR $file_action == "after") AND !$file_search ) continue;
						
						if( ($file_action == "before" OR $file_action == "after" OR $file_action == "replaceall" OR $file_action == "create") AND !$file_replace) continue;
						
						$files[$file_name][] = array('action' => $file_action, 'searchcode' => $file_search, 'replacecode' => $file_replace );
	
					}
				}
				
			}
		}
		
		if (!$id) {
			
			$row = $db->super_query( "SELECT id FROM " . PREFIX . "_plugins WHERE name='{$name}'" );
			
			if( $row['id'] ) {
				echo_error ($lang['plugins_nerror_1']);
			}
			
			$db->query( "INSERT INTO " . PREFIX . "_plugins (name, description, icon, version, dleversion, versioncompare, active, mysqlinstall, mysqlupgrade, mysqlenable, mysqldisable, mysqldelete) values ('{$name}', '{$description}','{$icon}','{$version}','{$dleversion}','{$versioncompare}', '1', '{$mysqlinstall}', '{$mysqlupgrade}','{$mysqlenable}','{$mysqldisable}','{$mysqldelete}')" );
			$id = $_SESSION['upload_plugins']['id'] = $db->insert_id();
			$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '116', '{$name}')" );
	
			execute_query($id, $_POST['mysqlinstall'] );
			execute_query($id, $_POST['mysqlenable'] );
			
		} else {
			
			$row = $db->super_query( "SELECT id FROM " . PREFIX . "_plugins WHERE id='{$id}'" );
			
			if (!$row['id']) echo_error ("ID not valid", "ID not valid");
			
			$row = $db->super_query( "SELECT id FROM " . PREFIX . "_plugins WHERE name='{$name}'" );
		
			if( $row['id'] AND $row['id'] != $id ) {
				echo_error ($lang['plugins_nerror_1']);
			}
		
			$db->query( "DELETE FROM " . PREFIX . "_plugins_logs WHERE plugin_id = '{$id}'" );
			$db->query( "UPDATE " . PREFIX . "_plugins SET name='$name', description='$description', icon='$icon', version='$version', dleversion='$dleversion', versioncompare='$versioncompare', mysqlinstall='$mysqlinstall', mysqlupgrade='$mysqlupgrade', mysqlenable='$mysqlenable', mysqldisable='$mysqldisable', mysqldelete='$mysqldelete' WHERE id='{$id}'" );
			$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '117', '{$name}')" );
	
			execute_query($id, $_POST['mysqlupgrade'] );
			
		}
		
		$db->query( "DELETE FROM " . PREFIX . "_plugins_files WHERE plugin_id='{$id}'" );
		
		if(count($files)) {
			
			$row = $db->super_query( "SELECT active FROM " . PREFIX . "_plugins WHERE id='{$id}'" );
			
			foreach( $files as $key => $value ) {
				foreach ($value as $value2) {
					$key = $db->safesql($key);
					$value2['action'] = $db->safesql($value2['action']);
					$value2['searchcode'] = $db->safesql($value2['searchcode']);
					$value2['replacecode'] = $db->safesql($value2['replacecode']);
					$db->query( "INSERT INTO " . PREFIX . "_plugins_files (plugin_id, file, action, searchcode, replacecode, active) values ('{$id}', '{$key}', '{$value2['action']}', '{$value2['searchcode']}', '{$value2['replacecode']}', '{$row['active']}')" );
				}
	
			}
	
		}
		
	}

}

function folders_check_chmod( $dir,  $bad_folders = array() ) {

	$folder = str_replace(ROOT_DIR, "", $dir);

	if(!is_writable($dir)) {
		$bad_folders[] = $folder;
	}

	if ( $dh = @opendir( $dir ) ) {
		
		while ( false !== ( $file = readdir($dh) ) ) {
			
			if ( $file == '.' or $file == '..' or $file == '.svn' or $file == '.DS_store' ) {
					continue;
			}
		
			if ( is_dir( $dir . "/" . $file ) ) {

				$bad_folders = folders_check_chmod( $dir . "/" . $file, $bad_folders );
				
			}
		}
	}
	
	return $bad_folders;
}


$filename_arr = explode( ".", $_FILES['pluginfile']['name'] );
$type = strtolower(end( $filename_arr ));

if($type != "xml" AND $type != "zip") {
	echo_error ($lang['plugins_errors_8']);
}

if( $type == "xml" ){
	$plugin_file = trim( @file_get_contents($_FILES['pluginfile']['tmp_name']) );
	
	if(!$plugin_file) {
		echo_error ($lang['upload_error_3']);
	}
	
	install_xml_plugin($plugin_file, $_SESSION['upload_plugins']['id']);
	
	
} else {
	
	include_once (DLEPlugins::Check(ENGINE_DIR.'/classes/antivirus.class.php'));
	$zip = new ZipArchive();
	$antivirus = new antivirus();
	
	if(@$zip->open( $_FILES['pluginfile']['tmp_name'], ZIPARCHIVE::CHECKCONS ) !== true) {
		echo_error ($lang['upgr_f_error_16']);
	}
	
	$plugin_file = false;
	$plugin_file_index = false;
	
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {

		if ( $zip->statIndex($i) ) {
			$file = $zip->statIndex($i);
			
			if ( substr($file['name'], -1) == '/' ) continue;
			
			$filename_arr = explode( ".", $file['name'] );
			$type = strtolower(end( $filename_arr ));
			
			if( $type == "xml" AND strpos($file['name'], "/") == false ) {
				$plugin_file = $zip->getFromIndex($i);
				$plugin_file_index = $i;
			}
			
			if(in_array("./" . $file['name'], $antivirus->good_files)) {
				echo_error ($lang['plugins_errors_10']);
			}

		}

	}
	
	if( !$plugin_file ) {
		echo_error ($lang['plugins_errors_9']);
	}
	
	$no_access = folders_check_chmod(ROOT_DIR."/engine" );
	$no_access = array_merge($no_access, folders_check_chmod(ROOT_DIR."/language" ) );
	
	if(count($no_access) AND !isset( $_SESSION['upload_plugins']['ftp'] )) {
		
        if(@move_uploaded_file($_FILES['pluginfile']['tmp_name'], ENGINE_DIR . "/cache/system/" . md5('uploads_plugin'.SECURE_AUTH_KEY) . ".zip")) {
			$_SESSION['upload_plugins']['file'] = true;
			echo "{\"status\": \"needftp\"}";
			die();
        } else {
			echo_error ("{$lang['media_upload_st6']} {$_FILES['pluginfile']['name']} {$lang['media_upload_st10']}");
		}
		
	}
	
	install_xml_plugin($plugin_file, $_SESSION['upload_plugins']['id']);
	
	try {
		
		$fs = new dle_zip_extract( $_FILES['pluginfile']['tmp_name'] );
		$fs->skip_index[] = $plugin_file_index;
		
		if( $_SESSION['upload_plugins']['ftp'] ) {
			$fs->FtpConnect( $_SESSION['upload_plugins']['ftp'] );
		}
		
		$fs->ExtractZipArchive();
		
		if( $_SESSION['upload_plugins']['ftp'] ) {
			$fs->DisconnectFTP();
		}
		
		if( isset( $_SESSION['upload_plugins']['file'] ) ) {
			unset($_SESSION['upload_plugins']['file']);
			@unlink(ENGINE_DIR . "/cache/system/" . md5('uploads_plugin'.SECURE_AUTH_KEY) . ".zip");
		}
		
		if( count($fs->errors_list) ) {
			foreach($fs->errors_list as $error) {
				$db->query( "INSERT INTO " . PREFIX . "_plugins_logs (plugin_id, area, error, type) values ('{$_SESSION['upload_plugins']['id']}', '".$db->safesql( htmlspecialchars( $error['file'], ENT_QUOTES, $config['charset'] ), false)."', '".$db->safesql( htmlspecialchars( $error['error'], ENT_QUOTES, $config['charset'] ) )."', 'upload')" );
			}
		}
		
	} catch ( Exception $e ) {

		echo_error ($e->getMessage());
		
	}

}

unset($_SESSION['upload_plugins']['id']);
clear_all_caches();
echo "{\"status\": \"succes\"}";

?>
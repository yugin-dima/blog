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
 File: blockip.php
-----------------------------------------------------
 Use: Blocking visitors by IP
=====================================================
*/
if( !defined( 'DATALIFEENGINE' ) OR !defined( 'LOGGED_IN' ) ) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

if( ! $user_group[$member_id['user_group']]['admin_blockip'] ) {
	msg( "error", $lang['index_denied'], $lang['index_denied'] );
}

if( isset( $_REQUEST['ip_add'] ) ) $ip_add = $db->safesql( htmlspecialchars( strip_tags( trim( $_REQUEST['ip_add'] ) ), ENT_QUOTES, $config['charset'] ) ); else $ip_add = "";
if( isset( $_REQUEST['ip'] ) ) $ip = htmlspecialchars( strip_tags( trim( $_REQUEST['ip'] ) ), ENT_QUOTES, $config['charset'] ); else $ip = "";
if( isset( $_REQUEST['id'] ) ) $id = intval( $_REQUEST['id'] ); else $id = 0;

if( $action == "add" ) {
	
	if( $_REQUEST['user_hash'] == "" or $_REQUEST['user_hash'] != $dle_login_hash ) {
		
		die( "Hacking attempt! User not found" );
	
	}
	
	include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/parse.class.php'));
	
	$parse = new ParseFilter();
	$parse->safe_mode = true;
	$banned_descr = $db->safesql( $parse->BB_Parse( $parse->process( $_POST['descr'] ), false ) );
	
	if( (trim( $_POST['date'] ) == "") OR (($_POST['date'] = strtotime( $_POST['date'] )) === - 1) OR !$_POST['date']) {
		$this_time = 0;
		$days = 0;
	} else {
		$this_time = $_POST['date'];
		$days = 1;
	}
	
	if( ! $ip_add ) {
		msg( "error", $lang['ip_error'], $lang['ip_error'], "?mod=blockip" );
	}

	$row = $db->super_query( "SELECT id FROM " . PREFIX . "_banned WHERE ip ='$ip_add'" );

	if ( $row['id'] ) {
		msg( "error", $lang['ip_error_1'], $lang['ip_error_1'], "?mod=blockip" );
	}
	
	$db->query( "INSERT INTO " . USERPREFIX . "_banned (descr, date, days, ip) values ('$banned_descr', '$this_time', '$days', '$ip_add')" );
	$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '9', '{$ip_add}')" );
	
	@unlink( ENGINE_DIR . '/cache/system/banned.php' );

} elseif( $action == "delete" ) {
	
	if( $_REQUEST['user_hash'] == "" or $_REQUEST['user_hash'] != $dle_login_hash ) {
		
		die( "Hacking attempt! User not found" );
	
	}
	
	if( ! $id ) {
		msg( "error", $lang['ip_error'], $lang['ip_error'], "?mod=blockip" );
	}
	
	$db->query( "DELETE FROM " . USERPREFIX . "_banned WHERE id = '$id'" );
	$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '10', '')" );

	@unlink( ENGINE_DIR . '/cache/system/banned.php' );

}

echoheader( "<i class=\"fa fa-lock position-left\"></i><span class=\"text-semibold\">{$lang['opt_ipban']}</span>", $lang['header_filter_1'] );

echo <<<HTML
<form action="" method="post" class="form-horizontal">
<input type="hidden" name="action" value="add">
<input type="hidden" name="user_hash" value="{$dle_login_hash}">
<div class="panel panel-default">
  <div class="panel-heading">
    {$lang['ip_add']}
  </div>
  <div class="panel-body">
	
		<div class="form-group">
		  <label class="control-label col-md-2 col-sm-3">{$lang['ip_type']}</label>
		  <div class="col-md-10 col-sm-9">
			<input class="form-control width-350" type="text" name="ip_add" value="{$ip}">
		  </div>
		 </div>
		<div class="form-group">
		  <label class="control-label col-md-2 col-sm-3">{$lang['ban_date']}</label>
		  <div class="col-md-10 col-sm-9">
			<input  class="form-control" style="width:190px;" data-rel="calendar" type="text" name="date" autocomplete="off">
		  </div>
		 </div>
		<div class="form-group">
		  <label class="control-label col-md-2 col-sm-3">{$lang['ban_descr']}</label>
		  <div class="col-md-10 col-sm-9">
			<textarea  class="classic width-350" rows="5" name="descr"></textarea>
		  </div>
		 </div>
		 
	</div>
	
	<div class="panel-body text-muted text-size-small" style="margin-top: -20px;">
		{$lang['ip_example']}
	</div>
	<div class="panel-footer">
		<button type="submit" class="btn bg-teal btn-sm btn-raised position-left"><i class="fa fa-floppy-o position-left"></i>{$lang['user_save']}</button>
	</div>
</div>


</form>
HTML;

echo <<<HTML
<div class="panel panel-default">
  <div class="panel-heading">
    {$lang['ip_list']}
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-xs table-hover">
      <thead>
      <tr>
        <th style="width: 200px">{$lang['title_filter']}</th>
        <th style="width: 190px">{$lang['ban_date']}</th>
        <th>{$lang['ban_descr']}</th>
        <th style="width: 180px">{$lang['vote_action']}</th>
      </tr>
      </thead>
	  <tbody>
HTML;

$db->query( "SELECT * FROM " . USERPREFIX . "_banned WHERE users_id = '0' ORDER BY id DESC" );

$i = 0;
if( !$langformatdatefull ) $langformatdatefull = "d.m.Y H:i";
while ( $row = $db->get_row() ) {
	$i ++;
	
	if( $row['date'] ) $endban = langdate( $langformatdatefull, $row['date'] );
	else $endban = $lang['banned_info'];
	
	echo "
        <tr>
        <td>
        {$row['ip']}
        </td>
        <td>
        {$endban}
        </td>
        <td>
        " . stripslashes( $row['descr'] ) . "
        </td>
        <td>
        <a class=\"btn bg-danger btn-sm btn-raised\" href=\"?mod=blockip&action=delete&id={$row['id']}&user_hash={$dle_login_hash}\"><i class=\"fa fa-unlock position-left\"></i>{$lang['ip_unblock']}</a></td>
        </tr>
        ";
}

if( $i == 0 ) {
	echo "<tr>
     <td height=\"18\" colspan=\"4\"><p align=\"center\"><br><b>{$lang['ip_empty']}<br><br></b></td>
    </tr>";
}

echo <<<HTML
	  </tbody>
	</table>
 
  </div>
</div>
HTML;

echofooter();
?>
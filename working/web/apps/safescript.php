<?php namespace { //we are in some apps/[app-name]/main.php with set include_path to it require_once '../../../_app/config/Editor.php'; require_once '../../../_library/PFC/Editor/AppSess.php'; require_once '../../../_library/PFC/Editor/AppLogin.php'; //shortcut App  use PFC\Editor\AppLogin; use PFC\Editor\AppSess; use PFC\Editor\Config;  //start private session session_start(); AppSess::start();    if(!Config::nologin && !AppLogin::isLogged()) {      die('NOT ALLOWED - NO USER');  }    }
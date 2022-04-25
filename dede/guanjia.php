<?php
require_once dirname(__FILE__) . '/config.php';
require_once DEDEINC . '/datalistcp.class.php';
require_once DEDEADMIN . "/inc/inc_catalog_options.php";
if (empty($dopost)) {
    $dopost = '';
}

if ($dopost == 'save') {
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    if (empty($token)) {
        ShowMsg('请填写从搜外内容管家获取的 TOKEN!', 'guanjia.php');
        exit();
    }

    $adminDir = explode('/', str_replace("\\", '/', dirname(__FILE__)));
    $adminDir = $adminDir[count($adminDir) - 1];

    $data = array(
        'token'     => trim($_POST['token']),
        'version'   => '1.0.0',
        'admin_dir' => $adminDir,
    );
    $value = json_encode($data);

    $varname = $dsql->GetOne("Select varname From `#@__sysconfig` where `varname` = 'guanjia_setting'");
    if ($varname != '') {
        $query = "update `#@__sysconfig` set value='$value' where `varname` = 'guanjia_setting'";
    } else {
        $query = "INSERT INTO `#@__sysconfig`(`varname`,`value`,`info`,`type`,`groupid`) VALUES('guanjia_setting','$value','搜外内容管家配置','string',0)";
    }
    $result = $dsql->ExecuteNoneQuery($query);
    if (empty($result)) {
        ShowMsg('保存失败!', 'guanjia.php');
        exit();
    }
    if (GetCache('guanjia', 'guanjia_setting')) {
        DelCache('guanjia', 'guanjia_setting');
    }

    ShowMsg('保存成功!', 'guanjia.php');
} else {
  $config = $dsql->GetOne("SELECT `value` FROM `#@__sysconfig` where `varname` = 'guanjia_setting'");
  $config = json_decode($config['value'], true);
  $config['version'] = '1.0.0';

  $url                = $GLOBALS['cfg_basehost'];
  $admindirs          = explode('/', str_replace("\\", '/', dirname(__FILE__)));
  $admindir           = $admindirs[count($admindirs) - 1];
  $config['app_path'] = $url . '/plus/guanjia.php?a=client';

  include DedeInclude("templets/guanjia.htm");
  exit();
}

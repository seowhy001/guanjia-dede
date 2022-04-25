<?php
/**
 * @package 搜外内容管家
 * @version 1.0.0
 */

$_SESSION = array();
require_once getIncDir() . '/common.inc.php';
require_once DEDEDATA . '/common.inc.php';

session_start();
if (empty($_SESSION['dede_admin_id'])) {
    $sql                            = "SELECT * FROM `#@__admin` where usertype = 10";
    $admin                          = $dsql->GetOne($sql);
    $_SESSION['dede_admin_id']      = $admin['id'];
    $_SESSION['dede_admin_type']    = $admin['usertype'];
    $_SESSION['dede_admin_channel'] = '0';
    $_SESSION['dede_admin_name']    = $admin['uname'];
    $_SESSION['dede_admin_purview'] = 'admin_AllowAll ';
    $_SESSION['dede_admin_style']   = 'newdedecms';
}

$guanjia = new Guanjia();

$_GET['a'] = parseAction(isset($_GET['a']) ? $_GET['a'] : '');

$config = $guanjia->getConfig();

require_once dirname(__FILE__) . '/../' . $config['admin_dir'] . '/config.php';

if (!in_array($_GET['a'], array(
  'client',
  'categories',
  'publish',
  'upgrade',
))) {
  return $guanjia->res(-1, "访问受限");
}

// 执行
$guanjia->run();

class Guanjia
{
    private $config;

    public function __construct()
    {
      // todo
    }

    public function run() {
      $action = $_GET['a'];
      if ($action == 'client') {
          return $this->client();
      }

      $this->verifySign();

      $funcName = $action;
      if (method_exists($this, $funcName)) {
          return $this->{$funcName}();
      }
      
      $this->res(-1, '错误的入口');
    }

    public function client() {
      echo '<center>搜外内容管家接口</center>';
    }

    public function categories()
    {
      global $dsql;
      $channels   = $dsql->GetOne("SELECT `id`,`nid`,`typename` FROM `#@__channeltype` where nid = 'article'"); // 获取文章频道
      $categories = array();
      $query      = "Select id,reid,topid,typename,ispart,channeltype From `#@__arctype` where ispart = 0 and channeltype=" . $channels['id'] . " order by sortrank asc";
      $dsql->SetQuery($query);
      $dsql->Execute();
      while ($row = $dsql->GetObject()) {
          $categories[] = array(
              'id'        => intval($row->id),
              'parent_id' => intval($row->reid),
              'title'     => $row->typename,
          );
      }

      $this->res(1, "", $categories);
    }

    public function publish()
    {
       global $cfg_phpurl;
       global $cfg_basehost;
        require_once DEDEINC . '/image.func.php';
        require_once DEDEINC . '/oxwindow.class.php';
        require_once DEDEINC . '/helpers/time.helper.php';

        require_once DEDEINC . '/customfields.func.php';
        require_once DEDEADMIN . '/inc/inc_archives_functions.php';

        $title  = trim($_POST['title']); // 标题
        $body   = trim($_POST['content']); // 内容
        $typeid = trim($_POST['category_id']); // 栏目

        if(!$title || !$body) {
          $this->res(-1, "没有可用内容");
        }

        $title = $this->removeUtf8mb4($title);
        $body  = $this->removeUtf8mb4($body);

        $notpost    = 1;
        $typeid2    = 0;
        $autokey    = 0;
        $remote     = 0;
        $dellink    = 0;
        $autolitpic = 0;
        $click      = 1;
        $writers    = '管理员';
        $source     = '未知';
        $ishtml     = 1;
        $arcrank    = '0';
        $money      = '0';
        $ddisremote = 0;
        $sptype     = 'hand';
        $voteid     = '';
        $keywords   = '';
        $weight     = 6;

        global $dsql;
        $channels = $dsql->GetOne("SELECT `id`,`nid`,`typename`  FROM `#@__channeltype` where nid = 'article'");

        if (!CheckChannel($typeid, $channels['id'])) {
          $this->res(-1, "选择的栏目不是文章栏目");
        }

        $pubdate = GetMkTime(time());
        $senddate    = time();
        $sortrank    = AddDay($pubdate, $sortup);
        $ismake      = $ishtml == 0 ? -1 : 0;
        $title       = preg_replace("#\"#", '＂', $title);
        $title       = dede_htmlspecialchars(cn_substrR($title, $cfg_title_maxlen));
        $shorttitle  = cn_substrR($shorttitle, 36);
        $color       = cn_substrR($color, 7);
        $writers     = cn_substrR($writers, 20);
        $source      = cn_substrR($source, 30);
        $description = cn_substrR($description, $cfg_auot_description);
        $keywords    = cn_substrR($keywords, 60);
        $filename    = trim(cn_substrR($filename, 40));
        $userip      = GetIP();
        $isremote    = (empty($isremote) ? 0 : $isremote);
        $serviterm   = empty($serviterm) ? "" : $serviterm;
        $channelid   = $channels['id'];

        if (!TestPurview('a_Check,a_AccCheck,a_MyCheck')) {
            $arcrank = -1;
        }
        $adminid = 1;

        $litpic = GetDDImage('none', $picname, $ddisremote);

        //生成文档ID
        $arcID = GetIndexKey($arcrank, $typeid, $sortrank, $channelid, $senddate, $adminid);

        if (empty($arcID)) {
          $this->res(-1, "发布文章失败，无法生成文章ID");
        }
        $autokey = 1;

        $body = $this->analyseHtmlBody($body, $description, $litpic, $keywords, 'htmltext');

        $inadd_f = $inadd_v = '';

        //处理图片文档的自定义属性
        if ($litpic != '' && !preg_match("#p#", $flag)) {
            $flag = ($flag == '' ? 'p' : $flag . ',p');
        }
        if ($redirecturl != '' && !preg_match("#j#", $flag)) {
            $flag = ($flag == '' ? 'j' : $flag . ',j');
        }

        //跳转网址的文档强制为动态
        if (preg_match("#j#", $flag)) {
            $ismake = -1;
        }

        //加入主档案表
        $query = "INSERT INTO `#@__archives`(`id`,`typeid`,`typeid2`,`sortrank`,`flag`,`ismake`,`channel`,`arcrank`,`click`,`money`,`title`,`shorttitle`,
        `color`,`writer`,`source`,`litpic`,`pubdate`,`senddate`,`mid`,`voteid`,`notpost`,`description`,`keywords`,`filename`,`dutyadmin`,`weight`)
        VALUES ('$arcID','$typeid','$typeid2','$sortrank','$flag','$ismake','$channelid','$arcrank','$click','$money',
        '$title','$shorttitle','$color','$writers','$source','$litpic','$pubdate','$senddate',
        '$adminid','$voteid','$notpost','$description','$keywords','$filename','$adminid','$weight');";

        if (!$dsql->ExecuteNoneQuery($query)) {
            $gerr = $dsql->GetError();
            $dsql->ExecuteNoneQuery("DELETE FROM `#@__arctiny` WHERE id='$arcID'");
            $this->res(-1, "把数据保存到数据库主表时出错");
            exit();
        }

        //加入附加表
        $cts      = $dsql->GetOne("SELECT addtable FROM `#@__channeltype` WHERE id='$channelid' ");
        $addtable = trim($cts['addtable']);
        if (empty($addtable)) {
            $dsql->ExecuteNoneQuery("DELETE FROM `#@__archives` WHERE id='$arcID'");
            $dsql->ExecuteNoneQuery("DELETE FROM `#@__arctiny` WHERE id='$arcID'");
            $this->res(-1, "没找到当前模型[{$channelid}]的主表信息，无法完成操作！");
        }
        $useip   = GetIP();
        $templet = empty($templet) ? '' : $templet;
        // 有可能表不同步
        $exists = $dsql->GetOne("SELECT aid FROM `{$addtable}` WHERE aid='$arcID' ");
        if ($exists != '') {
          $query   = "UPDATE `{$addtable}` SET body = '$body' WHERE aid = '$arcID'";
          if (!$dsql->ExecuteNoneQuery($query)) {
              $gerr = $dsql->GetError();
              $dsql->ExecuteNoneQuery("Delete From `#@__archives` where id='$arcID'");
              $dsql->ExecuteNoneQuery("Delete From `#@__arctiny` where id='$arcID'");
              $this->res(-1, "把数据保存到数据库附加表 `{$addtable}` 时出错");
          }
        } else {
          $query   = "INSERT INTO `{$addtable}`(aid,typeid,redirecturl,templet,userip,body{$inadd_f}) Values('$arcID','$typeid','$redirecturl','$templet','$useip','$body'{$inadd_v})";
          if (!$dsql->ExecuteNoneQuery($query)) {
              $gerr = $dsql->GetError();
              $dsql->ExecuteNoneQuery("Delete From `#@__archives` where id='$arcID'");
              $dsql->ExecuteNoneQuery("Delete From `#@__arctiny` where id='$arcID'");
              $this->res(-1, "把数据保存到数据库附加表 `{$addtable}` 时出错");
          }
        }

        $artUrl = MakeArt($arcID, true, true, $isremote);
        if ($artUrl == '') {
            $artUrl = $cfg_phpurl . "/view.php?aid=$arcID";
        }
        
        if (strpos($artUrl, "http") !== 0) {
          $artUrl = $cfg_basehost . $artUrl;
        }
        ClearMyAddon($arcID, $title);
        $this->updateIndexPage();

        $this->res(1, "发布成功", array(
          'url' => $artUrl,
        ));
    }

    public function getConfig()
    {
        if (empty($this->config)) {
          global $dsql;
          $config = $dsql->GetOne("SELECT `value` FROM `#@__sysconfig` where `varname` = 'guanjia_setting'");
          $this->config = json_decode($config['value'], true);
        }
        return $this->config;
    }

    public function verifySign()
    {
      if (!isset($_GET['sign'])) {
        $this->res(-1, '未授权操作');
      }

      $sign      = $_GET['sign'];
      $checkTime  = $_GET['_t'];

      $config    = $this->getConfig();
      $signature = $this->signature($config['token'], $checkTime);
      if ($sign != $signature) {
          $this->res(-1, '签名不正确');
      }
      return $this;
    }
    
    private function signature($token, $_t)
    {
        $signature = md5($token . $_t);
        return $signature;
    }

    /**
     * json输出
     * @param      $code
     * @param null $msg
     * @param null $data
     * @param null $extra
     */
    public function res($code, $msg = null, $data = null, $extra = null)
    {
        @header('Content-Type:application/json;charset=UTF-8');
        if(is_array($msg)){
            $msg = implode(",", $msg);
        }
        $output = array(
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        );
        if (is_array($extra)) {
            foreach ($extra as $key => $val) {
                $output[$key] = $val;
            }
        }
        echo json_encode($output);
        die;
    }

    /**
     * 处理HTML文本
     * 删除非站外链接、自动摘要、自动获取缩略图
     *
     * @access    public
     * @param     string  $body  内容
     * @param     string  $description  描述
     * @param     string  $litpic  缩略图
     * @param     string  $keywords  关键词
     * @param     string  $dtype  类型
     * @return    string
     */
    public function analyseHtmlBody($body, &$description, &$litpic, &$keywords, $dtype = '')
    {
        global $autolitpic, $remote, $dellink, $autokey, $cfg_basehost, $cfg_auot_description, $id, $title, $cfg_soft_lang;
        $autolitpic = (empty($autolitpic) ? '' : $autolitpic);
        $body       = stripslashes($body);
        $autokey    = 1;

        //远程图片本地化
        if ($remote == 1) {
            $body = GetCurContent($body);
        }
        //删除非站内链接
        if ($dellink == 1) {
            $allow_urls = array($_SERVER['HTTP_HOST']);
            // 读取允许的超链接设置
            if (file_exists(DEDEDATA . "/admin/allowurl.txt")) {
                $allow_urls = array_merge($allow_urls, file(DEDEDATA . "/admin/allowurl.txt"));
            }
            $body = Replace_Links($body, $allow_urls);
        }

        //自动摘要
        if ($description == '' && $cfg_auot_description > 0) {
            $description = cn_substr(html2text($body), $cfg_auot_description);
            $description = trim(preg_replace('/#p#|#e#/', '', $description));
            $description = addslashes($description);
        }

        //自动获取缩略图
        if ($autolitpic == 1 && $litpic == '') {
            $litpic = GetDDImgFromBody($body);
        }

        //自动获取关键字
        if ($autokey == 1 && $keywords == '') {
            $subject = $title;
            $message = $body;
            include_once DEDEINC . '/splitword.class.php';
            $keywords = '';
            $sp       = new SplitWord($cfg_soft_lang, $cfg_soft_lang);
            $sp->SetSource($subject, $cfg_soft_lang, $cfg_soft_lang);
            $sp->StartAnalysis();
            $titleindexs = preg_replace("/#p#|#e#/", '', $sp->GetFinallyIndex());
            $sp->SetSource(Html2Text($message), $cfg_soft_lang, $cfg_soft_lang);
            $sp->StartAnalysis();
            $allindexs = preg_replace("/#p#|#e#/", '', $sp->GetFinallyIndex());

            if (is_array($allindexs) && is_array($titleindexs)) {
                foreach ($titleindexs as $k => $v) {
                    if (strlen($keywords . $k) >= 60) {
                        break;
                    } else {
                        if (strlen($k) <= 2) {
                            continue;
                        }

                        $keywords .= $k . ',';
                    }
                }
                foreach ($allindexs as $k => $v) {
                    if (strlen($keywords . $k) >= 60) {
                        break;
                    } else if (!in_array($k, $titleindexs)) {
                        if (strlen($k) <= 2) {
                            continue;
                        }

                        $keywords .= $k . ',';
                    }
                }
            }
            $sp = null;
        }
        $body = GetFieldValueA($body, $dtype, $id);
        $body = addslashes($body);
        return $body;
    }

    // 生成静态页 - 首页
    private function updateIndexPage()
    {
        global $dsql;
        require_once DEDEINC . "/arc.partview.class.php";
        $row = $dsql->GetOne("SELECT * FROM #@__homepageset");
        if ($row['showmod'] == 1) {
            $remotepos = empty($remotepos) ? '/index.html' : $remotepos;
            $isremote  = empty($isremote) ? 0 : $isremote;
            $serviterm = empty($serviterm) ? "" : $serviterm;

            $homeFile = DEDEADMIN . "/" . $row['position'];
            $homeFile = str_replace("\\", "/", $homeFile);
            $homeFile = str_replace("//", "/", $homeFile);

            $fp = fopen($homeFile, "w");
            fclose($fp);
            $templet                = str_replace("{style}", $cfg_df_style, $row['templet']);
            $GLOBALS['_arclistEnv'] = 'index';

            $pv = new PartView();
            $pv->SetTemplet($cfg_basedir . $cfg_templets_dir . "/" . $templet);
            $pv->SaveToHtml($homeFile);
            return true;
        }
    }

    private function removeUtf8mb4($input)
    {
        $extensions = get_loaded_extensions();
        $output     = '';
        if (in_array('mbstring', $extensions)) {
            $length = mb_strlen($input, 'utf-8');
            for ($i = 0; $i < $length; $i++) {
                $char = mb_substr($input, $i, 1, 'utf-8');

                if (strlen($char) < 4) {
                    $output .= $char;
                }
            }
        } else if (in_array('iconv', $extensions)) {
            $length = iconv_strlen($input, 'utf-8');
            for ($i = 0; $i < $length; $i++) {
                $char = iconv_substr($input, $i, 1, 'utf-8');
                if (strlen($char) < 4) {
                    $output .= $char;
                }
            }
        }
        return $output;
    }
}

function getIncDir() {
  $root = dirname(__DIR__) . "/";
  // 尝试感知 include
  $configFile = $root . "include/common.inc.php";
  $includeDir = 'include';
  if (!file_exists($configFile)) {
    $dir_handle = opendir($root);
    while (($file = readdir($dir_handle)) !== false) {
        if (substr($file, 0, 1) !== '.' and is_dir($root . $file)) {
            $dir_handle2 = opendir($root . $file);
            while (($file2 = readdir($dir_handle2)) !== false) {
                if ($file2 === 'common.inc.php') {
                    $filePath = $root . $file . '/' . $file2;
                    $content = file_get_contents($filePath);
                    if (strpos($content, 'DEDE_ENVIRONMENT') !== false) {
                        $configFile = $filePath;
                        $includeDir = $file;
                        break 2;
                    }
                }
            }
            closedir($dir_handle2);
        }
    }
    closedir($dir_handle);
  }
  if (!file_exists($configFile)) {
    exit("无法感知配置文件");
  }

  return $root . $includeDir;
}

function parseAction($a)
{
    $a = lcfirst(str_replace(" ", "", ucwords(str_replace(array("/", "_"), " ", $a))));
    return $a;
}
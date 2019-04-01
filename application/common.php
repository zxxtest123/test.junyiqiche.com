<?php
// 公共助手函数
error_reporting(E_PARSE | E_ERROR | E_WARNING);

use think\Request;
use think\Config;
use think\Cache;
use think\Env;
use fast\Http;

// 应用公共文件
///////////////////////////////////////////
/**
 * me function
 */
///////////////////////////////////////////
if (!function_exists('__')) {
    /**
     * 打印变量
     */
    function pr($var)
    {
        $template = PHP_SAPI !== 'cli' ? '<pre>%s</pre>' : "\n%s\n";
        printf($template, print_r($var, true));
    }
}


if (!function_exists('emoji_encode')) {
    /**
     * emoji 表情转义
     * @param $nickname
     * @return string
     */
    function emoji_encode($nickname)
    {
        $strEncode = '';
        $length = mb_strlen($nickname, 'utf-8');
        for ($i = 0; $i < $length; $i++) {
            $_tmpStr = mb_substr($nickname, $i, 1, 'utf-8');
            if (strlen($_tmpStr) >= 4) {
                $strEncode .= '[[EMOJI:' . rawurlencode($_tmpStr) . ']]';
            } else {
                $strEncode .= $_tmpStr;
            }
        }
        return $strEncode;
    }
}
if (!function_exists('emoji_decode')) {
    /**
     * emoji 表情解密
     * @param $nickname
     * @return string
     */
    function emoji_decode($str)
    {
        $strDecode = preg_replace_callback('|\[\[EMOJI:(.*?)\]\]|', function ($matches) {
            return rawurldecode($matches[1]);
        }, $str);
        return $strDecode;
    }
}

if (!function_exists('arraySort')) {
    function arraySort($array, $keys, $sort = 'asc')
    {
        $newArr = $valArr = array();
        foreach ($array as $key => $value) {
            $valArr[$key] = $value[$keys];
        }
        ($sort == 'asc') ? asort($valArr) : arsort($valArr);//先利用keys对数组排序，目的是把目标数组的key排好序
        reset($valArr); //指针指向数组第一个值
        foreach ($valArr as $key => $value) {
            $newArr[$key] = $array[$key];
        }
        return $newArr;
    }
}


function strexists($string, $find)
{
    return !(strpos($string, $find) === FALSE);
}

function ihttp_request($url, $post = '', $extra = array(), $timeout = 60)
{
    $urlset = parse_url($url);
    if (empty($urlset['path'])) {
        $urlset['path'] = '/';
    }
    if (!empty($urlset['query'])) {
        $urlset['query'] = "?{$urlset['query']}";
    }
    if (empty($urlset['port'])) {
        $urlset['port'] = $urlset['scheme'] == 'https' ? '443' : '80';
    }
    if (strexists($url, 'https://') && !extension_loaded('openssl')) {
        if (!extension_loaded("openssl")) {
            //die('请开启您PHP环境的openssl');
        }
    }
    if (function_exists('curl_init') && function_exists('curl_exec')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlset['scheme'] . '://' . $urlset['host'] . ($urlset['port'] == '80' ? '' : ':' . $urlset['port']) . $urlset['path'] . $urlset['query']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (is_array($post)) {
                $post = http_build_query($post);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        if (defined('CURL_SSLVERSION_TLSv1')) {
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        if (!empty($extra) && is_array($extra)) {
            $headers = array();
            foreach ($extra as $opt => $value) {
                if (strexists($opt, 'CURLOPT_')) {
                    curl_setopt($ch, constant($opt), $value);
                } elseif (is_numeric($opt)) {
                    curl_setopt($ch, $opt, $value);
                } else {
                    $headers[] = "{$opt}: {$value}";
                }
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        }
        $data = curl_exec($ch);
        $status = curl_getinfo($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($errno || empty($data)) {
            //return error(1, $error);
        } else {
            return ihttp_response_parse($data);
        }
    }
    $method = empty($post) ? 'GET' : 'POST';
    $fdata = "{$method} {$urlset['path']}{$urlset['query']} HTTP/1.1\r\n";
    $fdata .= "Host: {$urlset['host']}\r\n";
    if (function_exists('gzdecode')) {
        $fdata .= "Accept-Encoding: gzip, deflate\r\n";
    }
    $fdata .= "Connection: close\r\n";
    if (!empty($extra) && is_array($extra)) {
        foreach ($extra as $opt => $value) {
            if (!strexists($opt, 'CURLOPT_')) {
                $fdata .= "{$opt}: {$value}\r\n";
            }
        }
    }
    $body = '';
    if ($post) {
        if (is_array($post)) {
            $body = http_build_query($post);
        } else {
            $body = urlencode($post);
        }
        $fdata .= 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}";
    } else {
        $fdata .= "\r\n";
    }
    if ($urlset['scheme'] == 'https') {
        $fp = fsockopen('ssl://' . $urlset['host'], $urlset['port'], $errno, $error);
    } else {
        $fp = fsockopen($urlset['host'], $urlset['port'], $errno, $error);
    }
    stream_set_blocking($fp, true);
    stream_set_timeout($fp, $timeout);
    if (!$fp) {
        //return error(1, $error);
    } else {
        fwrite($fp, $fdata);
        $content = '';
        while (!feof($fp))
            $content .= fgets($fp, 512);
        fclose($fp);
        return ihttp_response_parse($content, true);
    }
}

function ihttp_response_parse($data, $chunked = false)
{
    $rlt = array();
    $pos = strpos($data, "\r\n\r\n");
    $split1[0] = substr($data, 0, $pos);
    $split1[1] = substr($data, $pos + 4, strlen($data));

    $split2 = explode("\r\n", $split1[0], 2);
    preg_match('/^(\S+) (\S+) (\S+)$/', $split2[0], $matches);
    $rlt['code'] = $matches[2];
    $rlt['status'] = $matches[3];
    $rlt['responseline'] = $split2[0];
    $header = explode("\r\n", $split2[1]);
    $isgzip = false;
    $ischunk = false;
    foreach ($header as $v) {
        $row = explode(':', $v);
        $key = trim($row[0]);
        $value = trim($row[1]);
        if (is_array($rlt['headers'][$key])) {
            $rlt['headers'][$key][] = $value;
        } elseif (!empty($rlt['headers'][$key])) {
            $temp = $rlt['headers'][$key];
            unset($rlt['headers'][$key]);
            $rlt['headers'][$key][] = $temp;
            $rlt['headers'][$key][] = $value;
        } else {
            $rlt['headers'][$key] = $value;
        }
        if (!$isgzip && strtolower($key) == 'content-encoding' && strtolower($value) == 'gzip') {
            $isgzip = true;
        }
        if (!$ischunk && strtolower($key) == 'transfer-encoding' && strtolower($value) == 'chunked') {
            $ischunk = true;
        }
    }
    if ($chunked && $ischunk) {
        $rlt['content'] = ihttp_response_parse_unchunk($split1[1]);
    } else {
        $rlt['content'] = $split1[1];
    }
    if ($isgzip && function_exists('gzdecode')) {
        $rlt['content'] = gzdecode($rlt['content']);
    }

    //$rlt['meta'] = $data;
    if ($rlt['code'] == '100') {
        return ihttp_response_parse($rlt['content']);
    }
    return $rlt;
}

function ihttp_response_parse_unchunk($str = null)
{
    if (!is_string($str) or strlen($str) < 1) {
        return false;
    }
    $eol = "\r\n";
    $add = strlen($eol);
    $tmp = $str;
    $str = '';
    do {
        $tmp = ltrim($tmp);
        $pos = strpos($tmp, $eol);
        if ($pos === false) {
            return false;
        }
        $len = hexdec(substr($tmp, 0, $pos));
        if (!is_numeric($len) or $len < 0) {
            return false;
        }
        $str .= substr($tmp, ($pos + $add), $len);
        $tmp = substr($tmp, ($len + $pos + $add));
        $check = trim($tmp);
    } while (!empty($check));
    unset($tmp);
    return $str;
}


function ihttp_get($url)
{
    return ihttp_request($url);
}

function ihttp_post($url, $data)
{
    $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
    return ihttp_request($url, $data, $headers);
}

function gets($url = NULL)
{
    if ($url) {
        $rslt = ihttp_get($url);
        if (strtolower(trim($rslt['status'])) == 'ok') {
            //pr($rslt) ;exit;
            if (is_json($rslt['content'])) { //返回格式是json 直接返回数组
                $return = json_decode($rslt['content'], true);
                if ($return['errcode']) //有错误
                    exit('Error:<br>Api:' . $url . '  <br>errcode:' . $return['errcode'] . '<br>errmsg:' . $return['errmsg']);
                return $return;
            } else {  //先暂时直接返回，以后其它格式再增加
                return $rslt['content'];
            }
        }
        exit('远程请求失败：' . $url);
    }
    exit('未发现远程请求地址');
}

/**
 * 远程post请求
 */
function posts($url = NULL, $data = NULL)
{
    if ($url && $data) {
        $rslt = ihttp_post($url, $data);
        if (strtolower(trim($rslt['status'])) == 'ok') {
            //pr($rslt) ;
            if (is_json($rslt['content'])) { //返回格式是json 直接返回数组
                $return = json_decode($rslt['content'], true);
                if ($return['errcode']) //有错误
                    exit('Error:<br>Api:' . $url . '  <br>errcode:' . $return['errcode'] . '<br>errmsg:' . $return['errmsg']);
                return $return;
            } else {  //先暂时直接返回，以后其它格式再增加
                return $rslt['content'];
            }
        }
        exit('远程请求失败：' . $url);
    }
    exit('post远程请求，参数错误');
}

if (!function_exists('is_json')) {
    function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

}

if (!function_exists('getWxAccessToken')) {
    /**
     * 该公共方法获取和全局缓存js-sdk需要使用的access_token
     * @param $appid
     * @param $secret
     * @return mixed
     */
    function getWxAccessToken()
    {

        $config = get_addon_config('cms');

        $appid = $config['wxappid'];
        $secret = $config['wxappsecret'];
        //我们将access_token全局缓存在文件中,每次获取的时候,先判断是否过期,如果过期重新获取再全局缓存
        //我们缓存的在文件中的数据，包括access_token和该access_token的过期时间戳.
        //获取缓存的access_token
        $access_token_data = json_decode(Cache::get('access_token'), true);

        //判断缓存的access_token是否存在和过期，如果不存在和过期则重新获取.
        if ($access_token_data !== null && $access_token_data['access_token'] && $access_token_data['expires_in'] > time()) {

            return $access_token_data['access_token'];
        } else {
            //重新获取access_token,并全局缓存
            $result = Http::sendRequest("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}", 'GET');
            if ($result['ret']) {
                $data = (array)json_decode($result['msg'], true);
                //获取access_token
                if ($data != null && $data['access_token']) {
                    //设置access_token的过期时间,有效期是7200s
                    $data['expires_in'] = $data['expires_in'] + time();
                    //将access_token全局缓存，快速缓存到文件中.
                    Cache::set('access_token', json_encode($data));

                    //返回access_token
                    return $data['access_token'];
                }
            } else {
                exit('微信获取access_token失败');
            }
        }
    }
}
if (!function_exists('xmlstr_to_array')) {

    /**
     * xml转数组
     * @param $xmlstr
     * @return mixed
     */
    function xmlstr_to_array($xmlstr)
    {
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xmlstr, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * fa function
 */
///////////////////////////////////////////
if (!function_exists('__')) {

    /**
     * 获取语言变量值
     * @param string $name 语言变量名
     * @param array $vars 动态变量值
     * @param string $lang 语言
     * @return mixed
     */
    function __($name, $vars = [], $lang = '')
    {
        if (is_numeric($name) || !$name)
            return $name;
        if (!is_array($vars)) {
            $vars = func_get_args();
            array_shift($vars);
            $lang = '';
        }
        return \think\Lang::get($name, $vars, $lang);
    }

}

if (!function_exists('format_bytes')) {

    /**
     * 将字节转换为可读文本
     * @param int $size 大小
     * @param string $delimiter 分隔符
     * @return string
     */
    function format_bytes($size, $delimiter = '')
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 6; $i++)
            $size /= 1024;
        return round($size, 2) . $delimiter . $units[$i];
    }

}

if (!function_exists('datetime')) {

    /**
     * 将时间戳转换为日期时间
     * @param int $time 时间戳
     * @param string $format 日期时间格式
     * @return string
     */
    function datetime($time, $format = 'Y-m-d H:i:s')
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        return date($format, $time);
    }

}

if (!function_exists('human_date')) {

    /**
     * 获取语义化时间
     * @param int $time 时间
     * @param int $local 本地时间
     * @return string
     */
    function human_date($time, $local = null)
    {
        return \fast\Date::human($time, $local);
    }

}

if (!function_exists('cdnurl')) {

    /**
     * 获取上传资源的CDN的地址
     * @param string $url 资源相对地址
     * @param boolean $domain 是否显示域名 或者直接传入域名
     * @return string
     */
    function cdnurl($url, $domain = false)
    {
        $url = preg_match("/^https?:\/\/(.*)/i", $url) ? $url : \think\Config::get('upload.cdnurl') . $url;
        if ($domain && !preg_match("/^(http:\/\/|https:\/\/)/i", $url)) {
            if (is_bool($domain)) {
                $public = \think\Config::get('view_replace_str.__PUBLIC__');
                $url = rtrim($public, '/') . $url;
                if (!preg_match("/^(http:\/\/|https:\/\/)/i", $url)) {
                    $url = request()->domain() . $url;
                }
            } else {
                $url = $domain . $url;
            }
        }
        return $url;
    }

}


if (!function_exists('is_really_writable')) {

    /**
     * 判断文件或文件夹是否可写
     * @param    string $file 文件或目录
     * @return    bool
     */
    function is_really_writable($file)
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return is_writable($file);
        }
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === FALSE) {
                return FALSE;
            }
            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        } elseif (!is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE) {
            return FALSE;
        }
        fclose($fp);
        return TRUE;
    }

}

if (!function_exists('rmdirs')) {

    /**
     * 删除文件夹
     * @param string $dirname 目录
     * @param bool $withself 是否删除自身
     * @return boolean
     */
    function rmdirs($dirname, $withself = true)
    {
        if (!is_dir($dirname))
            return false;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        if ($withself) {
            @rmdir($dirname);
        }
        return true;
    }

}

if (!function_exists('copydirs')) {

    /**
     * 复制文件夹
     * @param string $source 源文件夹
     * @param string $dest 目标文件夹
     */
    function copydirs($source, $dest)
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        foreach (
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item
        ) {
            if ($item->isDir()) {
                $sontDir = $dest . DS . $iterator->getSubPathName();
                if (!is_dir($sontDir)) {
                    mkdir($sontDir, 0755, true);
                }
            } else {
                copy($item, $dest . DS . $iterator->getSubPathName());
            }
        }
    }

}

if (!function_exists('mb_ucfirst')) {

    function mb_ucfirst($string)
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_strtolower(mb_substr($string, 1));
    }

}

if (!function_exists('addtion')) {

    /**
     * 附加关联字段数据
     * @param array $items 数据列表
     * @param mixed $fields 渲染的来源字段
     * @return array
     */
    function addtion($items, $fields)
    {
        if (!$items || !$fields)
            return $items;
        $fieldsArr = [];
        if (!is_array($fields)) {
            $arr = explode(',', $fields);
            foreach ($arr as $k => $v) {
                $fieldsArr[$v] = ['field' => $v];
            }
        } else {
            foreach ($fields as $k => $v) {
                if (is_array($v)) {
                    $v['field'] = isset($v['field']) ? $v['field'] : $k;
                } else {
                    $v = ['field' => $v];
                }
                $fieldsArr[$v['field']] = $v;
            }
        }
        foreach ($fieldsArr as $k => &$v) {
            $v = is_array($v) ? $v : ['field' => $v];
            $v['display'] = isset($v['display']) ? $v['display'] : str_replace(['_ids', '_id'], ['_names', '_name'], $v['field']);
            $v['primary'] = isset($v['primary']) ? $v['primary'] : '';
            $v['column'] = isset($v['column']) ? $v['column'] : 'name';
            $v['model'] = isset($v['model']) ? $v['model'] : '';
            $v['table'] = isset($v['table']) ? $v['table'] : '';
            $v['name'] = isset($v['name']) ? $v['name'] : str_replace(['_ids', '_id'], '', $v['field']);
        }
        unset($v);
        $ids = [];
        $fields = array_keys($fieldsArr);
        foreach ($items as $k => $v) {
            foreach ($fields as $m => $n) {
                if (isset($v[$n])) {
                    $ids[$n] = array_merge(isset($ids[$n]) && is_array($ids[$n]) ? $ids[$n] : [], explode(',', $v[$n]));
                }
            }
        }
        $result = [];
        foreach ($fieldsArr as $k => $v) {
            if ($v['model']) {
                $model = new $v['model'];
            } else {
                $model = $v['name'] ? \think\Db::name($v['name']) : \think\Db::table($v['table']);
            }
            $primary = $v['primary'] ? $v['primary'] : $model->getPk();
            $result[$v['field']] = $model->where($primary, 'in', $ids[$v['field']])->column("{$primary},{$v['column']}");
        }

        foreach ($items as $k => &$v) {
            foreach ($fields as $m => $n) {
                if (isset($v[$n])) {
                    $curr = array_flip(explode(',', $v[$n]));

                    $v[$fieldsArr[$n]['display']] = implode(',', array_intersect_key($result[$n], $curr));
                }
            }
        }
        return $items;
    }

}

if (!function_exists('var_export_short')) {

    /**
     * 返回打印数组结构
     * @param string $var 数组
     * @param string $indent 缩进字符
     * @return string
     */
    function var_export_short($var, $indent = "")
    {
        switch (gettype($var)) {
            case "string":
                return '"' . addcslashes($var, "\\\$\"\r\n\t\v\f") . '"';
            case "array":
                $indexed = array_keys($var) === range(0, count($var) - 1);
                $r = [];
                foreach ($var as $key => $value) {
                    $r[] = "$indent    "
                        . ($indexed ? "" : var_export_short($key) . " => ")
                        . var_export_short($value, "$indent    ");
                }
                return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
            case "boolean":
                return $var ? "TRUE" : "FALSE";
            default:
                return var_export($var, TRUE);
        }
    }

    /**
     * 得到字符串中的数字
     */
    if (!function_exists('findNum')) {
        function findNum($str = '')
        {

            $str = trim($str);

            if (empty($str)) {
                return '';
            }

            $result = '';

            for ($i = 0; $i < strlen($str); $i++) {

                if (is_numeric($str[$i])) {

                    $result .= $str[$i];

                }

            }

            return $result;
        }
    }

    /**
     * 检查是否为手机号
     */
    if (!function_exists('checkPhoneNumberValidate')) {
        function checkPhoneNumberValidate($phone_number)
        {
            //@2017-11-25 14:25:45 https://zhidao.baidu.com/question/1822455991691849548.html
            //中国联通号码：130、131、132、145（无线上网卡）、155、156、185（iPhone5上市后开放）、186、176（4G号段）、175（2015年9月10日正式启用，暂只对北京、上海和广东投放办理）,166,146
            //中国移动号码：134、135、136、137、138、139、147（无线上网卡）、148、150、151、152、157、158、159、178、182、183、184、187、188、198
            //中国电信号码：133、153、180、181、189、177、173、149、199
            $g = "/^1[34578]\d{9}$/";
            $g2 = "/^19[89]\d{8}$/";
            $g3 = "/^166\d{8}$/";
            if (preg_match($g, $phone_number)) {
                return true;
            } else if (preg_match($g2, $phone_number)) {
                return true;
            } else if (preg_match($g3, $phone_number)) {
                return true;
            }

            return false;

        }
    }

    /**
     * 某个时间戳在当前时间的多久前
     */
    if (!function_exists('format_date')) {
        function format_date($time)
        {
            $nowtime = time();
            $difference = $nowtime - $time;
            switch ($difference) {
                case $difference <= '60' :
                    $msg = '刚刚';
                    break;
                case $difference > '60' && $difference <= '3600' :
                    $msg = floor($difference / 60) . '分钟前';
                    break;
                case $difference > '3600' && $difference <= '86400' :
                    $msg = floor($difference / 3600) . '小时前';
                    break;
                case $difference > '86400' && $difference <= '2592000' :
                    $msg = floor($difference / 86400) . '天前';
                    break;
                case $difference > '2592000' && $difference <= '31536000':
                    $msg = floor($difference / 2592000) . '个月前';
                    break;
                case $difference > '31536000':
                    $msg = floor($difference / 31104000) . '年前';
                    break;
            }
            return $msg;
        }
    }

    /**
     * 发送验证码
     * @param $mobile
     * @param $template_id
     * @param null $user_id
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    if (!function_exists('message_send')) {
        function message_send($mobile, $template_id, $user_id = null)
        {
            if (!$mobile) return ['error', 'msg' => '参数缺失或格式错误'];
            if (!checkPhoneNumberValidate($mobile)) return ['error', 'msg' => '手机号格式错误'];
            $authnum = '';
            //随机生成四位数验证码
            $list = explode(",", "0,1,2,3,4,5,6,7,8,9");
            for ($i = 0; $i < 4; $i++) {
                $randnum = rand(0, 9);
                $authnum .= $list[$randnum];
            }

            $Ucpass = [
                'accountsid' => Env::get('sms.accountsid'),
                'token' => Env::get('sms.token'),
                'appid' => Env::get('sms.appid'),
                'templateid' => $template_id,
            ];

            $url = 'http://open.ucpaas.com/ol/sms/sendsms';
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'json' => [
                    'sid' => $Ucpass['accountsid'],
                    'token' => $Ucpass['token'],
                    'appid' => $Ucpass['appid'],
                    'templateid' => $Ucpass['templateid'],
                    'param' => $authnum,
                    'mobile' => $mobile,
                    'uid' => $user_id
                ]
            ]);
            if ($response) {
                $result = json_decode($response->getBody(), true);
                $num = '';
                if ($result['code'] == '000000') {
                    //查询当前手机号，如果存在更新他的的请求次数与 请求时间
                    $getPhone = think\Db::name('cms_login_info')->where(['login_phone' => $mobile])->find();
                    if ($getPhone) {
                        $num = $getPhone['login_num'];
                        ++$num;
                        return think\Db::name('cms_login_info')->update([
                            'login_time' => strtotime($result['create_date']),
                            'login_code' => $authnum,
                            'login_num' => $num,
                            'login_phone' => $mobile,
                            'id' => $getPhone['id'],
                            'login_state' => 0,
                            'user_id' => $user_id
                        ]) ? ['success', 'msg' => '发送成功'] : ['error', 'msg' => '发送失败'];

                    } else {
                        //否则新增当前用户到登陆表
                        think\Db::name('cms_login_info')->insert([
                            'login_time' => strtotime($result['create_date']),
                            'login_code' => $authnum,
                            'login_num' => 1,
                            'login_phone' => $mobile,
                            'login_state' => 0,
                            'user_id' => $user_id
                        ]) ? ['success', 'msg' => '发送成功'] : ['error', 'msg' => '发送失败'];
                    }
                } else {
                    return ['error', 'msg' => $result['msg']];
//                $this->error($result['msg'], $result);
                }
            } else {
                $err = json_decode($response->getBody(), true);
                return ['error', 'msg' => $err['msg']];
//            $this->error($err['msg'], $err);
            }
        }
    }

    /**
     * 报价
     * @param int $user_id
     * @param $phone
     * @param $money
     * @param $models_id
     * @param $type
     * @param $templateid
     * @param $param
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    if (!function_exists('sendOffers')) {

        function sendOffers($user_id, $by_user_id, $phone, $money, $models_id, $type, $templateid, $param)
        {

            $typeModels = $type == 'buy' ? new \addons\cms\model\BuycarModel : new \addons\cms\model\ModelsInfo; //转换表名
            if (!(int)$user_id || !(int)$by_user_id|| !(float)$money || !(string)$type || !(int)$models_id || !(string)$param || !checkPhoneNumberValidate($phone)) {
//            $this->error('缺少参数或参数格式错误');
                return ['error', 'msg' => '缺少参数或参数格式错误'];
            }
            try {
                $merchantsPhone = trim($typeModels->get(['id' => $models_id])->phone);//商户的手机号
//            $modelsInfo = collection($typeModels->with(['brand'])->select(['id' => $models_id]))->toArray();
//            $modelsInfo = $modelsInfo[0]['brand']['name'] . ' ' . $modelsInfo[0]['models_name'];  //拼接品牌、车型
                if ($phone) {
                    think\Db::name('user')->where(['id' => $user_id])->setField('mobile', $phone);  //每次执行一次更新手机号操作
                }
//            $newPone = substr($user_id, 7);//手机尾号4位数
                $url = 'http://open.ucpaas.com/ol/sms/sendsms';

                $client = new \GuzzleHttp\Client();
                $response = $client->request('POST', $url, [
                    'json' => [
                        'sid' => Env::get('sms.accountsid'),
                        'token' => Env::get('sms.token'),
                        'appid' => Env::get('sms.appid'),
                        'templateid' => $templateid,
                        'param' => $param,  //参数
                        'mobile' => $merchantsPhone,
                        'uid' => $user_id
                    ]
                ]);

                if ($response) {
                    $result = json_decode($response->getBody(), true);
                    if ($result['code'] == '000000') { //发送成功
                        $field = $type == 'buy' ? 'buy_car_id' : 'models_info_id';
                        return \addons\cms\model\QuotedPrice::create(
                            ['user_ids' => $user_id, 'by_user_ids' => $by_user_id, 'money' => $money, $field => $models_id, 'type' => $type, 'quotationtime' => time(), 'is_see' => 2]
                        ) ? ['success', 'msg' => '报价成功'] : ['error', 'msg' => '报价失败'];
                    }
                    return ['error', 'msg' => $result['msg']];
                }
                return ['error', 'msg' => '短信通知失败'];
            } catch (\think\Exception $e) {
                return ['error', 'msg' => $e->getMessage()];
            }
        }

    }

    /**
     * 检查是否为银行卡号
     * @param $card_number
     * @return string
     */
    if (!function_exists('check_bankCard')) {
        function check_bankCard($card_number)
        {
            $arr_no = str_split($card_number);
            $last_n = $arr_no[count($arr_no) - 1];
            krsort($arr_no);
            $i = 1;
            $total = 0;
            foreach ($arr_no as $n) {
                if ($i % 2 == 0) {
                    $ix = $n * 2;
                    if ($ix >= 10) {
                        $nx = 1 + ($ix % 10);
                        $total += $nx;
                    } else {
                        $total += $ix;
                    }
                } else {
                    $total += $n;
                }
                $i++;
            }
            $total -= $last_n;
            $x = 10 - ($total % 10);
            if ($x == $last_n) {
                return 'true';
            } else {
                return 'false';
            }
        }
    }
}

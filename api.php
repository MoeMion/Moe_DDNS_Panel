<?php
require 'config.php';
require 'vendor/autoload.php';

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

if(!isset($_REQUEST['action']) or !isset($_REQUEST['key']) or $_REQUEST['key'] != $globalKey ){
exit(json_encode(array('code'=>'403','action'=>'error','msg'=>'Sorry,您没有访问权限！')));
}

switch ($_REQUEST['action']) {
    
    case 'listRecords':
        listRecords();
        break;
    
    case 'addRecord':
        checkHost($_REQUEST['host']);
        addRecord($_REQUEST['host']);
        break;

    case 'toggleRecord':
        checkHost($_REQUEST['host']);
        toggleRecord($_REQUEST['host']);
        break;

    case 'updateRecord':
        checkHost($_REQUEST['host']);
        if($_REQUEST['ip']){$ip=$_REQUEST['ip'];}else{$ip=getRealAddress();}
        updateRecord($_REQUEST['host'],$ip);
        break;

    case 'fetchInfo':
        exit(json_encode(array('code'=>'200','apiAddress'=>$apiAddress,'prefix'=>$prefix,'domain'=>$domain,'hostlen'=>$hostlen)));
        break;

    default:
        exit(json_encode(array('code'=>'403','action'=>'error','msg'=>'参数错误或缺失')));
        break;
}

function listRecords(){
    $result = array();
    $db = new PDO($GLOBALS['dsn'], $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    foreach($db->query("select * from records;")->fetchAll() as $value){
        if(time()-$value["time"]>=60*60*24){$status="已超过1天未更新";}
        else if($value['status']=="0"){$status="已禁用";}
        else{$gaptime=(time()-$value["time"])%60;$status="$gaptime 分钟前更新";}
        array_push($result,array("id"=>$value['id'],"host"=>$value['host'],"value"=>$value['value'],"status"=>$status));
    }
    exit(json_encode($result));
}

function addRecord($host){
    $db = new PDO($GLOBALS['dsn'], $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    $result = $db->query("select * from records where host = '$host'")->fetch();
    if(!empty($result)){
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>'此DDNS记录已存在！')));
    }
    $query = ['query' => ['DomainName' => $GLOBALS['domain'],'RR' => $host.$GLOBALS['prefix'],'Type' => "A",'Value' => "127.0.0.1",]];
    $request = aliRequest("AddDomainRecord",$query);
    if($request['type']){
        $time = time();
        $recordId = $request['msg'];
        $db->exec("insert into records values (null,'$host','127.0.0.1','$time','1','$recordId');");
        exit(json_encode(array('code'=>'200','action'=>'success','msg'=>"$host 记录添加成功！")));
    }else{
        $msg = $request['msg'];
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>"$msg")));
    }
}

function toggleRecord($host){
    $db = new PDO($GLOBALS['dsn'], $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    $result = $db->query("select status,recordid from records where host = '$host'")->fetch();
    if(empty($result)){
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>"$host 记录不存在！")));
    }
    if($result['status']){
        $query=['query' => ['RecordId' => $result['recordid'],'Status' => "Disable"]];
        $request = aliRequest("SetDomainRecordStatus",$query);
        if($request['type']){
            $db->exec("update records set status = 0 where host = '$host';");
            exit(json_encode(array('code'=>'200','action'=>'success','msg'=>"$host 记录已禁用！")));
        }else{
            $msg = $request['msg'];
            exit(json_encode(array('code'=>'500','action'=>'error','msg'=>"$msg")));
        }
    }else{
        $query=['query' => ['RecordId' => $result['recordid'],'Status' => "Enable"]];
        $request = aliRequest("SetDomainRecordStatus",$query);
        if($request['type']){
            $db->exec("update records set status = 1 where host = '$host';");
            exit(json_encode(array('code'=>'200','action'=>'success','msg'=>"$host 记录已禁用！")));
        }else{
            $msg = $request['msg'];
            exit(json_encode(array('code'=>'500','action'=>'error','msg'=>"$msg")));
        }
    }
}

function updateRecord($host,$ip){
    $db = new PDO($GLOBALS['dsn'], $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    $result = $db->query("select status,recordid from records where host = '$host'")->fetch();
    if(empty($result)){
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>"$host 记录不存在！")));
    }
    if($result['status']){
        if($result['value']==$ip){exit(json_encode(array('code'=>'500','action'=>'warning','msg'=>"$host 记录未发生变化！")));}
        $query = ['query' => ['RecordId' => $result['recordid'],'RR' => $host.$GLOBALS['prefix'],'Type' => "A",'Value' => $ip,],];
        $request = aliRequest("UpdateDomainRecord",$query);
        if($request['type']){
            $db->exec("update records set value = '$ip' where host = '$host';");
            exit(json_encode(array('code'=>'200','action'=>'success','msg'=>"$host 记录已更新！")));
        }else{
            $msg = $request['msg'];
            exit(json_encode(array('code'=>'500','action'=>'error','msg'=>"$msg")));
        }
    }else{
        exit(json_encode(array('code'=>'500','action'=>'success','msg'=>"$host 记录被已禁用！")));
    }
}



function resetRecord($host){
    $db = new PDO($GLOBALS['dsn'], $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    $result = $db->query("select recordid from records where host='$host'")->fetch();
    if(empty($result)){ return false;}
    $query = ['query' => ['RecordId' => $result['recordid'],'RR' => $host.$GLOBALS['prefix'],'Type' => "A",'Value' => "127.0.0.1",],];
    $request = aliRequest("UpdateDomainRecord",$query);
    if($request['type']==1) return true; else return false;
}

function checkHost($host){
    if($host==null){
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>'参数错误或缺失')));
    }else if(!preg_match("/^[a-z0-9]+$/u",$host)){
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>'主机名应当仅由小写字母与数字组成！')));
    }else if($GLOBALS['hostlen'] and strlen($host)>$GLOBALS['hostlen']){
        exit(json_encode(array('code'=>'500','action'=>'error','msg'=>'主机名不能超过'.$GLOBALS['hostlen'].'个字符！')));
    }
}

function getRealAddress(){
	if (getenv("HTTP_CLIENT_IP")){
		$ip = getenv("HTTP_CLIENT_IP");
	}else if (getenv("HTTP_X_FORWARDED_FOR")){
		$ip = getenv("HTTP_X_FORWARDED_FOR");
    if (strstr($ip, ',')){
      $tmp = explode(',', $ip);
      $ip = trim($tmp[0]);
    }
	}else{
		$ip = getenv("REMOTE_ADDR");
	}
	return $ip;
	}

function aliRequest($action,$query){
    $accessKeyId=$GLOBALS['accessKeyId'];
    $accessSecret=$GLOBALS['accessSecret'];

    AlibabaCloud::accessKeyClient($accessKeyId,$accessSecret)
        ->regionId('cn-hangzhou')
        ->asDefaultClient();
    try {
        $result = AlibabaCloud::rpc()
        ->product('Alidns')
        ->scheme('https')
        ->version('2015-01-09')
        ->action("$action")
        ->method('POST')
        ->host('alidns.aliyuncs.com')
        ->options($query)
        ->options([
            'query' => [
                'RegionId' => "cn-hangzhou",
            ],
        ])
        ->request();
    
    $resultMsg = $result->toArray();
    return array('type'=>'1','msg'=>$resultMsg['RecordId']);
    } catch (ClientException $e) {
        return array('type'=>'0','msg'=>$e->getErrorMessage());
    } catch (ServerException $e) {
        return array('type'=>'0','msg'=>$e->getErrorMessage());
    }
}
?>
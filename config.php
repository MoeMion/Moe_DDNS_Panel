<?php
$globalKey=''; //全局访问密钥
$accessKeyId=''; //阿里云 accessKeyId
$accessSecret=''; //阿里云 accessSecret
$apiAddress=''; //api请求地址，方便魔改与伪静态 例：https://domain/api.php
$domain=''; //域名，如:google.com
$prefix=''; //前缀，如:.ddns，其中的"."不能省略，此处也可为多级如.1.ddns 若填写此项则实际解析的域名为[host]+[profix].[domain]，如test.ddns.google.com
$hostlen=; //允许的主机名长度，0为不限 
$dbType='mysql'; //数据库类型,PDO支持的都行
$dbHost='localhost'; //数据库主机名
$dbName=''; //数据库名
$dbUser=''; //数据库连接用户名
$dbPass=''; //对应的密码
$dsn="$dbType:host=$dbHost;dbname=$dbName"; //PDO连接信息
?>
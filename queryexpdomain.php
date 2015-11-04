<?php

function settimeout($timeout){
	$timeout=array(
	"http"=>array(
		"timeout"=>$timeout
		),
	);
	return $ctx = stream_context_create($timeout);
}

function unparck($dubarray,$elename=null){
		$res=array();
		$i=count($dubarray);
		while($i){
			$ele=array_shift($dubarray);
			//echo $ele[$elename];
			array_push($res,$ele[$elename]);
			$i--;
		}
		return $res;
}

function QueryWhois($whoisserver, $domain){
        $port = 43;
        $timeout = 20;
		$rtimeout = 30; 
        $fp = fsockopen($whoisserver, $port, $errno, $errstr, $timeout);
		if(!$fp){
			 echo("Socket Error " . $errno . " - " . $errstr);
			 return "";
		}
		stream_set_timeout($fp,$rtimeout);
        fputs($fp, $domain . "\r\n");
        $out = "";

        while(!feof($fp)){
				$info = stream_get_meta_data($fp);
				if($info['timed_out']){
					return "";
				}
                $out .= fgets($fp);
        }
        fclose($fp);

        $res = "";
        if((strpos(strtolower($out), "error") === FALSE) && (strpos(strtolower($out), "not allocated") === FALSE)) {
                $rows = explode("\n", $out);
                foreach($rows as $row) {
                        $row = trim($row);
                        if(($row != '') && ($row{0} != '#') && ($row{0} != '%')) {
                                $res .= $row."\n";
                        }
                }
        }
        return $res;
}

function unicode2utf8($str){
        if(!$str) return $str;
        $decode = json_decode($str);
        if($decode) return $decode;
        $str = '["' . $str . '"]';
        $decode = json_decode($str);
        if(count($decode) == 1){
                return $decode[0];
        }
        return $str;
}

function GetDomall($url,&$domlist,&$xyz_domall,$flog){
	$urlcache="";
	$urlcache=file_get_contents($url);
	if($urlcache){
		$json_data=json_decode($urlcache);
		$totalpage=$json_data->{'total'};
		$curpage=$json_data->{'page'};
		while($curpage<=$totalpage){
			GetDomlist($url,$curpage,&$domlist,&$xyz_domall);
			fwrite($flog,date('G:i:s')." current: ".$curpage." page,total: ".$totalpage."pages\r\n");
			$curpage+=1;
			sleep(rand(1,3));
		}
		
	}
}

function GetDomlist($url,$page,$domlist,$xyz_domall){
	$domain="";
	$allxyz="";
	$json_data=array();
	$xyz_domain=array();
	$xyz_records="";
	$urlstr=$url."&page=".$page;
	
	while(!($allxyz=file_get_contents($urlstr,0,settimeout(5)))){
			sleep(rand(3,5));
	}
	if($allxyz){
		$json_data=json_decode($allxyz);
		foreach($json_data->{'rows'} as $xyz_domain){
			if(!in_array($xyz_domain->{'cell'}[0],$domlist)){
				array_push($xyz_domall,$xyz_domain->{'cell'}[0]);
			}
		}
		
	}
}

include "dao.php";
include "domain.php";
include "punycode.php";

$flog=fopen(date('y-n-j')."-taskdeal.log","a+");
$dao=new SimpleDao();

$dao->table("xyzdomains_info");
$xyz_exist=array();
$url="https://domainpunch.com/_local/php/deleted.php?tld=xyz&_search=false&sord=asc";

$querysql=array("0" => "domain");


//A  geturldomain,into db
//当前数据库已存在的所有域名
$xyz_existall=$dao->query($querysql);
//从网页添加新域名
$xyz_domall=array();
GetDomall($url,unparck($xyz_existall,"domain"),$xyz_domall,$flog);

foreach($xyz_domall as $xyz_dom){
	$id=$dao->insert(array("domain"=>$xyz_dom));
	if($id<0){
		fwrite($flog,date('G:i:s')."Err: ".($xyz_dom).mysql_error()."\r\n");
		die("insert err.".mysql_error());
	}
	fwrite($flog,date('G:i:s')."  get new domain: ".($xyz_dom)."\r\n");
}

//B  querywhois,into db
//当前需要处理的域名
//删除时间为0并且（当前-过期时间=11 或者 当前-过期时间=31)
$querysql="select domain from xyzdomains_info as i where  createtime is null or (DATEDIFF(DATE_FORMAT(NOW(),'".date('Y')."-%m-%d %H:%i:%s') ,DATE_FORMAT(i.expirytime,'".date('Y')."-%m-%d %H:%i:%s'))=11 or DATEDIFF(DATE_FORMAT(NOW(),'".date('Y')."-%m-%d %H:%i:%s') ,DATE_FORMAT(i.expirytime,'".date('Y')."-%m-%d %H:%i:%s'))=31)";

//$querysql="select domain from xyzdomains_info as i where  deletetime=0";

$i=0;
$j=1;
$xyz_exist=$dao->getall($querysql);
$i=count($xyz_exist);
if($i){
	foreach(unparck($xyz_exist,"domain")as $domain){
		fwrite($flog,date('G:i:s')." process (".$j."\\".$i.") ".($domain)."\r\n");
		$j++;
		$xyz_dom=new domain($domain);
		if(!$xyz_dom){
			fwrite($flog,$domain." read whois err(not responed),skip.");
			usleep(rand(3000000,5000000));
			continue;
		}
		if(!$xyz_dom->GetUpdatetime() || !$xyz_dom->GetCreatetime() || !$xyz_dom->GetExpirytime()){
			fwrite($flog,$xyz_dom->domainname." whois is null or incomplete,skip.\r\n==============");
			unset($xyz_dom);
			continue;
		}
		fwrite($flog,"updatetime=".$xyz_dom->GetUpdatetime()."\r\n");
		fwrite($flog,"createtime=".$xyz_dom->GetCreatetime()."\r\n");
		fwrite($flog,"expirytime=".$xyz_dom->GetExpirytime()."\r\n");
		fwrite($flog,"register".$xyz_dom->GetRegister()."\r\n");
		fwrite($flog,"deletetime=".$xyz_dom->GetDeletetime()."\r\n");

		foreach($xyz_dom->GetStatus()as $val){
			fwrite($flog,"status=".$val."\r\n");
		}
		
		if(0==$xyz_dom->GetDeletetime()){		//没有算出,等下次再算
			$querysql=array(
			"updatetime" =>$xyz_dom->GetUpdatetime(),
			"createtime" =>$xyz_dom->GetCreatetime(),
			"expirytime" =>$xyz_dom->GetExpirytime(),
			"register"	 =>$xyz_dom->GetRegister(),
			"deletetime" =>$xyz_dom->GetDeletetime(),
			);
		}else{									//算出直接更新删除时间				
			$querysql=array(
				"updatetime" =>$xyz_dom->GetUpdatetime(),
				"createtime" =>$xyz_dom->GetCreatetime(),
				"expirytime" =>$xyz_dom->GetExpirytime(),
				"register"	 =>$xyz_dom->GetRegister(),
				"deletetime" =>strtotime($xyz_dom->GetUpdatetime())+$xyz_dom->GetDeletetime(),
			);
		}
		$wheresql=array(
			"domain" =>$domain,
		);
	
		$dao->table("xyzdomains_info");
		$res=$dao->update($querysql,$wheresql);
		if($res<0){
			fwrite($flog,date('G:i:s')."updte Err: ".($domain)."\r\n");
			die("update Err.\r\n");
		}

		unset($xyz_dom);
		fwrite($flog,date('G:i:s')."===================\r\n");
		usleep(rand(3000000,5000000));
	
	}//end each
}else{
	fwrite($flog,date('G:i:s')."no domains need precessing,all done.\r\n");
}

//D. querydb,del records
//fwrite($flog,date('G:i:s')."执行删除过期时间小于今天的域名\r\n");
$querysql="delete from xyzdomains_info where deletetime<>0 and (deletetime-UNIX_TIMESTAMP(now()))<0";
$res=$dao->get($querysql);

fwrite($flog,date('G:i:s')."执行删除过期时间小于今天的域名:".@mysql_affected_rows()."\r\n");
echo "all done.";
fwrite($flog,date('G:i:s')." all done.\r\n");

fclose($flog);
$dao->release();

?>

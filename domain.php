<?php

class Domain{

  
	public				$domainname		=null;
	private				$_createtime	=null;
	private				$_updatetime	=null;
	private				$_expirytime	=null;
	private				$_deletetime	=null;
	private				$_register		=null;
	private				$_status		=null;
	public				$O_Dao			=null;
	public				$O_Punycode		=null;

	private				$strkey			=array(
		"update" => "/Updated Date:(.*)r?\n/i",
		"create" => "/Creation Date:(.*)r?\n/i",
		"expiry" => "/Expiry Date:(.*)r?\n/i",
		"status" => "/Status:(.*)http/i",
		"registrar" => "/Registrar:(.*)r?\n/i",
	);
	
	private				$getvalue		=array(
		"update"	=>	array(),
		"create"	=>	array(),
		"expiry"	=>	array(),
		"status"	=>	array(),
		"registrar"	=>	array(),
	);

	public	function Domain($domname){
		$rest="";
		$this->O_Punycode=new Punycode();
		$this->domainname=$domname;
	  //$this->domainname="\u8bf7\u628a.xyz";
		if(!$this->Isformat()){
			$this->domainname=$this->CoverDomain();
		}
		$i=0;
		while(!$rest){
			if($i>9){
				return;
			}
			$rest=$this->Whois();
			if(!$rest){
				sleep(rand(1,3));
			}
			$i++;
		}
		$this->GetValue($rest);
		$this->SetCreatetime();
		$this->SetUpdatetime();
		$this->SetExpirytime();
		$this->SetStatus();
		$this->SetRegistrar();
		$this->calcdeltime($this->GetStatus());
	}

	public	function calcdeltime($domain_status){
		 /* 
			关于域名状态可查看http://wwwxxx.com/list.asp?unid=167
		 */
		if(is_array($domain_status)){
			if(in_array('pendingDelete',$domain_status) && !in_array('redemptionPeriod',$domain_status)){
				$this->_deletetime=432000;
				return;
			}else if(in_array('redemptionPeriod',$domain_status) && !in_array('pendingDelete',$domain_status)){
				$this->_deletetime=3024000;
				return;
			}else{
				//$this->_deletetime=0;
			}
		}
		$this->_deletetime=0;
	}

	public	function Isformat(){
        if(!preg_match("/^([-a-z0-9]{2,100})\.([a-z\.]{2,8})$/i", $this->domainname)) {
                return false;
        }
        return true;
	}	
	
	public	function CoverDomain(){
		return $this->O_Punycode->encode(unicode2utf8($this->domainname));
	}

	public	function GetCreatetime(){
		return $this->_createtime;
	}

	public	function GetUpdatetime(){
		return $this->_updatetime;
	}

	public	function GetExpirytime(){
		return $this->_expirytime;
	}
	
	public	function GetRegister(){
		return $this->_register;
	}

	public	function GetDeletetime(){
		return $this->_deletetime;
	}

	public	function GetStatus(){
		return $this->_status;
	}
	
	public	function GetString($whois,$strkey){
		$findstr=array();
		preg_match_all($strkey,$whois,$matchs);
		foreach($matchs[1] as $value){
			array_push($findstr,trim($value));
		}
		return $findstr;
	}
	
	public	function GetValue($whois){
		foreach($this->strkey as $skey => $val){
			$this->getvalue[$skey]=$this->GetString($whois,$val);
		}
	}

	public	function SetCreatetime(){

		foreach($this->getvalue["create"] as $value){
			$this->_createtime=$value;
		}
	}

	public	function SetUpdatetime(){
		foreach($this->getvalue["update"] as $value){
			$this->_updatetime=$value;
		}
	}

	public	function SetExpirytime(){
		foreach($this->getvalue["expiry"] as $value){
			$this->_expirytime=$value;
		}
	}

	public	function SetStatus(){
			$this->_status=$this->getvalue["status"];
	}

	public	function SetRegistrar(){
		foreach($this->getvalue["registrar"] as $value){
			$this->_register=$value;
		}
	}

	public	function Whois(){
		$whoisserver="whois.nic.xyz";
		return $rst=QueryWhois($whoisserver, $this->domainname);
	}
}
?>

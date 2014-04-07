<?php
# ircsyslogbot - Program to log syslog messages to IRC
#
# Copyright (C) 2013,2014 Håkon Struijk Holmen <hawken@thehawken.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

require_once "formatting.php";
require_once "config.php";


$sl = new SyslogListener($syslog_addr, 514);
$irc = new Bot($irc_host,$irc_port,$irc_nick,$irc_user,$irc_gecos,$oper_u, $oper_p,$chan);
$sel = new Select(array($irc->getsock(),$sl->sock));

while(!$sel->eof()){
	if(!$irc->getsock()){
		mylog(1,"Reopening IRC, old connection died.\n");
		sleep(1);
		$irc = new Bot($irc_host,$irc_port,$irc_nick,$irc_user,$irc_gecos,$oper_u, $oper_p,$chan);
	}
	
	$sel->insert(array($irc->getsock(),$sl->sock));
	$sel->select();
	foreach($sel->last as $s){
		if($s === $sl->sock){
			$data = $sl->read();
			$irc->syslog_in($data);
		} else if($s === $irc->getsock()){
			$irc->read();
		}
	}
}

function mylog($p,$line){
	global $pri;
	if($p<=$pri) echo $line."\n";
}

class Select{
	var $socks = array();
	var $last = array();
	
	function __construct($sockarr){
		$this->insert($sockarr);
	}
	function insert($arr){
		$this->socks = $arr;
	}
	function select(){
		$read = $this->socks;
		$null = $null2 = null;
		$num = stream_select($read,$null,$null2,null);
		$this->last = $read;
		return $num;
	}
	function eof(){
		foreach($this->socks as $s) if(!$s) return true;
		return false;
	}	
}
class IRCsock {
	var $sock;
	var $host;
	var $port;
	function __construct($host,$port){
		$this->sock = stream_socket_client("ssl://$host:$port")
			or die("TCP sockets unavail :(\n");
		$this->host = $host;
		$this->port = $port;

	}
	function __destruct(){
		if($this->sock)
			$this->write("QUIT :I was garbage collected\n");
		$this->close();
	}
	function write($str){
		mylog(4,"IRC-out: ".str_replace(array("\r","\n"),"",$str));
		fwrite($this->sock,$str,strlen($str));
	}
	function read(){
		$str = fgets($this->sock, 65536);
		if($str===false){
			$this->sock=false;
			return false;
		}
		$str = trim($str,"\r\n");
		if(empty($str)) return false;
		mylog(4,"IRC-in:  $str");

		if(!preg_match('/^\:([^ ]+) +([^:]+?)( +\:(.*?))?$/', $str, $m)) return false;


		$data = array();
		$data['from'] = $m[1];
		$data['args'] = explode(" ",preg_replace('/  +/', ' ', $m[2]));
		if(isset($m[4])) $data['line'] = $m[4];

		return $data;
	}
	function close(){
		if($this->sock) fclose($this->sock);
	}
	function nick($nick){
		$this->write("NICK :$nick\r\n");
	}
	function user($user,$gecos){
		$this->write("USER $user 0 0 :$gecos\r\n");
	}
	function oper($user,$pw){
		$this->write("OPER $user :$pw\r\n");
	}
	function join($chan){
		$this->write("JOIN :$chan\r\n");
	}
	function part($chan,$reason="left"){
		$this->write("PART $chan :$reason\r\n");
	}
	function privmsg($to,$line){
		$this->write("PRIVMSG $to :$line\r\n");
	}
	static function parse_from($from){
		if(!preg_match('/^([^\! ]+)\!([^ @]+)@([^ ]+)$/', $from, $m)) return false;
		$data = array();
		$data['nick'] = $m[1];
		$data['user'] = $m[2];
		$data['host'] = $m[3];
		return $data;
	}
}
class Bot {
	var $ircsock;

	var $state;
	var $complaints = ""; // Used to log errors regarding IRC connection

	
	var $nick;
	var $user;
	var $gecos;

	var $opername;
	var $operpw;

	var $chan;

	function __construct($host,$port,$nick,$user,$gecos,$opername,$operpw,$chan){
		$this->ircsock = new IRCsock($host,$port);
		$this->state = "unreg";

		$this->nick = $nick;
		$this->user = $user;
		$this->gecos = $gecos;
		$this->opername = $opername;
		$this->operpw = $operpw;
		$this->chan = $chan;

		$this->state_unreg();
	}
	function getsock(){
		return $this->ircsock->sock;
	}
	function read(){
		$data = $this->ircsock->read();
		$from = IRCsock::parse_from($data['from']);
		if($data['args'][0] == '001' && $this->state=="unreg"){
			$this->state = "unauth";
			$this->state_unauth();
			return;
		}
		if($data['args'][0]=="MODE" && isset($data['line']) && $data['line']=="+o"){
			$this->state = "unjoined";
			$this->state_unjoined();
			return;
		}
		if($data['args'][0]=="JOIN" && $from['nick'] == $this->nick){
			$ch = $data['line'];
			if($ch!=$this->chan){
				$this->ircsock->part($ch,"I'll only be in ".$this->chan."!");
			} else if($ch==$this->chan){
				$this->state="in";
				$this->state_in();
			}
			return;
		}
	}
	function tochan($data){
		$this->ircsock->privmsg($this->chan,$data);
	}
	function colorhash($str){
		$sum = hexdec(substr(md5($str), 0, 4));
		return ($sum%14)+2;
	}
	function syslog_format($data){
		$addr = $data['addr']; $port = $data['port']; $prio = $data['priority']; $facil = $data['facility']; $line = $data['data'];

		$result = syslog_filter($addr,$port,$prio,$facil,$line);
		if($result===false) return;
		$addr = $result['addr'];
		$port = $result['port'];
		$prio = $result['prio'];
		$facil = $result['facil'];
		$line = $result['line'];
		
		$addr = gethostbyaddr($addr);
		$addr = fmt::bold($addr.str_repeat(" ", 18-strlen($addr)));
		// blue = 2;
		// green = 3;
		// lightred = 4;
		// brown = 5;
		// purple = 6;
		// orange = 7;
		// yellow = 8;
		// lightgreen = 9;
		// cyan = 10;
		// lightcyan = 11;
		// lightblue = 12;
		// pink = 13;
		// grey = 14;
		// lightgrey = 15;
		$prio = $prio.str_repeat(" ", 8-strlen($prio));
		$facil = $facil.str_repeat(" ", 8-strlen($facil));
		$ch = $this->colorhash($facil);
		$facil = fmt::color($facil,$ch);
		switch($prio){
		case "emerg   ": $prio = fmt::color($prio,fmt::black,fmt::yellow);
		case "alert   ": $prio = fmt::color($prio,fmt::black,fmt::pink);
		case "critical": $prio = fmt::color($prio,fmt::yellow);
		case "error   ": $prio = fmt::color($prio,fmt::pink);
		case "warning ": $prio = fmt::color($prio,fmt::orange);
		case "notice  ": $prio = fmt::color($prio,fmt::cyan);
		case "info    ": $prio = fmt::color($prio,fmt::green);
		case "debug   ": $prio = fmt::color($prio,fmt::lightgrey);
		}
		return "$addr [$prio $facil] $line";
		
	}
	function syslog_in($data){
		if($this->state!="in") return;

		$this->tochan($this->syslog_format($data));
	}

	function state_in(){
		mylog(3,"IRC bot in state in");
		$this->tochan("Hi everyone!");
		if(!empty($this->complaints)){
			$this->tochan("I have a few complaints: ".$this->complaints);
			$this->complaints="";
		}
	}
	function state_unjoined(){
		mylog(3,"IRC bot in state unjoined");
		$this->ircsock->join($this->chan);
	}
	function state_unauth(){
		mylog(3,"IRC bot in state unauth");
		$this->ircsock->oper($this->opername,$this->operpw);
	}
	function state_unreg(){
		mylog(3,"IRC bot in state unreg");
		$this->ircsock->user($this->user,$this->gecos);
		$this->ircsock->nick($this->nick);
	}

}

class SyslogListener {
	var $sock;
	function __construct($addr="127.0.0.1", $port=514){
		$this->sock = stream_socket_server("udp://$addr:$port", $no, $str, STREAM_SERVER_BIND)
			or die("UDP sockets unavail :(\n");
	}
	function __destruct(){
		if($this->sock !== false) fclose($this->sock);
	}
	function read(){
		$buf = stream_socket_recvfrom($this->sock, 65536, 0, $host);
		preg_match('/^([^:]+):([0-9]+)$/', $host, $m);
		$addr = $m[1];
		$port = $m[2];
		mylog(4,"syslog:  ".str_replace(array("\r","\n"),"",$buf));
		return $this->parse_syslog($buf,$addr,$port);
	}
	function parse_syslog($data, $addr, $port){
		if(!preg_match('/^<([0-9]+)>(.*)$/', $data, $m)){
			echo "Malformed syslog packet :(\n";
			return false;
		}
		$ret = array();
		$ret['addr']= $addr;
		$ret['port']= $port;
		$number = intval($m[1]);
		$pri = $number&7;
		$fac = $number>>3;
		$ret['priority']= $this->map_prio($pri);
		$ret['prio'] = $pri;
		$ret['facility']= $this->map_facil($fac);
		$ret['data']= $m[2];
		return $ret;
	}
	function map_prio($pri){
		switch($pri){
			case 0: return "emerg";
			case 1: return "alert";
			case 2: return "critical";
			case 3: return "error";
			case 4: return "warning";
			case 5: return "notice";
			case 6: return "info";
			case 7: return "debug";
			default:return "unknown/invalid";
		}
	}
	function map_facil($facil){
		switch($facil){
			case 0:  return "kern";
			case 1:  return "user";
			case 2:  return "mail";
			case 3:  return "daemon";
			case 4:  return "auth";
			case 5:  return "syslog";
			case 6:  return "lpr";
			case 7:  return "news";
			case 8:  return "uucp";
			case 9:  return "clock";
			case 10: return "authpriv";
			case 11: return "ftp";
			case 12: return "ntp";
			case 13: return "logaudit";
			case 14: return "logalert";
			case 15: return "cron";
			case 16: return "l0/net";
			case 17: return "local1";
			case 18: return "local2";
			case 19: return "local3";
			case 20: return "local4";
			case 21: return "local5";
			case 22: return "local6";
			case 23: return "local7";
			default:return "unknown/invalid";
		}
	}
}


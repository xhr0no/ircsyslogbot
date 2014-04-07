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

$path = dirname(__FILE__);

require_once "$path/include/formatting.php";
require_once "$path/include/select.php";
require_once "$path/include/ircsock.php";
require_once "$path/include/sysloglistener.php";
require_once "$path/include/bot.php";
require_once "$path/config.php";


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
		$data = $this->syslog_format($data);
		if($data !== false)
			$this->tochan($data);
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

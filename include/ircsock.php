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

class IRCsock {
	var $sock;
	var $host;
	var $port;
	function __construct($host,$port,$ssl){
		if($ssl)
			$proto = "ssl://";
		else
			$proto = "tcp://";
		$this->sock = stream_socket_client("$proto$host:$port")
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

		$data = array();

		if(substr($str,0,1) == ":"){
			$space = strpos($str, " ");
			if($space === false){
				// From occupies whole line
				$data['from'] = substr($str,1);
				return $data;
			} else {
				$data['from'] = substr($str,1,$space-1);
				$str = ltrim(substr($str,$space), " ");
			}
		}
		$data['args'] = array();
		while(substr($str,0,1) != ":"){
			$space = strpos($str, " ");
			if($space === false){
				// Takes up rest of string
				$data['args'][] = substr($str, 0);
				break;
			} else {
				$data['args'][] = substr($str,0,$space);
				$str = ltrim(substr($str, $space), " ");
				if(strlen($str) == 0) break;
			}
		}

		if(substr($str,0,1) == ":"){
			$data['line'] = substr($str, 1);
			$str = "";
		}

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
	function pong($line){
		$this->write("PONG :$line\r\n");
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

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


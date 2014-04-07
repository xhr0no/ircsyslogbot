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


$sl = new SyslogListener($syslog_addr, $syslog_port);
$irc = new Bot($irc_host,$irc_port,$irc_nick,$irc_user,$irc_gecos,$oper_u, $oper_p,$chan,$colorful);
$sel = new Select(array($irc->getsock(),$sl->sock));

while(!$sel->eof()){
	if(!$irc->getsock()){
		mylog(1,"Reopening IRC, old connection died.\n");
		sleep(1);
		$irc = new Bot($irc_host,$irc_port,$irc_nick,$irc_user,$irc_gecos,$oper_u, $oper_p,$chan,$colorful);
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

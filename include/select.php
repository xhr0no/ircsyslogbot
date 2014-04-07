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

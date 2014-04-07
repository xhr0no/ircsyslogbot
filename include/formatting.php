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
class fmt{
	// fmt::color
	const white = 0;
	const black = 1;
	const blue = 2;
	const green = 3;
	const lightred = 4;
	const brown = 5;
	const purple = 6;
	const orange = 7;
	const yellow = 8;
	const lightgreen = 9;
	const cyan = 10;
	const lightcyan = 11;
	const lightblue = 12;
	const pink = 13;
	const grey = 14;
	const lightgrey = 15;
	function test(){
		return	self::bold("bold")." ".
			self::underline("underline")." ".
			self::italic("italic")." ".
			self::normal("normal")." ".
			self::color("color",self::yellow,self::grey);
	
	}
	function bold($text){
		return self::encapsulate($text,"\x02");
	}
	function underline($text){
		return self::encapsulate($text,"\x1f");
	}
	function italic($text){
		return self::encapsulate($text,"\x26");
	}
	function normal($text){
		return self::encapsulate($text,"\x0f");
	}
	function color($text,$fg=false,$bg=false){
		if($fg!==false){
			$fg=intval($fg);
			if($fg<0 || $fg>15) $fg=false;
			else $fg = str_repeat("0",2-strlen($fg)).$fg;
		}
		if($bg!==false){
			$bg=intval($bg);
			if($bg<0 || $bg>15) $bg=false;
			else $bg = str_repeat("0",2-strlen($bg)).$bg;
		}

		if($fg===false && $bg===false) return self::encapsulate($text,"\x03");
		if($fg!==false && $bg===false) return self::encapsulate($fg.$text,"\x03");
		if($fg!==false && $bg!==false) return self::encapsulate($fg.",".$bg.$text,"\x03");
		return false;
	}
	function encapsulate($text,$what){
		return $what.$text.$what;
	}
};

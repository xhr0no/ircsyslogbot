IRC syslog bot
==============

Usage
-----
* Edit rsyslog.conf to log to the bot, example: \*.\* @127.0.0.1
* Edit config.php to match your network
* run php ircsyslogbot.php

TODO
----
* Enable user to select wether to use SSL or not
* Privilege dropping
* Daemonize + logfile in case you don't want a screen

Credits
-------
* hawken
* Heretic121
* BluCoders community


Changelog
---------
Newest first:
- Improved parsing of IRC lines (Ability to respond to ping + potential speedup since preg_match was dropped)
- configurable syslog listening port
- Responds to ping
- Can manage without oper
- Can be configured to not give colorful lines
- Proper file hierarchy for easier maintaining
- Does not attempt to send an empty line to IRC anymore when message got filtered away

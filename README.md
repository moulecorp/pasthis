# *Pasthis*

## Stupid Simple Pastebin
Pasthis is a simple pastebin written in [php](https://www.php.net/)
using [sqlite](https://sqlite.org/) as database backend and
[prettify](https://code.google.com/p/google-code-prettify/) to handle the syntax
highlighting.

## Deployment
1. Download [Pasthis](https://github.com/moulecorp/pasthis).
2. Put it in a directory on your web server.
3. Configure the web server:
  - Apache: edit the RewriteBase directive in the
    [.htaccess](https://github.com/moulecorp/pasthis/blob/master/.htaccess) if
    needed.
  - Nginx: see the provided
    [nginx.conf](https://github.com/moulecorp/pasthis/blob/master/nginx.conf).
  - Caddy: see the provided Caddyfile
4. Make sure that the folder is _readable_ and _writable_ by www-data, since
this is required by php to be able to create the sqlite database.

It is recommended to call the cron method on a regular basis to avoid an
unwanted growing database and to delete expired pastes (also as a security
concern). To do this on a GNU/Linux machine edit the /etc/crontab file and add
the following line:

		*/10 * * * * www-data php /path/to/pasthis/index.php

Be aware expired pastes are deleted when requested or when the cron method is
called. Without the previous cron configuration, their deletion can't be
ensured. They just won't be displayed.

## Update
1. Update to the latest version (keep the database!)
2. Run $ php update.php

## Tips
### Command line tool

A [command line tool](https://github.com/moulecorp/pasthis/blob/master/pasthis.pl)
is available allowing you to send pastes from the console standard input (STDIN)
or from a file. In order to take advantage of this tool, download it, make it
executable and display the help output for more information:

		chmod +x ./pasthis.py
		./pasthis.py --help
		./pasthis.py --url http://www.example.net/pasthis/ file.txt

### Tabulations

Tabulations are handled in the textarea allowing you to write directly into
Pasthis without changing the textarea focus.

## Specifications:
  - Pasthis MUST supports color highlighting
  - Pasthis SHOULD be able to work without JS if necessary
  - Pasthis MUST be lightweight
  - Pasthis MUST NOT use clunky-javascript-powered-crypo
  - Pasthis SHOULD NOT use predictable (by a casual attacker) pastes url
  - Pasthis MUST deter trivial spam attacks
  - Pasthis MUST have a way to delete outdated pastes without user intervention
  - Pasthis MUST allow the user to see the raw content
  - Pasthis MUST NOT store users IP in plain-text
  - Pasthis MUST NOT depend on external services

## Implementation
### Anti-spam
Every time a paste is sent, a value (called degree) is associated to
the poster's ip hash. It is used in the following formula:

    T = time() + intval(pow(degree, 2.5))

If the user posts another paste after T, the degree is reset to zero.
If he tries before T, the degree is incremented, and the paste is denied.

There is also an hidden field, that set the degree to 512 (Which corresponds
to ~72h) if filled.


### Display
The user can access the raw version of a paste by appending
@raw to its id.

## Authors and License

		Copyright (C) 2014 - 2018 Julien (jvoisin) Voisin - dustri.org
		Copyright (C) 2014 - 2018 Antoine Tenart <antoine.tenart@ack.tf>

		This program is free software; you can redistribute it and/or
		modify it under the terms of the GNU General Public License
		as published by the Free Software Foundation; either version 2
		of the License, or (at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
		02110-1301, USA.

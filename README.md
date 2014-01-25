# *Pasthis*

## Stupid Simple Pastebin
Pasthis is a simple pastebin written in [php](https://www.php.net/)
and using [sqlite](https://sqlite.org/) as database backend.

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

## Implementation
## Anti-spam
1. If the hidden field is filled, the user is banned for ~72h.
2. A simple almost-cubic throttle is used, to prevent burst-spam

## Display
The user can access the raw version of a paste by appending
@raw to its id.

## Authors and License
 - Copyright (C) 2014 Julien (jvoisin) Voisin - dustri.org
 - Copyright (C) 2014 Antoine Tenart <atenart@n0.pe>

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

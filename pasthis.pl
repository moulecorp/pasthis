#!/usr/bin/perl

#  Copyright (C) 2014 Antoine Tenart <atenart@n0.pe>
# 
#  This program is free software; you can redistribute it and/or
#  modify it under the terms of the GNU General Public License
#  as published by the Free Software Foundation; either version 2
#  of the License, or (at your option) any later version.
# 
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
# 
#  You should have received a copy of the GNU General Public License
#  along with this program; if not, write to the Free Software
#  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
#  02110-1301, USA.

use strict;
use Getopt::Long;
use WWW::Mechanize;

local $/;

my $mech   = WWW::Mechanize->new ();
my $url    = undef;
my $expire = '1h';
my $file   = undef;
my $help   = undef;

die ('Command line arguments error') unless
GetOptions (
	'url=s'    => \$url,
	'expire=s' => \$expire,
	'file=s'   => \$file,
	'help'     => \$help,
);

if (defined ($help)) {
	print <<EOF;
Usage:\t$0 [OPTIONS] STDIN
\t$0 [OPTIONS] --file file_to_paste

OPTIONS
\t--url URL\t\tSend paste to pasthis located at URL.
\t--expire EXPIRATION\tDelete paste after EXPIRATION. Defaults to 1d.
\t--help\t\t\tPrint this help.

EXPIRATION
\tburn\tBurn after a single read.
\t10m\t10 minutes.
\t1h\t1 hour.
\t1m\t1 month.
\t1y\t1 year.
\teternal\tNever expires.
EOF
	exit 0;
}

die ('Please provide an url. See help.') unless defined ($url);

my $file_content = undef;
if (defined ($file)) {
	open my $fh, '<', $file or die $!;
	$file_content = <$fh>;
}

my %expirations = (
	'burn'    => 0,
	'10m'     => 600,
	'1h'      => 3600,
	'1d'      => 86400,
	'1m'      => 2678400,
	'1y'      => 31536000,
	'eternal' => -1,
);

$mech->post (
	$url,
	[
		'd' => defined ($expirations{$expire}) ? $expirations{$expire} : 86400,
		'p' => defined ($file_content) ? $file_content : <STDIN>,
	]
);

die ('Error while sending paste to pasthis at '.$url) unless $mech->success ();

my $id = $mech->find_link (text_regex => qr/^[a-zA-Z0-9]{6}$/, n => 1)->text ();
print "paste:\t".$url.'/'.$id."\n";
print "raw:\t".$url.'/'.$id."\@raw\n";

1;

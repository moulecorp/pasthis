#!/usr/bin/python
#
# Copyright (C) 2018 - 2020 Antoine Tenart <antoine.tenart@ack.tf>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
# 02110-1301, USA.

import argparse
import os.path
import requests
import sys

def get_parser():
    parser = argparse.ArgumentParser(description="Pasthis command line tool",
            epilog="Expiration period should be one of: burn,10m,1h,1d,1w,never.")
    parser.add_argument("file", help="file to paste or stdin (default: stdin)", nargs="?", default="-")
    parser.add_argument("-u", "--url", help="send paste to Pasthis at a given URL (default: https://__PASTHIS_DOMAIN_NAME__)",
            default="https://__PASTHIS_DOMAIN_NAME__")
    parser.add_argument("-e", "--expire", help="delete paste after a given period (default: 1d)",
            default="1d")
    parser.add_argument("--hl", help="syntax highlighting", action="store_true")
    parser.add_argument("-w", "--wrap", help="wrap long lines", action="store_true")
    return parser

def get_file_content(path):
    if path == "-":
        return sys.stdin.read()
    else:
        if not os.path.isfile(path):
            return None

        with open(path) as f:
            return f.read()
    return None

def main():
    parser = get_parser()
    args = parser.parse_args()

    content = get_file_content(args.file)
    if content is None:
        print("Unable to read %s." % args.file)
        return -1

    period = {
        "burn":  -2,
        "never": -1,
        "10m":   600,
        "1h":    3600,
        "1d":    86400,
        "1w":    604800,
        "1m":    2678400
    }

    payload = {
        "p": content,
        "d": period[args.expire],
        "ricard": "",
    }
    if args.hl:
        payload["highlighting"] = "1"
    if args.wrap:
        payload["wrap"] = "1"

    req = requests.post(args.url, data=payload)
    if req.status_code != 200:
        print("Failed to upload content (error %s)." % req.satus_code)
        return -1

    print(req.url)
    print("%s@raw" % req.url)

    return 0

if __name__ == "__main__":
    sys.exit(main())

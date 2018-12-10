<?php
/**
 *  Pasthis - Stupid Simple Pastebin
 *
 * Copyright (C) 2014 - 2018 Julien (jvoisin) Voisin - dustri.org
 * Copyright (C) 2014 - 2018 Antoine Tenart <antoine.tenart@ack.tf>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

final class Pasthis {
    public $title;
    private $contents = array ();
    private $db;

    function __construct ($title = 'Pasthis') {
        $this->title = $title;
        $dsn = 'sqlite:' . dirname(__FILE__) .'/pasthis.db';
        try {
            $this->db = new PDO ($dsn);
            $this->db->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            $this->db->setAttribute (PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die ('Unable to open database: ' . $e->getMessage ());
        }
        $this->db->exec ('pragma auto_vacuum = 1');
        $this->db->exec (
            "CREATE TABLE if not exists pastes (
                id PRIMARY KEY,
                deletion_date TIMESTAMP,
                highlighting INTEGER,
                wrap INTEGER,
                paste BLOB
            );"
        );
        $this->db->exec (
            "CREATE TABLE if not exists users (
                hash PRIMARY KEY,
                nopaste_period TIMESTAMP,
                degree INTEGER
            );"
        );
    }

    function __destruct () {
        $this->db = null;
    }

    private function add_content ($content, $prepend = false) {
        if (!$prepend)
            $this->contents[] = $content;
        else
            array_unshift ($this->contents, $content);
    }

    private function render () {
        print '<!DOCTYPE html>';
        print '<html>';
        print '<head>';
        print '<title>'.htmlentities ($this->title).'</title>';
        print '<link href="./css/style.css" rel="stylesheet" type="text/css" />';
        print '<link href="./css/prettify.css" rel="stylesheet" type="text/css" />';
        print '</head>';
        print '<body>';
        print '<h1>Pasthis</h1>';
        while (list (, $ct) = each ($this->contents))
            print $ct;
        print '<div id="footer">';
        print 'Powered by <a href="https://github.com/moulecorp/pasthis">pasthis</a> - ';
        print '<a href="./pasthis.py">command-line tool</a>';
        print '</div>';
        print '</body>';
        print '</html>';
        exit ();
    }

    private function remaining_time ($timestamp) {
        if ($timestamp == -1)
            return 'Never expires.';
        elseif ($timestamp == 0)
            return 'Expired.';
        elseif ($timestamp == -2)
            return 'One reading remaining.';

        $format = function ($t,$s) { return $t ? $t.' '.$s.($t>1 ? 's' : '' ).' ' : ''; };

        $expiration = new DateTime ('@'.$timestamp);
        $interval = $expiration->diff (new DateTime (), true);

        $ret = 'Expires in '.$format ($interval->days, 'day');
        if ($interval->days < 31) {
            $ret .= $format ($interval->h, 'hour');
            if ($interval->d === 0) {
                $ret .= $format ($interval->i, 'minute');
                if ($interval->h === 0)
                    $ret .= $format ($interval->s, 'second');
            }
        }
        return rtrim ($ret).'.';
    }

    function prompt_paste () {
        $this->add_content (
            '<form method="post" action=".">
                <label for="d">Expiration: </label>
                <select name="d" id="d">
                    <option value="-2">burn after reading</option>
                    <option value="600">10 minutes</option>
                    <option value="3600">1 hour</option>
                    <option value="86400" selected="selected">1 day</option>
                    <option value="604800">1 week</option>
                    <option value="-1">eternal</option>
                </select>
                <input type="text" id="ricard" name="ricard"
                        placeholder="Do not fill me!" />
                <input type="submit" id="submit" value="Send paste">
                <span id="left">
                <input type="checkbox" id="wrap" name="wrap"> wrap long lines<br />
                <input type="checkbox" id="highlighting" name="highlighting"> syntax highlighting<br />
                </span>
                <textarea autofocus required name="p"></textarea>
            </form>'
        );
        $this->add_content ('<script src="./js/textarea.js"></script>');

        $this->render ();
    }

    private function generate_id () {
        $query = $this->db->prepare (
            "SELECT id FROM pastes
             WHERE id = :uniqid;"
        );
        $query->bindParam (':uniqid', $uniqid, PDO::PARAM_STR, 6);

        do {
            $uniqid = substr (uniqid (), -6);
            $query->execute ();
        } while ($query->fetch () != false);

        return $uniqid;
    }

    private function nopaste_period ($degree) {
        return time () + intval (pow ($degree, 2.5));
    }

    private function check_spammer () {
        $hash = sha1 ($_SERVER['REMOTE_ADDR']);

        $query = $this->db->prepare (
            "SELECT * FROM users
             WHERE hash = :hash"
        );
        $query->bindValue (':hash', $hash, PDO::PARAM_STR);
        $query->execute ();
        $result = $query->fetch ();

        $in_period = (!empty ($result) and time () < $result['nopaste_period']);
        $obvious_spam = (!isset ($_POST['ricard']) or !empty ($_POST['ricard']));

        $degree = $in_period ? $result['degree']+1 : ($obvious_spam ? 512 : 1);
        $nopaste_period = $this->nopaste_period ($degree);

        $query = $this->db->prepare (
            "REPLACE INTO users
             (hash, nopaste_period, degree)
             VALUES (:hash, :nopaste_period, :degree);"
        );
        $query->bindValue (':hash', $hash, PDO::PARAM_STR);
        $query->bindValue (':nopaste_period', $nopaste_period, PDO::PARAM_INT);
        $query->bindValue (':degree', $degree, PDO::PARAM_INT);
        $query->execute ();

        if ($in_period or $obvious_spam)
            die ('Spam');
    }

    function add_paste ($deletion_date, $highlighting, $wrap, $paste) {
        $this->check_spammer();

        $deletion_date = intval ($deletion_date);

        if ($deletion_date > 0)
            $deletion_date += time ();

        $uniqid = $this->generate_id ();

        $query = $this->db->prepare (
            "INSERT INTO pastes (id, deletion_date, highlighting, wrap, paste)
             VALUES (:uniqid, :deletion_date, :highlighting, :wrap, :paste);"
        );
        $query->bindValue (':uniqid', $uniqid, PDO::PARAM_STR);
        $query->bindValue (':deletion_date', $deletion_date, PDO::PARAM_INT);
        $query->bindValue (':highlighting', $highlighting, PDO::PARAM_INT);
        $query->bindValue (':wrap', $wrap, PDO::PARAM_INT);
        $query->bindValue (':paste', $paste, PDO::PARAM_STR);
        $query->execute ();

        header ('location: ./' . $uniqid);
    }

    function show_paste ($param) {
        $id = str_replace ("@raw", "", $param);
        $is_raw = intval(strtolower (substr ($param, -4)) == "@raw");

        $fail = false;
        $query = $this->db->prepare(
            "SELECT * FROM pastes
             WHERE id = :id;"
        );
        $query->bindValue (':id', $id, PDO::PARAM_STR);
        $query->execute ();
        $result = $query->fetch ();

        if ($result == null) {
            $fail = true;
        } elseif ($result['deletion_date'] < time ()
                and $result['deletion_date'] >= 0) {
            $query = $this->db->prepare (
                "DELETE FROM pastes
                 WHERE id = :id;"
            );
            $query->bindValue (':id', $id, PDO::PARAM_STR);
            $query->execute ();

            /* do not fail on "burn after reading" pastes */
            if ($result['deletion_date'] != 0)
                $fail = true;
        } elseif ($result['deletion_date'] == -2) {
            $query = $this->db->prepare (
                "UPDATE pastes
                 SET deletion_date=0
                 WHERE id = :id;"
            );
            $query->bindValue (':id', $id, PDO::PARAM_STR);
            $query->execute ();
        }

        if ($fail) {
            $this->add_content ('<p>Meh, no paste for this id :(</p>');
            $this->prompt_paste ();
        } else {
            header ('X-Content-Type-Options: nosniff');

            if ($is_raw) {
                header ('Content-Type: text/plain; charset=utf-8');

                print $result['paste'];
                exit ();
            } else {
                header ('Content-Type: text/html; charset=utf-8');

                if ($result['highlighting']) {
                    $this->add_content ('<script>window.onload=function(){prettyPrint();}</script>');
                    $this->add_content ('<script src="./js/prettify.js"></script>', true);
                }
                $this->add_content (
                    '<div id="links">
                         <a href="./'.$id.'@raw">Raw</a> - <a href="./">New paste</a>
                         <span id="left">'.$this->remaining_time ($result['deletion_date']).'</span>
                     </div>'
                );
                $class = 'prettyprint linenums';
                if ($result['wrap'])
                    $class .= ' wrap';

                $this->add_content ('<pre class="'.$class.'">'.htmlentities ($result['paste']).'</pre>');
            }
        }

        $this->render ();
    }

    function cron () {
        $this->db->exec (
            "DELETE FROM pastes
             WHERE deletion_date > 0
             AND strftime ('%s','now') > deletion_date;
             DELETE FROM users
             WHERE strftime ('%s','now') > nopaste_period;"
        );
    }
}

$pastebin = new Pasthis ();

if (php_sapi_name () == 'cli') {
    $pastebin->cron ();
    exit ();
}

if (isset ($_GET['p']))
    $pastebin->show_paste ($_GET['p']);
elseif (isset ($_POST['d']) && isset ($_POST['p']))
    $pastebin->add_paste ($_POST['d'], isset ($_POST['highlighting']),
                          isset ($_POST['wrap']), $_POST['p']);
else
    $pastebin->prompt_paste ();
?>

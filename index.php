<?php
/**
 *  Pasthis - Stupid Simple Pastebin
 *
 * Copyright (C) 2014 Julien (jvoisin) Voisin - dustri.org
 * Copyright (C) 2014 Antoine Tenart <atenart@n0.pe>
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
        $this->db = new SQLite3 ('pasthis.db',
                SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        if (is_null ($this->db)) {
            if (file_exists('pasthis.db'))
                die ("Unable to open database, check permissions");
            else
                die ("Unable to create database, check permissions");
        }
        $this->db->query (
            "CREATE TABLE if not exists pastes (
                id PRIMARY KEY,
                deletion_date TIMESTAMP,
                paste BLOB
            );"
        );
    }

    function __destruct () {
        $this->db->close ();
    }

    function add_content ($content, $prepend = false) {
        if (!$prepend)
            $this->contents[] = $content;
        else
            array_unshift ($this->content, $content);
    }

    private function render () {
        print '<!DOCTYPE html>';
        print '<html>';
        print '<head>';
        print '<title>'.htmlentities ($this->title).'</title>';
        print '<link href="./css/style.css" rel="stylesheet" type="text/css" />';
        print '</head>';
        print '<body onload="prettyPrint()">';
        while (list (, $ct) = each ($this->contents))
            print $ct;
        print '</body>';
        print '</html>';
        exit ();
    }

    function prompt_paste () {
        $this->add_content (
            "<form method='post' action='.'>
                <label for='d'>Expiration: </label>
                <select name='d' id='d'>
                    <option value='0'>burn after reading</option>
                    <option value='600'>10 minutes</option>
                    <option value='3600' selected='selected'>1 hour</option>
                    <option value='86400'>1 day</option>
                    <option value='2678400'>1 month</option>
                    <option value='31536000'>1 year</option>
                    <option value='-1'>eternal</option>
                </select>
                <input type='text' id='ricard' name='ricard'
                        placeholder='Do not fill me!' />
                <input type='submit' value='Send paste'>
                <textarea autofocus required name='p'></textarea>
            </form>"
        );

        $this->render ();
    }

    private function generate_id () {
        do {
            $uniqid = substr (uniqid (), -6);
            $result = $this->db->querySingle (
                "SELECT id FROM pastes
                WHERE id='".$uniqid."';"
            );
        } while (!is_null ($result));

        return $uniqid;
    }

    function add_paste ($deletion_date, $paste) {
        if (isset ($_POST['ricard']) and $_POST['ricard'] != '')
            /* die, just die */
            die ();

        $paste = SQLite3::escapeString ($paste);
        $deletion_date = intval ($deletion_date);

        if ($deletion_date > 0)
            $deletion_date += time ();

        $uniqid = $this->generate_id ();

        $this->db->query ("INSERT INTO pastes (id, deletion_date, paste)
                VALUES ('".$uniqid."','".$deletion_date."','".$paste."');");

        $this->add_content (
            "<ul>
                <a href='?p=".$uniqid."'>".$uniqid."</a>
                (raw:<a href='?p=".$uniqid."@raw'>".$uniqid."@raw</a>)
            </ul>"
        );

        $this->render ();
    }

    function show_paste ($id, $raw) {
        $id = SQLite3::escapeString ($id);
        $raw = intval ($raw);

        $fail = false;
        $request = $this->db->query ("SELECT * FROM pastes WHERE id='".$id."';");
        if (!($request instanceof Sqlite3Result))
            die ('Unable to perform query on the database');

        $result = $request->fetchArray ();

        if ($result === false) {
            $fail = true;
        } elseif ($result['deletion_date'] < time ()
                and $result['deletion_date'] != -1) {
            $this->db->exec ("DELETE FROM pastes WHERE id='".$id."';");

            /* do not fail on "burn after reading" pastes */
            if ($result['deletion_date'] != 0)
                $fail = true;
        }

        if ($fail) {
            $this->add_content ("Meh, no paste for this id :<");
        } elseif (!$raw) {
            $this->add_content ('<script src="./js/prettify.js"></script>', true);
            $this->add_content ('<pre class="prettyprint">'.
                    htmlspecialchars ($result['paste']).'</pre>');
            $this->add_content ('<div><a href="./">New paste</a></div>');
        } else {
            header ("Content-Type: text/plain");
            print $result['paste'];
            exit ();
        }

        $this->render ();
    }

    function cron () {
        $this->db->exec (
            "DELETE FROM pastes
             WHERE deletion_date != 0
             AND deletion_date != -1
             AND date('now') > deletion_date;"
        );
    }
}

$pastebin = new Pasthis ();

if (isset ($_GET['p']))
    $pastebin->show_paste (str_replace ("@raw", "", $_GET['p']),
            strtolower (substr ($_GET['p'], -4)) == "@raw");
elseif (isset ($_POST['d']) && isset ($_POST['p']))
    $pastebin->add_paste ($_POST['d'], $_POST['p']);
else
    $pastebin->prompt_paste ();
?>

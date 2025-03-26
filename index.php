<?php
/**
 *  Pasthis - Stupid Simple Pastebin
 *
 * Copyright (C) 2014 - 2025 Julien (jvoisin) Voisin - dustri.org
 * Copyright (C) 2014 - 2025 Antoine Tenart <antoine.tenart@ack.tf>
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
    private $contents = array();
    private $db;
    private $default_expiration = 86400;
    private $expirations = array(
        -2 => "burn after reading",
        600 => "10 minutes",
        3600 => "1 hour",
        86400 => "1 day",
        604800 => "1 week",
        -1 => "eternal",
    );

    function __construct($title = 'Pasthis - Simple pastebin') {
        $this->title = $title;
        $dsn = 'sqlite:' . dirname(__FILE__) .'/pasthis.db';
        try {
            $this->db = new PDO($dsn);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            die('Unable to open database: ' . $e->getMessage());
        }
        $this->db->exec('pragma auto_vacuum = 1');
        $this->db->exec(
            "CREATE TABLE if not exists pastes (
                id PRIMARY KEY,
                deletion_date TIMESTAMP,
                highlighting INTEGER,
                wrap INTEGER,
                paste BLOB
            );"
        );
        $this->db->exec(
            "CREATE TABLE if not exists users (
                hash PRIMARY KEY,
                nopaste_period TIMESTAMP,
                degree INTEGER
            );"
        );
    }

    function __destruct() {
        $this->db = null;
    }

    private function add_content($content, $prepend = false) {
        if (!$prepend)
            $this->contents[] = $content;
        else
            array_unshift($this->contents, $content);
    }

    private function render() {
        print '<!DOCTYPE html>';
        print '<html>';
        print '<head>';
        print '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        print '<title>Pasthis - Simple pastebin</title>';
        print '<link href="./css/normalize.css" rel="stylesheet" type="text/css">';
        print '<link href="./css/skeleton.css" rel="stylesheet" type="text/css">';
        print '<link href="./css/style.css" rel="stylesheet" type="text/css">';
        print '<link href="./css/prettify.css" rel="stylesheet" type="text/css">';
        print '</head>';
        print '<body>';
        print '<div class="container">';
        print '<h1>Pasthis</h1>';
        foreach ($this->contents as $ct)
            print $ct;
        print '<div class="footer">';
        print 'Powered by <a href="https://github.com/moulecorp/pasthis">Pasthis</a> | ';
        print '<a href="./?cli">Command-line tool</a> | ';
        print 'No statistics, no list.';
        print '</div>';
        print '</div>';
        print '</body>';
        exit();
    }

    private function remaining_time($timestamp) {
        if ($timestamp == -1)
            return 'Never expires.';
        elseif ($timestamp == 0)
            return 'Expired.';
        elseif ($timestamp == -2)
            return 'One reading remaining.';

        $format = function($t,$s) { return $t ? $t.' '.$s.($t>1 ? 's' : '' ).' ' : ''; };

        $expiration = new DateTime('@'.$timestamp);
        $interval = $expiration->diff(new DateTime(), true);

        $ret = 'Expires in '.$format($interval->d, 'day');
        $ret .= $format($interval->h, 'hour');
        if ($interval->d === 0) {
            $ret .= $format($interval->i, 'minute');
            if ($interval->h === 0)
                $ret .= $format($interval->s, 'second');
        }
        return rtrim($ret).'.';
    }

    function prompt_paste() {
        $this->add_content(
            '<form method="post" action=".">
                 <div class="row">
                     <select name="d">'
        );
        $this->add_content(
                         '<option value="'.$this->default_expiration.'" selected hidden>Expiration ('.$this->expirations[$this->default_expiration].')</option>'
        );
        foreach ($this->expirations as $val => $text)
                $this->add_content(
                         '<option value="'.$val.'">'.$text.'</option>'
                );
        $this->add_content(
                    '</select>
                     <button type="submit">Send paste</button>
                     <div class="right">
                         <label>
                             <input type="checkbox" name="wrap">
                             <span class="label-body">wrap long lines</span>
                         </label>
                         <label>
                             <input type="checkbox" name="highlighting">
                             <span class="label-body" for="">syntax highlighting</span>
                         </label>
                     </div>
                 </div>
                 <div class="row">
                     <input type="text" id="ricard" name="ricard" placeholder="Do not fill me!">
                     <textarea autofocus required name="p" placeholder="Tab key can be used."></textarea>
                 </div>
             </form>'
        );
        $this->add_content('<script defer src="./js/textarea.js"></script>');

        $this->render();
    }

    private function generate_id() {
        $query = $this->db->prepare(
            "SELECT id FROM pastes
             WHERE id = :uniqid;"
        );
        $query->bindParam(':uniqid', $uniqid, PDO::PARAM_STR, 6);

        do {
            $uniqid = substr(uniqid(), -6);
            $query->execute();
        } while ($query->fetch() != false);

        return $uniqid;
    }

    private function nopaste_period($degree) {
        return time() + intval(pow($degree, 2.5));
    }

    private function check_spammer() {
        $hash = sha1($_SERVER['REMOTE_ADDR']);

        $query = $this->db->prepare(
            "SELECT * FROM users
             WHERE hash = :hash"
        );
        $query->bindValue(':hash', $hash, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch();

        $in_period = (!empty($result) and time() < $result['nopaste_period']);
        $obvious_spam = (!isset($_POST['ricard']) or !empty($_POST['ricard']));

        $degree = $in_period ? $result['degree']+1 : ($obvious_spam ? 512 : 1);
        $nopaste_period = $this->nopaste_period($degree);

        $query = $this->db->prepare(
            "REPLACE INTO users
             (hash, nopaste_period, degree)
             VALUES (:hash, :nopaste_period, :degree);"
        );
        $query->bindValue(':hash', $hash, PDO::PARAM_STR);
        $query->bindValue(':nopaste_period', $nopaste_period, PDO::PARAM_INT);
        $query->bindValue(':degree', $degree, PDO::PARAM_INT);
        $query->execute();

        if ($in_period or $obvious_spam)
            die('Spam');
    }

    function add_paste($expiration, $highlighting, $wrap, $paste) {
        $this->check_spammer();

        $expiration = intval($expiration);
        if (!isset($this->expirations[$expiration])) {
            header('location: ./');
            return;
        }

        $deletion_date = $expiration;
        if ($deletion_date > 0)
            $deletion_date += time();

        $uniqid = $this->generate_id();

        $query = $this->db->prepare(
            "INSERT INTO pastes (id, deletion_date, highlighting, wrap, paste)
             VALUES (:uniqid, :deletion_date, :highlighting, :wrap, :paste);"
        );
        $query->bindValue(':uniqid', $uniqid, PDO::PARAM_STR);
        $query->bindValue(':deletion_date', $deletion_date, PDO::PARAM_INT);
        $query->bindValue(':highlighting', $highlighting, PDO::PARAM_INT);
        $query->bindValue(':wrap', $wrap, PDO::PARAM_INT);
        $query->bindValue(':paste', $paste, PDO::PARAM_STR);
        $query->execute();

        header('location: ./' . $uniqid);
    }

    function show_paste($param) {
        $id = str_replace("@raw", "", $param);
        $is_raw = intval(strtolower(substr($param, -4)) == "@raw");

        $fail = false;
        $query = $this->db->prepare(
            "SELECT * FROM pastes
             WHERE id = :id;"
        );
        $query->bindValue(':id', $id, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch();

        if ($result == null) {
            $fail = true;
        } elseif ($result['deletion_date'] < time()
                and $result['deletion_date'] >= 0) {
            $query = $this->db->prepare(
                "DELETE FROM pastes
                 WHERE id = :id;"
            );
            $query->bindValue(':id', $id, PDO::PARAM_STR);
            $query->execute();

            /* do not fail on "burn after reading" pastes */
            if ($result['deletion_date'] != 0)
                $fail = true;
        } elseif ($result['deletion_date'] == -2) {
            $query = $this->db->prepare(
                "UPDATE pastes
                 SET deletion_date=0
                 WHERE id = :id;"
            );
            $query->bindValue(':id', $id, PDO::PARAM_STR);
            $query->execute();
        }

        if ($fail) {
            $this->add_content('<div class="warning">Meh, no paste for this id :(</div>');
            $this->prompt_paste();
        } else {
            header('X-Content-Type-Options: nosniff');

            if ($is_raw) {
                header('Content-Type: text/plain; charset=utf-8');

                print $result['paste'];
                exit();
            } else {
                header('Content-Type: text/html; charset=utf-8');

                $class = 'prettyprint linenums';

                if ($result['highlighting']) {
                    $this->add_content('<script>window.onload=function(){prettyPrint();}</script>');
                    $this->add_content('<script defer src="./js/prettify.js"></script>', true);
                    $class .= ' highlighting';
                }

                $this->add_content(
                    '<a href="./'.$id.'@raw">Raw</a> | <a href="./">New paste</a>
                     <div class="right">'.$this->remaining_time($result['deletion_date']).'</div>'
                );

                if ($result['wrap'])
                    $class .= ' wrap';

                $this->add_content('<pre class="'.$class.'">'.htmlentities($result['paste']).'</pre>');
            }
        }

        $this->render();
    }

    function serve_cli() {
        $uri = $_SERVER['SERVER_NAME'] . explode('?', $_SERVER['REQUEST_URI'])[0];
        $script = file_get_contents('./pasthis.py');
        $script = str_replace('__PASTHIS_DOMAIN_NAME__', $uri, $script);

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="pasthis"');

        print $script;
        exit();
    }

    function cron() {
        $this->db->exec(
            "DELETE FROM pastes
             WHERE deletion_date > 0
             AND strftime ('%s','now') > deletion_date;
             DELETE FROM users
             WHERE strftime ('%s','now') > nopaste_period;"
        );
    }
}

$pastebin = new Pasthis();

if (php_sapi_name() == 'cli') {
    $pastebin->cron();
    exit();
}

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'on')
    exit('Meh, not accessed over HTTPS.');

if (isset($_GET['p']))
    $pastebin->show_paste($_GET['p']);
elseif (isset($_POST['d']) && isset($_POST['p']))
    $pastebin->add_paste($_POST['d'], isset($_POST['highlighting']),
                         isset($_POST['wrap']), $_POST['p']);
elseif (isset($_GET['cli']))
    $pastebin->serve_cli();
else
    $pastebin->prompt_paste();
?>

<?php

// Run with the installed library: php tests/easee_log_test.php /opt/loxberry/libs/phplib
$library = isset($argv[1]) ? $argv[1] : '/opt/loxberry/libs/phplib';
if (!is_file($library . '/loxberry_log.php')) {
    fwrite(STDERR, "Pass the directory containing LoxBerry's loxberry_log.php.\n");
    exit(1);
}
set_include_path(__DIR__ . '/fixtures' . PATH_SEPARATOR . $library . PATH_SEPARATOR . get_include_path());
require_once 'loxberry_system.php';

$worker = isset($argv[2]) && $argv[2] === '--worker';
$root = $worker ? $argv[3] : sys_get_temp_dir() . '/easee-log-test-' . bin2hex(random_bytes(8));
if (!$worker) {
    mkdir($root . '/log/system_tmpfs', 0700, true);
    mkdir($root . '/config', 0700);
    mkdir($root . '/api', 0700);
    mkdir($root . '/parallel', 0700);
}
define('LBHOMEDIR', $root);
define('LBSCONFIGDIR', $root . '/config');
$lbhomedir = $root;
$lbplogdir = $root . ($worker ? '/parallel' : '/api');
$lbpplugindir = 'easee_home';
require_once __DIR__ . '/../webfrontend/html/easee_log.php';

if ($worker) {
    $log = easee_start_log($lbplogdir, $lbpplugindir, intval($argv[4]));
    LOGINF('concurrent request');
    $GLOBALS['stdLog'] = null;
    unset($log);
    exit(0);
}

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function release_log(&$log)
{
    $GLOBALS['stdLog'] = null;
    $log = null;
}

function remove_test_files($directory)
{
    foreach (new DirectoryIterator($directory) as $file) {
        if ($file->isDot()) {
            continue;
        }
        if ($file->isDir()) {
            remove_test_files($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($directory);
}

try {
    $first = strtotime('2026-10-06 12:00:00');
    $second = strtotime('2026-10-07 12:00:00');
    $filename = $lbplogdir . '/easeeAPIcalls_2026-10-06.log';
    $next = $lbplogdir . '/easeeAPIcalls_2026-10-07.log';

    $log = easee_start_log($lbplogdir, $lbpplugindir, $first);
    check($GLOBALS['stdLog'] === $log, 'Daily logger must be the default logger');
    check($log->loglevel() === 6, 'Logger must inherit the native level');
    LOGINF('first request');
    LOGDEB('hidden debug');
    LOGERR('earlier failure');
    release_log($log);
    $metadata = easee_log_metadata($filename, $lbpplugindir);
    check(empty($metadata['LOGEND']), 'Current day must remain open between requests');
    check(strpos(file_get_contents($filename), 'hidden debug') === false, 'Info must filter debug messages');
    $key = $metadata['LOGKEY'];
    $db = new SQLite3(LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat');
    $db->exec("INSERT OR REPLACE INTO logs_attr (keyref, attrib, value) VALUES ($key, 'ATTENTIONMESSAGES', '<ERROR> earlier failure')");
    $db->close();

    $log = easee_start_log($lbplogdir, $lbpplugindir, $first + 3600);
    LOGINF('same day, later hour');
    release_log($log);
    $metadata = easee_log_metadata($filename, $lbpplugindir);
    check($metadata['LOGKEY'] === $key, 'Same day must reuse the database session');
    check(intval($metadata['STATUS']) === 3, 'Appending must preserve earlier errors');
    check(strpos($metadata['ATTENTIONMESSAGES'], 'earlier failure') !== false, 'Appending must preserve attention messages');
    check(substr_count(file_get_contents($filename), 'TASK STARTED') === 1, 'Same day must only start once');
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second);
    LOGINF('new day');
    release_log($log);
    check(!empty(easee_log_metadata($filename, $lbpplugindir)['LOGEND']), 'Rollover must close the previous database session');
    check(intval(easee_log_metadata($filename, $lbpplugindir)['STATUS']) === 3, 'Closing must preserve the worst severity');
    check(strpos(easee_log_metadata($filename, $lbpplugindir)['ATTENTIONMESSAGES'], 'earlier failure') !== false, 'Closing must preserve stored attention messages');
    check(substr_count(file_get_contents($filename), 'TASK FINISHED') === 1, 'Rollover must write LOGEND once');
    check(empty(easee_log_metadata($next, $lbpplugindir)['LOGEND']), 'New day must remain open');
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second);
    release_log($log);
    check(substr_count(file_get_contents($filename), 'TASK FINISHED') === 1, 'Repeated requests must not close old logs again');

    $orphan = $lbplogdir . '/easeeAPIcalls_2026-10-05.log';
    file_put_contents($orphan, "legacy content\n");
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second);
    release_log($log);
    check(strpos(file_get_contents($orphan), 'legacy content') === 0, 'Recovery must never truncate an unregistered log');
    check(!empty(easee_log_metadata($orphan, $lbpplugindir)['LOGEND']), 'Legacy logs must be registered and closed');

    $db = new SQLite3(LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat');
    $db->exec("DELETE FROM logs_attr WHERE keyref IN (SELECT LOGKEY FROM logs WHERE FILENAME = '$next')");
    $db->exec("DELETE FROM logs WHERE FILENAME = '$next'");
    $db->close();
    $before = file_get_contents($next);
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second);
    release_log($log);
    check(easee_log_metadata($next, $lbpplugindir) !== null, 'An existing file missing its DB entry must recover');
    check(strpos(file_get_contents($next), $before) === 0, 'DB recovery must preserve file contents');

    $title = 'Aufrufe der Easee Cloud API (über easee.php) und Antworten';
    check(strpos(file_get_contents($next), '<LOGSTART>' . $title) !== false, 'New logs must use the requested title');
    $db = new SQLite3(LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat');
    $stored_title = $db->querySingle("SELECT value FROM logs_attr WHERE attrib = 'LOGSTARTMESSAGE' AND keyref = "
        . easee_log_metadata($next, $lbpplugindir)['LOGKEY']);
    check($stored_title === $title, 'Log manager must display the requested title');
    $db->close();
    LBSystem::$level = 7;
    LBSystem::$revision++;
    $hourly = $lbplogdir . '/easeeAPIcalls_2026-10-07_12.log';
    $next_hour = $lbplogdir . '/easeeAPIcalls_2026-10-07_13.log';
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second);
    check($log->loglevel() === 7, 'Native Debug must not be overridden');
    LOGDEB('visible debug');
    release_log($log);
    check(!empty(easee_log_metadata($next, $lbpplugindir)['LOGEND']), 'Switching to Debug must close the daily log');
    check(strpos(file_get_contents($hourly), 'visible debug') !== false, 'Debug must write into the hourly file');
    $hour_key = easee_log_metadata($hourly, $lbpplugindir)['LOGKEY'];
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second + 1800);
    release_log($log);
    check(substr_count(file_get_contents($hourly), 'TASK STARTED') === 1, 'Same hour must only start once');
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second + 3600);
    release_log($log);
    check(!empty(easee_log_metadata($hourly, $lbpplugindir)['LOGEND']), 'Next hour must close the previous hourly log');
    check(file_exists($next_hour), 'Next hour must create a new file');
    LBSystem::$level = 6;
    LBSystem::$revision++;
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second + 3700);
    release_log($log);
    check(!empty(easee_log_metadata($next_hour, $lbpplugindir)['LOGEND']), 'Switching to Info must close the hourly log');
    check(empty(easee_log_metadata($next, $lbpplugindir)['LOGEND']), 'Returning to Info must reopen the daily session');
    check(strpos(file_get_contents($next), $before) === 0, 'Returning to Info must preserve daily contents');
    LBSystem::$level = 7;
    LBSystem::$revision++;
    $log = easee_start_log($lbplogdir, $lbpplugindir, $second + 3800);
    release_log($log);
    check(empty(easee_log_metadata($next_hour, $lbpplugindir)['LOGEND']), 'Returning to Debug must reopen the hourly session');
    check(easee_log_metadata($hourly, $lbpplugindir)['LOGKEY'] === $hour_key, 'Closed earlier hourly logs must retain their session');
    LBSystem::$level = 6;
    LBSystem::$revision++;

    $workers = array();
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' '
        . escapeshellarg($library) . ' --worker ' . escapeshellarg($root) . ' ' . $first;
    for ($i = 0; $i < 4; $i++) {
        $process = proc_open($command, array(), $pipes);
        check(is_resource($process), 'Concurrent worker must start');
        $workers[] = $process;
    }
    foreach ($workers as $process) {
        check(proc_close($process) === 0, 'Concurrent worker must finish successfully');
    }
    $parallel = $root . '/parallel/easeeAPIcalls_2026-10-06.log';
    $contents = file_get_contents($parallel);
    check(substr_count($contents, 'TASK STARTED') === 1, 'Concurrent initialization must start only once');
    check(substr_count($contents, 'concurrent request') === 4, 'Concurrent initialization must not truncate requests');
    $db = new SQLite3(LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat');
    check($db->querySingle("SELECT COUNT(*) FROM logs WHERE FILENAME = '$parallel'") === 1, 'Concurrent initialization must register only one session');
    $db->close();

    LBSystem::$level = 0;
    LBSystem::$revision++;
    $disabled = strtotime('2026-10-08 12:00:00');
    $log = easee_start_log($lbplogdir, $lbpplugindir, $disabled);
    LOGINF('disabled output');
    release_log($log);
    $log = easee_start_log($lbplogdir, $lbpplugindir, $disabled);
    release_log($log);
    check(!file_exists($lbplogdir . '/easeeAPIcalls_2026-10-08.log'), 'Native Off must suppress file output');
    $db = new SQLite3(LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat');
    check($db->querySingle("SELECT COUNT(*) FROM logs WHERE FILENAME LIKE '%2026-10-08.log'") === 0, 'Off must not register sessions without files');
    $db->close();

    $failed = false;
    try {
        easee_start_log($root . '/missing', $lbpplugindir, $second);
    } catch (RuntimeException $error) {
        $failed = true;
    }
    check($failed, 'Lock failures must surface explicitly');
    echo "Easee daily/hourly logging checks passed.\n";
} finally {
    if (isset($log)) {
        release_log($log);
    }
    remove_test_files($root);
}

<?php

require_once "loxberry_log.php";

function easee_log_metadata($filename, $package)
{
    $dbfile = LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat';
    if (!file_exists($dbfile)) {
        return null;
    }
    $db = new SQLite3($dbfile, SQLITE3_OPEN_READONLY);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    try {
        $query = $db->prepare(
            "SELECT LOGKEY, LOGEND,
                (SELECT value FROM logs_attr WHERE keyref = LOGKEY AND attrib = 'STATUS') AS STATUS,
                (SELECT value FROM logs_attr WHERE keyref = LOGKEY AND attrib = 'ATTENTIONMESSAGES') AS ATTENTIONMESSAGES
             FROM logs WHERE FILENAME = :filename AND PACKAGE = :package
             ORDER BY LOGKEY DESC LIMIT 1"
        );
        $query->bindValue(':filename', $filename, SQLITE3_TEXT);
        $query->bindValue(':package', $package, SQLITE3_TEXT);
        $result = $query->execute();
        $metadata = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $query->close();
        return $metadata === false ? null : $metadata;
    } finally {
        $db->close();
    }
}

function easee_log_object($filename, $package, $metadata)
{
    $log = LBLog::newLog(array(
        'package' => $package,
        'name' => 'Easee API Calls',
        'filename' => $filename,
        'append' => file_exists($filename) || $metadata !== null ? 1 : 0,
        'addtime' => 1
    ));
    if ($metadata !== null) {
        if ($metadata['STATUS'] !== null) {
            $log->STATUS(intval($metadata['STATUS']));
        }
        if (!empty($metadata['ATTENTIONMESSAGES'])) {
            $log->ATTENTIONMESSAGES($metadata['ATTENTIONMESSAGES']);
        }
    }
    if (!$log->dbkey()) {
        $log->LOGSTART('Aufrufe der Easee Cloud API (über easee.php) und Antworten');
    } else {
        $log->logtitle('Aufrufe der Easee Cloud API (über easee.php) und Antworten');
    }
    return $log;
}

function easee_start_log($logdir, $package, $now = null)
{
    $now = $now === null ? time() : $now;
    $lock = fopen(rtrim($logdir, '/') . '/.easee_api_log.lock', 'c+');
    if ($lock === false) {
        throw new RuntimeException('Cannot open Easee log rotation lock');
    }
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock Easee log rotation');
        }
        $level = intval(LBSystem::pluginloglevel($package));
        $period = date($level === 7 ? 'Y-m-d_H' : 'Y-m-d', $now);
        $filename = rtrim($logdir, '/') . '/easeeAPIcalls_' . $period . '.log';
        // Sessions outlive HTTP requests; close them on period or log-level changes.
        $files = glob(rtrim($logdir, '/') . '/easeeAPIcalls_*.log');
        if ($files === false) {
            throw new RuntimeException('Cannot scan Easee logs');
        }
        foreach ($files as $previous) {
            if (!preg_match('/^easeeAPIcalls_(\d{4}-\d{2}-\d{2}(?:_\d{2})?)\.log$/', basename($previous), $match)
                || strcmp($match[1], date('Y-m-d_H', $now)) > 0
                || ($previous === $filename && $level !== 0)
                || !is_file($previous)) {
                continue;
            }
            $metadata = easee_log_metadata($previous, $package);
            if ($metadata === null || empty($metadata['LOGEND'])) {
                $old = easee_log_object($previous, $package, $metadata);
                $old->LOGEND('Log closed on rotation to ' . $period);
                unset($old);
            }
        }

        // Keep in-flight requests using the previous entry point working during deployment.
        function easee_start_daily_log($logdir, $package, $now = null)
        {
            return easee_start_log($logdir, $package, $now);
        }
        if ($level === 0) {
            $log = LBLog::newLog(array(
                'package' => $package,
                'name' => 'Easee API Calls',
                'nofile' => 1
            ));
        } else {
            $metadata = easee_log_metadata($filename, $package);
            $log = easee_log_object($filename, $package, $metadata);
            // Reuse the file when switching back within the same day/hour.
            if ($metadata !== null && !empty($metadata['LOGEND'])) {
                $db = new SQLite3(LBHOMEDIR . '/log/system_tmpfs/logs_sqlite.dat');
                $db->enableExceptions(true);
                $db->busyTimeout(5000);
                try {
                    $query = $db->prepare('UPDATE logs SET LOGEND = NULL, LASTMODIFIED = :modified WHERE LOGKEY = :key');
                    $query->bindValue(':modified', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                    $query->bindValue(':key', $metadata['LOGKEY'], SQLITE3_INTEGER);
                    $query->execute()->finalize();
                    $query->close();
                } finally {
                    $db->close();
                }
            }
        }
        $GLOBALS['stdLog'] = $log;
        return $log;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

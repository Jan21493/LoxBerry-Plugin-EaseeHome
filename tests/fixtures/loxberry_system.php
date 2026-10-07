<?php

class LBSystem
{
    public static $level = 6;
    public static $revision = 1;

    public static function pluginloglevel($package)
    {
        return self::$level;
    }

    public static function plugindb_changed_time()
    {
        return self::$revision;
    }

    public static function plugindata($package)
    {
        return array('PLUGINDB_TITLE' => 'Easee Home', 'PLUGINDB_VERSION' => 'test');
    }

    public static function lbversion()
    {
        return 'test';
    }
}

function currtime($format = null)
{
    return date('Y-m-d H:i:s');
}

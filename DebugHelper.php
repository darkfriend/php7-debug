<?php

namespace darkfriend\helpers;

/**
 * Class DebugHelper
 * @package darkfriend\helpers
 * @author darkfriend <hi@darkfriend.ru>
 * @version 1.0.2
 */
class DebugHelper
{
    public static $mainKey = 'ADMIN';
    public static $traceMode;
    public static $fileSizeRotate = 30;

    /** @var string */
    protected static $pathLog = '/';
    /** @var string */
    protected static $hashName;
    /** @var string */
    protected static $root;

    const TRACE_MODE_REPLACE = 1;
    const TRACE_MODE_APPEND = 2;
    const TRACE_MODE_SESSION = 3;

    /**
     * Output formatted <pre>
     * @param array $o
     * @param bool $die stop application after output
     * @param bool $show all output or output only $_COOKIE['ADMIN']
     * @return void
     */
    public static function print_pre($o, $die = false, $show = true)
    {
        $bt = \debug_backtrace();
        $bt = $bt[0];
        $dRoot = $_SERVER["DOCUMENT_ROOT"];
        $dRoot = \str_replace("/", "\\", $dRoot);
        $bt["file"] = \str_replace($dRoot, "", $bt["file"]);
        $dRoot = \str_replace("\\", "/", $dRoot);
        $bt["file"] = \str_replace($dRoot, "", $bt["file"]);
        if (!$show && !empty($_COOKIE[self::$mainKey])) $show = true;
        if (!$show) return;
        ?>
        <div style='font-size:9pt; color:#000; background:#fff; border:1px dashed #000;'>
            <div style='padding:3px 5px; background:#99CCFF; font-weight:bold;'>File: <?= $bt["file"] ?>
                [<?= $bt["line"] ?>]
            </div>
            <pre style='padding:10px;'><?= \htmlentities(\print_r($o, true)) ?></pre>
        </div>
        <?php
        if ($die) die();
    }

    /**
     * Call function for only developers
     * @param callable $func
     * @param mixed $params
     * @return void
     */
    public static function call(callable $func, $params = [])
    {
        $show = isset($_COOKIE[self::$mainKey]);
        if (!$show) $show = isset($_GET[self::$mainKey]);
        if ($show) $func($params);
    }

    /**
     * Save trace message
     * @param mixed $message
     * @param string $category
     * @return void
     */
    public static function trace($message, $category = 'common')
    {
        $bt = \debug_backtrace();
        $bt = $bt[0];
        $dRoot = $_SERVER["DOCUMENT_ROOT"];
        $dRoot = \str_replace("/", "\\", $dRoot);
        $bt["file"] = \str_replace($dRoot, "", $bt["file"]);
        $dRoot = \str_replace("\\", "/", $dRoot);
        $bt["file"] = \str_replace($dRoot, "", $bt["file"]);

        switch (self::$traceMode) {
            case self::TRACE_MODE_REPLACE:
                $flag = \FILE_BINARY | \LOCK_EX;
                break;
            default:
                $flag = \FILE_APPEND | \LOCK_EX;
        }

//        $file = $_SERVER['DOCUMENT_ROOT'] . self::$pathLog . self::$hashName . 'trace.log';
        $file = self::getFile();

        if (!\is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0777, true);
        }

        LogRotate::process($file, static::$fileSizeRotate);

        \file_put_contents(
            $file,
            'TRACE: ' . $category . "\n"
            . 'DATE: ' . \date('Y-m-d H:i:s') . "\n"
            . "FILE: {$bt['file']} [{$bt['line']}]" . "\n"
            . "\n" . "\n"
            . \print_r($message, true)
            . "\n------TRACE_END------.\n\n\n\n",
            $flag
        );
    }

    /**
     * Return path to file
     * @return string
     * @since 1.0.2
     */
    public static function getFile()
    {
        if(strpos(self::$pathLog, self::getRoot()) !== false) {
            $file = self::$pathLog;
        } else {
            $file = self::getRoot() . self::$pathLog;
        }

        if(strpos(self::$pathLog,'.log')===false) {
            $file = rtrim($file);
            $file .= '/'. self::$hashName . 'trace.log';
        }

        return $file;
    }

    /**
     * @param null|string $sessionHash
     * @param null|integer $mode
     * @param string $pathLog
     */
    public static function traceInit($sessionHash = null, $mode = null, $pathLog = '/')
    {
        self::setHashSession($sessionHash);
        self::setTraceMode($mode);
        self::$pathLog = $pathLog;
    }

    /**
     * Generated hash session for trace file
     * @return string
     */
    protected static function generateHashSession()
    {
        if (!self::$hashName) {
            self::$hashName = \time() . '-';
        }
        return self::$hashName;
    }

    /**
     * Set hash for trace file
     * @param string $hash
     * @return void
     */
    public static function setHashSession($hash = null)
    {
        if (!$hash) self::generateHashSession();
        self::$hashName = $hash . '-';
    }

    /**
     * @param null|string $mode mode file trace TRACE_MODE_REPLACE/TRACE_MODE_APPEND/TRACE_MODE_SESSION
     * @return void
     */
    public static function setTraceMode($mode = null)
    {
        if (!self::$traceMode) {
            if (!$mode) $mode = self::TRACE_MODE_APPEND;
            self::$traceMode = $mode;
            if ($mode == self::TRACE_MODE_SESSION) {
                self::generateHashSession();
            }
        }
    }

    /**
     * Set root directory
     * @param string $str
     * @since 1.0.2
     */
    public static function setRoot($str)
    {
        self::$root = $str;
    }

    /**
     * Get root directory
     * @return string
     * @since 1.0.2
     */
    public static function getRoot()
    {
        return self::$root ? self::$root : $_SERVER['DOCUMENT_ROOT'];
    }
}
<?php

namespace darkfriend\helpers;

/**
 * Class DebugHelper
 * @package darkfriend\helpers
 * @author darkfriend <hi@darkfriend.ru>
 * @version 1.0.4
 */
class DebugHelper
{
    /**
     * @var string
     * @deprecated
     */
    public static $mainKey = 'ADMIN';
    public static $traceMode;
    /** @var int value in Mb */
    public static $fileSizeRotate = 30;

    /** @var string */
    protected static $pathLog = '/';
    /** @var string */
    protected static $hashName;

    /**
     * @var array = [
     *     'htmlentities' => true,
     *     'root' => $_SERVER['DOCUMENT_ROOT'],
     *     'cookieName' => 'ADMIN,
     * ]
     */
    protected static $config;

    const TRACE_MODE_REPLACE = 1;
    const TRACE_MODE_APPEND = 2;
    const TRACE_MODE_SESSION = 3;

    /**
     * Output formatted <pre>
     * @param array $o
     * @param bool $die stop application after output
     * @param bool $show all output or output only $_COOKIE['ADMIN']
     * @param array $params
     * @return void
     */
    public static function print_pre($o, $die = false, $show = true, $params = [])
    {
        if(!isset($params['btIndex'])) {
            $params['btIndex'] = 0;
        }

        $bt = \debug_backtrace();
        $bt = $bt[$params['btIndex']];
        $dRoot = $_SERVER['DOCUMENT_ROOT'] ?? self::getRoot();
        $dRoot = \str_replace("/", "\\", $dRoot);
        $bt['file'] = \str_replace($dRoot, "", $bt['file']);
        $dRoot = \str_replace("\\", "/", $dRoot);
        $bt['file'] = \str_replace($dRoot, "", $bt['file']);

        if(isset($params['pIndex'])) {
            $bt['pIndex'] = $params['pIndex'];
        } else {
            $bt['pIndex'] = 0;
        }

        if(!self::isCli()) {
            if (
                !$show
                && !empty($_COOKIE[self::getConfig('cookieName',self::$mainKey)])
            ) {
                $show = true;
            }
            if (!$show) return;
            echo self::getOutputPreWeb($o, $bt);
        } else {
            if (!$show) return;
            echo self::getOutputPreCli($o, $bt);
        }

        if ($die) die();
    }

    /**
     * Output formatted <pre>.
     * Wrap for print_pre()
     * @param ...$o
     * @return self
     * @see print_pre()
     * @since 1.0.3
     */
    public static function pre(...$o)
    {
        foreach ($o as $k=>$item) {
            self::print_pre(
                $item,
                false,
                true,
                [
                    'btIndex' => 1,
                    'pIndex' => $k
                ]
            );
        }
        return self::class;
    }

    /**
     * Set configs
     * @param array $config
     * @return self
     * @since 1.0.4
     */
    public static function conf($config=[])
    {
        self::$config = $config;
        return self::class;
    }

    /**
     * Terminates execution of the script
     * @param bool $die
     * @since 1.0.4
     */
    public static function d($die=true)
    {
        if($die) {
            die();
        }
    }

    /**
     * Alias ```self::d()```
     * @param bool $die
     * @see d()
     * @since 1.0.4
     */
    public static function stop($die=true)
    {
        self::d($die);
    }

    /**
     * Call function for only developers
     * @param callable $func
     * @param mixed $params
     * @return void
     */
    public static function call(callable $func, $params = [])
    {
        $cookieName = self::getConfig('cookieName', self::$mainKey);
        $show = isset($_COOKIE[$cookieName]);
        if (!$show) $show = isset($_GET[$cookieName]);
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
        self::setConfig('root', $str);
    }

    /**
     * Get root directory
     * @return string
     * @since 1.0.2
     */
    public static function getRoot()
    {
        return self::getConfig('root', $_SERVER['DOCUMENT_ROOT']);
    }

    /**
     * Get config param
     * @param string $param
     * @param mixed $default
     * @return mixed
     * @since 1.0.4
     */
    public static function getConfig($param, $default = '')
    {
        if(isset(self::$config[$param])) {
            return self::$config[$param];
        } else {
            return $default;
        }
    }

    /**
     * Set value to config param
     * @param string $param
     * @param mixed $value
     * @return self
     * @since 1.0.4
     */
    public static function setConfig($param, $value = '')
    {
        self::$config[$param] = $value;
        return self::class;
    }

    /**
     * Get output string print_pre for web
     * @param mixed $o
     * @param array $bt
     * @return string
     * @since 1.0.3
     */
    protected static function getOutputPreWeb($o, $bt)
    {
        if(!isset($bt['pIndex'])) {
            $bt['pIndex'] = 'null';
        }
        return "
        <div style='font-size:9pt; color:#000; background:#fff; border:1px dashed #000;'>
            <div style='padding:3px 5px; background:#99CCFF; font-weight:bold;'>File: {$bt['file']}
                [{$bt['line']}:{$bt['pIndex']}]
            </div>
            <pre style='padding:10px;'>".self::getOutputPre($o)."</pre>
        </div>
        ";
    }

    /**
     * Get output string print_pre for cli
     * @param mixed $o
     * @param array $bt
     * @return string
     * @since 1.0.3
     */
    protected static function getOutputPreCli($o, $bt)
    {
        if(!isset($bt['pIndex'])) {
            $bt['pIndex'] = 0;
        }
        return
            PHP_EOL."File: {$bt['file']} [{$bt['line']}:{$bt['pIndex']}]:"
            .PHP_EOL
            .self::getOutputPre($o)
            .PHP_EOL;
    }

    /**
     * Get output string
     * @param mixed $o
     * @return string
     * @since 1.0.3
     */
    protected static function getOutputPre($o)
    {
        if(self::isCli()) {
            return \var_export($o, true);
        } else {
            $o = \print_r($o, true);
            if(self::getConfig('htmlentities', true)) {
                $o = \htmlentities($o);
            }
            return $o;
        }
    }

    /**
     * Checked of cli
     * @return bool
     * @since 1.0.3
     */
    protected static function isCli(): bool
    {
        return self::getMode()=='cli';
    }

    /**
     * Get mode script
     * @return string
     * @since 1.0.3
     */
    protected static function getMode()
    {
        return \php_sapi_name();
    }
}
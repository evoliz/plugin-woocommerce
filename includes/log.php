<?php

const EVOLIZ_LOG_DEBUG = "DEBUG";
const EVOLIZ_LOG_INFO = "INFO";
const EVOLIZ_LOG_WARNING = "WARNING";
const EVOLIZ_LOG_ERROR = "ERROR";

function writeLog(string $message, string $code = null, string $level = EVOLIZ_LOG_INFO)
{
    $date = new DateTime();
    $date = $date->format("d/m/Y H:i:s");
    $version = getCurrentVersion();

    $message = str_replace('.<br>', ', ', $message);
    $message = strip_tags($message);

    if ($code !== null && $level === EVOLIZ_LOG_ERROR) {
        $level = $level . " ($code)";
    }

    error_log("[ $date ] [ v$version ] $level : $message\n", 3, plugin_dir_path(__FILE__) . '../evoliz.log');
}

function clearLog()
{
    $date = new DateTime();
    $minDate = $date->modify('-30 days');

    $file = plugin_dir_path(__FILE__) . '../evoliz.log';

    $lines = file($file, FILE_IGNORE_NEW_LINES);

    if ($lines) {
        foreach($lines as $key => $line) {
            if ($line === '') {
                unset($lines[$key]);
            } else {
                preg_match('/[\[][^\[\]]*[\]]/', $line, $regexResults);
                $logDate = substr($regexResults[0], 2, -2);

                if (DateTime::createFromFormat('d/m/Y H:i:s', $logDate) < $minDate) {
                    unset($lines[$key]);
                } else {
                    break;
                }
            }
        }

        $logs = implode(PHP_EOL, $lines);
        file_put_contents($file, $logs);
    }
}

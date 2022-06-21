<?php

const EVOLIZ_LOG_INFO = "INFO";
const EVOLIZ_LOG_WARNING = "WARNING";
const EVOLIZ_LOG_ERROR = "ERROR";

function writeLog(string $message, string $code = null, string $level = EVOLIZ_LOG_INFO)
{
    $date = new DateTime();
    $date = $date->format("d/m/Y H:i:s");

    $message = str_replace('.<br>', ', ', $message);
    $message = strip_tags($message);

    if ($code !== null && $level === EVOLIZ_LOG_ERROR) {
        $level = $level . " ($code)";
    }

    error_log("[ $date ] $level : $message\n", 3, plugin_dir_path(__FILE__) . '../evoliz.log');
}

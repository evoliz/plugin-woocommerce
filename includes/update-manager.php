<?php

function getPluginVersion()
{
    return file_get_contents(plugin_dir_path(dirname(__FILE__)) . "./version.txt");
}

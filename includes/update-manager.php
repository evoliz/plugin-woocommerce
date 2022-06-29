<?php

function getPluginVersion(): string
{
    return file_get_contents(plugin_dir_path(dirname(__FILE__)) . "./version.txt");
}

function getLatestRelease()
{
    $url = 'https://api.github.com/repos/evoliz/plugin-woocommerce/releases/latest';

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

    curl_setopt($curl, CURLOPT_USERPWD, 'DyRize' . ':' . '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');

    return json_decode(curl_exec($curl));
}

function getLatestReleaseVersion()
{
    return substr(getLatestRelease()->tag_name, 1);
}

function checkUpdate()
{
    getPluginVersion() === getLatestReleaseVersion() ?: sendUpdateNotification();
}

function sendUpdateNotification()
{
    $latestVersion = getLatestReleaseVersion();
    $downloadUrl = getLatestRelease()->zipball_url;

    writeLog("[ Update Manager ] A new version of the Evoliz module is available ($latestVersion).\n");
    echo "<div class='notice notice-warning is-dismissible'>
        <p><b>Une nouvelle version du module Evoliz est disponible ($latestVersion)
        <br>
        <a href='$downloadUrl'>Veuillez la télécharger en cliquant ici</a></b></p>
    </div>";
}

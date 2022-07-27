<?php

function manageVersion($callToGithub = false)
{
    $versionManager = json_decode(file_get_contents(plugin_dir_path(dirname(__FILE__)) . "./version.json"));

    if ($callToGithub) {
        $url = 'https://api.github.com/repos/evoliz/plugin-woocommerce/releases/latest';

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

//        curl_setopt($curl, CURLOPT_USERPWD, '' . ':' . '');

        $result = json_decode(curl_exec($curl));

        if ($result->tag_name !== null) {
            $versionManager->latestRelease->version = substr($result->tag_name, 1);
            $versionManager->latestRelease->downloadLink = $result->zipball_url;
            file_put_contents(plugin_dir_path(dirname(__FILE__)) . "./version.json", json_encode($versionManager));
        }
    }

    return $versionManager;
}

function checkUpdate()
{
    $versionManager = manageVersion(true);
    $actualVersion = $versionManager->version;
    $latestRelease = $versionManager->latestRelease;

    $actualVersion === $latestRelease->version ?: sendUpdateNotification($latestRelease);
}

function sendUpdateNotification($latestRelease)
{
    writeLog("[ Update Manager ] A new version of the Evoliz module is available ($latestRelease->version).\n");
    echo "<div class='notice notice-warning is-dismissible'>
        <p><b>Une nouvelle version du module Evoliz est disponible ($latestRelease->version)
        <br>
        <a href='$latestRelease->downloadLink'>Veuillez la télécharger en cliquant ici</a></b></p>
    </div>";
}

<?php

abstract class EvolizSettings
{
    public static function init() {
        add_action('admin_menu',  __CLASS__ . '::addAdminMenu');
        add_action('admin_init',  __CLASS__ . '::evolizSettingsInit');
    }

    public static function addAdminMenu() {
        add_menu_page(
            'Evoliz',
            'Evoliz',
            'manage_options',
            'evoliz_settings',
            __CLASS__ . '::evolizOptionsPage',
            plugin_dir_url(__FILE__) . "Assets/img/logo.png",
            54
        );
    }

    public static function evolizOptionsPage()
    {
        ?>
        <div class="wrap">
            <h1>Configuration du module Evoliz</h1>

            <?php
            if (isset($_GET['settings-updated'])) {
                add_settings_error('wporg_messages', 'wporg_message', 'Paramètres sauvegardés', 'updated');
            }
            settings_errors('wporg_messages');
            $activeTab = "configuration";
            if (isset($_GET["tab"])) {
                $activeTab = $_GET["tab"];
            }

            checkUpdate()
            ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=evoliz_settings&tab=configuration" class="nav-tab
                <?php
                if ($activeTab == 'configuration') {
                    echo ' nav-tab-active';
                }
                ?>">
                    Configuration
                </a>

                <a href="?page=evoliz_settings&tab=utils" class="nav-tab
                <?php
                if ($activeTab == 'utils') {
                    echo ' nav-tab-active';
                }
                ?>">
                    Utilitaires
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields($activeTab . "_section");
                do_settings_sections("evoliz_settings");

                if ($activeTab == "configuration")
                    submit_button("Enregistrer les changements", "primary", "evoliz_submit_config");
                ?>
            </form>
            <hr>
            <?php echo "Version du module Evoliz : " . getPluginVersion() ?>
        </div>
        <?php
    }

    public static function evolizSettingsInit()
    {
        wp_enqueue_style('Evoliz', plugin_dir_url(__FILE__) . 'Assets/css/evoliz.css');

        if (!isset($_GET["tab"]))
            $tab = "configuration";
        else {
            $tab = $_GET["tab"];
        }

        if ($tab === "configuration") {
            add_settings_section("configuration_section", "Paramètres du module Evoliz", __CLASS__ . '::displayConfigurationHeader', "evoliz_settings");
            add_settings_field("wc_evz_company_id", "Company ID", __CLASS__ . '::displayCompanyID', "evoliz_settings", "configuration_section");
            add_settings_field("wc_evz_public_key", "Public Key", __CLASS__ . '::displayPublicKey', "evoliz_settings", "configuration_section");
            add_settings_field("wc_evz_secret_key", "Secret Key", __CLASS__ . '::displaySecretKey', "evoliz_settings", "configuration_section");
            add_settings_section("eu_vat_section", "Traitement de la TVA Intracom", __CLASS__ . '::displayEuVatHeader', "evoliz_settings");
            add_settings_field("wc_evz_enable_vat_number", "Traitement des numéros de TVA Intracom", __CLASS__ . '::displayEnableVatNumber', "evoliz_settings", "eu_vat_section");
            add_settings_field("wc_evz_eu_vat_number", "Champ du numéro de TVA Intracom", __CLASS__ . '::displayVatNumberFields', "evoliz_settings", "eu_vat_section");

            register_setting("configuration_section", "wc_evz_company_id");
            register_setting("configuration_section", "wc_evz_public_key");
            register_setting("configuration_section", "wc_evz_secret_key");
            register_setting("configuration_section", "wc_evz_enable_vat_number");
            register_setting("configuration_section", "wc_evz_eu_vat_number");

        } elseif ($tab === 'utils') {
            add_settings_section("infos_section", "Informations utiles", __CLASS__ . '::displayInfos', "evoliz_settings");
            add_settings_section("logs_section", "Télécharger le fichier de log", __CLASS__ . '::displayLogs', "evoliz_settings");
            add_settings_section("contact_section", "Contacter Evoliz", __CLASS__ . '::displayContact', "evoliz_settings");
        }
    }

    public static function displayConfigurationHeader()
    {
        echo "<b>Configuration du module :</b>
        <br/>- Le <b>Company ID</b>, la <b>Public Key</b> ainsi que la <b>Secret Key</b> se trouvent sur <a href='#' target='_blank'>votre page profil Evoliz</a>.
        <br/>- <b>Synchronisation des produits</b> : vos produits seront mis à jour dans Evoliz (nom, prix, taxe, description, quantité, ...) dès lors qu'ils seront commandés.
        <br/>- <b>Synchronisation des nouveaux clients</b> : les clients qui passent une commande seront créés dans Evoliz et identifiés grâce à leur nom de société (professionnel) ou leur nom.
        <br/>- <b>Synchronisation des nouveaux contacts clients</b> : les contacts clients associés aux clients seront créés dans Evoliz et identifiés grâce à leur adresse email.
        <br/>- <b>Synchronisation des ventes</b> : les commandes passées à l'état \"En cours\" généreront des commandes, et les commandes passées à l'état \"Terminée\" généreront la facture et le paiement correspondant dans Evoliz.";
    }

    public static function displayCompanyID()
    {
        ?>
        <input style="width: 385px;" type="number" name="wc_evz_company_id" id="wc_evz_company_id"
               value="<?php echo esc_attr(get_option('wc_evz_company_id')); ?>"/>
        <?php
    }

    public static function displayPublicKey()
    {
        ?>
        <input style="width: 385px;" type="text" name="wc_evz_public_key" id="wc_evz_public_key"
               value="<?php echo esc_attr(get_option('wc_evz_public_key')); ?>"/>
        <?php
    }

    public static function displaySecretKey()
    {
        ?>
        <input style="width: 385px;" type="text" name="wc_evz_secret_key" id="wc_evz_secret_key"
               value="<?php echo esc_attr(get_option('wc_evz_secret_key')); ?>"/>
        <?php
    }

    public static function displayEuVatHeader()
    {
        echo "Si vos clients sont soumis à la TVA Intracom veuillez activer le tritement de celle-ci ci dessous.
        <br>Par ailleurs, vous avez peut-être déjà ajouté le champ 'Numéro de TVA Intracom' à l'aide d'un plugin.
        <br>Si c'est le cas, veuillez fournir le 'meta name' (et non le label) du champ ci-dessous. Sinon, laissez le champ vide.";
    }

    public static function displayEnableVatNumber()
    {
        echo '<label class="switch" for="wc_evz_enable_vat_number">
            <input name="wc_evz_enable_vat_number" id="wc_evz_enable_vat_number" type="checkbox"' .
            (esc_attr(get_option('wc_evz_enable_vat_number')) == "on" ? ' checked="checked"' : '') . '/>' .
            '<span class="slider round"></span>
        </label>';
    }

    public static function displayVatNumberFields()
    {
        ?>
        <input style="width: 385px;" type="text" name="wc_evz_eu_vat_number" id="wc_evz_eu_vat_number"
               value="<?php echo esc_attr(get_option('wc_evz_eu_vat_number')); ?>"/>
        <?php
    }

    public static function displayInfos()
    {
        echo "<p>
            Version du module Evoliz : " . getPluginVersion()
                . "<br><br>
            Nous travaillons en permanence sur le module afin de l'améliorer. Veillez à bien mettre à jour votre module à chaque fois que l'option vous est proposée.
        </p>";
    }

    public static function displayLogs()
    {
        echo "<p>
            <a href='" . plugin_dir_url(__FILE__) . "includes/download-log.php'>" . "Cliquez ici pour télécharger le fichier evoliz.log</a>
        </p>";
    }

    public static function displayContact()
    {
        echo "<p>
            En cas de problème, n'hésitez pas à <a href='https://www.evoliz.com\' target='_blank'>nous contacter directement sur Evoliz.</a>
        </p>";
    }
}

<?php

use Evoliz\Client\Config;

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
            $activeTab = "credentials";
            if (isset($_GET["tab"])) {
                $activeTab = $_GET["tab"];
            }

            checkUpdate()
            ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=evoliz_settings&tab=credentials" class="nav-tab
                <?php
                if ($activeTab == 'credentials') {
                    echo ' nav-tab-active';
                }
                ?>">
                    Connecter WooCommerce & Evoliz
                </a>

                <a href="?page=evoliz_settings&tab=utils" class="nav-tab
                <?php
                if ($activeTab == 'utils') {
                    echo ' nav-tab-active';
                }
                ?>">
                    Informations utiles
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields("evoliz_settings");
                do_settings_sections("evoliz_settings");

                if ($activeTab == "credentials") {
                    submit_button("Enregistrer les modifications", "primary", "evoliz_submit_config");
                }
                ?>
            </form>
            <hr>
            <?php echo "Version du module Evoliz : " . manageVersion()->version ?>
        </div>
        <?php
    }

    public static function evolizSettingsInit()
    {
        if (!isset($_GET["tab"]))
            $tab = "credentials";
        else {
            $tab = $_GET["tab"];
        }

        if ($tab === "credentials") {
            add_settings_section("description_section", "Description", __CLASS__ . '::displayDescriptionHeader', "evoliz_settings");

            add_settings_section("credentials_section", "Identifiants et connexion", __CLASS__ . '::displayCredentialsHeader', "evoliz_settings");
            add_settings_field("wc_evz_public_key", "Clé publique", __CLASS__ . '::displayPublicKey', "evoliz_settings", "credentials_section");
            add_settings_field("wc_evz_secret_key", "Clé secrète", __CLASS__ . '::displaySecretKey', "evoliz_settings", "credentials_section");
            add_settings_field("wc_evz_company_id", "Numéro de client", __CLASS__ . '::displayCompanyID', "evoliz_settings", "credentials_section");

            register_setting("evoliz_settings", "wc_evz_public_key");
            register_setting("evoliz_settings", "wc_evz_secret_key");
            register_setting("evoliz_settings", "wc_evz_company_id", [
                'type' => 'int',
                'sanitize_callback' => __CLASS__ . '::validateCompanyId'
            ]);

            add_settings_section("eu_vat_section", "Options de traitement de la TVA Intracommunautaire", __CLASS__ . '::displayEuVatHeader', "evoliz_settings");
            add_settings_field("wc_evz_enable_vat_number", "Traitement de la TVA intracommunautaire", __CLASS__ . '::displayEnableVatNumber', "evoliz_settings", "eu_vat_section");
            add_settings_field("wc_evz_eu_vat_number", "Champ du numéro de TVA intracommunautaire", __CLASS__ . '::displayVatNumberFields', "evoliz_settings", "eu_vat_section");

            register_setting("evoliz_settings", "wc_evz_enable_vat_number");
            register_setting("evoliz_settings", "wc_evz_eu_vat_number");

        } elseif ($tab === 'utils') {
            add_settings_section("help_section", "Informations utiles", __CLASS__ . '::displayHelp', "evoliz_settings");
            add_settings_section("logs_section", "Fichier de log", __CLASS__ . '::displayLogs', "evoliz_settings");
        }
    }

    public static function validateCompanyId($newCompanyId) {
        $oldCompanyId = get_option('wc_evz_company_id');

        try {
            if (get_option('wc_evz_public_key') === '' || get_option('wc_evz_secret_key') === '') {
                throw new Exception();
            }
            $config = new Config((int) $newCompanyId, get_option('wc_evz_public_key'), get_option('wc_evz_secret_key'));
            $config->authenticate();
        } catch (Exception $e) {
            add_settings_error('wporg_messages', 'wporg_message', 'Les identifiants n\'ont pas pu permettre de se connecter à l\'API Evoliz', 'error');
            return '';
        }

        if (!$config->hasValidCompanyId()) {
            add_settings_error('wporg_messages', 'wporg_message', 'Le numéro de client est invalide', 'error');
            return $oldCompanyId;
        }

        return $newCompanyId;
    }

    public static function displayDescriptionHeader()
    {
        echo "<p>La connexion du module Evoliz enrichit votre expérience WooCommerce. A compter de l’installation du plugin, bénéficiez de :</p>
        <p><b>La synchronisation des nouveaux clients : </b>les clients qui effectuent une commande sont créés dans Evoliz en temps réel. Grâce aux champs [Société/Nom] et/ou [Nom du contact], nous pouvons analyser si le client est existant dans Evoliz, et ce, afin de ne pas créer de doublon.</p>
        <p><b>La synchronisation des nouveaux contacts clients : </b>les contacts clients associés au client sont également créés dans Evoliz. Là aussi, les champs [Email] et [ID du client associé] nous permettent de  détecter les contacts clients existants afin de ne pas créer de doublon.</p>
        <p><b>La synchronisation des opérations de ventes : </b>
        <br/>- toute <b>commande à l’état « en cours »</b> génère la création d’un bon de commande dans Evoliz
        <br/>- toute <b>commande à l’état « terminée »</b> génère la création d’une facture dans Evoliz ainsi que le paiement qu’il lui est associé</p>
        <p><b>Bon à savoir : </b>nous avons fait le choix de NE PAS synchroniser tout l'historique WooCommerce (clients, contacts client…). Seules les nouvelles commandes généreront les différentes synchronisations présentées ci-dessus.</p>";
    }

    public static function displayCredentialsHeader()
    {
        echo "<p>Pour connecter Evoliz à WooCommerce, vous aurez besoin de renseigner les identifiants de votre clé API. Rendez-vous dans votre compte Evoliz pour <a href='https://www.evoliz.com/aide/applications/624-evoliz-comment-creer-cle-api.html' target='_blank'>créer votre clé API et/ou récupérer vos données de connexion.</a></p>
        <p>De plus, vous devrez renseigner le numéro client de votre compte Evoliz. Pour le retrouver, vous devez vous connecter à ce dernier puis cliquer sur le point d'interrogation (coin supérieur droit) et récupérer la première partie du numéro de client affiché.</p>";
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
        echo "<p>Dans le cas où vous facturez des clients de type professionnel (entreprise ou administration publique), il est obligatoire de renseigner le numéro de TVA Intracommunautaire.</p>
        <p>Si tout ou partie de vos clients sont soumis à la TVA Intracommunautaire, l’activation de l’option du \"Traitement de la TVA intracommunautaire\" est nécessaire.</p>
        <p><b>Bon à savoir : </b>si vous utilisez déjà un plugin externe de gestion de la TVA, il vous suffit de renseigner le « Meta Name » dans le champ ci-dessous (Attention, il ne s’agit pas du Label). Si vous n'utilisez aucun plugin, ce champ doit être laissé vide.</p>";
    }

    public static function displayEnableVatNumber()
    {
        echo '<label for="wc_evz_enable_vat_number">
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

    public static function displayHelp()
    {
        echo "<p>Notre support client est disponible :
        <br>- par chat depuis le site <a href='www.evoliz.com' target='_blank'>www.evoliz.com</a>
        <br>- par téléphone au <a href='tel:01 46 72 50 04'>01 46 72 50 04</a>
        <br>- par email à l'adresse <a href='mailto:support+api@evoliz.com'>support+api@evoliz.com</a></p>";
    }

    public static function displayLogs()
    {
        echo "<p><a href='" . plugin_dir_url(__FILE__) . "includes/download-log.php'>" . "Télécharger le fichier evoliz.log</a></p>";
    }
}

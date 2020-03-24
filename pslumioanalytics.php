<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Mikel Martin <mikel@tiralineasestudio.com>
 * @copyright 2007-2020 Lumio Analitycs
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}
require_once _PS_MODULE_DIR_ . '/pslumioanalytics/vendor/autoload.php';

class PsLumioAnalytics extends Module
{
    /** @var array Errors displayed after post processing */
    public $errors = array();
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'pslumioanalytics';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Lumio';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->module_key = '8c5182d8ea6f712bff38d4471f22f322';

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Lumio analytics');
        $this->description = $this->l(
            'Add Tracking script for Lumio analytics and Informative panel Lumio analytics in detail.'
        );
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('PSLUMIOANALYTICS_KEY', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayFooter');
    }

    public function uninstall()
    {
        $this->registerLumioIntegration(false);
        Configuration::deleteByName('PSLUMIOANALYTICS_KEY');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPs-lumio-analyticsModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $key = Configuration::get('PSLUMIOANALYTICS_KEY');
        if ($this->isValidKey($key)) {
            return $this->context->smarty->fetch($this->local_path.'views/templates/admin/lumio_panel.tpl');
        }

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        if (isset($this->errors) && count($this->errors)) {
            $output .= $this->displayError(implode('<br />', $this->errors));
        } elseif (((bool)Tools::isSubmit('submitPs-lumio-analyticsModule')) == true) {
            $output .= $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }
        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPs-lumio-analyticsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'msg' => $this->errors,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'name' => 'PSLUMIOANALYTICS_KEY',
                        'label' => $this->l('Lumio tracking key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'PSLUMIOANALYTICS_KEY' => Configuration::get('PSLUMIOANALYTICS_KEY'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
        $key = Configuration::get('PSLUMIOANALYTICS_KEY');
        if (self::isValidKey($key)) {
            $this->registerLumioIntegration(true);
        } else {
            $this->errors[] = $this->trans('The integration key is invalid', array(), 'Admin.Notifications.Error');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    public function hookDisplayFooter()
    {
        $key = Configuration::get('PSLUMIOANALYTICS_KEY');
        if (self::isValidKey($key)) {
            ?>
           <script
            type="text/javascript"
            async key="<?php echo $key; ?>"
                src="https://app.lumio-analytics.com/widgets/lumio-analytics.js">
           </script>
            <?php
        }
    }

    private static function isValidKey($key)
    {
        return preg_match('/^\w{40}$/', $key);
    }

    /**
     * Send registration to the API
     *
     * @param  boolean $is_active Activate or deactivate.
     * @return void
     */
    protected function registerLumioIntegration($is_active = true)
    {
        $client      = new \Lumio\IntegrationAPI\Client();
        $key = Configuration::get('PSLUMIOANALYTICS_KEY');
        $integration = new \Lumio\IntegrationAPI\Model\Integration(
            array(
                'key'              => $key,
                'url'              => _PS_BASE_URL_.__PS_BASE_URI__,
                'platform'         => 'PrestaShop',
                'platform_version' => _PS_VERSION_,
                'plugin'           => $this->name,
                'plugin_version'   => $this->version,
                'status'           => $is_active,
            )
        );

        try {
            $client->registerIntegration($integration);
        } catch (Exception $e) {
            echo 'Exception when calling AdminsApi->getAll: ', $e->getMessage(), PHP_EOL;
        }
    }
}

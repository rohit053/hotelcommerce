<?php
/**
* 2010-2016 Webkul.
*
* NOTICE OF LICENSE
*
* All right is reserved,
* Please go through this link for complete license : https://store.webkul.com/license.html
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please refer to https://store.webkul.com/customisation-guidelines/ for more information.
*
*  @author    Webkul IN <support@webkul.com>
*  @copyright 2010-2016 Webkul IN
*  @license   https://store.webkul.com/license.html
*/

class AdminZipCodeWiseShippingController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'zipcode_shipping_impact';
        $this->className = 'ZipCodeShippingImpact';
        $this->context = Context::getContext();
        $this->_join .= 'LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (cl.`id_country` = a.`id_country` AND cl.`id_lang` = '.Configuration::get('PS_LANG_DEFAULT').')';
        $this->_select = 'cl.`name` as name';
        $this->identifier = 'id';
        parent::__construct();

        $options_impact_behaviour = array(
            array(
                'id_option' => 'min',
                'name' => $this->l('Minimum Impact Price'),
            ),
            array(
                'id_option' => 'max',
                'name' => $this->l('Maximum Impact Price'),
            ),
            array(
                'id_option' => 'add_all',
                'name' => $this->l('Combine Impact Prices'),
            ),
        );

        $this->fields_options = array(
            'impact_behaviour' => array(
                'title' => $this->l('Impact Price Behaviour'),
                'icon' => 'icon-cogs',
                'fields' => array(
                    'ZIP_IMPACT_BEHAVE' => array(
                        'desc' => $this->l('How will impacts work in case of more than one impact price are applying on one zipcode.'),
                        'type' => 'select',
                        'identifier' => 'id_option',
                        'list' => $options_impact_behaviour,
                        'title' => $this->l('Impact Behaviour'),
                        'type' => 'select',
                    ),
                    'ZIPCODE_ADD_TAX' => array(
                        'title' => $this->l('Add Tax'),
                        'desc' => $this->l('Add taxes(which tax is applied on the shipping method) on the impact price.'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'type' => 'bool',
                    ),
                ),
                'submit' => array('name' => 'submitZipConfiguration', 'title' => $this->l('Save')),
            ),
            'zipcode_csv_import' => array(
                'title' => $this->l('Zipcodes CSV Import'),
                'description' => $this->l('Upload a csv file of all your zipcodes. Please download by clicking link "Download Sample CSV" (On top right of the page) and view the correct format of the csv. Please ensure your csv must follow the sample csv format.'),
                'icon' => 'icon-upload',
                'fields' => array(
                    'ZIP_CSV_FILE' => array(
                        'title' => $this->l('Zipcode CSV File'),
                        'type' => 'file',
                        'hint' => $this->l('Upload csv file of zipcodes here.'),
                        'name' => 'zip_csv',
                        'url' => _PS_IMG_,
                    ),
                ),
                'submit' => array('name' => 'submitCsvUpload', 'title' => $this->l('Upload Csv')),
            ),
        );
        $this->bulk_actions = array('delete' => array('text' => $this->l('Delete selected'),
            'icon' => 'icon-trash',
            'confirm' => $this->l('Delete selected items?'), ));
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminHome'));
        }
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['new_zipcode_impact'] = array(
            'href' => self::$currentIndex.'&addzipcode_shipping_impact&token='.$this->token,
            'desc' => $this->l('Add new impact', null, null, false),
            'icon' => 'process-icon-new',
        );
        $csvLink = _MODULE_DIR_.'zipcodewiseshipping/views/demo_csv/demo_zipcodes.csv';
        $this->page_header_toolbar_btn['generate_pdf'] = array(
            'href' => $csvLink,
            'desc' => $this->l('Download Sample CSV'),
            'icon' => 'process-icon-save-date',
        );
        parent::initPageHeaderToolbar();
    }

    public function renderList()
    {
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->initZipCodeImpactList();
        $lists = parent::renderList();
        //init and render the second list
        $this->list_skip_actions = array();
        $this->_filter = false;
        $this->initZipCsvUploadedList();
        $this->checkFilterForZipcodeCsvsList();
        $this->toolbar_title = $this->l('Uploaded Csv(s)');
        if (!Tools::isSubmit('submitCsvUpload')) {
            $this->postProcess();
        }
        $lists .= parent::renderList();

        return $lists;
    }

    protected function initZipCodeImpactList()
    {
        $zonesArr = array();
        $zones = Zone::getZones();
        foreach ($zones as $zone) {
            $zonesArr[$zone['id_zone']] = $zone['name'];
        }
        $impactTypes = array(1 => 'increase', 0 => 'decrease');
        $impactBehaviours = array(0 => 'For One Zip', 1 => 'For Zip Range');
        $this->fields_list = array(
            'id' => array(
                'title' => $this->l('ID') ,
            ),
            'id_zone' => array(
                'title' => $this->l('Zone'),
                'align' => 'center',
                'type' => 'select',
                'list' => $zonesArr,
                'filter_key' => 'a!id_zone',
                'callback' => 'getZoneName',
            ),
            'name' => array(
                'title' => $this->l('Country'),
                'align' => 'center',
                'filter_key' => 'cl!name',
            ),
            'is_zip_range' => array(
                'title' => $this->l('Range Behaviour'),
                'align' => 'center',
                'type' => 'select',
                'list' => $impactBehaviours,
                'filter_key' => 'a!is_zip_range',
                'callback' => 'getRangeOrNot',
            ),
            'zip_code_from' => array(
                'title' => $this->l('Min Zip Code'),
                'align' => 'center',
            ),
            'zip_code_to' => array(
                'title' => $this->l('Max Zip Code'),
                'align' => 'center',
                'callback' => 'getMaxZipcode',
            ),
            'impact_type' => array(
                'title' => $this->l('Impact Type'),
                'align' => 'center',
                'callback' => 'getImpactType',
                'type' => 'select',
                'list' => $impactTypes,
                'filter_key' => 'a!impact_type',
            ),
            'impact_price' => array(
                'title' => $this->l('Impact Price'),
                'align' => 'center',
                'callback' => 'getFormatedImpactPrice',
            ),
            'active' => array(
                'title' => $this->l('Status'),
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'orderby' => false,
            ),
        );
    }

    protected function initZipCsvUploadedList()
    {
        $this->table = 'zipcode_csv_info';
        $this->className = 'ZipCsvsInformation';
        $this->_defaultOrderBy = $this->identifier = 'id';
        $this->list_id = 'zipcode_csv_info';
        $this->_select = null;
        $this->_join = null;
        $this->_orderBy = null;
        $this->deleted = false;
        $this->bulk_actions = array();
        $this->actions = array();
        $this->toolbar_btn = array();

        $this->fields_list = array(
            'id' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
            ),
            'original_csv_name' => array(
                'title' => $this->l('Csv Name'),
                'align' => 'center',
            ),
            'total_records' => array(
                'title' => $this->l('Total Records'),
                'align' => 'center',
            ),
            'date_add' => array(
                'title' => $this->l('Uploaded Date'),
                'align' => 'center',
            ),
            'new_csv_name' => array(
                'title' => $this->l('Download'),
                'align' => 'center',
                'callback' => 'getDownloadLink',
                'search' => false,
            ),
        );
    }

    protected function filterToField($key, $filter)
    {
        if ($this->table == 'zipcode_shipping_impact') {
            $this->initZipCodeImpactList();
        } elseif ($this->table == 'zipcode_csv_info') {
            $this->initZipCsvUploadedList();
        }

        return parent::filterToField($key, $filter);
    }

    protected function checkFilterForZipcodeCsvsList()
    {
        // test if a filter is applied for this list
        if (Tools::isSubmit('submitFilter'.$this->table) || $this->context->cookie->{'submitFilter'.$this->table} !== false) {
            $this->filter = true;
        }
        // test if a filter reset request is required for this list
        if (Tools::getIsset('submitReset'.$this->table)) {
            $this->action = 'reset_filters';
        } else {
            $this->action = '';
        }
    }

    /**
     * [getMaxZipcode description] - A callback function for setting max limit of zipcode.
     *
     * @param [type] $echo [description]
     * @param [type] $tr   [description]
     */
    public function getMaxZipcode($echo)
    {
        if ($echo) {
            $return = $echo;
        } else {
            $return = '----';
        }

        return $return;
    }

    public function getImpactType($echo)
    {
        if ($echo) {
            $return = $this->l('Increase');
        } else {
            $return = $this->l('Decrease');
        }

        return $return;
    }

    public function getZoneName($echo)
    {
        if ($echo) {
            $return = (new Zone($echo))->name;
        } else {
            $return = $this->l('Zone Id missing');
        }

        return $return;
    }

    public function getCountryName($echo)
    {
        if ($echo) {
            $return = (new Country($echo))->name[$this->context->language->id];
        } else {
            $return = $this->l('Country Id missing');
        }

        return $return;
    }

    public function getFormatedImpactPrice($echo)
    {
        return Tools::displayPrice($echo);
    }

    /**
     * [getRangeOrNot description] - A callback function for setting range behaviour of zipcode.
     *
     * @param [type] $echo [description]
     * @param [type] $tr   [description]
     */
    public function getRangeOrNot($echo)
    {
        if ($echo) {
            $return = $this->l('For Zip Range');
        } else {
            $return = $this->l('For One Zip');
        }

        return $return;
    }

    public function getDownloadLink($echo, $row)
    {
        $csv_link = _MODULE_DIR_.'zipcodewiseshipping/views/uploaded_csv/'.$row['new_csv_name'];
        $html = '<a class="btn btn-default '.$echo.'" title="'.$this->l('Download ').$row['original_csv_name'].'" href="'.$csv_link.'"><i class="icon-download"></i>&nbsp;&nbsp;'.$this->l('Download Csv').'</a>';

        return $html;
    }

    public function renderForm()
    {
        Media::addJsDef(
            array(
                'admin_zipwiseshipping_link' => $this->context->link->getAdminLink('AdminZipCodeWiseShipping'),
            )
        );
        $this->context->controller->addJs(_PS_MODULE_DIR_.$this->module->name.'/views/js/zipwiseshipping.js');
        $idZone = 0;
        if ($this->display == 'edit') {
            $id = Tools::getValue('id');
            $zipCodeShippingImpact = new ZipCodeShippingImpact($id);
            $idZone = $zipCodeShippingImpact->id_zone;
            Media::addJsDef(
                array(
                    'selected_id_country' => $zipCodeShippingImpact->id_country,
                )
            );
        }
        if ($idZone) {
            Media::addJsDef(array(
                'update_page' => 1,
            ));
        } else {
            Media::addJsDef(array(
                'update_page' => 0,
            ));
        }
        $impactTypes = array(
            array(
                'id_option' => 1,
                'name' => $this->l('Increase'),
            ),
            array(
                'id_option' => 0,
                'name' => $this->l('Decrease'),
            ),
        );
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Zipcode wise impact'),
                'icon' => 'icon-truck',
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'active',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ),
                    ),
                    'hint' => $this->l('This impact will be active or deactive.'),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Zone'),
                    'name' => 'id_zone',
                    'options' => array(
                        'query' => Zone::getZones(),
                        'id' => 'id_zone',
                        'name' => 'name',
                    ),
                    'hint' => $this->l('Geographical region.'),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Country'),
                    'name' => 'id_country',
                    'options' => array(
                        'query' => ($idZone ? Country::getCountriesByZoneId($idZone, $this->context->language->id) : array()),
                        'id' => 'id_country',
                        'name' => 'name',
                    ),
                    'hint' => $this->l('Countries in the zone you have selected.'),
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Impact For Range'),
                    'name' => 'is_zip_range',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ),
                    ),
                    'hint' => $this->l('You want to set an impact price for a zipcode range(if enable) or for a single zipcode(if disable).'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Zip Code From'),
                    'name' => 'zip_code_from',
                    'hint' => $this->l('Minimum Zipcode Value For the range Or the value of the zipcode to set impact for a single zipcode.'),
                ),
                array(
                    'type' => 'text',
                    'class' => 'col-sm-3',
                    'label' => $this->l('Zip Code To'),
                    'name' => 'zip_code_to',

                    'hint' => $this->l('Maximum Zipcode value for the zipcode range.'),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Impact Type'),
                    'name' => 'impact_type',
                    'options' => array(
                        'query' => $impactTypes,
                        'id' => 'id_option',
                        'name' => 'name',
                    ),
                    'hint' => $this->l('Whether price will increase or decrease on selected zipcodes.'),
                ),
                array(
                    'type' => 'text',
                    'class' => 'col-sm-3',
                    'label' => $this->l('Impact On Price'),
                    'name' => 'impact_price',

                    'hint' => $this->l('Value of the impact price.'),
                ),
            ),
        );
        $this->fields_form['submit'] = array(
            'title' => $this->l('Save'),
        );

        return parent::renderForm();
    }

    public function processSave()
    {
        $idCountry = Tools::getValue('id_country');
        $country = new Country($idCountry);
        $zipRange = Tools::getValue('is_zip_range');
        $zipcodeFrom = Tools::getValue('zip_code_from');
        if (!($country->checkZipCode($zipcodeFrom))) {
            $this->errors[] = Tools::displayError($this->l('The Zipcode From entered is not in the correct format for selected country.'));
        }
        if ($zipRange) {
            $zipcodeTo = Tools::getValue('zip_code_to');
            if (!($country->checkZipCode($zipcodeTo))) {
                $this->errors[] = Tools::displayError($this->l('The Zipcode To entered is not in the correct format for selected country.'));
            } elseif ($zipcodeTo < $zipcodeFrom) {
                $this->errors[] = Tools::displayError($this->l('The Zipcode To entered should be greater than Zip Code From for  a zip range.'));
            }
        } else {
            $_POST['zip_code_to'] = '';
        }
        parent::processSave();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCsvUpload')) {
            $zipCsv = $_FILES['zip_csv'];
            $zipCsvInfo = new SplFileInfo($zipCsv['name']);
            $zipCsvExt = $zipCsvInfo->getExtension();
            if ($zipCsvExt == 'csv') {
                if ($zipCsv['size'] > 0) {
                    $file = fopen($zipCsv['tmp_name'], 'r');
                    $headerArray = fgetcsv($file);
                    $this->validateZipcodeCsvHeader($headerArray);
                    if (!count($this->errors)) {
                        $dataProcessed = 0;
                        $line = 2;
                        $zipcodeShippingImpact = new ZipCodeShippingImpact();
                        while (($result = fgetcsv($file)) !== false) {
                            $emptyRowCondition = $zipcodeShippingImpact->isRowEmpty($result);
                            if (array(null) !== $result && !$emptyRowCondition) { // ignore blank lines
                                $this->validateCsvFieldValues($result, $line);
                                ++$line;
                            }
                        }
                        $file = fopen($zipCsv['tmp_name'], 'r');
                        $headerArray = fgetcsv($file);
                        if (!count($this->errors)) {
                            while (($result = fgetcsv($file)) !== false) {
                                $emptyRowCondition = $zipcodeShippingImpact->isRowEmpty($result);
                                if (array(null) !== $result && !$emptyRowCondition) { // ignore blank lines
                                    $dataProcessed = 1;
                                    $this->saveCsvZipcodeData($result);
                                }
                            }
                        }
                        fclose($file);
                        if ($dataProcessed) {
                            $zipcodeShippingImpact = new ZipCodeShippingImpact();
                            $csvName = $zipcodeShippingImpact->generateRandomeNumber(8);
                            $path = _PS_MODULE_DIR_.'zipcodewiseshipping/views/uploaded_csv/'.$csvName.'.csv';
                            $file_uploaded = $zipcodeShippingImpact->uploadCsvFile($path, $zipCsv['tmp_name']);
                            if ($file_uploaded) {
                                $zipCsvInfo = new ZipCsvsInformation();
                                $zipCsvInfo->original_csv_name = $zipCsv['name'];
                                $zipCsvInfo->new_csv_name = $csvName.'.csv';
                                $zipCsvInfo->total_records = $line - 2;
                                $zipCsvInfo->save();
                                if ($zipCsvInfo->id) {
                                    Tools::redirectAdmin(self::$currentIndex.'&conf=4&token='.$this->token);
                                }
                            }
                        }
                    }
                } else {
                    $this->errors[] = Tools::displayError($this->l('Please select a zipcode csv file and then try to upload it.'));
                }
            } else {
                $this->errors[] = Tools::displayError($this->l('Invalid file type. Please try to upload a csv file only'));
            }
        } elseif (Tools::isSubmit('submitZipConfiguration')) {
            Configuration::updateValue('ZIP_IMPACT_BEHAVE', Tools::getValue('ZIP_IMPACT_BEHAVE'));
            Configuration::updateValue('ZIPCODE_ADD_TAX', Tools::getValue('ZIPCODE_ADD_TAX'));
            Tools::redirectAdmin(self::$currentIndex.'&conf=4&token='.$this->token);
        } else {
            parent::postProcess();
        }
    }

    private function validateZipcodeCsvHeader($headerArray)
    {
        $zipCsvFields = array('id_zone','id_country','is_range','min_zipcode','max_zipcode','impact_type','impact_price','active');
        if (count($zipCsvFields) < count($headerArray)) {
            $this->errors[] = Tools::displayError($this->l('Some Extra Fields are added in your csv. Please make sure csv format must exactly same as sample Csv. May be some spaces are left at wrong places in your csv file. You can again download the sample zipcode csv and start loading your data.'));
        } else {
            if ($headerArray) {
                $missingFields = '';
                foreach ($zipCsvFields as $field) {
                    if (!in_array($field, $headerArray)) {
                        $missingFields .= $field.', ';
                    }
                }
                if ($missingFields) {
                    $this->errors[] = Tools::displayError($this->l('Some Mandatory fields are missing In your uploaded CSV : '.$missingFields));
                }
            }
        }
    }

    private function validateCsvFieldValues($fields_values, $csv_line)
    {
        $idZone = $fields_values[0];
        $idCountry = $fields_values[1];
        $zipRange = $fields_values[2];
        $zipCodeFrom = $fields_values[3];
        $zipcodeTo = $fields_values[4];
        $impactType = $fields_values[5];
        $impactPrice = $fields_values[6];
        $active = $fields_values[7];
        $countryIdZone = 0;
        if (isset($idCountry) && $idCountry) {
            $countryIdZone = Country::getIdZone($idCountry);
        }
        if (!isset($idZone) || !$idZone) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Id Zone is required field.'));
        } elseif (!Validate::isUnsignedId($idZone)) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Invalid Id Zone please enter a valid Id.'));
        }
        if (!isset($idCountry) || !$idCountry) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Id Country is required field.'));
        } elseif (!Validate::isUnsignedId($idCountry)) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Invalid Id Country please enter a valid Id.'));
        } elseif ($countryIdZone != $idZone) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Invalid Id Country Country should belong to the entered zone Id.'));
        }
        if (!isset($impactType) || !$impactType) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Please enter the impact type.'));
        } elseif (!in_array($impactType, array('increase', 'decrease'))) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Invalid impact type field value. Please enter either increase or decrease'));
        }
        if (!isset($impactPrice)) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Please enter the impact price.'));
        } elseif (!Validate::isPrice($impactPrice)) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Please enter a Valid impact price.'));
        }
        $country = new Country($idCountry);
        if (!($country->checkZipCode($zipCodeFrom))) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('The Zipcode From entered is not in the correct format for selected country.'));
        }
        if (!in_array($active, array('1', '0'))) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Invalid active field value. Please enter either 1 or 0'));
        }
        if (!in_array($zipRange, array('1', '0'))) {
            $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Invalid zip_range field value. Please enter either 1 or 0'));
        } elseif (isset($zipRange) && $zipRange == 1) {
            if (!isset($zipcodeTo) || !$zipcodeTo) {
                $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('Max zipcode is required for the zipcode range.'));
            } elseif (!($country->checkZipCode($zipcodeTo))) {
                $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('The Zipcode To entered is not in the correct format for selected country.'));
            } elseif ($zipcodeTo < $zipCodeFrom) {
                $this->errors[] = Tools::displayError($this->l('CSV File Line').' -'.$csv_line.': '.$this->l('The Zipcode To entered should be greater than Zip Code From for  a zip range.'));
            }
        } else {
            $_POST['zip_code_to'] = '';
        }
    }

    private function saveCsvZipcodeData($fields_values)
    {
        if (!isset($fields_values[2]) || !$fields_values[2]) {
            $fields_values[2] = 0;
            $fields_values[4] = '';
        }
        if ($fields_values[5] == 'increase') {
            $fields_values[5] = 1;
        } elseif ($fields_values[5] == 'decrease') {
            $fields_values[5] = 0;
        } else {
            $fields_values[5] = 1;
        }

        $zipcodeShippingImpact = new ZipCodeShippingImpact();
        $zipcodeShippingImpact->id_zone = $fields_values[0];
        $zipcodeShippingImpact->id_country = $fields_values[1];
        $zipcodeShippingImpact->is_zip_range = $fields_values[2];
        $zipcodeShippingImpact->zip_code_from = $fields_values[3];
        $zipcodeShippingImpact->zip_code_to = $fields_values[4];
        $zipcodeShippingImpact->impact_type = $fields_values[5];
        $zipcodeShippingImpact->impact_price = $fields_values[6];
        $zipcodeShippingImpact->active = $fields_values[7];
        $zipcodeShippingImpact->save();

        return $zipcodeShippingImpact->id;
    }

    public function ajaxProcessFindCountryByZone()
    {
        $idZone = Tools::getValue('id_zone');
        $country_detail = Country::getCountriesByZoneId($idZone, Configuration::get('PS_LANG_DEFAULT'));
        $json_array_rev = Tools::jsonEncode($country_detail);
        die($json_array_rev);
    }
}

<?php
    class HotelRoomTypeFeaturePricing extends ObjectModel
    {
        public $id_product;
        public $feature_price_name;
        public $date_selection_type;
        public $date_from;
        public $date_to;
        public $is_special_days_exists;
        public $special_days;
        public $impact_way;
        public $impact_type;
        public $impact_value;
        public $active;
        public $date_add;
        public $date_upd;

        public static $definition = array(
            'table' => 'htl_branch_features',
            'primary' => 'id',
            'fields' => array(
                'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
                'feature_price_name' => array('type' => self::TYPE_STRING),
                'date_from' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
                'date_to' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
                'impact_way' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
                'is_special_days_exists' => array('type' => self::TYPE_INT),
                'date_selection_type' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
                'special_days' => array('type' => self::TYPE_STRING),
                'impact_type' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
                'impact_value' => array('type' => self::TYPE_FLOAT),
                'active' => array('type' => self::TYPE_INT),
                'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
                'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ));

        public function __construct()
        {
            parent::__construct();
            $this->moduleInstance = new Hotelreservationsystem();
        }

        /**
         * [getRoomTypeActiveFeaturePrices returns room type active feature price plans]
         * @param  [int] $id_product [id of the product]
         * @return [array|false]     [returns array of all active feature plans of the room type if found else returns false]
         */
        public static function getRoomTypeActiveFeaturePrices($id_product)
        {
            return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'htl_room_type_feature_pricing` WHERE `id_product`='.(int) $id_product.' AND `active`=1');
        }

        /**
         * [getRoomTypeActiveFeaturePricesByDateRange returns room type active feature price plans by supplied date Range]
         * @param  [int] $id_product [id of the product]
         * @param  [date] $date_from  [start date of the date range]
         * @param  [date] $date_to    [end date of the date range]
         * @return [array|false]      [returns array of all active feature plans of the room type if found else returns false]
         */
        public static function getRoomTypeActiveFeaturePricesByDateRange($id_product, $date_from, $date_to)
        {
            return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'htl_room_type_feature_pricing` WHERE (`id_product`=0 OR `id_product`='.(int) $id_product.') AND `active`=1 AND `date_from` <= \''.$date_to.'\' AND `date_to` >= \''.$date_from.'\'');
        }

        /**
         * [checkRoomTypeFeaturePriceExistance returns room type active feature price plan by supplied date Range and supplied feature price plan type else returns false]
         * @param  [int] $id_product [id of the product]
         * @param  [date] $date_from  [start date of the date range]
         * @param  [date] $date_to    [end date of the date range]
         * @param  [type] $type       [Type of the feature price plan must be among 'specific_date', 'special_day' and 'date_range']
         * @return [array|false]      [returns room type active feature price plan by supplied date Range and supplied feature price plan type else returns false]
         */
        public function checkRoomTypeFeaturePriceExistance($id_product, $date_from, $date_to, $type='date_range', $current_Special_days=false)
        {
        	if ($type == 'specific_date') {
				return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_room_type_feature_pricing` WHERE `id_product`='.(int) $id_product.' AND `date_selection_type`=2 AND `date_from` = \''.$date_from.'\'');

			} else if ($type == 'special_day') {
				$featurePrice = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_room_type_feature_pricing` WHERE `id_product`='.(int) $id_product.' AND `is_special_days_exists`=1 AND `active`=1 AND `date_from` < \''.$date_to.'\' AND `date_to` > \''.$date_from.'\'');
				if ($featurePrice) {
					$specialDays = Tools::jsonDecode($featurePrice['special_days']);
					$currentSpecialDays = Tools::jsonDecode($current_Special_days);
					$commonValues = array_intersect ($specialDays , $currentSpecialDays);
					if ($commonValues) {
						return $featurePrice;
					}
				}
				return false;
			} else if ($type == 'date_range') {

				return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_room_type_feature_pricing` WHERE `id_product`='.(int) $id_product.' AND `date_selection_type`=1 AND `is_special_days_exists`=0 AND `date_from` < \''.$date_to.'\' AND `date_to` > \''.$date_from.'\'');
			}
        }

        /**
         * [countFeaturePriceSpecialDays returns number of special days between a date range]
         * @param  [array] $specialDays [array containing special days to be counted]
         * @param  [date] $date_from   [start date of the date range]
         * @param  [date] $date_to     [end date of the date range]
         * @return [int]              [number of special days]
         */
        public static function countFeaturePriceSpecialDays($specialDays, $date_from, $date_to)
        {
            $totalDaySeconds = 24*60*60;
            $specialDaysCount = 0;

            for ($date = strtotime($date_from); $date <= strtotime($date_to)-$totalDaySeconds; $date = ($date + $totalDaySeconds)) {
                if (in_array(strtolower(Date('D', $date)), $specialDays)) {
                    $specialDaysCount++;
                }
            }
            return $specialDaysCount;
        }

        /**
         * [getHotelRoomTypesRatesByDate returns hotel room types rates accrding to feature price plans]
         * @param  [int]  $id_hotel   [id of th hotel]
         * @param  integer $id_product [id of the product if supplied only rates of this room type will be returned]
         * @param  [date]  $date_from  [start date of the date range]
         * @param  [date]  $date_to    [end date of the date range]
         * @return [array|false]       [returns array containing rates of room type of a hotel if found else returns false]
         */
        public function getHotelRoomTypesRatesByDate($id_hotel, $id_product=0, $date_from, $date_to)
        {
            $totalDaySeconds = 24*60*60;
            $hotelRoomType = new HotelRoomType();
            $context = Context::getContext();
            $roomTypeRates = array();
            $hotelCartBookingData = new HotelCartBookingData();
            $incr = 0;
            for ($date = strtotime($date_from); $date <= strtotime($date_to); $date = ($date + $totalDaySeconds)) {
                $currentDate = date('Y-m-d', $date);
                $nextDayDate = date('Y-m-d', strtotime($currentDate) + 86400);
                if ($id_product) {
                    $roomTypePrice = $hotelCartBookingData->getRoomTypeTotalPrice($id_product, $currentDate, $nextDayDate);
                    $roomTypeRates[$incr]['date'] = $currentDate;
                    $roomTypeRates[$incr]['room_types'][0]['id'] = $id_product;
                    $roomTypeRates[$incr]['room_types'][0]['rates'] = $roomTypePrice;
                } else {
                    $hotelRoomTypes = $hotelRoomType->getRoomTypeByHotelId($id_hotel, $context->language->id);
                    if ($hotelRoomTypes) {
                        $roomTypeRates[$incr]['date'] = $currentDate;
                        foreach ($hotelRoomTypes as $key => $product) {
                            $roomTypePrice = $hotelCartBookingData->getRoomTypeTotalPrice($product['id_product'], $currentDate, $nextDayDate);
                            $roomTypeRates[$incr]['room_types'][$key]['id'] = $product['id_product'];
                            $roomTypeRates[$incr]['room_types'][$key]['rates'] = $roomTypePrice;
                        }
                    } else {
                        return false;
                    }
                }
                $incr++;
            }
            return $roomTypeRates;
        }

        /**
         * [updateRoomTypesFeaturePrices update and creates feature price plans by supplied information]
         * @param  [array] $featurePricePlans [feature price plans sent from channel manager]
         * @return [array]        [success if process is finished successfully else fasiled with errors]
         * @information [if any feature price plan for the same date_from and date_to(supplied in the $featurePricePlans array) it will be updated otherwise it is added. While adding date range type rate plans if any plan already exist then feature price for all specific dates in the date range will be created (or updated if specific date feature price plan exists)]
         */
        public function updateRoomTypesFeaturePrices($featurePricePlans)
        {
            $moduleInstance = new HotelReservationSystem();
            $this->errors[] = array();
            if ($featurePricePlans) {
                $totalDaySeconds = 24*60*60;
                if (isset($featurePricePlans['data']) && $featurePricePlans['data']) {
                    foreach ($featurePricePlans['data'] as $roomTypeRatesData) {
                        $dateFrom = date('Y-m-d', strtotime($roomTypeRatesData['dateFrom']));
                        $dateTo = date('Y-m-d', strtotime($roomTypeRatesData['dateTo']) + 86400);
                        if ($roomTypeRatesData['roomType']) {
                            foreach ($roomTypeRatesData['roomType'] as $key => $roomTypeRates) {
                                $id_product = $key;
                                $productPriceTE = Product::getPriceStatic((int) $id_product, false);
                                if ($productPriceTE > $roomTypeRates['rate']) {
                                    $priceImpactWay = 1;
                                    $impactValue = $productPriceTE - $roomTypeRates['rate'];
                                } else {
                                    $priceImpactWay = 2;
                                    $impactValue = $roomTypeRates['rate'] - $productPriceTE;
                                }
                                $params = array();
                                $params['roomTypeId'] = $id_product;
                                $params['featurePriceName'] = 'Test Feature Price';
                                $params['dateFrom'] = $dateFrom;
                                $params['dateTo'] = $dateTo;
                                $params['priceImpactWay'] = $priceImpactWay; 
                                $params['isSpecialDaysExists'] = 0;
                                $params['jsonSpecialDays'] = null;
                                $params['priceImpactType'] = 2;
                                $params['impactValue'] = $impactValue;
                                $params['enableFeaturePrice'] = 1;
                                $nextDate = date('Y-m-d', strtotime($dateFrom) + 86400);
                                if ($nextDate == $dateTo) {
                                    $params['dateSelectionType'] = 2;
                                    $featurePriceExists = $this->checkRoomTypeFeaturePriceExistance($id_product, $dateFrom, $dateTo, $type='specific_date');
                                    if ($featurePriceExists) {
                                        if (!$this->saveFeaturePricePlan($featurePriceExists['id'], 2, $params)) {
                                            $this->errors[] = $this->moduleInstance->l('Some error occured while saving Feature Price Plan Info:: Date From : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Date To : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Room Type Id : ', 'HotelRoomTypeFeaturePricing').$params['roomTypeId'];
                                        }
                                    } else {
                                        if (!$this->saveFeaturePricePlan(0, 2, $params)) {
                                            $this->errors[] = $this->moduleInstance->l('Some error occured while saving Feature Price Plan Info:: Date From : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Date To : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Room Type Id : ', 'HotelRoomTypeFeaturePricing').$params['roomTypeId'];
                                        }
                                    }
                                } else {
                                    $params['dateSelectionType'] = 1;
                                    $featurePriceExists = $this->checkRoomTypeFeaturePriceExistance($id_product, $dateFrom, $dateTo, $type='date_range');
                                    if ($featurePriceExists) {
                                        if ($featurePriceExists['date_from'] == $dateFrom && $featurePriceExists['date_to'] == $dateTo) {
                                            if (!$this->saveFeaturePricePlan($featurePriceExists['id'], 1, $params)) {
                                                $this->errors[] = $this->moduleInstance->l('Some error occured while saving Feature Price Plan Info:: Date From : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Date To : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Room Type Id : ', 'HotelRoomTypeFeaturePricing').$params['roomTypeId'];
                                            }
                                        } else {
                                            for ($date = strtotime($dateFrom); $date <= strtotime($dateTo)-$totalDaySeconds; $date = ($date + $totalDaySeconds)) {
                                                $currentDate = date('Y-m-d', $date);
                                                $nextDayDate = date('Y-m-d', strtotime($currentDate) + 86400);
                                                $params['dateFrom'] = $currentDate;
                                                $params['dateTo'] = $nextDayDate;
                                                $params['dateSelectionType'] = 2;
                                                $featurePriceExists = $this->checkRoomTypeFeaturePriceExistance($id_product, $currentDate, $nextDayDate, $type='specific_date');
                                                if ($featurePriceExists) {
                                                    if (!$this->saveFeaturePricePlan($featurePriceExists['id'], 2, $param)) {
                                                        $this->errors[] = $this->moduleInstance->l('Some error occured while saving Feature Price Plan Info:: Date From : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Date To : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Room Type Id : ', 'HotelRoomTypeFeaturePricing').$params['roomTypeId'];
                                                    }
                                                } else {
                                                    if (!$this->saveFeaturePricePlan(0, 2, $params)) {
                                                        $this->errors[] = $this->moduleInstance->l('Some error occured while saving Feature Price Plan Info:: Date From : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Date To : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Room Type Id : ', 'HotelRoomTypeFeaturePricing').$params['roomTypeId'];
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        if (!$this->saveFeaturePricePlan(0, 1, $params)) {
                                            $this->errors[] = $this->moduleInstance->l('Some error occured while saving Feature Price Plan Info:: Date From : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Date To : ', 'HotelRoomTypeFeaturePricing').$params['dateFrom'].$this->moduleInstance->l(' Room Type Id : ', 'HotelRoomTypeFeaturePricing').$params['roomTypeId'];
                                        }
                                    }
                                }
                            }
                        } else {
                            $this->errors[] = $this->moduleInstance->l('Room Types for which Feature prices to be updated are not found.', 'HotelRoomTypeFeaturePricing');
                        }
                    }
                } else {
                    $this->errors[] = $this->moduleInstance->l('Update Information not found.', 'HotelRoomTypeFeaturePricing');
                }
            } else {
                $this->errors[] = $this->moduleInstance->l('Update Information not found.', 'HotelRoomTypeFeaturePricing');
            }

            if (count($this->errors)) {
                $result['status'] = 'failed';
                $result['errors'] = $this->errors;
            } else {
                $result['status'] = 'success';
            }
            return $result;
        }

        /**
         * [saveFeaturePricePlan add or update feature price plan]
         * @param  integer $id                [id of the feature price plan if 0 means to add else to update the feature price plan]
         * @param  [int]  $dateSelectionType [date selection type 1 or 2 (date range or specific date)]
         * @param  [array]  $params            [Room type rate plan info]
         * @return [bool]                     [returns true is successfuly added or updated else returns false]
         */
        public function saveFeaturePricePlan($id=0, $dateSelectionType, $params)
        {
            if ($id) {
                $roomTypeFeaturePricing = new HotelRoomTypeFeaturePricing($id);
            } else {
                $roomTypeFeaturePricing = new HotelRoomTypeFeaturePricing();
            }
            $roomTypeFeaturePricing->id_product = $params['roomTypeId'];
            $roomTypeFeaturePricing->feature_price_name = $params['featurePriceName'];
            $roomTypeFeaturePricing->date_selection_type = $params['dateSelectionType'];
            if ($dateSelectionType == 1) {
                $roomTypeFeaturePricing->date_from = $params['dateFrom'];
                $roomTypeFeaturePricing->date_to = $params['dateTo'];
            } else {
                $roomTypeFeaturePricing->date_from = $params['dateFrom'];
                $roomTypeFeaturePricing->date_to = $params['dateTo'];
            }
            $roomTypeFeaturePricing->impact_way = $params['priceImpactWay'];
            $roomTypeFeaturePricing->is_special_days_exists = $params['isSpecialDaysExists'];
            $roomTypeFeaturePricing->special_days = $params['jsonSpecialDays'];
            $roomTypeFeaturePricing->impact_type = $params['priceImpactType'];
            $roomTypeFeaturePricing->impact_value = $params['impactValue'];
            $roomTypeFeaturePricing->active = $params['enableFeaturePrice'];
            return $roomTypeFeaturePricing->save();
        }
    }

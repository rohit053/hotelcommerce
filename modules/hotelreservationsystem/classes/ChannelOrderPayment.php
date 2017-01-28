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

class ChannelOrderPayment extends PaymentModule
{
	public function __construct()
	{
		//$this->module = Module::getInstanceByName('hotelreservationsystem');
		$this->module = Module::getInstanceByName('hotelreservationsystem');
		$this->name = 'chanelPayment';
		$this->active = 1;
		
	}
}

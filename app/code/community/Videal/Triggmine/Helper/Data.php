<?php
class Videal_Triggmine_Helper_Data extends Mage_Core_Helper_Abstract
{
	const XML_PATH_ENABLED              = 'triggmine/config/is_on';
	const XML_PATH_API_KEY              = 'triggmine/config/rest_api';

	public function isEnabled($store = null)
	{
		return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
	}

	public function getAPIKey($store = null) {
		$api_key = Mage::getStoreConfig(self::XML_PATH_API_KEY, $store);
		return $api_key ? $api_key : false;
	}
}
	 
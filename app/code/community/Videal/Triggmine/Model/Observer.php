<?php
require_once dirname(__FILE__) . '/core/Core.php';

class Videal_Triggmine_Model_Observer extends TriggMine_Core
{
	const  VERSION = '2.0.0';
	const  SETTING_EXPORT = 'triggmine_send_export';

	private $_scriptFiles   = array();
	private $_scripts       = array();

	/**
	 * Tells whether current request is AJAX one.
	 * AJAX doesn't equal to async.
	 * @return bool
	 */
	public function isAjaxRequest()
	{
		return Mage::app()->getFrontController()->getRequest()->isAjax();
	}

	/**
	 * Tells about JS support in the integrator.
	 * @return bool
	 */
	public function supportsJavaScript()
	{
		return true;
	}

	function __construct()
	{
		parent::__construct();

	}

	/**
	 * Adds JS into the HTML.
	 * @param string $script JS code.
	 */
	public function registerJavaScript($script)
	{
		$this->_scripts[] = $script;
	}

	/**
	 * Adds &lt;script&gt; tag into the HTML.
	 * Modifies the URL depending on whether it is a plugin file or not.
	 * @param string $url Relative or absolute URL of the JS file.
	 * @param bool $isPluginFile Is it a part of plugin?
	 */
	public function registerJavaScriptFile($url, $isPluginFile = true)
	{
		$this->_scriptFiles[] = $isPluginFile ?
			Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS) . $url :
			$url
		;
	}

	public function outputJavaScript() {
		foreach ($this->_scriptFiles as $scriptFile) {
			echo "<script type='text/javascript' src='$scriptFile'></script>" . PHP_EOL;
		}

		foreach ($this->_scripts as $script) {
			echo "<script type='text/javascript'>/* <![CDATA[ */ $script /* ]]> */</script>" . PHP_EOL;
		}
	}

	public function onFooterEvent()
	{
		$this->registerJavaScriptFile('videal/triggmine/api.js', true);
		$this->outputJavaScript();
	}
	/**
	 * Returns URL of the website.
	 */
	public function getSiteUrl()
	{
		return  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
	}

	/**
	 * Returns array with buyer info [BuyerEmail, FirstName, LastName].
	 */
	public function getBuyerInfo()
	{
		$customer = Mage::getSingleton('customer/session')->getCustomer()->getData();
		return array(
			'BuyerEmail' => $customer['email'],
			'FirstName'  => $customer['firstname'],
			'LastName'   => $customer['lastname']
		);
	}

	/**
	 * Returns absolute URL to the shopping cart page.
	 * @return string Shopping cart URL.
	 */
	public function getCartUrl()
	{
		return Mage::helper('checkout/cart')->getCartUrl();
	}

	/**
	 * Returns a name of your CMS / eCommerce platform.
	 * @return string Agent name.
	 */
	public function getAgent()
	{
		return 'Magento';
	}

	/**
	 * Returns a version of your CMS / eCommerce platform.
	 * @return string Version.
	 */
	public function getAgentVersion()
	{
		return Mage::getVersion();
	}

	/**
	 * Tells whether current user is admin.
	 * @return bool Is user an administrator.
	 */
	protected function _isUserAdmin()
	{
		return Mage::app()->getStore()->isAdmin();
	}

	protected function _fillShoppingCart($cartContent)
	{
		if ( empty( $cartContent['Items'] ) ) {
			return false;
		}

		$oCart = Mage::getSingleton('checkout/cart');

		$oCart->getQuote()->removeAllItems();

		$cartItems = $cartContent['Items'];

		foreach ( $cartItems as $aProduct ) {
			unset($ids, $iProductId, $aOptions, $aOptionIds, $sOptionIds);
			$ids = explode('|', $aProduct['CartItemId']);
			$iProductId = array_shift($ids);

			if ( !empty($ids) )
			{
				$aOptions['options'] = array();
				foreach ( $ids as $sOptionIds )
				{
					$aOptionIds = explode('_', $sOptionIds);
					$aOptions['options'][$aOptionIds[0]] = $aOptionIds[1];
				}
			}

			$aOptions['qty']        = $aProduct['Count'];
			$aOptions['product']    = $iProductId;

			$oCart->addProduct($iProductId, $aOptions);
		}

		$oCart->saveQuote();
		Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
	}

	public function install()
	{

	}

	public function uninstall()
	{

	}

	/**
	 * Returns a value of the setting having given name.
	 * @param string $key Setting name.
	 * @return string Setting value.
	 */
	protected function _getSettingValue($key)
	{
		$key = str_replace('triggmine_', 'triggmine/config/', $key);

		return Mage::getStoreConfig($key);
	}


	public function onCartItemAdded(Varien_Event_Observer $observer)
	{
		$products = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
		foreach ( $products as $product )
		{
			if ( !$catalogProduct           = $observer->getEvent()->getProduct() ) throw new Exception('No productModel');
			if ( $catalogProduct->getId()   != $product->getProductId() )           continue;
			if ( !$productId                = $catalogProduct->getId() )            throw new Exception('No productId');
			if ( !$productName              = $catalogProduct->getName() )          throw new Exception('No productName');
			if ( !$productPrice             = $catalogProduct->getPrice() )         throw new Exception('No productPrice');
			if ( !$productQty               = $product->getQty() )                  throw new Exception('No productQnt');

			if ( $options = $product->getBuyRequest()->getData('options') )
			{
				foreach ( $options as $typeId => $optionId )
					$productId .= '|' . $typeId . '_' . $optionId;
			}

			$data = array(
				"CartItemId"        => (string) $productId,
				"ThumbUrl"          => $catalogProduct->getSmallImageUrl(),
				"ImageUrl"          => $catalogProduct->getImageUrl(),
				"Title"             => $productName,
				"ShortDescription"  => $catalogProduct->getShortDescription(),
				"FullDescription"   => $catalogProduct->getDescription(),

				"Price"             => $productPrice,
				"Count"             => $productQty,
			);
		}
		$this->addCartItem($data);
	}

	public function onCartItemDeleted(Varien_Event_Observer $observer)
	{
		$product = $observer->getEvent()->getQuoteItem();

		$productId = $product->getProductId();
		$this->deleteCartItem( $productId );
	}

	public function onCartItemUpdated(Varien_Event_Observer $observer)
	{
		$products = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
		$newProducts = $observer->info;

		foreach ( $products as $product )
		{
			foreach ( $newProducts as $newProductId => $newProduct )
			{
				if ( $newProductId != $product->getId() || $newProduct['qty'] == $product->getQty() ) continue;

				if ( !$productId       = $product->getProductId() ) throw new Exception('No productId');
				if ( !$productName     = $product->getName()      ) throw new Exception('No productName');

				if ( $options = $product->getBuyRequest()->getData('options') )
				{
					foreach ( $options as $typeId => $optionId )
						$productId .= '|' . $typeId . '_' . $optionId;
				}

				$obj = Mage::getModel('catalog/product');
				$_product = $obj->load($productId); // Enter your Product Id in $product_id

				$data = array(
					"CartItemId"        => (string) $productId,
					"ThumbUrl"          => $product->getProduct()->getSmallImageUrl(),
					"ImageUrl"          => $product->getProduct()->getImageUrl(),
					"Title"             => $productName,
					"ShortDescription"  => $_product->getShortDescription(),
					"FullDescription"   => $_product->getDescription(),

					"Price"             => $product->getProduct()->getPrice(),
					"Count"             => $newProduct['qty'],
				);
			}
		}

		$this->updateCartItem($data);
	}

	public function onCartCleanedUp(Varien_Event_Observer $observer)
	{
		if ( Mage::app()->getFrontController()->getRequest()->getParam('update_cart_action') == "empty_cart" ) {
			$this->cleanupCart();
		}

	}

	public function onBuyerLoggedIn(Varien_Event_Observer $observer)
	{
		$this->logInBuyer( $this->getBuyerInfo() );

		$data = $this->_refillShoppingCart();

		$this->updateCartFull($data);
	}

	public function _refillShoppingCart()
	{
		$quote = Mage::getSingleton('checkout/session')->loadCustomerQuote()->getQuote();

		$cart = array();

		foreach ($quote->getAllItems() as $item)
		{
			$productID = $item->getProductId();
			$product = Mage::getModel('catalog/product')->load($productID);

			$data = array(
				'CartItemId'  => (string) $productID,
				'Title'       => $item->getName(),
				'Price'       => (string) number_format($item->getPrice(), 2, ".", ""),
				'Count'       => (string) $item->getQty(),
				'Description' => $product->getDescription(),
				'ImageUrl'    => $product->getImageUrl()
			);

			$cart['Items'][] = $data;
		}

		return $cart;
	}

	public function onBuyerLoggedOut(Varien_Event_Observer $observer)
	{
		$this->logOutBuyer();
	}

	public function onCartPurchased(Varien_Event_Observer $observer)
	{
		$this->purchaseCart( $this->getBuyerInfo() );
	}

	public function onPageLoaded(Varien_Event_Observer $observer)
	{
		if(!$this->isAjaxRequest()) {
			parent::onPageLoaded();
			$this->outputJavaScript();
		}
	}

	public function onBuyerRegister(Varien_Event_Observer $observer)
	{
		$email = $observer->getEvent()->getCustomer()->getEmail();
		$this->logInBuyer( $email );
	}

	protected function _prepareCartItemData(Varien_Event_Observer $observer)
	{
		$product = $observer->getEvent()->getData('product');
		$productId = $product->getId();

		if ( $options = $product->getOptions() )
		{
			foreach ( $options as $typeId => $optionId )
				$productId .= '|' . $typeId . '_' . $optionId;
		}

		$data = array(
			'CartItemId'        => $productId,
			'Title'             => $product->getName(),
			'Description'       => $product->getDescription(),
			'ShortDescription'  => $product->getShortDescription(),
			'Price'             => $product->getPrice(),
			'Count'             => $product->getQty(),
			"ThumbUrl"          => $product->getSmallImageUrl(),
			"ImageUrl"          => $product->getImageUrl(),
		);

		return $data;
	}

	protected function _getUserDataFromDatabase($email)
	{
		$customer = Mage::getModel("customer/customer");
		$customer->setWebsiteId(Mage::app()->getWebsite()->getId());
		$customer->loadByEmail($email);

		if ( $customer->getId() ) {
			$user = $customer->getData();

			$data = array(
				'BuyerRegEnd' => gmdate ( "Y-m-d H:i:s",  strtotime( $user['created_at'] ) )
			);

			return $data;
		}

		$data = array(
			'BuyerRegStart' => gmdate( "Y-m-d H:i:s", time() )
		);

		return $data;
	}

	/**
	 * Returns a json string of orders
	 *
	 * @param $data
	 *
	 * @return string Json
	 */
	public function exportOrders($data)
	{
		$span   = $data->Span;
		$span   = explode('-', $span);
		$spanMax= $span[1];
		$offset = (int) $data->Offset;
		$next   = (int) $data->Next;
		$nextQ  = $offset + $next - 1;
		$mainOutput = array();

		for ($i = $offset; $i <= $nextQ; $i++) {
			$order = Mage::getModel('sales/order')->load($i);
			if($order) {
				$localOutput = array();
				$orderData = $order->getData();

				$localOutput['CartId']   = $orderData['entity_id']; //Purchase ID
				$localOutput['Amount']   = number_format ($order->getGrandTotal(), 2, '.' , $thousands_sep = ''); //Purchase Total
				$localOutput['DateTime'] = gmdate( "Y-m-d H:i:s\Z", strtotime($orderData['created_at']) );
				$localOutput['State']    =  ( $orderData['state'] == 'new' ) ? 1 : 2;
				$localOutput['Email']    = $orderData['customer_email'];

				$orderItems = $order->getItemsCollection();

				foreach ($orderItems as $item){
					$orderProductID = $item->getProductId();
					$product = Mage::getModel('catalog/product')->load($orderProductID);

					$data = array(
						'CartItemId'  => (string) $orderProductID,
						'Title'       => $item->getName(),
						'Price'       => (string) number_format($item->getPrice(), 2, ".", ""),
						'Count'       => (string) $item->getQtyOrdered(),
						'Description' => $product->getDescription(),
						'ImageUrl'    => $product->getImageUrl()
					);

					$localOutput['Content'][] = $data;
				}
				$mainOutput[] = $localOutput;
			}
		}

		return $mainOutput;
	}

	public function onChangedTriggmine(Varien_Event_Observer $observer)
	{

		$test = $this->test();
		if ($test === true) {
			if ($this->_getSettingValue(self::SETTING_IS_ON)) {
				$this->activate();
			} else {
				$this->deactivate();
			}
			if ($this->_getSettingValue(self::SETTING_EXPORT)) {
				$this->onSendExport('all');
			}
		} else {
			Mage::throwException($test);
		}
	}

	protected function _onSendExport($input)
	{
		$data = $this->_prepareExportData($input);
		$this->sendExport($data);
	}

	protected function _prepareExportData($input)
	{
		if($input == 'all') {
			$orders = Mage::getModel("sales/order")->getCollection();
			$lastFirstId = $orders->getFirstItem()->getId();
			$lastOrderId = $orders->getLastItem()->getId();

			if ($lastFirstId && $lastFirstId) {
				$data = array(
					'Url'  => $this->getSiteUrl() . '/?' . self::KEY_TRIGGMINE_EXPORT,
					'Span' => '' . $lastFirstId . '-' . $lastOrderId . ''
				);

				return $data;
			} else {
				return false;
			}
		}

		return false;
	}
}

<?php
defined('_JEXEC') or die('Restricted access');
if (!class_exists('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

require_once(dirname(__FILE__).'/includes/online-api-php-sdk/init.php');

class plgVmPaymentSatispay extends vmPSPlugin {
  function __construct(&$subject, $config) {
    parent::__construct($subject, $config);

		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);


		\SatispayOnline\Api::setSecurityBearer($method->security_bearer);
		\SatispayOnline\Api::setStaging($method->staging);

		\SatispayOnline\Api::setPluginName('VirtueMart');
    \SatispayOnline\Api::setPlatformVersion(VM_VERSION);
    \SatispayOnline\Api::setType('ECOMMERCE-PLUGIN');
  }

	function plgVmConfirmedOrder($cart, $order) {
		$details = $order['details']['BT'];
		if (!($method = $this->getVmPluginMethod($details->virtuemart_paymentmethod_id)))
			return NULL;

		$currency = shopFunctions::getCurrencyByID($details->order_currency, 'currency_code_3');

		$checkout = \SatispayOnline\Checkout::create(array(
      'description' => '#'.$details->order_number,
			'phone_number' => '',
      'redirect_url' => JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=redirect&pm='.$details->virtuemart_paymentmethod_id.'&order='.$details->virtuemart_order_id,
      'callback_url' => JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=callback&pm='.$details->virtuemart_paymentmethod_id.'&order='.$details->virtuemart_order_id.'&uuid={uuid}',
      'amount_unit' => round($details->order_total * 100),
      'currency' => $currency,
			'metadata' => array(
				'order_id' => $details->virtuemart_order_id
			)
    ));

		$cart->emptyCart();

		$app = JFactory::getApplication();
		$app->redirect($checkout->checkout_url);
	}

	function plgVmOnPaymentResponseReceived(&$html) {
		switch (vRequest::getString('action')) {
			case 'redirect':
				$pm = vRequest::getInt('pm');

				if (!($method = $this->getVmPluginMethod($pm))) {
					return NULL;
				}

				$charge = \SatispayOnline\Charge::get(vRequest::getString('charge_id'));

				$order = $charge->metadata->order_id;
				$orderModel = VmModel::getModel('orders');
				$order = $orderModel->getOrder($order);

				$details = $order['details']['BT'];

				if (vRequest::getString('ok') == 'true') {
          header('Location: '.JURI::root().'index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$details->order_number.'&order_pass='.$details->order_pass);
				} else {
					header('Location: '.JURI::root().'index.php?option=com_virtuemart&view=cart&Itemid='.$details->item_id.'&lang='.vRequest::getCmd('lang',''));
				}
				break;
			case 'callback':
				$uuid = vRequest::getString('uuid');
				$pm = vRequest::getInt('pm');

				if (!($method = $this->getVmPluginMethod($pm))) {
					return NULL;
				}

				$charge = \SatispayOnline\Charge::get($uuid);

				$order = $charge->metadata->order_id;
				$orderModel = VmModel::getModel('orders');
				$order = $orderModel->getOrder($order);

				if ($charge->status == 'SUCCESS') {
					$details = $order['details']['BT'];

					$dbValues['payment_name'] = 'Satispay';
					$dbValues['order_number'] = $details->order_number;
					$dbValues['virtuemart_paymentmethod_id'] = $details->virtuemart_paymentmethod_id;
					// $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
					// $dbValues['cost_min_transaction'] = $method->cost_min_transaction;
					// $dbValues['cost_percent_total'] = $method->cost_percent_total;
					$dbValues['payment_currency'] = shopFunctions::getCurrencyByID($details->order_currency, 'currency_code_3');
					$dbValues['satispay_transaction'] = $charge->id;
					// $dbValues['email_currency'] = $email_currency;
					// $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
					// $dbValues['tax_id'] = $method->tax_id;
					$this->storePSPluginInternalData($dbValues);

					$order_data = array();
					$order_data['order_status'] = 'C';
					$order_data['customer_notified'] = 1;
					$orderModel->updateStatusForOneOrder($details->virtuemart_order_id, $order_data, true);
				}
				break;
		}
		echo '';
		exit;
	}

	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Satispay Table');
	}

	function getTableSQLFields() {
		$SQLfields = array(
			'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'staging' => 'smallint(1)',
			'security_bearer' => 'varchar(255)'
		);
		return $SQLfields;
	}

	protected function checkConditions($cart, $method, $cart_prices) {
		return true;
	}

	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {
		return $this->OnSelectCheck($cart);
	}

	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {
		return $this->setOnTablePluginParams ($name, $id, $table);
	}

	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}
}

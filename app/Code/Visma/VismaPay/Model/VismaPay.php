<?php

namespace Visma\VismaPay\Model;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class VismaPay extends \Magento\Payment\Model\Method\AbstractMethod
{

protected $_code = 'visma_pay';

protected $_canUseForMultishipping  = false;
protected $_isGateway = true;
protected $_canAuthorize = true;
protected $_canCapture = true;
protected $_canCapturePartial = false;
protected $_canCaptureOnce = true;
protected $_canRefund = false;
protected $_canVoid = false;
protected $_canUseInternal = false;
protected $_canUseCheckout = true;
protected $_isInitializeNeeded = true;

protected $_urlBuilder;
protected $_exception;
protected $_transactionRepository;
protected $_transactionBuilder;
protected $_storeManager;
protected $_checkoutSession;

protected $allowed_currencies = array('EUR');

public function __construct(
	\Magento\Framework\UrlInterface $urlBuilder,
	\Magento\Framework\Exception\LocalizedExceptionFactory $exception,
	\Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
	\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
	\Magento\Store\Model\StoreManagerInterface $storeManager,
	\Visma\VismaPay\Model\Config $config,
	\Magento\Framework\Model\Context $context,
	\Magento\Framework\Registry $registry,
	\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
	\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
	\Magento\Payment\Helper\Data $paymentData,
	\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
	\Magento\Payment\Model\Method\Logger $logger,
	\Magento\Checkout\Model\Session $checkoutSession,
	\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
	\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
	array $data = []
	) 
{
	$this->_urlBuilder = $urlBuilder;
	$this->_exception = $exception;
	$this->_transactionRepository = $transactionRepository;
	$this->_transactionBuilder = $transactionBuilder;
	$this->config = $config;
	$this->_storeManager = $storeManager;
	$this->_checkoutSession = $checkoutSession;

	parent::__construct(
		$context,
		$registry,
		$extensionFactory,
		$customAttributeFactory,
		$paymentData,
		$scopeConfig,
		$logger,
		$resource,
		$resourceCollection,
		$data
	);
	
	$embedded = $this->_scopeConfig->getValue('payment/visma_pay/embedded', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	$vp_active = $this->_scopeConfig->getValue('payment/visma_pay/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

	if($this->_code != 'visma_pay')
	{
		$bank_payments = $this->_scopeConfig->getValue('payment/visma_pay/bank_payments', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$creditcards_payments = $this->_scopeConfig->getValue('payment/visma_pay/creditcards_payments', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$invoice_payments = $this->_scopeConfig->getValue('payment/visma_pay/invoice_payments', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$wallet_payments = $this->_scopeConfig->getValue('payment/visma_pay/wallet_payments', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$laskuyritykselle = $this->_scopeConfig->getValue('payment/visma_pay/laskuyritykselle', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

		$invoice_methods = array('visma_pay_joustoraha');
		$bank_methods = array(
			'visma_pay_osuuspankki',
			'visma_pay_nordea',
			'visma_pay_danskebank',
			'visma_pay_spankki',
			'visma_pay_alandsbanken',
			'visma_pay_paikallisosuuspankki',
			'visma_pay_handelsbanken',
			'visma_pay_aktia',
			'visma_pay_saastopankki',
			'visma_pay_omasaastopankki'
		);
		
		$wallet_methods = array(
			'visma_pay_mobilepay',
			'visma_pay_masterpass',
			'visma_pay_pivo',
			'visma_pay_siirto'
		);

		if($embedded == 1 && $vp_active == 1)
		{
			if(in_array($this->_code, $invoice_methods) && $invoice_payments != 1)
				return false;
			else if($this->_code == 'visma_pay_creditcards' && $creditcards_payments != 1)
				return false;
			else if($this->_code == 'visma_pay_laskuyritykselle' && $laskuyritykselle != 1)
				return false;
			else if(in_array($this->_code, $bank_methods) && $bank_payments != 1)
				return false;
			else if(in_array($this->_code, $wallet_methods) && $wallet_payments != 1)
				return false;
		}
		else
			return false;
	}
	else if($this->_code == 'visma_pay' && $embedded == 1 && $vp_active == 1)
	{
		return false;
	}
}

public function canUseForCurrency($currencyCode)
{
	$limitcurrency = $this->_scopeConfig->getValue('payment/visma_pay/limitcurrency', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

	if ($currencyCode !== "EUR" && $limitcurrency == 1)
		return false;
	else
		return $this->checkIfAvailable($currencyCode);
}

public function checkIfAvailable($currencyCode)
{
	$payment_methods = json_decode($this->_checkoutSession->getData('payment_methods'));
	
	if (empty($payment_methods) && $this->_code == 'visma_pay')
	{
		$response = $this->getPaymentMethodsForCurrency($currencyCode);
		if (!$response || empty($response['payment_methods']))
			return false;
		else
			$payment_methods = json_decode($response['payment_methods']);
	}

	if ($this->_code == 'visma_pay' && !empty($payment_methods))
		return true;

	$name_arr = explode("_", $this->_code);
	$name = $name_arr[2];
	if(is_array($payment_methods))
	{
		foreach ($payment_methods as $payment_method)
		{
			if ($payment_method->selected_value == $name)
				return true;
		}
	}
	
	return false;
}

public function getPaymentMethodsForCurrency($currency)
{
	$api_key = $this->_scopeConfig->getValue('payment/visma_pay/api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	$private_key = $this->_scopeConfig->getValue('payment/visma_pay/private_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	$data = array(
		'version' => "2",
		'api_key' => $api_key,
		'currency' => $currency,
		'authcode' => strtoupper(hash_hmac('sha256', $api_key, $private_key))
	);
	$ctype = array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($data)));
	$curl_error = "";
	$pm = json_decode($this->curl($this->config->getDPMUrl(), $ctype, json_encode($data), $curl_error));
	if (isset($pm) && $pm->result == 0)
	{
		if (!empty($pm->payment_methods))
		{
			$this->_checkoutSession->setData('payment_methods', json_encode($pm->payment_methods));
			return array("result" => true, "payment_methods" => json_encode($pm->payment_methods));
		}
	}
	return array("result" => false);
}

public function initialize($paymentAction, $stateObject)
{
	$payment = $this->getInfoInstance();

	$order = $payment->getOrder();
	$order->setCanSendNewEmailFlag(false);
	$order->setCustomerNoteNotify(false);

	$stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
	$stateObject->setStatus('new');
	$stateObject->setIsNotified(false);
}
public function getCheckoutUrl($order, $storeId = null)
{
	$orderStatus = $order->getStatus();

	$complete_statuses = array(
		\Magento\Sales\Model\Order::STATE_CANCELED,
		\Magento\Sales\Model\Order::STATE_PROCESSING,
		\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
	);

	if (in_array($orderStatus, $complete_statuses))
		return array("url" => $this->getFailureUrl());

	$payment = $order->getPayment();
	if (!$payment)
		return array("url" => $this->getFailureUrl());
		
	$vp_auth_url = $this->config->getAuthUrl();
	$vp_token_url = $this->config->getTokenUrl();
	$version = "w3.1";
	$amount = (int) round($order->getBaseGrandTotal() * 100);
	$currency = $this->_storeManager->getStore()->getBaseCurrency()->getCode();
	$om = \Magento\Framework\App\ObjectManager::getInstance();
	$resolver = $om->get('Magento\Framework\Locale\Resolver');
	$lang = substr($resolver->getLocale(),0,2);
	
	if(!in_array($lang, array('fi', 'sv', 'en', 'ru')))
		$lang = "en";

	$api_key = $this->getConfigData('api_key');
	$private_key = $this->getConfigData('private_key');
	$bank_payments = $this->getConfigData('bank_payments');
	$creditcards_payments = $this->getConfigData('creditcards_payments');
	$invoice_payments = $this->getConfigData('invoice_payments');
	$wallet_payments = $this->getConfigData('wallet_payments');
	$laskuyritykselle = $this->getConfigData('laskuyritykselle');
	$orderid_prefix = $this->getConfigData('orderid_prefix');
	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	$productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
	$magento_version = $productMetadata->getVersion();
	$plugin_version = $this->config->getPluginVersion();

	$orderId = $order->getIncrementId();
	if ($orderid_prefix == "")
		$vp_order_id = $orderId . "_" . time();
	else
		$vp_order_id = $orderid_prefix . "_" . $orderId . "_" . time();
	$payment->setAdditionalInformation("vp_order_id", $vp_order_id)->save();
	$returnAddress =  $this->_urlBuilder->getUrl("visma_pay/checkout/checkreturn", ['_secure' => true]) . "?id=" . $orderId;
	$notifyAddress = $this->_urlBuilder->getUrl("visma_pay/checkout/checkreturn", ['_secure' => true]) . "?id=" . $orderId . "&notify=1";
	$payment_method = array(
		'type' => 'e-payment',
		'return_url' => $returnAddress,
		"notify_url" => $notifyAddress,
		"lang" => $lang
	);

	$selected = array();

	$selected_method = $order->getPayment()->getMethod();
	$selected_value = explode("_", $selected_method);
	
	if($selected_method != 'visma_pay' && $selected_method != '' && isset($selected_value[2]))
		array_push($selected,$selected_value[2]);
	else
	{
		$payment_methods = json_decode($this->_checkoutSession->getData('payment_methods', true));

		if(in_array($currency, $this->allowed_currencies))
		{
			if ($creditcards_payments == 1)
				array_push($selected,"creditcards");
			if ($wallet_payments == 1)
				array_push($selected,"wallets");
			if ($bank_payments == 1)
				array_push($selected,"banks");
			if ($invoice_payments == 1)
				array_push($selected,"creditinvoices");
			if ($laskuyritykselle == 1)
				array_push($selected,"laskuyritykselle");
		}
		else if($limitcurrency == 0)
		{
			if (empty($payment_methods))
			{
				$response = $this->getPaymentMethodsForCurrency($currencyCode);
				if (!$response || empty($response->payment_methods))
					return array("url" => $this->getFailureUrl());
				else
					$payment_methods = json_decode($response->payment_methods);
				
				foreach ($payment_methods as $method)
				{
					$key = $method->selected_value;
					if($method->group == 'creditcards')
						$key = strtolower($method->name);

					if($method->group == 'creditcards'  && $creditcards_payments == 1)
					{
						$selected[] = $method->group; //creditcards
					}
					else if($method->group == 'wallets' && $wallet_payments == 1)
					{
						$selected[] = $method->selected_value;
					}
					else if($method->group == 'banks' && $bank_payments == 1)
					{
						$selected[] = $method->selected_value;
					}
					else if($method->group == 'creditinvoices')
					{
						if($method->selected_value == 'laskuyritykselle' && $laskuyritykselle == 1)
							$selected[] = $method->selected_value;
						else if($invoice_payments == 1)
							$selected[] = $method->selected_value;
					}
				}
			}
		}
		else
			return array("url" => $this->getFailureUrl());

	}

	if (count($selected) > 0)
		$payment_method["selected"] = $selected;
	$shipping_tax_percent = ($order->getBaseShippingAmount() == 0) ? 0 : round(100 * ($order->getBaseShippingTaxAmount() / $order->getBaseShippingAmount()));
	$products = array();
	$product_amount = 0;
	$send_items = $this->getConfigData('send_items');
	if ($send_items == 1)
	{
		foreach ($order->getAllVisibleItems() as $item) 
		{
			$item_tax_percent = ($item->getProduct()->getTypeID() == 'bundle' && $item->getBasePrice() != 0) ? round(100 * (($item->getBasePriceInclTax() - $item->getBasePrice()) / $item->getBasePrice())) : $item->getTaxPercent();
			$product = array(
				'title' => $item->getName(),
				'id' => $item->getSku(),
				'count' => (int)$item->getQtyOrdered(),
				'pretax_price' => (int)(round($item->getBasePrice()*100)),
				'price' => (int)(round($item->getBasePriceInclTax()*100)),
				'tax' => (int)$item_tax_percent,
				'type' => 1
			);
			$product_amount += $product['price'] * $product['count'];
			array_push($products, $product);
		}
		if ($order->getBaseDiscountAmount() != 0)
		{
			$discount_pretax = -1 * (abs($order->getBaseDiscountAmount()) - abs($order->getBaseDiscountTaxCompensationAmount()));
			$discount_tax_pct = ($discount_pretax == 0) ? 0 : round(100 * (abs($order->getBaseDiscountTaxCompensationAmount()) / abs($discount_pretax)));
			$product = array(
				'title' => $order->getDiscountDescription(),
				'id' => "discount",
				'count' => 1,
				'pretax_price' => (int)(round($discount_pretax*100)),
				'price' => (int)(round($order->getBaseDiscountAmount()*100)),
				'tax' => (int)$discount_tax_pct,
				'type' => 4
			);
			$product_amount += $product['price'];
			array_push($products, $product);

		}
		if ($order->getShippingDescription() !== NULL)
		{
			$product = array(
				'title' => $order->getShippingDescription(),
				'id' => $order->getShippingMethod(),
				'count' => 1,
				'pretax_price' => (int)(round($order->getBaseShippingAmount()*100)),
				'price' => (int)(round($order->getBaseShippingInclTax()*100)),
				'tax' => (int)$shipping_tax_percent,
				'type' => 2
			);
			$product_amount += $product['price'];
			array_push($products, $product);
		}
	}
	$authCode = strtoupper(hash_hmac('sha256', $api_key . "|" . $vp_order_id, $private_key));
	$data = array(
		'version' => $version,
		'api_key' => $api_key,
		'order_number' => $vp_order_id,
		'amount' => $amount,
		'currency' => $currency,
		'payment_method' => $payment_method,
		'authcode' => $authCode
	);

	if ((count($products) > 0) && ($product_amount == $amount))
		$data['products'] = $products;

	$customer_data = $order->getBillingAddress();
	$customer_shipping_data = $order->getShippingAddress();
	
	$customer_info = array(
		'firstname' => $customer_data->getFirstname(),
		'lastname' => $customer_data->getLastname(), 
		'email' => $customer_data->getEmail(),
		'address_street' => $customer_data->getStreetLine(1),
		'address_city' => $customer_data->getCity(),
		'address_zip' => $customer_data->getPostcode(),
		'address_country' => $customer_data->getCountryId()
	);

	if($customer_shipping_data)
	{
		$customer_shipping_info = array(
			'shipping_firstname' => $customer_shipping_data->getFirstname(),
			'shipping_lastname' => $customer_shipping_data->getLastname(), 
			'shipping_email' => $customer_shipping_data->getEmail(),
			'shipping_address_street' => $customer_shipping_data->getStreet1(),
			'shipping_address_city' => $customer_shipping_data->getCity(),
			'shipping_address_zip' => $customer_shipping_data->getPostcode(),
			'shipping_address_zip' => $customer_shipping_data->getPostcode(),
			'shipping_address_country' => $customer_shipping_data->getCountryId()
		);

		$customer_info = array_merge($customer_info, $customer_shipping_info);
	}

	$data['customer'] = $customer_info;
	$send_receipt = $this->getConfigData('send_receipt');

	$data["plugin_info"] = "Magento2|" . $magento_version . "|" . $plugin_version;
	if ($send_receipt == 1)
		$data["email"] = $customer_data->getEmail();

	try {
		$ctype = array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($data)));
		$curl_error = '';
		$response = $this->curl($vp_auth_url, $ctype, json_encode($data), $curl_error);
		$response = json_decode($response);
		if (isset($response->result) && $response->result === 0)
		{
			$token = isset($response->token) ? $response->token : "";
			$message = __("Payment forwarded to Visma Pay gateway with order number %1", $vp_order_id);
			$order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
			$order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT));			
			$order->addStatusHistoryComment($message);
			$order->save();
			return array("url" => $vp_token_url . "/" . $token);
		}
		else if (isset($response->result) && $response->result === 1)
		{
			$message = __("Validation error in Visma Pay gateway. Please check private key and api key.");
			if(isset($response->errors) && count($response->errors) > 0)
			{
				$message .= "<br>".__("API returned errors").":<br>";
				foreach($response->errors as $i => $returned_error)
				{
					$message .= ($i+1).". ".$returned_error."<br>";
				}
			}
			$order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
			$order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_CANCELED));
			$order->addStatusHistoryComment($message);
			$order->save();


		}
		else if (isset($response->result) && $response->result === 2) 
		{
			$message = __("Visma Pay - rejected, duplicate order number - order number - %1", $vp_order_id);
			$order->addStatusHistoryComment($message);

			$order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
			$order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_CANCELED));
			$order->save();
		}
		else 
		{
			$order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
			$order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_CANCELED));
			$message = __("Visma Pay - curl error") . ': ' . $curl_error;
			$order->addStatusHistoryComment($message);
			$order->save();

		}
		$this->_checkoutSession->restoreQuote();
		return array("url" => $this->getFailureUrl(), "error" => __("Error trying to create payment using Visma Pay Payment Gateway."));
	} catch (Exception $e) {
		$order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
		$order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_CANCELED));
		$message = __("Visma Pay - curl error");
		$order->addStatusHistoryComment($message);
		$order->save();
		$this->_checkoutSession->restoreQuote();
		return array("url" => $this->getFailureUrl(), "error" => __("Error trying to create payment using Visma Pay Payment Gateway."));
	}

}

public function getSuccessUrl()
{
	return $this->_urlBuilder->getUrl("checkout/onepage/success", ['_secure' => true]);
}

public function getFailureUrl()
{
	return $this->_urlBuilder->getUrl("checkout/cart", ['_secure' => true]);
}

public function curl($url, $ctype, $posts, &$error = null)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $ctype);

	curl_setopt($ch, CURLOPT_POSTFIELDS, $posts);
	$curl_response = curl_exec ($ch);

	if(!$curl_response)
		$error = curl_error($ch) . ' (error code: ' . curl_errno($ch) . ')';

	curl_close ($ch);
	return $curl_response;
}



}

<?php
namespace Visma\VismaPay\Model;

class Config
{
	protected $_scopeConfigInterface;
	protected $customerSession;
	protected $gateway_url = "https://www.vismapay.com/";
	protected $assetRepo;
	protected $request;

	protected $plugin_version = "1.0.0";

	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Backend\Model\Session\Quote $sessionQuote,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Framework\View\Asset\Repository $assetRepo,
		\Magento\Framework\App\RequestInterface $request
		)
	{
		$this->_scopeConfigInterface = $configInterface;
		$this->customerSession = $customerSession;
		$this->sessionQuote = $sessionQuote;
		$this->_checkoutSession = $checkoutSession;
		$this->assetRepo = $assetRepo;
		$this->request = $request;
	}

	public function getApiUrl()
	{
		return $this->gateway_url . 'pbwapi/';
	}
	public function getAuthUrl()
	{
		return $this->gateway_url . 'pbwapi/auth_payment';
	}
	public function getTokenUrl()
	{
		return $this->gateway_url . 'pbwapi/token';
	}
	public function getCaptureUrl()
	{
		return $this->gateway_url . 'pbwapi/capture';
	}
	public function getDPMUrl()
	{
		return $this->gateway_url . 'pbwapi/merchant_payment_methods';
	}
	public function getStatusCheckUrl()
	{
		return $this->gateway_url . 'pbwapi/check_payment_status';
	}
	public function getPluginVersion()
	{
		return $this->plugin_version;
	}

	public function getEmbedSetting()
	{
		$embed = $this->_scopeConfigInterface->getValue('payment/visma_pay/embedded', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$active = $this->_scopeConfigInterface->getValue('payment/visma_pay/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		if($active == 1)
			return $embed;
		else
			return 0;
	}
	public function getPaymentMethods()
	{
		$bank_payments = $this->_scopeConfigInterface->getValue('payment/visma_pay/bank_payments');
		$creditcards_payments = $this->_scopeConfigInterface->getValue('payment/visma_pay/creditcards_payments');
		$invoice_payments = $this->_scopeConfigInterface->getValue('payment/visma_pay/invoice_payments');
		$laskuyritykselle = $this->_scopeConfigInterface->getValue('payment/visma_pay/laskuyritykselle');
		$wallet_payments = $this->_scopeConfigInterface->getValue('payment/visma_pay/wallet_payments');

		$methods = array();

		if ($bank_payments == 1)  array_push($methods,__("Banks"));
		if ($creditcards_payments == 1) array_push($methods,__("Creditcards"));
		if ($invoice_payments == 1) array_push($methods,__("Credit Invoices"));
		if ($laskuyritykselle == 1) array_push($methods,__("Enterpay-yrityslasku"));
		if ($wallet_payments == 1) array_push($methods,__("Wallets"));
				
		$string = implode(", ", $methods);
		if ($string !== "")
			$string = " (" . $string . ")";
		return $string;
	}
	public function getDescription()
	{
		$description = $this->_scopeConfigInterface->getValue('payment/visma_pay/description');
		return __($description);
	}

	public function getBpfLogo()
	{
		$params = array('_secure' => $this->request->isSecure());
		return $this->assetRepo->getUrlWithParams('Visma_VismaPay::images', $params);
	}
}
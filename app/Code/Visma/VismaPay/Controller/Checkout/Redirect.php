<?php

namespace Visma\VismaPay\Controller\Checkout;

class Redirect extends \Magento\Framework\App\Action\Action
{

	protected $_checkoutSession;
	protected $_paymentMethod;
	protected $messageManager;

	public function __construct(
	\Magento\Framework\App\Action\Context $context,
	\Magento\Checkout\Model\Session $checkoutSession,
	\Visma\VismaPay\Model\VismaPay $paymentMethod
	) {
		$this->_paymentMethod = $paymentMethod;
		$this->_checkoutSession = $checkoutSession;
		$this->messageManager = $context->getMessageManager();

		parent::__construct($context);
	}

	public function execute()
	{
		$response = $this->_paymentMethod->getCheckoutUrl($this->getOrder());
		if (isset($response["error"]))
			$this->messageManager->addError($response["error"]);
		$this->getResponse()->setRedirect($response["url"]);
	}

	private function getOrder()
	{
		return $this->_checkoutSession->getLastRealOrder();
	}
}

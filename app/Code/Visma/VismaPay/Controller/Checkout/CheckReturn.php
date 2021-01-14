<?php
namespace Visma\VismaPay\Controller\Checkout;

class CheckReturn extends \Magento\Framework\App\Action\Action
{
	protected $_model;
	protected $_orderSender; 
	protected $_checkoutSession;
	protected $_messageManager;
	protected $_orderRepository;
	protected $_orderStatusHistoryRepository;
	protected $_searchCriteriaBuilder;
	private $vp_extra_info;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Visma\VismaPay\Model\VismaPay $model,
		\Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
		\Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
		) {
		$this->_model = $model;
		$this->_orderSender = $orderSender;
		$this->_checkoutSession = $checkoutSession;
		$this->_orderRepository = $orderRepository;
		$this->_messageManager = $messageManager;
		$this->_orderStatusHistoryRepository = $orderStatusHistoryRepository;
		$this->_searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->vp_extra_info = "";
		parent::__construct($context);
	}

	public function execute()
	{
		$content = $this->getRequest()->getParams();
		$returnCode = isset($content['RETURN_CODE']) ? $content['RETURN_CODE'] : '';
		$orderNumber = isset($content['ORDER_NUMBER']) ? $content['ORDER_NUMBER'] : '';
		$authCode = isset($content['AUTHCODE']) ? $content['AUTHCODE'] : '';   
		$settled =  isset($content['SETTLED']) ? $content['SETTLED'] : '';
		$orderIncrementId = isset($content['id']) ? $content['id'] : '';
		$notify = isset($content['notify']) ? $content['notify'] : 0;

		if ($returnCode === '' || empty($orderNumber) || empty($authCode))
			exit();

		$merchantPrivateKey = $this->_model->getConfigData('private_key');

		$authCodeConfirm = $returnCode.'|'.$orderNumber;

		if(isset($content['SETTLED']))
			$authCodeConfirm .= '|'. $content['SETTLED'];
		if(isset($content['CONTACT_ID']))
			$authCodeConfirm .= '|'. $content['CONTACT_ID'];
		if(isset($content['INCIDENT_ID']))
			$authCodeConfirm .= '|'. $content['INCIDENT_ID'];

		$authCodeConfirm = strtoupper(hash_hmac('sha256', $authCodeConfirm, $merchantPrivateKey));
		if (isset($orderIncrementId) && $orderIncrementId !== "")
		{
			$order = $this->visma_pay_get_order_by_increment($orderIncrementId);
			$vp_order_number = $order->getPayment()->getAdditionalInformation('vp_order_id');

			if ($orderNumber != $vp_order_number) {
				$this->cancel($order, $returnCode, $notify, __("Order number mismatch"));
			}

			if ($order->getStatus() == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) 
			{
				if ($authCodeConfirm == $authCode)
				{
					$this->statusCheck($order);
					switch ($returnCode) {
						case 0:
							$this->success($order, $settled, $notify);
							break;
						case 1:
							$this->cancel($order, $returnCode, $notify);            
							break;
						case 4:
							$this->review($order); 
							break;
						case 10:
							$this->cancel($order, $returnCode, $notify);
							break;
						default:
							$this->cancel($order, $returnCode, $notify);         
							break;
					}
				} 
				else
					$this->cancel($order, $returnCode, $notify, __("Response MAC code not matching."));
			}


			else if ($order->getStatus() == \Magento\Sales\Model\Order::STATE_PROCESSING)
			{
				
				if ($notify == 0)
				{
					$this->getResponse()->setRedirect(
						$this->_model->getSuccessUrl()
						);
				}
				else
					echo "OK";          
				
			}
			else
			{
				if ($notify == 0)
				{
					if ($order->getStatus() == \Magento\Sales\Model\Order::STATE_CANCELED)
					{
						$comment = __('Payment canceled');
					}
					else
						$comment = __('Visma Pay - Order is in wrong state.');
					$this->_messageManager->addNotice($comment);
					$this->getResponse()->setRedirect(
						$this->_model->getFailureUrl()
					);
				}
				else
					echo "OK";
				
			}
		}
	}

	private function cancel($order, $returnCode, $notify, $error = null) 
	{
		$vp_order_number = $order->getPayment()->getAdditionalInformation('vp_order_id');

		$current_status = $order->getStatus();
		$complete_statuses = array(
			\Magento\Sales\Model\Order::STATE_CANCELED,
			\Magento\Sales\Model\Order::STATE_PROCESSING,
			\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
		);

		if ($returnCode == 1)
			$comment = __("Visma Pay - payment cancelled - order number - %1", $vp_order_number);
		else if ($returnCode == 10)
			$comment = __("Visma Pay - payment with order number %1 not done, reason being a maintenance break in our service", $vp_order_number);
		else
		{
			if ($error)
				$comment = $error;
			else
				$comment = __('Visma Pay - Unknown error');
		}

		// Dont cancel an order in a completed status
		if (in_array($current_status, $complete_statuses))
			$comment = __('Visma Pay - Order is in wrong state.');
		else
			$order->cancel();

		$customerComment = $comment;
		$comment .= $this->vp_extra_info;

		$order->save();
		$this->_orderStatusHistoryRepository->save($order->addStatusHistoryComment($comment, $order->getStatus()));
		if ($notify == 0)
		{
			$this->_getCheckoutSession()->restoreQuote();
			$this->_messageManager->addNotice($customerComment);
			$this->getResponse()->setRedirect($this->_model->getFailureUrl());
		}
		else
			echo "OK";
	}

	private function _getCheckoutSession()
	{
		return $this->_checkoutSession;
	}

	private function success($order, $settled, $notify)
	{
		$vp_order_number = $order->getPayment()->getAdditionalInformation('vp_order_id');
		$payment = $order->getPayment();
		if ($settled == 1)
		{
			$comment = __("Payment authorized and settled. Visma Pay order number %1", $vp_order_number) .'.'. PHP_EOL;
			$comment .= $this->vp_extra_info;
			$payment->setPreparedMessage($comment);
			$payment->setAdditionalInformation('settled', 1);
			$payment->capture();
		}
		else if($settled == 0)
		{
			$comment = __("Payment authorized. Settle the payment in Visma Pay merchant portal. Visma Pay order number %1", $vp_order_number) .'.'. PHP_EOL;
			$comment .= $this->vp_extra_info;
			$payment->setPreparedMessage($comment);
			$payment->setAdditionalInformation('settled', 0);
			$payment->authorize($payment, $order->getBaseGrandTotal());
		}
		
		try
		{
			$this->_orderSender->send($order);
		}
		catch(Exception $e)
		{
		}

		$order->save();

		if ($notify == 0)
		{
			$this->getResponse()->setRedirect(
				$this->_model->getSuccessUrl()
				);
		}
		else
			echo "OK";
		
	}

	private function review($order)
	{
		$order->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
		$vp_order_number = $order->getPayment()->getAdditionalInformation('vp_order_id');
		$comment = __("Visma Pay - error in payment. Refresh payment status manually in merchant panel - order number %1", $vp_order_number);
		$comment .= $this->vp_extra_info;
		$this->_orderStatusHistoryRepository->save($order->addStatusHistoryComment($comment, $order->getStatus()));
		$order->save();

		$this->getResponse()->setRedirect(
			$this->_model->getFailureUrl()
			);
	}

	private function statusCheck($order)
	{
		$vp_status_check_url = $this->_model->config->getStatusCheckUrl();
		$merchantApiKey = $this->_model->getConfigData('api_key');
		$merchantPrivateKey = $this->_model->getConfigData('private_key');
		$vp_order_number = $order->getPayment()->getAdditionalInformation('vp_order_id');

		$authCode = strtoupper(hash_hmac('sha256', $merchantApiKey . "|" . $vp_order_number, $merchantPrivateKey));

		$data = array('version' => 'w3.1', 'api_key' => $merchantApiKey, 'order_number' => $vp_order_number, 'authcode' => $authCode);
		$ctype = array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($data)));
		try
		{
			$response = $this->curl($vp_status_check_url, $ctype, json_encode($data));
			$result = json_decode($response);
			if(!is_object($result))
				return false;
		}
		catch (Exception $e) {
			return false;
		}

		if(isset($result->source->object) && $result->source->object === 'card')
		{
			$this->vp_extra_info .= "<br>". __('Chosen payment method: Card payment'). "<br>";
			$this->vp_extra_info .= "<br>". __('Card payment info: ') . "<br>";

			if(isset($result->source->card_verified))
			{
				$vp_verified = $this->visma_pay_translate_verified_code($result->source->card_verified);
				$this->vp_extra_info .= isset($vp_verified) ? __('Verified: ') . $vp_verified . "<br>" : '';
			}

			$this->vp_extra_info .= isset($result->source->card_country) ? __('Card country: ') . $result->source->card_country . "<br>" : '';
			$this->vp_extra_info .= isset($result->source->client_ip_country) ? __('Client IP country: ') . $result->source->client_ip_country . "<br>" : '';

			if(isset($result->source->error_code))
			{
				$vp_error = $this->visma_pay_translate_error_code($result->source->error_code);
				$this->vp_extra_info .= isset($vp_error) ? __('Error: ') . $vp_error . "<br>" : '';
			}								
		}
		elseif (isset($result->source->brand))
			$this->vp_extra_info .= "<br>". __('Chosen payment method: ') . ' ' . $result->source->brand . "<br>";
	}

	private function visma_pay_translate_verified_code($vp_verified_code)
	{
		switch ($vp_verified_code)
		{
			case 'Y':
				return ' Y - ' . __('3-D Secure was used.');
			case 'N':
				return ' N - ' . __('3-D Secure was not used.');
			case 'A':
				return ' A - ' . __('3-D Secure was attempted but not supported by the card issuer or the card holder is not participating.');
			default:
				return null;
		}
	}

	private function visma_pay_translate_error_code($vp_error_code)
	{
		switch ($vp_error_code)
		{
			case '04':
				return ' 04 - ' . __('The card is reported lost or stolen.');
			case '05':
				return ' 05 - ' . __('General decline. The card holder should contact the issuer to find out why the payment failed.');
			case '51':
				return ' 51 - ' . __('Insufficient funds. The card holder should verify that there is balance on the account and the online payments are actived.');
			case '54':
				return ' 54 - ' . __('Expired card.');
			case '61':
				return ' 61 - ' . __('Withdrawal amount limit exceeded.');
			case '62':
				return ' 62 - ' . __('Restricted card. The card holder should verify that the online payments are actived.');
			case '1000':
				return ' 1000 - ' . __('Timeout communicating with the acquirer. The payment should be tried again later.');
			default:
				return null;
		}
	}

	private function curl($url, $ctype, $posts)
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
		curl_close ($ch);
		return $curl_response;
	}

	private function visma_pay_get_order_by_increment($increment_id)
	{
		$criteria = $this->_searchCriteriaBuilder->addFilter('increment_id', $increment_id)->create();
		$order = null;
		try
		{
			$orders = $this->_orderRepository->getList($criteria);
			$order = $orders->getFirstItem();
		} 
		catch (Exception $exception)
		{
			return false;
		}
		return $order;
	}
}


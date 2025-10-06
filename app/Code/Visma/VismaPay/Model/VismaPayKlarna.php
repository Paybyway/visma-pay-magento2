<?php

namespace Visma\VismaPay\Model;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class VismaPayKlarna extends VismaPay
{
	protected $_code = 'visma_pay_klarna';
}

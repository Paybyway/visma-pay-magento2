<?php

namespace Visma\VismaPay\Model;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class VismaPayMobilepay extends VismaPay
{
	protected $_code = 'visma_pay_mobilepay';
}

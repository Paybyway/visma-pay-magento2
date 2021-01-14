<?php
namespace Visma\VismaPay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;


class ConfigProvider implements ConfigProviderInterface
{
	protected $methods = [];
	protected $escaper;
	protected $config;

	public function __construct(
		PaymentHelper $paymentHelper,
		Escaper $escaper,
		Config $config
		) {
		$this->escaper = $escaper;
		$this->config = $config;

	}

	public function getConfig()
	{
		$outConfig = [];
		$outConfig['payment']['visma_pay']['embed'] = $this->config->getEmbedSetting();
		$outConfig['payment']['visma_pay']['description'] = $this->config->getDescription();
		$outConfig['payment']['visma_pay']['payment_methods'] = $this->config->getPaymentMethods();
		$outConfig['payment']['visma_pay']['vp_logo_src'] = $this->config->getBpfLogo();
		return $outConfig;
	}

}

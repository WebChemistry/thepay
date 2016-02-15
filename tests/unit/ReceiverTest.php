<?php

class ReceiverTest extends \Codeception\TestCase\Test {

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	/** @var array */
	protected $params = [
		'merchantId' => 1, 'accountId' => 1,
		'currency' => 'CZK', 'methodId' => 1, 'description' => '',
		'merchantData' => '', 'status' => TpReturnedPayment::STATUS_OK,
		'paymentId' => 1, 'ipRating' => '', 'isOffline' => TRUE,
		'needConfirm' => FALSE, 'value' => 1
	];

	/** @var array */
	private $requiredArgs = [
		"merchantId", "accountId", "value", "currency", "methodId", "description", "merchantData",
		"status", "paymentId", "ipRating", "isOffline", "needConfirm", "password"
	];

	/**
	 * @param array $args
	 * @return \Nette\Http\UrlScript
	 */
	protected function getUrl($args = []) {
		$url = new \Nette\Http\UrlScript();
		$args = array_merge($this->params, $args);
		if (!array_key_exists('signature', $args)) {
			$args['signature'] = $this->getCorrectSignature(array_merge([
				'password' => 'my$up3rsecr3tp4$$word',
				'merchantId' => 1,
				'accountId' => 1
			],$args));
		}
		foreach ($args as $name => $value) {
			if (is_array($value)) {
				$value = serialize($value);
			}
			$url->setQueryParameter($name, $value);
		}

		return $url;
	}

	/**
	 * @param array $args
	 * @return string
	 */
	protected function getCorrectSignature($args = []) {
		$out = [];
		foreach ($this->requiredArgs as $name) {
			if (array_key_exists($name, $args) && $args[$name] !== NULL) {
				if (is_array($args[$name])) {
					$args[$name] = serialize($args[$name]);
				}
				$out[] = $name . '=' . $args[$name];
			}
		}

		return md5(implode('&', $out));
	}

	/**
	 * @param array $args
	 * @return \WebChemistry\ThePay\Receiver
	 */
	protected function getReceiver($args = []) {
		$extension = new \WebChemistry\ThePay\DI\ThePayExtension();
		$request = new \Nette\Http\Request($this->getUrl($args));
		$thePay = new \WebChemistry\ThePay\ThePay($extension->defaultValues, $request);

		return $thePay->getReceiver();
	}

	public function testSignature() {
		$this->assertTrue($this->getReceiver()->verify(FALSE));
		$this->assertFalse($this->getReceiver(['signature' => 'xx'])->verify(FALSE));

		$this->tester->assertExceptionThrown('WebChemistry\ThePay\Exception', function () {
			$this->getReceiver(['signature'])->verify();
		});

		$this->tester->assertExceptionThrown('WebChemistry\ThePay\Exception', function () {
			$this->getReceiver(['accountId' => NULL])->verify();
		});
	}

	public function testStatus() {
		$receiver = $this->getReceiver();
		$this->assertTrue($receiver->isSuccess());
		$this->assertFalse($receiver->isCanceled());
		$this->assertFalse($receiver->isError());
		$this->assertFalse($receiver->isUnderPaid());
		$this->assertFalse($receiver->isWaiting());

		$receiver = $this->getReceiver(['status' => TpReturnedPayment::STATUS_CANCELED]);
		$this->assertTrue($receiver->isCanceled());
		$this->assertTrue($receiver->isOffline());
	}

	public function testPrice() {
		$receiver = $this->getReceiver([
			'value' => 'string'
		]);
		$this->assertFalse($receiver->verify(FALSE));
		$this->tester->assertExceptionThrown('WebChemistry\ThePay\Exception', function () use ($receiver) {
			$receiver->verify();
		});
	}

	public function testMerchantData() {
		$data = $this->getReceiver(['merchantData' => 'test'])->getMerchantData();
		$this->assertSame('test', $data);

		$data = $this->getReceiver(['merchantData' => ['test']])->getMerchantData();
		$this->assertSame(['test'], $data);
	}

	public function testOtherParameters() {
		$receiver = $this->getReceiver();
		$this->assertTrue(is_int($receiver->getStatus()));
		$this->assertTrue($receiver->isOffline());
		$this->assertFalse($receiver->getNeedConfirm());
	}

}
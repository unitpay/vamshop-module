<?php 
App::uses('PaymentAppController', 'Payment.Controller');

class UnitpayController extends PaymentAppController {
	public $uses = array('PaymentMethod', 'Order');
	public $module_name = 'Unitpay';
	public $icon = 'unitpay.png';
	
	public function settings ()
	{
		$this->set('data', $this->PaymentMethod->findByAlias($this->module_name));
	}

	public function install()
	{

		$new_module = array();
		$new_module['PaymentMethod']['active'] = '1';
		$new_module['PaymentMethod']['name'] = Inflector::humanize($this->module_name);
		$new_module['PaymentMethod']['icon'] = $this->icon;
		$new_module['PaymentMethod']['alias'] = $this->module_name;

		$new_module['PaymentMethodValue'][0]['payment_method_id'] = $this->PaymentMethod->id;
		$new_module['PaymentMethodValue'][0]['key'] = 'public_key';
		$new_module['PaymentMethodValue'][0]['value'] = '';

		$new_module['PaymentMethodValue'][1]['payment_method_id'] = $this->PaymentMethod->id;
		$new_module['PaymentMethodValue'][1]['key'] = 'secret_key';
		$new_module['PaymentMethodValue'][1]['value'] = '';

		$this->PaymentMethod->saveAll($new_module);
			
		$this->Session->setFlash(__('Module Installed'));
		$this->redirect('/payment_methods/admin/');
	}

	public function uninstall()
	{

		$module_id = $this->PaymentMethod->findByAlias($this->module_name);

		$this->PaymentMethod->delete($module_id['PaymentMethod']['id'], true);
			
		$this->Session->setFlash(__('Module Uninstalled'));
		$this->redirect('/payment_methods/admin/');
	}
	
	public function before_process () 
	{
		$order = $this->Order->read(null,$_SESSION['Customer']['order_id']);

		$public_key_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'public_key')));
		$public_key = $public_key_payment['PaymentMethodValue']['value'];

		$sum = $order_summ = number_format($order['Order']['total'], 2, '.', '');
		$account = $order['Order']['id'];
		$desc = 'Заказ №' . $order['Order']['id'];

		$content = '
		<form action="https://www.unitpay.ru/pay/' . $public_key . '" method="get">
			<input type="hidden" name="sum" value="' . $sum . '">
			<input type="hidden" name="account" value="' . $account . '">
			<input type="hidden" name="desc" value="' . $desc . '">
			<button class="btn btn-default" type="submit" value="{lang}Confirm Order{/lang}"><i class="fa fa-check"></i> {lang}Confirm Order{/lang}</button>
			</form>';


		// Get the default order status
		$default_status = $this->Order->OrderStatus->find('first', array('conditions' => array('default' => '1')));
		$order['Order']['order_status_id'] = $default_status['OrderStatus']['id'];

		// Save the order
		$this->Order->save($order);

		return $content;

	}
	
	public function after_process()
	{
	}

	public function callback()
	{
		$data = $_GET;

		$method = '';
		$params = array();
		if ((isset($data['params'])) && (isset($data['method'])) && (isset($data['params']['signature']))){
			$params = $data['params'];
			$method = $data['method'];
			$signature = $params['signature'];
			if (empty($signature)){
				$status_sign = false;
			}else{
				$status_sign = $this->verifySignature($params, $method);
			}
		}else{
			$status_sign = false;
		}
//    $status_sign = true;
		if ($status_sign){
			switch ($method) {
				case 'check':
					$result = $this->check( $params );
					break;
				case 'pay':
					$result = $this->pay( $params );
					break;
				case 'error':
					$result = $this->error( $params );
					break;
				default:
					$result = array('error' =>
						array('message' => 'неверный метод')
					);
					break;
			}
		}else{
			$result = array('error' =>
				array('message' => 'неверная сигнатура')
			);
		}
		$this->hardReturnJson($result);

	}

	function check( $params )
	{
		$order_id = $params['account'];
		$order = $this->Order->read(null, $order_id);

		if (!$order) {
			$result = array('error' =>
				array('message' => 'заказа не существует')
			);
		}else{

			$total = number_format($order['Order']['total'], 2, '.', '');
			if ((float)$total != (float)$params['orderSum']) {
				$result = array('error' =>
					array('message' => 'не совпадает сумма заказа')
				);
			}else{
				$result = array('result' =>
					array('message' => 'Запрос успешно обработан')
				);
			}
		}
		return $result;
	}
	function pay( $params )
	{
		$order_id = $params['account'];
		$order = $this->Order->read(null, $order_id);

		if (!$order) {
			$result = array('error' =>
				array('message' => 'заказа не существует')
			);
		}else{

			$total = number_format($order['Order']['total'], 2, '.', '');
			if ((float)$total != (float)$params['orderSum']) {
				$result = array('error' =>
					array('message' => 'не совпадает сумма заказа')
				);
			}else{

				$payment_method = $this->PaymentMethod->find('first', array('conditions' => array('alias' => $this->module_name)));
				$order_data = $this->Order->find('first', array('conditions' => array('Order.id' => $order_id)));
				$order_data['Order']['order_status_id'] = $payment_method['PaymentMethod']['order_status_id'];

				$this->Order->save($order_data);

				$result = array('result' =>
					array('message' => 'Запрос успешно обработан')
				);
			}
		}
		return $result;
	}
	function error( $params )
	{
		$order_id = $params['account'];
		$order = $this->Order->read(null, $order_id);

		if (!$order) {
			$result = array('error' =>
				array('message' => 'заказа не существует')
			);
		}else{
			$result = array('result' =>
				array('message' => 'Запрос успешно обработан')
			);
		}
		return $result;
	}
	function getSignature($method, array $params, $secretKey)
	{
		ksort($params);
		unset($params['sign']);
		unset($params['signature']);
		array_push($params, $secretKey);
		array_unshift($params, $method);
		return hash('sha256', join('{up}', $params));
	}
	function verifySignature($params, $method)
	{
		$secret_key_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'secret_key')));
		$secret = $secret_key_payment['PaymentMethodValue']['value'];
		return $params['signature'] == $this->getSignature($method, $params, $secret);
	}
	function hardReturnJson( $arr )
	{
		header('Content-Type: application/json');
		$result = json_encode($arr);
		die($result);
	}
	
}
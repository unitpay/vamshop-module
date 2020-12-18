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
        $new_module['PaymentMethodValue'][0]['key'] = 'domain';
        $new_module['PaymentMethodValue'][0]['value'] = '';

		$new_module['PaymentMethodValue'][1]['payment_method_id'] = $this->PaymentMethod->id;
		$new_module['PaymentMethodValue'][1]['key'] = 'public_key';
		$new_module['PaymentMethodValue'][1]['value'] = '';

		$new_module['PaymentMethodValue'][2]['payment_method_id'] = $this->PaymentMethod->id;
		$new_module['PaymentMethodValue'][2]['key'] = 'secret_key';
		$new_module['PaymentMethodValue'][2]['value'] = '';
		
		$new_module['PaymentMethodValue'][3]['payment_method_id'] = $this->PaymentMethod->id;
		$new_module['PaymentMethodValue'][3]['key'] = 'nds';
		$new_module['PaymentMethodValue'][3]['value'] = 'none';
		
		$new_module['PaymentMethodValue'][4]['payment_method_id'] = $this->PaymentMethod->id;
		$new_module['PaymentMethodValue'][4]['key'] = 'delivery_nds';
		$new_module['PaymentMethodValue'][4]['value'] = 'none';

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

        $domain_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'domain')));
        $domain = $domain_payment['PaymentMethodValue']['value'];

		$public_key_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'public_key')));
		$public_key = $public_key_payment['PaymentMethodValue']['value'];
		
		$secret_key_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'secret_key')));
		$secret_key = $secret_key_payment['PaymentMethodValue']['value'];
		
		$nds_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'nds')));
		$nds = $nds_payment['PaymentMethodValue']['value'];

		$delivery_nds_payment = $this->PaymentMethod->PaymentMethodValue->find('first', array('conditions' => array('key' => 'delivery_nds')));
		$delivery_nds = $delivery_nds_payment['PaymentMethodValue']['value'];
		
		$sum = $order_summ = number_format($order['Order']['total'], 2, '.', '');
		$account = $order['Order']['id'];
		$desc = 'Заказ №' . $order['Order']['id'];
		$currency = $_SESSION['Customer']['currency_code'];
		
		$signature = hash('sha256', join('{up}', array(
            $account,
            $currency,
            $desc,
            $sum,
            $secret_key
        )));

		$items = array();
		
		
		App::import('Model', 'ContentProduct');
		$ContentProduct = new ContentProduct();

		App::import('Model', 'TaxCountryZoneRate');
		$TaxCountryZoneRate = new TaxCountryZoneRate();
			
			
		
		foreach($order["OrderProduct"] as $item) {
			//$ContentProduct = $ContentProduct->find('first', array('conditions' => array('ContentProduct.content_id' => $item['content_id'])));
			//$TaxCountryZoneRate = $TaxCountryZoneRate->find('first', array('conditions' => array('TaxCountryZoneRate.country_zone_id' => $order['Order']['bill_state'], 'TaxCountryZoneRate.tax_id' => $ContentProduct['ContentProduct']['tax_id'])));

			$items[] = array(
				"name" => $item["name"],
				"count" => $item["quantity"],
				//"price" => number_format($item["price"], 2, '.', ''),
				"price" => $item["price"],
				"currency" => $currency,
				"type" => "commodity",
				"nds" => $nds,
				//"nds" => isset($TaxCountryZoneRate["TaxCountryZoneRate"]["rate"]) ? $this->getTaxRates($TaxCountryZoneRate["TaxCountryZoneRate"]["rate"]) : "none"
			);
		}
		
		if($order["Order"]["shipping"] > 0) {
			$items[] = array(
				"name" => "Доставка",
				"count" => 1,
				//"price" => number_format($order["Order"]["shipping"], 2, '.', ''),
				"price" => $order["Order"]["shipping"],
				"currency" => $currency,
				"type" => "service",
				"nds" => $delivery_nds,
			);
		}
		
		$cashItems = base64_encode(json_encode($items));
		
		$content = '
		<form action="https://' . $domain . '/pay/' . $public_key . '" method="get">
			<input type="hidden" name="sum" value="' . $sum . '">
			<input type="hidden" name="account" value="' . $account . '">
			<input type="hidden" name="desc" value="' . $desc . '">
			<input type="hidden" name="currency" value="' . $currency . '">
			<input type="hidden" name="signature" value="' . $signature . '">
			<input type="hidden" name="customerPhone" value="' . preg_replace('/\D/', '', $order["Order"]["phone"]). '">
			<input type="hidden" name="customerEmail" value="' . $order["Order"]["email"] . '">
			<input type="hidden" name="cashItems" value="' . $cashItems . '">
			<button class="btn btn-default" type="submit" value="{lang}Confirm Order{/lang}"><i class="fa fa-check"></i> {lang}Confirm Order{/lang}</button>
			</form>';


		// Get the default order status
		$default_status = $this->Order->OrderStatus->find('first', array('conditions' => array('default' => '1')));
		$order['Order']['order_status_id'] = $default_status['OrderStatus']['id'];

		// Save the order
		$this->Order->save($order);

		return $content;

	}
	
	public function getTaxRates($rate){
        switch (intval($rate)){
            case 10:
                $vat = 'vat10';
                break;
            case 20:
                $vat = 'vat20';
                break;
            case 0:
                $vat = 'vat0';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
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
			if ((float)$total != (float) number_format($params['orderSum'], 2, '.', '')) {
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
			if ((float)$total != (float) number_format($params['orderSum'], 2, '.', '')) {
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
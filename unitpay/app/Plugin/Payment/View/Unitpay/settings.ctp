<?php

echo $this->Form->input('unitpay.domain', array(
    'label' => 'DOMAIN',
    'type' => 'text',
    'value' => $data['PaymentMethodValue'][0]['value']
));

echo $this->Form->input('unitpay.public_key', array(
	'label' => 'PUBLIC KEY',
	'type' => 'text',
	'value' => $data['PaymentMethodValue'][1]['value']
));

echo $this->Form->input('unitpay.secret_key', array(
	'label' => 'SECRET KEY',
	'type' => 'text',
	'value' => $data['PaymentMethodValue'][2]['value']
));

echo $this->Form->input('unitpay.nds', array(
	'label' => 'НДС (none, vat0, vat10, vat20)',
	'type' => 'text',
	'value' => $data['PaymentMethodValue'][3]['value']
));

echo $this->Form->input('unitpay.delivery_nds', array(
	'label' => 'НДС (Доставка) (none, vat0, vat10, vat20)',
	'type' => 'text',
	'value' => $data['PaymentMethodValue'][4]['value']
));
?>
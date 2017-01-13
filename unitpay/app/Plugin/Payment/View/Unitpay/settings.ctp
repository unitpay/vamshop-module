<?php

echo $this->Form->input('unitpay.public_key', array(
	'label' => 'PUBLIC KEY',
	'type' => 'text',
	'value' => $data['PaymentMethodValue'][0]['value']
));

echo $this->Form->input('unitpay.secret_key', array(
	'label' => 'SECRET KEY',
	'type' => 'text',
	'value' => $data['PaymentMethodValue'][1]['value']
));

?>
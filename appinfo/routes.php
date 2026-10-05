<?php

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#driver', 'url' => '/fahrer', 'verb' => 'GET'],
		['name' => 'api#list', 'url' => '/api/v1/records/{kind}', 'verb' => 'GET'],
		['name' => 'api#save', 'url' => '/api/v1/records/{kind}', 'verb' => 'POST'],
		['name' => 'api#createOrder', 'url' => '/api/v1/orders', 'verb' => 'POST'],
		['name' => 'api#orderAction', 'url' => '/api/v1/orders/{id}/{operation}', 'verb' => 'POST'],
		['name' => 'api#action', 'url' => '/api/v1/actions/{operation}', 'verb' => 'POST'],
		['name' => 'api#settings', 'url' => '/api/v1/settings', 'verb' => 'GET'],
		['name' => 'api#setSettings', 'url' => '/api/v1/settings', 'verb' => 'PUT'],
		['name' => 'api#upload', 'url' => '/api/v1/attachments', 'verb' => 'POST'],
		['name' => 'api#download', 'url' => '/api/v1/attachments/{id}', 'verb' => 'GET'],
		['name' => 'api#export', 'url' => '/api/v1/export/{kind}', 'verb' => 'GET'],
		['name' => 'driver#exchange', 'url' => '/public/v1/session', 'verb' => 'POST'],
		['name' => 'driver#view', 'url' => '/public/v1/vehicle', 'verb' => 'GET'],
		['name' => 'driver#report', 'url' => '/public/v1/defects', 'verb' => 'POST'],
		['name' => 'driver#upload', 'url' => '/public/v1/photos', 'verb' => 'POST'],
	],
];

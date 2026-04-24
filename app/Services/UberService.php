<?php

namespace App\Services;

class UberService
{
    public function getOrder($orderId)
    {
        return [
            'id' => $orderId,
            'display_id' => 'B' . rand(1000, 9999),
            'state' => 'NEW',
            'eater' => [
                'name' => 'John Customer'
            ],
            'items' => [
                [
                    'title' => 'Burger',
                    'quantity' => 2,
                    'price' => [
                        'unit_price' => 500
                    ]
                ],
                [
                    'title' => 'Pizza',
                    'quantity' => 1,
                    'price' => [
                        'unit_price' => 1200
                    ]
                ]
            ],
            'payment' => [
                'total' => 2200
            ]
        ];
    }

    private $baseUrl = "https://test-api.uber.com";

    public function getOrders()
    {
        $token = env('UBER_ACCESS_TOKEN');

        return Http::withToken($token)
            ->get($this->baseUrl . "/v1/delivery/orders")
            ->json();
    }
}

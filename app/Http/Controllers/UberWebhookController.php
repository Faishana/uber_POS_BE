<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\OrderItem;

class UberWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Uber Webhook Received:', $request->all());

        // 🔹 Get order ID from webhook
        $orderId = $request->meta['resource_id'] ?? null;

        if (!$orderId) {
            return response()->json(['message' => 'No order ID'], 400);
        }

        // 🔹 Call Uber API
        $response = Http::withToken(env('UBER_ACCESS_TOKEN'))
            ->get("https://test-api.uber.com/v1/delivery/orders/$orderId");

        $data = $response->json();

        // 🔥 Fallback if Uber has no data (sandbox limitation)
        if (!$data || isset($data['error'])) {

            Log::warning("Uber API returned no data. Using fallback for order: $orderId");

            $data = [
                "id" => $orderId,
                "display_id" => "B" . rand(1000, 9999),
                "state" => "NEW",
                "eater" => ["name" => "Test Customer"],
                "items" => [
                    [
                        "title" => "Burger",
                        "quantity" => 2,
                        "price" => ["unit_price" => 500]
                    ],
                    [
                        "title" => "Pizza",
                        "quantity" => 1,
                        "price" => ["unit_price" => 1200]
                    ]
                ],
                "payment" => ["total" => 2200]
            ];
        }

        // 🔹 Save Order
        $order = Order::updateOrCreate(
            ['uber_order_id' => $data['id']],
            [
                'display_id'   => $data['display_id'] ?? rand(1000,9999),
                'status'       => $data['state'] ?? 'NEW',
                'customer_name'=> $data['eater']['name'] ?? 'Customer',
                'total'        => $data['payment']['total'] ?? 0,
                'raw_json'     => json_encode($data)
            ]
        );

        // 🔹 Remove old items (avoid duplicates)
        OrderItem::where('order_id', $order->id)->delete();

        // 🔹 Save Items
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'name'     => $item['title'] ?? 'Item',
                    'qty'      => $item['quantity'] ?? 1,
                    'price'    => $item['price']['unit_price'] ?? 0
                ]);
            }
        }

        return response()->json([
            'message' => 'Order saved successfully',
            'order' => $order->load('items')
        ]);
    }
}

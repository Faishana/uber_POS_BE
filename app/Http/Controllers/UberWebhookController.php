<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\UberService;

class UberWebhookController extends Controller
{
    protected $uber;

    public function __construct(UberService $uber)
    {
        $this->uber = $uber;
    }

    public function handle(Request $request)
    {
        // 🔹 Log incoming webhook
        Log::info('Uber Webhook Received:', $request->all());

        // 🔹 Get order ID
        $orderId = $request->input('meta.resource_id');

        if (!$orderId) {
            return response()->json(['message' => 'No order ID'], 400);
        }

        // 🔹 Fetch order from Uber via Service
        $data = $this->uber->getOrder($orderId);

        // 🔥 Handle failure / fallback
        if (!$data || isset($data['error'])) {

            Log::warning("Uber API failed for order: {$orderId}");

            // 🔹 Use mock only in local
            if (app()->environment('local')) {
                $data = $this->uber->mockOrder($orderId);
            } else {
                return response()->json([
                    'message' => 'Failed to fetch order from Uber'
                ], 500);
            }
        }

        // 🔹 Save Order
        $order = Order::updateOrCreate(
            ['uber_order_id' => $data['id']],
            [
                'display_id'    => $data['display_id'] ?? 'B' . rand(1000, 9999),
                'status'        => $data['state'] ?? 'NEW',
                'customer_name' => $data['eater']['name'] ?? 'Customer',
                'total'         => $data['payment']['total'] ?? 0,
                'raw_json'      => json_encode($data)
            ]
        );

        // 🔹 Remove old items
        OrderItem::where('order_id', $order->id)->delete();

        // 🔹 Save Items
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'name'     => $item['title'] ?? 'Item',
                    'qty'      => $item['quantity'] ?? 1,
                    'price'    => $item['price']['unit_price'] ?? 0
                ]);
            }
        }

        // 🔹 Final log
        Log::info('Order saved successfully', [
            'order_id' => $order->id,
            'uber_order_id' => $order->uber_order_id
        ]);

        return response()->json([
            'message' => 'Order saved successfully',
            'order' => $order->load('items')
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\UberService;

class OrderController extends Controller
{
    // 🔹 Fetch orders from Uber (optional)
    public function fetchUberOrders(UberService $uber)
    {
        return response()->json($uber->getOrders());
    }

    // 🔹 Create order (manual / testing)
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'nullable|string',
            'display_id' => 'nullable|string',
            'customer' => 'nullable|string',
            'total' => 'nullable|numeric',
            'items' => 'nullable|array'
        ]);

        $order = Order::create([
            'uber_order_id' => $data['order_id'] ?? uniqid('UBER_'),
            'display_id' => $data['display_id'] ?? 'B' . rand(1000, 9999),
            'status' => 'NEW',
            'customer_name' => $data['customer'] ?? 'Manual Customer',
            'total' => $data['total'] ?? 0,
            'raw_json' => json_encode($data)
        ]);

        // 🔹 Save items (if exists)
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'name' => $item['name'] ?? 'Item',
                    'qty' => $item['qty'] ?? 1,
                    'price' => $item['price'] ?? 0
                ]);
            }
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->load('items')
        ]);
    }

    // 🔹 Get all orders
    public function index()
    {
        $orders = Order::with('items')->latest()->get();
        return response()->json($orders);
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:NEW,ACCEPTED,READY,REJECTED'
        ]);

        $order = Order::findOrFail($id);

        // 🔥 LOG BEFORE UPDATE
        Log::info("Status change request received", [
            'order_id' => $order->id,
            'uber_order_id' => $order->uber_order_id,
            'new_status' => $data['status']
        ]);

        $order->update(['status' => $data['status']]);

        // 🔥 LOG AFTER UPDATE
        Log::info("Order status updated in DB", [
            'order_id' => $order->id,
            'status' => $order->status
        ]);

       // 🔥 Uber sync
    $token = env('UBER_ACCESS_TOKEN');

    try {

        $response = null;

        if ($data['status'] === 'ACCEPTED') {
            $response = Http::withToken($token)->post(
                "https://test-api.uber.com/v1/delivery/orders/{$order->uber_order_id}/accept"
            );
        }

        if ($data['status'] === 'REJECTED') {
            $response = Http::withToken($token)->post(
                "https://test-api.uber.com/v1/delivery/orders/{$order->uber_order_id}/cancel",
                ['reason' => 'ITEM_UNAVAILABLE']
            );
        }

        if ($data['status'] === 'READY') {
            $response = Http::withToken($token)->post(
                "https://test-api.uber.com/v1/delivery/orders/{$order->uber_order_id}/ready_for_pickup"
            );
        }

        // 🔥 Log ONLY if request was made
        if ($response) {
            Log::info("Uber API response", [
                'order_id' => $order->uber_order_id,
                'status_code' => $response->status(),
                'response' => $response->json()
            ]);
        }

    } catch (\Exception $e) {
        Log::error("Uber sync failed", [
            'error' => $e->getMessage(),
            'order_id' => $order->id
        ]);
    }

        return response()->json([
            'message' => 'Status updated successfully',
            'order' => $order
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\UberService;

class OrderController extends Controller
{

    public function fetchUberOrders(UberService $uber)
    {
        $orders = $uber->getOrders();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        $order = Order::create([
            'uber_order_id' => $data['order_id'] ?? 'TEST123',
            'display_id' => $data['display_id'] ?? 'D123',
            'status' => 'NEW',
            'customer_name' => $data['customer'] ?? 'Test Customer',
            'total' => $data['total'] ?? 1000,
            'raw_json' => json_encode($data)
        ]);

        return response()->json([
            'message' => 'Order Saved',
            'order' => $order
        ]);
    }

    public function index()
    {
        $orders = \App\Models\Order::with('items')->latest()->get();

        return response()->json($orders);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = \App\Models\Order::findOrFail($id);

        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Status updated',
            'order' => $order
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Luigel\Paymongo\Facades\Paymongo;
class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:3',
            'description' => 'nullable|string|max:500',
        ]);

        $amount = $validated['amount'] * 100;

        $paymentIntent = Paymongo::paymentIntent()->create([
            'amount' => $amount,
            'payment_method_allowed' => ['card', 'gcash', 'paymaya'],
            'currency' => $validated['currency'] ?? 'PHP',
            'description' => $validated['description'] ?? 'Sample payment from React app',
        ]);

        return response()->json($paymentIntent);
    }
}

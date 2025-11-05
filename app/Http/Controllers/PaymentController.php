<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Luigel\Paymongo\Facades\Paymongo;
class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $amount = $request->amount * 100;

        $paymentIntent = Paymongo::paymentIntent()->create([
            'amount' => $amount,
            'payment_method_allowed' => ['card', 'gcash', 'paymaya'],
            'currency' => 'PHP',
            'description' => 'Sample payment from React app',
        ]);

        return response()->json($paymentIntent);
    }
}

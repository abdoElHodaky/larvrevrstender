<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for specific payment updates
Broadcast::channel('payment.{paymentId}', function ($user, $paymentId) {
    // Check if user owns this payment
    $payment = \App\Models\Payment::find($paymentId);
    if (!$payment) {
        return false;
    }
    
    return (int) $user->id === (int) $payment->customer_id || 
           (int) $user->id === (int) $payment->merchant_id;
});

// Private channel for invoice updates
Broadcast::channel('invoice.{invoiceId}', function ($user, $invoiceId) {
    // Check if user owns this invoice
    $invoice = \App\Models\Invoice::find($invoiceId);
    if (!$invoice) {
        return false;
    }
    
    return (int) $user->id === (int) $invoice->customer_id || 
           (int) $user->id === (int) $invoice->merchant_id;
});

// Private channel for user's payments
Broadcast::channel('user.{userId}.payments', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for merchant's payments
Broadcast::channel('merchant.{merchantId}.payments', function ($user, $merchantId) {
    return (int) $user->id === (int) $merchantId;
});

// Private channel for customer's payments
Broadcast::channel('customer.{customerId}.payments', function ($user, $customerId) {
    return (int) $user->id === (int) $customerId;
});

// Private channel for escrow updates
Broadcast::channel('escrow.{escrowId}', function ($user, $escrowId) {
    // Check if user is involved in this escrow
    $escrow = \App\Models\Escrow::find($escrowId);
    if (!$escrow) {
        return false;
    }
    
    return (int) $user->id === (int) $escrow->buyer_id || 
           (int) $user->id === (int) $escrow->seller_id;
});

// Private channel for transaction updates
Broadcast::channel('transaction.{transactionId}', function ($user, $transactionId) {
    // Check if user owns this transaction
    $transaction = \App\Models\Transaction::find($transactionId);
    if (!$transaction) {
        return false;
    }
    
    return (int) $user->id === (int) $transaction->user_id;
});

// Private channel for payment method updates
Broadcast::channel('user.{userId}.payment-methods', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Admin channel for payment monitoring (high-level overview)
Broadcast::channel('admin.payments', function ($user) {
    return $user->role === 'admin' || $user->role === 'finance';
});

// Webhook status channel for merchants
Broadcast::channel('merchant.{merchantId}.webhooks', function ($user, $merchantId) {
    return (int) $user->id === (int) $merchantId || $user->role === 'admin';
});

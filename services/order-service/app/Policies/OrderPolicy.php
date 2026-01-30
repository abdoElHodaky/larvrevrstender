<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Order Policy
 * 
 * Defines authorization rules for order operations
 * Implements role-based access control for customers, merchants, and admins
 */
class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any orders
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all orders
        if ($user->isAdmin()) {
            return true;
        }

        // Customers and merchants can view orders with restrictions
        return $user->isCustomer() || $user->isMerchant();
    }

    /**
     * Determine whether the user can view the order
     */
    public function view(User $user, Order $order): bool
    {
        // Admins can view any order
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view their own orders
        if ($user->isCustomer() && $order->customer_id === $user->customer_profile->id) {
            return true;
        }

        // Merchants can view published orders or orders they've bid on
        if ($user->isMerchant()) {
            return $this->merchantCanViewOrder($user, $order);
        }

        return false;
    }

    /**
     * Determine whether the user can create orders
     */
    public function create(User $user): bool
    {
        // Only verified customers can create orders
        return $user->isCustomer() && $user->isVerified();
    }

    /**
     * Determine whether the user can update the order
     */
    public function update(User $user, Order $order): bool
    {
        // Admins can update any order
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can only update their own orders in draft or published status
        if ($user->isCustomer() && $order->customer_id === $user->customer_profile->id) {
            return in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PUBLISHED]);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the order
     */
    public function delete(User $user, Order $order): bool
    {
        // Only admins can delete orders
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can delete their own draft orders
        if ($user->isCustomer() && 
            $order->customer_id === $user->customer_profile->id && 
            $order->status === Order::STATUS_DRAFT) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can publish the order
     */
    public function publish(User $user, Order $order): bool
    {
        // Only the order owner can publish
        if (!$user->isCustomer() || $order->customer_id !== $user->customer_profile->id) {
            return false;
        }

        // Order must be in draft status and meet publication requirements
        return $order->status === Order::STATUS_DRAFT && $order->canBePublished();
    }

    /**
     * Determine whether the user can cancel the order
     */
    public function cancel(User $user, Order $order): bool
    {
        // Admins can cancel any order
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can cancel their own orders (except completed ones)
        if ($user->isCustomer() && 
            $order->customer_id === $user->customer_profile->id &&
            $order->status !== Order::STATUS_COMPLETED) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can upload images to the order
     */
    public function uploadImages(User $user, Order $order): bool
    {
        // Only the order owner can upload images
        if (!$user->isCustomer() || $order->customer_id !== $user->customer_profile->id) {
            return false;
        }

        // Can upload images to draft or published orders
        return in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PUBLISHED]);
    }

    /**
     * Determine whether the user can bid on the order
     */
    public function bid(User $user, Order $order): bool
    {
        // Only verified merchants can bid
        if (!$user->isMerchant() || !$user->merchant_profile->verified) {
            return false;
        }

        // Cannot bid on own orders (if merchant is also customer)
        if ($user->isCustomer() && $order->customer_id === $user->customer_profile->id) {
            return false;
        }

        // Order must be accepting bids
        return $order->canReceiveBids();
    }

    /**
     * Determine whether the user can view order statistics
     */
    public function viewStatistics(User $user): bool
    {
        // Admins can view all statistics
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view their own order statistics
        // Merchants can view statistics for orders they're involved with
        return $user->isCustomer() || $user->isMerchant();
    }

    /**
     * Determine whether the user can award the order
     */
    public function award(User $user, Order $order): bool
    {
        // Only the order owner can award
        if (!$user->isCustomer() || $order->customer_id !== $user->customer_profile->id) {
            return false;
        }

        // Order must be in bidding status with active bids
        return $order->status === Order::STATUS_BIDDING && $order->bids()->active()->exists();
    }

    /**
     * Determine whether the user can complete the order
     */
    public function complete(User $user, Order $order): bool
    {
        // Admins can complete any order
        if ($user->isAdmin()) {
            return true;
        }

        // Order owner can complete awarded orders
        if ($user->isCustomer() && 
            $order->customer_id === $user->customer_profile->id &&
            $order->status === Order::STATUS_AWARDED) {
            return true;
        }

        // Winning merchant can mark as completed (with customer confirmation)
        if ($user->isMerchant() && $order->status === Order::STATUS_AWARDED) {
            $award = $order->award;
            return $award && $award->merchant_id === $user->merchant_profile->id;
        }

        return false;
    }

    /**
     * Check if merchant can view order
     */
    protected function merchantCanViewOrder(User $user, Order $order): bool
    {
        // Can view published orders
        if (in_array($order->status, [Order::STATUS_PUBLISHED, Order::STATUS_BIDDING])) {
            return true;
        }

        // Can view orders they've bid on
        if ($order->bids()->where('merchant_id', $user->merchant_profile->id)->exists()) {
            return true;
        }

        // Can view awarded orders where they're the winner
        if ($order->status === Order::STATUS_AWARDED) {
            $award = $order->award;
            return $award && $award->merchant_id === $user->merchant_profile->id;
        }

        return false;
    }

    /**
     * Determine if user can manage order status
     */
    public function manageStatus(User $user, Order $order): bool
    {
        // Admins can manage any order status
        if ($user->isAdmin()) {
            return true;
        }

        // Order owners have limited status management
        if ($user->isCustomer() && $order->customer_id === $user->customer_profile->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can view order history
     */
    public function viewHistory(User $user, Order $order): bool
    {
        // Same as view permission but includes status history
        return $this->view($user, $order);
    }

    /**
     * Determine if user can export order data
     */
    public function export(User $user): bool
    {
        // Only admins and verified users can export data
        return $user->isAdmin() || ($user->isVerified() && ($user->isCustomer() || $user->isMerchant()));
    }
}

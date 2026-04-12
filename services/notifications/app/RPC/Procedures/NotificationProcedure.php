<?php

namespace App\RPC\Procedures;

use Shared\Core\BaseProcedure;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * RPC Procedures for Notification Operations
 * 
 * Handles all notification-related RPC calls from other services.
 */
class NotificationProcedure extends BaseProcedure
{
    protected NotificationService $notificationService;
    protected EmailService $emailService;
    protected SmsService $smsService;
    protected PushNotificationService $pushService;
    
    public function __construct(
        NotificationService $notificationService,
        EmailService $emailService,
        SmsService $smsService,
        PushNotificationService $pushService
    ) {
        $this->notificationService = $notificationService;
        $this->emailService = $emailService;
        $this->smsService = $smsService;
        $this->pushService = $pushService;
    }
    
    /**
     * Send email notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendEmail(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'to' => 'required|email',
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'template' => 'string|max:100',
                'template_data' => 'array',
                'from' => 'email',
                'reply_to' => 'email',
                'attachments' => 'array',
                'priority' => 'string|in:low,normal,high',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->emailService->sendEmail($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_id' => $result['notification_id'],
                    'message' => 'Email sent successfully',
                    'delivery_status' => $result['delivery_status'] ?? 'sent',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendEmail failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send email', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send SMS notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendSms(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'to' => 'required|string|max:20',
                'message' => 'required|string|max:1600',
                'template' => 'string|max:100',
                'template_data' => 'array',
                'priority' => 'string|in:low,normal,high',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->smsService->sendSms($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_id' => $result['notification_id'],
                    'message' => 'SMS sent successfully',
                    'delivery_status' => $result['delivery_status'] ?? 'sent',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendSms failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send SMS', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send push notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendPushNotification(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'array',
                'icon' => 'string|max:255',
                'image' => 'string|max:255',
                'click_action' => 'string|max:255',
                'priority' => 'string|in:low,normal,high',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->pushService->sendPushNotification($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_id' => $result['notification_id'],
                    'message' => 'Push notification sent successfully',
                    'delivery_status' => $result['delivery_status'] ?? 'sent',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendPushNotification failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send push notification', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send multi-channel notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendMultiChannel(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'channels' => 'required|array|min:1',
                'channels.*' => 'string|in:email,sms,push,whatsapp,telegram',
                'message_data' => 'required|array',
                'priority' => 'string|in:low,normal,high',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->notificationService->sendMultiChannel($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_ids' => $result['notification_ids'],
                    'message' => 'Multi-channel notification sent successfully',
                    'delivery_results' => $result['delivery_results'] ?? [],
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendMultiChannel failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send multi-channel notification', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send bid confirmation notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendBidConfirmation(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'auction_id' => 'required|integer|min:1',
                'bid_id' => 'required|integer|min:1',
                'bid_amount' => 'required|numeric|min:0',
                'auction_title' => 'required|string|max:255',
                'channels' => 'array',
                'channels.*' => 'string|in:email,sms,push',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->notificationService->sendBidConfirmation($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_ids' => $result['notification_ids'],
                    'message' => 'Bid confirmation sent successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendBidConfirmation failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send bid confirmation', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send outbid notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendOutbidNotification(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'auction_id' => 'required|integer|min:1',
                'previous_bid_amount' => 'required|numeric|min:0',
                'new_highest_amount' => 'required|numeric|min:0',
                'auction_title' => 'required|string|max:255',
                'auction_ends_at' => 'required|date',
                'channels' => 'array',
                'channels.*' => 'string|in:email,sms,push',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->notificationService->sendOutbidNotification($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_ids' => $result['notification_ids'],
                    'message' => 'Outbid notification sent successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendOutbidNotification failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send outbid notification', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send auction won notification
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendAuctionWonNotification(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'auction_id' => 'required|integer|min:1',
                'winning_amount' => 'required|numeric|min:0',
                'auction_title' => 'required|string|max:255',
                'payment_deadline' => 'date',
                'channels' => 'array',
                'channels.*' => 'string|in:email,sms,push',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->notificationService->sendAuctionWonNotification($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_ids' => $result['notification_ids'],
                    'message' => 'Auction won notification sent successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendAuctionWonNotification failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send auction won notification', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Send payment reminder
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendPaymentReminder(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'auction_id' => 'required|integer|min:1',
                'amount_due' => 'required|numeric|min:0',
                'payment_deadline' => 'required|date',
                'auction_title' => 'required|string|max:255',
                'reminder_type' => 'string|in:first,second,final',
                'channels' => 'array',
                'channels.*' => 'string|in:email,sms,push',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->notificationService->sendPaymentReminder($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'notification_ids' => $result['notification_ids'],
                    'message' => 'Payment reminder sent successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::sendPaymentReminder failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send payment reminder', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get notification status
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getStatus(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'notification_id' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $notification = Notification::where('notification_id', $params['notification_id'])->first();
            if (!$notification) {
                return $this->errorResponse('Notification not found', ['notification_id' => $params['notification_id']], 404);
            }
            
            return $this->successResponse([
                'notification' => $notification->toArray(),
                'status' => $notification->status,
                'notification_id' => $params['notification_id'],
            ]);
            
        } catch (Exception $e) {
            Log::error('NotificationProcedure::getStatus failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get notification status', ['error' => $e->getMessage()], 500);
        }
    }
}

-- Payment Notification Templates for Laravel Reverse Tender Platform
-- These templates support both English and Arabic for Saudi Arabia market

-- Customer Payment Due Notification (Email)
INSERT INTO notification_templates (name, type, language, subject, content, variables, active, created_at, updated_at) VALUES
('payment_due', 'email', 'en', 'Payment Due for Order {{order_number}}', 
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2c5aa0;">Payment Required for Your Order</h2>
        
        <p>Dear Customer,</p>
        
        <p>Your order <strong>{{order_number}}</strong> has been confirmed and is ready for payment.</p>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #2c5aa0;">Order Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Order Number:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;">{{order_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Merchant:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;">{{merchant_name}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Total Amount:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>{{total_amount}} {{currency}}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Due Date:</strong></td>
                    <td style="padding: 8px 0; color: #dc3545;"><strong>{{due_date}}</strong></td>
                </tr>
            </table>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{payment_link}}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Pay Now</a>
        </div>
        
        <p><strong>Important:</strong> Please complete your payment by the due date to avoid order cancellation.</p>
        
        <p>If you have any questions, please contact our support team.</p>
        
        <p>Best regards,<br>The Parts Marketplace Team</p>
    </div>
</body>
</html>', 
'["order_number", "merchant_name", "total_amount", "currency", "due_date", "payment_link"]', 
true, NOW(), NOW()),

-- Customer Payment Due Notification (Arabic)
('payment_due', 'email', 'ar', 'مطلوب دفع للطلب {{order_number}}', 
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; direction: rtl;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2c5aa0;">مطلوب دفع لطلبك</h2>
        
        <p>عزيزي العميل،</p>
        
        <p>تم تأكيد طلبك <strong>{{order_number}}</strong> وهو جاهز للدفع.</p>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #2c5aa0;">تفاصيل الطلب</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>رقم الطلب:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;">{{order_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>التاجر:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;">{{merchant_name}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>المبلغ الإجمالي:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>{{total_amount}} {{currency}}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>تاريخ الاستحقاق:</strong></td>
                    <td style="padding: 8px 0; color: #dc3545;"><strong>{{due_date}}</strong></td>
                </tr>
            </table>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{payment_link}}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">ادفع الآن</a>
        </div>
        
        <p><strong>مهم:</strong> يرجى إكمال الدفع قبل تاريخ الاستحقاق لتجنب إلغاء الطلب.</p>
        
        <p>إذا كان لديك أي أسئلة، يرجى الاتصال بفريق الدعم.</p>
        
        <p>مع أطيب التحيات،<br>فريق سوق قطع الغيار</p>
    </div>
</body>
</html>', 
'["order_number", "merchant_name", "total_amount", "currency", "due_date", "payment_link"]', 
true, NOW(), NOW()),

-- Payment Received Notification (Email - English)
('payment_received', 'email', 'en', 'Payment Received - Order {{order_number}}', 
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #28a745;">Payment Received Successfully!</h2>
        
        <p>Dear Customer,</p>
        
        <p>We have successfully received your payment for order <strong>{{order_number}}</strong>.</p>
        
        <div style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
            <h3 style="margin-top: 0; color: #155724;">Payment Confirmation</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #c3e6cb;"><strong>Order Number:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #c3e6cb;">{{order_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #c3e6cb;"><strong>Payment Amount:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #c3e6cb;"><strong>{{payment_amount}} {{currency}}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #c3e6cb;"><strong>Payment Date:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #c3e6cb;">{{payment_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Transaction ID:</strong></td>
                    <td style="padding: 8px 0;">{{transaction_id}}</td>
                </tr>
            </table>
        </div>
        
        <p><strong>What happens next?</strong></p>
        <ul>
            <li>Your order is now being processed by the merchant</li>
            <li>You will receive tracking information once the item is shipped</li>
            <li>Funds are held in escrow until you confirm delivery</li>
        </ul>
        
        <p>Thank you for your business!</p>
        
        <p>Best regards,<br>The Parts Marketplace Team</p>
    </div>
</body>
</html>', 
'["order_number", "payment_amount", "currency", "payment_date", "transaction_id"]', 
true, NOW(), NOW()),

-- Payment Failed Notification (Email - English)
('payment_failed', 'email', 'en', 'Payment Failed - Order {{order_number}}', 
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #dc3545;">Payment Failed</h2>
        
        <p>Dear Customer,</p>
        
        <p>Unfortunately, we were unable to process your payment for order <strong>{{order_number}}</strong>.</p>
        
        <div style="background: #f8d7da; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;">
            <h3 style="margin-top: 0; color: #721c24;">Payment Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f1aeb5;"><strong>Order Number:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f1aeb5;">{{order_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f1aeb5;"><strong>Amount:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f1aeb5;">{{payment_amount}} {{currency}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f1aeb5;"><strong>Failure Reason:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f1aeb5;">{{failure_reason}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Attempt Date:</strong></td>
                    <td style="padding: 8px 0;">{{attempt_date}}</td>
                </tr>
            </table>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{retry_payment_link}}" style="background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Try Payment Again</a>
        </div>
        
        <p><strong>Common solutions:</strong></p>
        <ul>
            <li>Check that your card details are correct</li>
            <li>Ensure you have sufficient funds</li>
            <li>Try a different payment method</li>
            <li>Contact your bank if the issue persists</li>
        </ul>
        
        <p>If you continue to experience issues, please contact our support team.</p>
        
        <p>Best regards,<br>The Parts Marketplace Team</p>
    </div>
</body>
</html>', 
'["order_number", "payment_amount", "currency", "failure_reason", "attempt_date", "retry_payment_link"]', 
true, NOW(), NOW()),

-- Merchant Order Placed Notification (Email - English)
('order_placed', 'email', 'en', 'New Order Received - {{order_number}}', 
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2c5aa0;">New Order Received!</h2>
        
        <p>Dear Merchant,</p>
        
        <p>You have received a new order <strong>{{order_number}}</strong> from our marketplace.</p>
        
        <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5aa0;">
            <h3 style="margin-top: 0; color: #2c5aa0;">Order Information</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;"><strong>Order Number:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;">{{order_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;"><strong>Customer:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;">{{customer_name}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;"><strong>Order Amount:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;"><strong>{{order_amount}} {{currency}}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;"><strong>Payment Status:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #b8daff;">{{payment_status}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Expected Delivery:</strong></td>
                    <td style="padding: 8px 0;">{{estimated_delivery}}</td>
                </tr>
            </table>
        </div>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="margin-top: 0;">Delivery Address:</h4>
            <p style="margin-bottom: 0;">{{delivery_address}}</p>
        </div>
        
        <p><strong>Next Steps:</strong></p>
        <ul>
            <li>Wait for payment confirmation (if pending)</li>
            <li>Prepare the item for shipment</li>
            <li>Update tracking information in your dashboard</li>
            <li>Funds will be released after delivery confirmation</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{merchant_dashboard_link}}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">View Order Details</a>
        </div>
        
        <p>Thank you for being part of our marketplace!</p>
        
        <p>Best regards,<br>The Parts Marketplace Team</p>
    </div>
</body>
</html>', 
'["order_number", "customer_name", "order_amount", "currency", "payment_status", "estimated_delivery", "delivery_address", "merchant_dashboard_link"]', 
true, NOW(), NOW()),

-- SMS Templates for Payment Due (English)
('payment_due', 'sms', 'en', '', 
'Payment due for order {{order_number}}. Amount: {{total_amount}} {{currency}}. Due: {{due_date}}. Pay now: {{payment_link}}', 
'["order_number", "total_amount", "currency", "due_date", "payment_link"]', 
true, NOW(), NOW()),

-- SMS Templates for Payment Due (Arabic)
('payment_due', 'sms', 'ar', '', 
'مطلوب دفع للطلب {{order_number}}. المبلغ: {{total_amount}} {{currency}}. الاستحقاق: {{due_date}}. ادفع الآن: {{payment_link}}', 
'["order_number", "total_amount", "currency", "due_date", "payment_link"]', 
true, NOW(), NOW()),

-- SMS Template for Payment Received
('payment_received', 'sms', 'en', '', 
'Payment received for order {{order_number}}. Amount: {{payment_amount}} {{currency}}. Your order is being processed.', 
'["order_number", "payment_amount", "currency"]', 
true, NOW(), NOW()),

-- In-App Notification Templates
('payment_due', 'in_app', 'en', 'Payment Due', 
'Your order {{order_number}} is ready for payment. Amount: {{total_amount}} {{currency}}. Due: {{due_date}}.', 
'["order_number", "total_amount", "currency", "due_date"]', 
true, NOW(), NOW()),

('payment_received', 'in_app', 'en', 'Payment Received', 
'Payment received for order {{order_number}}. Your order is now being processed by the merchant.', 
'["order_number"]', 
true, NOW(), NOW()),

('order_placed', 'in_app', 'en', 'New Order', 
'New order {{order_number}} received. Amount: {{order_amount}} {{currency}}. Payment: {{payment_status}}.', 
'["order_number", "order_amount", "currency", "payment_status"]', 
true, NOW(), NOW());

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_notification_templates_name_type ON notification_templates(name, type);
CREATE INDEX IF NOT EXISTS idx_notification_templates_type_active ON notification_templates(type, active);
CREATE INDEX IF NOT EXISTS idx_notification_templates_language ON notification_templates(language);

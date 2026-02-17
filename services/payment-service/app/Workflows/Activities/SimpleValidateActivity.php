<?php

namespace App\Workflows\Activities;

use Workflow\Activity;
use Exception;

class SimpleValidateActivity extends Activity
{
    public function uniqueId()
    {
        // Handle case where storedWorkflow is not initialized yet
        if (!isset($this->storedWorkflow) || !$this->storedWorkflow) {
            return static::class . ':' . uniqid();
        }
        
        return parent::uniqueId();
    }

    public function execute(array $data): array
    {
        error_log('SimpleValidateActivity started with data: ' . json_encode($data));
        
        // Simple validation logic
        if (empty($data['order_id'])) {
            throw new Exception('Order ID is required');
        }
        
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new Exception('Valid amount is required');
        }
        
        $result = [
            'validated' => true,
            'order_id' => $data['order_id'],
            'amount' => $data['amount']
        ];
        
        error_log('SimpleValidateActivity completed with result: ' . json_encode($result));
        
        return $result;
    }
}

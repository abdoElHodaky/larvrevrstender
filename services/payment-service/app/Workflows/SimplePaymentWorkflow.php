<?php

namespace App\Workflows;

use Workflow\Workflow;
use Workflow\WorkflowStub;
use App\Workflows\Activities\SimpleValidateActivity;
use function Workflow\activity;

class SimplePaymentWorkflow extends Workflow
{
    /**
     * Start the simple payment workflow
     */
    public static function start(array $paymentData): WorkflowStub
    {
        $workflow = WorkflowStub::make(static::class);
        $workflow->start($paymentData);
        return $workflow;
    }

    /**
     * Execute the simple payment workflow
     */
    public function execute(array $paymentData)
    {
        try {
            // Step 1: Simple validation
            $validationResult = yield activity(SimpleValidateActivity::class, $paymentData);
            
            $result = [
                'success' => true,
                'workflow_id' => $this->uniqueId(),
                'validation' => $validationResult
            ];
            
            // Debug logging
            error_log('SimplePaymentWorkflow completed with result: ' . json_encode($result));
            
            return $result;
        } catch (\Throwable $e) {
            error_log('SimplePaymentWorkflow error: ' . $e->getMessage());
            throw $e;
        }
    }
}

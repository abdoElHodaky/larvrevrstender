<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag() ||
                   $this->isWorkflowEntry($entry);
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Check if entry is workflow-related
     */
    protected function isWorkflowEntry(IncomingEntry $entry): bool
    {
        // Include workflow-related entries
        $workflowTypes = [
            'correlation',
            'correlation_span', 
            'correlation_rpc',
            'workflow_signal',
            'workflow_dlq',
            'workflow_metrics',
        ];

        if (in_array($entry->type, $workflowTypes)) {
            return true;
        }

        // Include entries with workflow tags
        $workflowTags = [
            'workflow',
            'saga',
            'correlation',
            'dlq',
            'signal',
            'intervention',
        ];

        foreach ($workflowTags as $tag) {
            if (in_array($tag, $entry->tags ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Boot the service provider
     */
    public function boot(): void
    {
        parent::boot();
        
        $this->configureWorkflowTags();
    }

    /**
     * Configure workflow-specific tags for Telescope
     */
    protected function configureWorkflowTags(): void
    {
        Telescope::tag(function (IncomingEntry $entry) {
            $tags = [];

            // Add correlation tags
            if (isset($entry->content['correlation_id'])) {
                $tags[] = 'correlation:' . $entry->content['correlation_id'];
            }

            if (isset($entry->content['trace_id'])) {
                $tags[] = 'trace:' . $entry->content['trace_id'];
            }

            if (isset($entry->content['workflow_id'])) {
                $tags[] = 'workflow:' . $entry->content['workflow_id'];
            }

            if (isset($entry->content['span_id'])) {
                $tags[] = 'span:' . $entry->content['span_id'];
            }

            // Add activity type tags
            if (isset($entry->content['activity_type'])) {
                $tags[] = 'activity:' . $entry->content['activity_type'];
            }

            // Add signal type tags
            if (isset($entry->content['signal_type'])) {
                $tags[] = 'signal:' . $entry->content['signal_type'];
            }

            // Add DLQ tags
            if (isset($entry->content['failure_id'])) {
                $tags[] = 'dlq:' . $entry->content['failure_id'];
            }

            return $tags;
        });
    }
}

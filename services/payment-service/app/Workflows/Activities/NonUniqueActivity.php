<?php

namespace App\Workflows\Activities;

use BadMethodCallException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use SplFileObject;
use LimitIterator;
use Throwable;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\Middleware\ActivityMiddleware;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application as App;

/**
 * NonUniqueActivity - Base Activity class that doesn't implement ShouldBeUnique
 * 
 * This class provides the same functionality as the original Activity class
 * but without implementing ShouldBeUnique, which prevents Laravel from calling
 * uniqueId() during job dispatch before the constructor is called.
 */
abstract class NonUniqueActivity implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RouteDependencyResolverTrait, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $maxExceptions = 3;
    public $backoff = [1, 5, 10];
    public $retryUntil;
    public $failOnTimeout = true;

    public array $arguments = [];

    private Container $container;

    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->arguments = $arguments;

        if (property_exists($this, 'connection')) {
            $this->onConnection($this->connection);
        }

        if (property_exists($this, 'queue')) {
            $this->onQueue($this->queue);
        }

        $this->afterCommit = true;
    }

    public function workflowId()
    {
        // Handle case where storedWorkflow is not initialized yet
        if (!isset($this->storedWorkflow) || !$this->storedWorkflow) {
            return null;
        }
        
        return $this->storedWorkflow->id;
    }

    public function webhookUrl(string $signalMethod = ''): string
    {
        // Handle case where storedWorkflow is not initialized yet
        if (!isset($this->storedWorkflow) || !$this->storedWorkflow) {
            return '';
        }
        
        $workflow = Str::kebab(class_basename($this->storedWorkflow->class));

        if ($signalMethod === '') {
            $signalMethod = 'handle';
        }

        $signal = Str::kebab($signalMethod);
        return route("workflows.signal.{$workflow}.{$signal}", [
            'workflowId' => $this->storedWorkflow->id,
        ]);
    }

    public function handle(): mixed
    {
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        $this->container = App::make(Container::class);

        // Handle case where storedWorkflow is not initialized yet
        if (!isset($this->storedWorkflow) || !$this->storedWorkflow) {
            // If storedWorkflow is not initialized, we can't check logs, so just execute
            try {
                return $this->{'execute'}(...$this->resolveClassMethodDependencies($this->arguments, $this, 'execute'));
            } catch (\Throwable $throwable) {
                // Can't create exception record without storedWorkflow, so just throw
                throw $throwable;
            }
        }

        $existingLog = $this->storedWorkflow->logs()->whereIndex($this->index)->first();
        if ($existingLog) {
            // Return the cached result instead of null, properly deserialized
            return $existingLog->result ? Serializer::unserialize($existingLog->result) : null;
        }

        try {
            return $this->{'execute'}(...$this->resolveClassMethodDependencies($this->arguments, $this, 'execute'));
        } catch (\Throwable $throwable) {
            // Only create exception record if storedWorkflow is initialized
            if (isset($this->storedWorkflow) && $this->storedWorkflow) {
                $this->storedWorkflow->exceptions()
                    ->create([
                        'class' => $this::class,
                        'index' => $this->index,
                        'exception' => Serializer::serialize($throwable),
                    ]);
            }

            throw $throwable;
        }
    }

    public function middleware()
    {
        // Handle case where storedWorkflow is not initialized yet
        if (!isset($this->storedWorkflow) || !$this->storedWorkflow) {
            return [];
        }
        
        return [
            new WithoutOverlappingMiddleware(
                $this->storedWorkflow->id,
                WithoutOverlappingMiddleware::ACTIVITY,
                0,
                $this->timeout
            ),
            new ActivityMiddleware(),
        ];
    }

    public function failed(Throwable $throwable): void
    {
        // Handle case where storedWorkflow is not initialized yet
        if (!isset($this->storedWorkflow) || !$this->storedWorkflow) {
            // Log the error but don't try to access storedWorkflow
            error_log('Activity failed before storedWorkflow was initialized: ' . $throwable->getMessage());
            return;
        }
        
        $workflow = $this->storedWorkflow->toWorkflow();

        $file = new SplFileObject($throwable->getFile());
        $iterator = new LimitIterator($file, max(0, $throwable->getLine() - 4), 7);

        $lines = [];
        foreach ($iterator as $i => $line) {
            $lines[] = sprintf('%d: %s', $i + 1, rtrim($line));
        }

        $workflow->catch($throwable, $lines);
    }
}

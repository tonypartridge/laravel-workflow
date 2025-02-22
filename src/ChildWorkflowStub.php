<?php

declare(strict_types=1);

namespace Workflow;

use function React\Promise\all;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Serializers\Y;

final class ChildWorkflowStub
{
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    public static function make($workflow, ...$arguments): PromiseInterface
    {
        $context = WorkflowStub::getContext();

        $log = $context->storedWorkflow->logs()
            ->whereIndex($context->index)
            ->first();

        if ($log) {
            ++$context->index;
            WorkflowStub::setContext($context);
            return resolve(Y::unserialize($log->result));
        }

        $storedChildWorkflow = $context->storedWorkflow->children()
            ->wherePivot('parent_index', $context->index)
            ->first();

        $childWorkflow = $storedChildWorkflow ? $storedChildWorkflow->toWorkflow() : WorkflowStub::make($workflow);

        if ($childWorkflow->running() && ! $childWorkflow->created()) {
            try {
                $childWorkflow->resume();
            } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
                // already running
            }
        } elseif (! $childWorkflow->completed()) {
            $childWorkflow->startAsChild($context->storedWorkflow, $context->index, $context->now, ...$arguments);
        }

        ++$context->index;
        WorkflowStub::setContext($context);
        $deferred = new Deferred();
        return $deferred->promise();
    }
}

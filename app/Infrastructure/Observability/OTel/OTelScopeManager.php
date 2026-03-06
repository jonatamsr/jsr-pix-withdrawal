<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\OTel;

use OpenTracing\Scope;
use OpenTracing\ScopeManager;
use OpenTracing\Span;
use SplStack;

final class OTelScopeManager implements ScopeManager
{
    /** @var SplStack<OTelScope> */
    private SplStack $scopes;

    public function __construct()
    {
        $this->scopes = new SplStack();
    }

    public function activate(Span $span, bool $finishSpanOnClose = self::DEFAULT_FINISH_SPAN_ON_CLOSE): Scope
    {
        $scope = new OTelScope($span, $this, $finishSpanOnClose);
        $this->scopes->push($scope);

        return $scope;
    }

    public function getActive(): ?Scope
    {
        if ($this->scopes->isEmpty()) {
            return null;
        }

        return $this->scopes->top();
    }

    public function deactivate(OTelScope $scope): void
    {
        $remaining = new SplStack();

        while (! $this->scopes->isEmpty()) {
            $current = $this->scopes->pop();
            if ($current === $scope) {
                break;
            }
            $remaining->push($current);
        }

        while (! $remaining->isEmpty()) {
            $this->scopes->push($remaining->pop());
        }
    }
}

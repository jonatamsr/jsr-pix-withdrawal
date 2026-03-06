<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\OTel;

use OpenTracing\Scope;
use OpenTracing\Span;

final class OTelScope implements Scope
{
    public function __construct(
        private readonly Span $span,
        private readonly OTelScopeManager $scopeManager,
        private readonly bool $finishOnClose,
    ) {
    }

    public function close(): void
    {
        $this->scopeManager->deactivate($this);

        if ($this->finishOnClose) {
            $this->span->finish();
        }
    }

    public function getSpan(): Span
    {
        return $this->span;
    }
}

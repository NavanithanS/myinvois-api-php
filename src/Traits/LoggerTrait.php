<?php

namespace Nava\MyInvois\Traits;

use Psr\Log\LoggerInterface;

/**
 * Single trait for all logging functionality.
 */
trait LoggerTrait
{
    protected ?LoggerInterface $logger = null;

    /**
     * Set the logger instance.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Log debug message if logging is enabled.
     */
    protected function logDebug(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->logger && ($this->config['logging']['enabled'] ?? false)) {
            $fullMessage = $this->formatLogMessage($message, $component);
            $this->logger->debug($fullMessage, $this->addLogContext($context));
        }
    }

    /**
     * Log error message if logging is enabled.
     */
    protected function logError(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->logger && ($this->config['logging']['enabled'] ?? false)) {
            $fullMessage = $this->formatLogMessage($message, $component);
            $this->logger->error($fullMessage, $this->addLogContext($context));
        }
    }

    /**
     * Format log message with optional component prefix.
     */
    private function formatLogMessage(string $message, ?string $component = null): string
    {
        $prefix = $component ? "MyInvois {$component}" : 'MyInvois';
        return "{$prefix}: {$message}";
    }

    /**
     * Add default context to log messages.
     */
    private function addLogContext(array $context): array
    {
        return array_merge([
            'client_id' => $this->clientId ?? null,
        ], $context);
    }

    protected function validateLogLevel(string $level): void
    {
        $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        if (!in_array(strtolower($level), $validLevels)) {
            throw new ValidationException('Invalid log level');
        }
    }

    protected function logStructured(
        string $level,
        string $message,
        array $context = [],
        ?string $component = null
    ): void {
        $this->validateLogLevel($level);

        $structuredContext = array_merge([
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'client_id' => $this->clientId ?? 'unknown',
            'component' => $component ?? 'unknown',
            'request_id' => $this->getRequestId(),
        ], $context);

        $this->logger->$level($message, $structuredContext);
    }
}

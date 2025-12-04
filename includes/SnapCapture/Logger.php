<?php

declare(strict_types=1);

namespace SnapCapture;

/**
 * File-based Logger for SnapCapture
 *
 * Provides comprehensive logging to help diagnose screenshot capture issues
 */
class Logger
{
    private string $logFile;
    private bool $enabled;
    private string $logLevel;

    // Log levels
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    private const LEVEL_PRIORITY = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
    ];

    /**
     * Constructor
     *
     * @param string $logFile Path to log file
     * @param bool $enabled Whether logging is enabled
     * @param string $logLevel Minimum log level (DEBUG, INFO, WARNING, ERROR)
     */
    public function __construct(
        string $logFile,
        bool $enabled = true,
        string $logLevel = self::LEVEL_INFO
    ) {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
        $this->logLevel = $logLevel;

        // Create log directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Create log file if it doesn't exist
        if (!file_exists($logFile)) {
            touch($logFile);
            chmod($logFile, 0644);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a message with a specific level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        // Check if this message should be logged based on level
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        $logEntry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $contextStr
        );

        // Append to log file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log to WordPress error log for convenience
        error_log("SnapCapture [{$level}]: {$message}" . $contextStr);
    }

    /**
     * Check if a message with the given level should be logged
     *
     * @param string $level Message level
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        $messagePriority = self::LEVEL_PRIORITY[$level] ?? 0;
        $minPriority = self::LEVEL_PRIORITY[$this->logLevel] ?? 0;

        return $messagePriority >= $minPriority;
    }

    /**
     * Clear the log file
     */
    public function clear(): void
    {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    /**
     * Get the last N lines from the log file
     *
     * @param int $lines Number of lines to retrieve
     * @return array
     */
    public function tail(int $lines = 50): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $file = file($this->logFile);
        if ($file === false) {
            return [];
        }

        return array_slice($file, -$lines);
    }

    /**
     * Get the log file path
     *
     * @return string
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set logging enabled/disabled
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Get current log level
     *
     * @return string
     */
    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    /**
     * Set log level
     *
     * @param string $level
     */
    public function setLogLevel(string $level): void
    {
        if (isset(self::LEVEL_PRIORITY[$level])) {
            $this->logLevel = $level;
        }
    }
}

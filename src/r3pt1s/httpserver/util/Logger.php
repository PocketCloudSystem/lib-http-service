<?php

namespace r3pt1s\httpserver\util;

use pmmp\thread\Thread;
use ReflectionClass;
use ReflectionException;
use Throwable;

final class Logger {
    use SingletonTrait {
        getInstance as private;
        getInstance as get;
    }

    private const string PREFIX = "§8[§c%s§8/§e%s§8] §8[§e%s§8] §r%s";

    public function info(string $message, string ...$args): self {
        return $this->send("§bINFO", $message, ...$args);
    }

    public function warn(string $message, string ...$args): self {
        return $this->send("§cWARN", $message, ...$args);
    }

    public function error(string $message, string ...$args): self {
        return $this->send("§4ERROR", $message, ...$args);
    }

    public function exception(Throwable $throwable): self {
        $this->error("§cUnhandled §e%s§c: §e%s §cwas thrown in §e%s §cat line §e%s", $throwable::class, $throwable->getMessage(), $throwable->getFile(), $throwable->getLine());
        $i = 1;
        foreach ($throwable->getTrace() as $trace) {
            $args = implode(", ", array_map(function(mixed $argument): string {
                if (is_object($argument)) {
                    try {
                        return new ReflectionClass($argument)->getShortName();
                    } catch (ReflectionException) {
                        return get_class($argument);
                    }
                } else if (is_array($argument)) {
                    return "array(" . count($argument) . ")";
                }
                return gettype($argument);
            }, ($trace["args"] ?? [])));

            if (isset($trace["line"])) {
                $this->error("§cTrace §e#%s §ccalled at '§e%s(%s)§c' in §e%s §cat line §e%s", $i, $trace["function"], $args, $trace["file"] ?? $trace["class"], $trace["line"]);
            } else {
                $this->error("§cTrace §e#%s §ccalled at '§e%s(%s)§c' in §e%s", $i, $trace["function"], $args, $trace["file"] ?? $trace["class"]);
            }
            $i++;
        }
        return $this;
    }

    private function send(string $level, string $message, string ...$args): self {
        $threadName = (Thread::getCurrentThread() === null ? "Main" : new ReflectionClass(Thread::getCurrentThread())->getShortName());
        echo TerminalColor::toColoredString(
                sprintf(self::PREFIX, $threadName, date("H:i:s"), $level, sprintf($message, ...$args))
            ) . "\n";

        return $this;
    }
}
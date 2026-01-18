<?php

namespace r3pt1s\httpserver\util;

enum TerminalColor {

    public static function toColoredString(string $message, bool $formatting = true): string {
        foreach (self::cases() as $color) {
            $message = str_replace($color->getColorCode(), ($formatting ? $color->getColor() : ""), $message);
        }
        return $message;
    }

    case BLACK;
    case WHITE;
    case DARK_GRAY;
    case GRAY;
    case BLUE;
    case DARK_BLUE;
    case DARK_CYAN;
    case CYAN;
    case DARK_RED;
    case RED;
    case DARK_GREEN;
    case GREEN;
    case MAGENTA;
    case PINK;
    case YELLOW;
    case ORANGE;
    case RESET;

    private static function meta(string $name, string $colorCode, string $color): array {
        return [$name, $colorCode, $color];
    }

    private function metadata(): array {
        static $cache = [];

        return $cache[spl_object_id($this)] ?? match ($this) {
            self::BLACK => self::meta("black", "§0", "\x1b[38;5;16m"),
            self::WHITE => self::meta("white", "§f", "\x1b[38;5;231m"),
            self::DARK_GRAY => self::meta("dark_gray", "§8", "\x1b[38;5;59m"),
            self::GRAY => self::meta("gray", "§7", "\x1b[38;5;145m"),
            self::BLUE => self::meta("blue", "§9", "\x1b[38;5;63m"),
            self::DARK_BLUE => self::meta("dark_blue", "§1", "\x1b[38;5;19m"),
            self::DARK_CYAN => self::meta("dark_cyan", "§3", "\x1b[38;5;37m"),
            self::CYAN => self::meta("cyan", "§b", "\x1b[38;5;87m"),
            self::DARK_RED => self::meta("dark_red", "§4", "\x1b[38;5;124m"),
            self::RED => self::meta("red", "§c", "\x1b[38;5;203m"),
            self::DARK_GREEN => self::meta("dark_green", "§2", "\x1b[38;5;34m"),
            self::GREEN => self::meta("green", "§a", "\x1b[38;5;83m"),
            self::MAGENTA => self::meta("magenta", "§5", "\x1b[38;5;127m"),
            self::PINK => self::meta("pink", "§d", "\x1b[38;5;207m"),
            self::YELLOW => self::meta("yellow", "§e", "\x1b[38;5;227m"),
            self::ORANGE => self::meta("orange", "§6", "\x1b[38;5;214m"),
            self::RESET => self::meta("reset", "§r", "\x1b[m")
        };
    }

    public function getName(): string {
        return $this->metadata()[0];
    }

    public function getColorCode(): string {
        return $this->metadata()[1];
    }

    public function getColor(): string {
        return $this->metadata()[2];
    }

    public function toString(): string {
        return $this->getColorCode();
    }
}
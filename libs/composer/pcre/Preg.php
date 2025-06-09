<?php

namespace Composer\Pcre;

final class Preg
{
    public static function isMatch($pattern, $subject)
    {
        return (bool) preg_match($pattern, $subject);
    }

    public static function match($pattern, $subject)
    {
        preg_match($pattern, $subject, $matches);
        return $matches;
    }

    public static function matchAll($pattern, $subject)
    {
        preg_match_all($pattern, $subject, $matches);
        return $matches;
    }

    public static function replace($pattern, $replacement, $subject, int $limit = -1)
    {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }

    public static function replaceCallback($pattern, callable $callback, $subject, int $limit = -1)
    {
        return preg_replace_callback($pattern, $callback, $subject, $limit);
    }

    public static function split($pattern, $subject, int $limit = -1)
    {
        return preg_split($pattern, $subject, $limit);
    }

    public static function grep($pattern, array $input)
    {
        return preg_grep($pattern, $input);
    }
}

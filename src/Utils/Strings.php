<?php
namespace Bobby\Servers\Utils;

class Strings
{
    public static function underlineToHump(string $world): string
    {
        $worldLength = strlen($world);
        $newWorld = '';
        for ($i = 0; $i < $worldLength; $i++) {
            $char = $world{$i};
            if ($world{$i} === '_') {
                continue;
            }
            if ($i !== 0 && !empty($world{$i}) && $world{$i - 1} === '-') {
                $char = strtoupper($char);
            }
            $newWorld .= $char;
        }
        return $newWorld;
    }
}
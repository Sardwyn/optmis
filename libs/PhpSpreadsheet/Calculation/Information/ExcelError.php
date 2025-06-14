<?php

namespace PhpOffice\PhpSpreadsheet\Calculation\Information;

use PhpOffice\PhpSpreadsheet\Calculation\ArrayEnabled;

class ExcelError
{
    use ArrayEnabled;

    /**
     * List of error codes.
     *
     * @var array<string, string>
     */
    public const ERROR_CODES = [
        'null' => '#NULL!', // 1
        'divisionbyzero' => '#DIV/0!', // 2
        'value' => '#VALUE!', // 3
        'reference' => '#REF!', // 4
        'name' => '#NAME?', // 5
        'num' => '#NUM!', // 6
        'na' => '#N/A', // 7
        'gettingdata' => '#GETTING_DATA', // 8
        'spill' => '#SPILL!', // 9
        'connect' => '#CONNECT!', //10
        'blocked' => '#BLOCKED!', //11
        'unknown' => '#UNKNOWN!', //12
        'field' => '#FIELD!', //13
        'calculation' => '#CALC!', //14
    ];

    public static function throwError(mixed $value): string
    {
        return in_array($value, self::ERROR_CODES, true) ? $value : self::ERROR_CODES['value'];
    }

    /**
     * ERROR_TYPE.
     *
     * @param mixed $value Value to check
     *
     * @return array<mixed>|int|string
     */
    public static function type(mixed $value = ''): array|int|string
    {
        if (is_array($value)) {
            return self::evaluateSingleArgumentArray([self::class, __FUNCTION__], $value);
        }

        $i = 1;
        foreach (self::ERROR_CODES as $errorCode) {
            if ($value === $errorCode) {
                return $i;
            }
            ++$i;
        }

        return self::NA();
    }

    /**
     * NULL.
     *
     * Returns the error value #NULL!
     *
     * @return string #NULL!
     */
    public static function null(): string
    {
        return self::ERROR_CODES['null'];
    }

    /**
     * NaN.
     *
     * Returns the error value #NUM!
     *
     * @return string #NUM!
     */
    public static function NAN(): string
    {
        return self::ERROR_CODES['num'];
    }

    /**
     * REF.
     *
     * Returns the error value #REF!
     *
     * @return string #REF!
     */
    public static function REF(): string
    {
        return self::ERROR_CODES['reference'];
    }

    /**
     * NA.
     *
     * Excel Function:
     *        =NA()
     *
     * Returns the error value #N/A
     *        #N/A is the error value that means "no value is available."
     *
     * @return string #N/A!
     */
    public static function NA(): string
    {
        return self::ERROR_CODES['na'];
    }

    /**
     * VALUE.
     *
     * Returns the error value #VALUE!
     *
     * @return string #VALUE!
     */
    public static function VALUE(): string
    {
        return self::ERROR_CODES['value'];
    }

    /**
     * NAME.
     *
     * Returns the error value #NAME?
     *
     * @return string #NAME?
     */
    public static function NAME(): string
    {
        return self::ERROR_CODES['name'];
    }

    /**
     * DIV0.
     *
     * @return string #DIV/0!
     */
    public static function DIV0(): string
    {
        return self::ERROR_CODES['divisionbyzero'];
    }

    /**
     * CALC.
     *
     * @return string #CALC!
     */
    public static function CALC(): string
    {
        return self::ERROR_CODES['calculation'];
    }

    /**
     * SPILL.
     *
     * @return string #SPILL!
     */
    public static function SPILL(): string
    {
        return self::ERROR_CODES['spill'];
    }
}

<?php
namespace Optmis_Yacht_Manager\Utils;

defined('ABSPATH') || exit;

class MatchingEngine
{
    /**
     * Compare a product signature to yacht system requirements.
     *
     * @param array $product_signature Normalized spec (from SchemaSignatureBuilder)
     * @param array $yacht_requirements Expected system spec for a yacht system
     * @return array Match result including confidence and details
     */
    public static function match(array $product_signature, array $yacht_requirements): array
    {
        $matched = [];
        $missing = [];
        $total = count($yacht_requirements);

        foreach ($yacht_requirements as $field => $expected) {
            $actual = $product_signature[$field] ?? null;
            if (is_null($actual)) {
                $missing[] = $field;
            } elseif (strtolower(trim($actual)) === strtolower(trim($expected))) {
                $matched[] = $field;
            } else {
                $missing[] = $field; // present but not matching
            }
        }

        $confidence = $total > 0 ? count($matched) / $total : 0;

        return [
            'confidence' => round($confidence, 2),
            'matched'    => $matched,
            'missing'    => $missing,
            'total'      => $total
        ];
    }
}
